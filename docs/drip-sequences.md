# Drip Sequences

A drip sequence is a series of emails sent to contacts automatically, each after a configurable delay from the previous one. Common use cases: welcome sequences, onboarding flows, re-engagement campaigns.

!!! tip "Define campaigns as YAML files"
    If your sequences are stable and long-lived, consider [file-based campaigns](file-based-campaigns.md) — define drip sequences as YAML files that live in version control and sync into the database with a single command.

## How it works

1. You create a `drip_sequence` campaign and add steps to it (each step is one email with a delay)
2. Contacts get enrolled — either at subscribe time, or explicitly via `DripService`
3. A cron job runs `courier:process-drips` frequently; it finds enrollments whose `next_send_at` has passed, sends the email, and advances the contact to the next step
4. When a contact completes all steps, their enrollment is marked `completed`; if they unsubscribe, all active enrollments are cancelled

## Creating a drip campaign

```php
<?php
$campaignService = service('campaignService');

$campaign = $campaignService->create([
    'name'       => 'Welcome Sequence',
    'subject'    => 'Welcome!',  // overridden per-step
    'from_name'  => 'Acme Team',
    'from_email' => 'hello@acme.com',
    'type'       => 'drip_sequence',
]);
```

## Adding steps

Each step needs a `view`, a `subject`, and a `delay_hours` — how many hours after the previous step (or enrollment) to wait before sending.

```php
<?php
// Step 1: send immediately (0-hour delay)
$campaignService->addDripStep($campaign->id, [
    'subject'     => 'Welcome to Acme!',
    'view'        => 'App\Views\emails\welcome_step1',
    'delay_hours' => 0,
]);

// Step 2: send 24 hours after step 1
$campaignService->addDripStep($campaign->id, [
    'subject'     => 'Getting started with Acme',
    'view'        => 'App\Views\emails\welcome_step2',
    'delay_hours' => 24,
]);

// Step 3: send 72 hours after step 2
$campaignService->addDripStep($campaign->id, [
    'subject'     => 'Pro tips you\'ll love',
    'view'        => 'App\Views\emails\welcome_step3',
    'delay_hours' => 72,
]);
```

Steps are ordered by `position`, which is assigned automatically (1, 2, 3…) if you don't set it.

## Enrolling contacts

### At subscribe time

The easiest way — pass `dripCampaignId` to `ContactService::subscribe()`:

```php
<?php
service('contactService')->subscribe(
    ['email' => 'ada@example.com'],
    dripCampaignId: $campaign->id
);
```

### Explicitly

```php
<?php
$dripService = service('dripService');
$enrollment  = $dripService->enroll($contact->id, $campaign->id);
```

`enroll()` returns `null` if the contact is already enrolled (any status — prevents double-enrolling). It throws a `RuntimeException` if the contact isn't subscribed or the campaign has no steps yet.

## Processing due steps

The `courier:process-drips` command does the actual sending. Run it frequently, using [CodeIgniter Tasks](https://github.com/codeigniter4/tasks):

```php
// app/Config/Tasks.php
$schedule->command('courier:process-drips')->everyMinute()->singleInstance();
```

On raw crontab, wrap the command in `flock` so overlapping invocations can't run at once:

```bash
* * * * * flock -n /var/lock/courier-drips.lock php /path/to/your/app/spark courier:process-drips
```

Each run processes up to `$batchSize` enrollments (default: 200). If you have more than that in the queue, they'll drain across successive runs — that's intentional so you don't blast your email provider.

### Overlapping runs

Before sending, each run atomically claims its batch — due enrollments move from `active` to an intermediate `processing` status. An overlapping run's claim query only matches `active` rows, so it can't pick up enrollments the first run already claimed, and each step is sent exactly once even if a run takes longer than the interval between runs.

If a run crashes or is killed mid-batch, its claimed enrollments stay `processing` until `$staleLockMinutes` (default: 15) passes, at which point the next run reclaims them back to `active` and retries. `singleInstance()`/`flock` are still recommended as defence in depth — they stop a second run from starting at all — but the claim is what actually guarantees no duplicate sends.

## Cancelling an enrollment

To cancel a specific contact from one campaign:

```php
<?php
$dripService->cancel($contact->id, $campaign->id);
```

To cancel all drip enrollments for a contact (this happens automatically on unsubscribe):

```php
<?php
$dripService->cancelAllForContact($contact->id);
```

## Checking enrollment status

```php
<?php
$enrollment = $dripService->getEnrollmentStatus($contact->id, $campaign->id);
// Returns DripEnrollmentDTO or null
```

Enrollment statuses: `active`, `processing`, `cancelled`, `completed`, `failed`.

`processing` is transient — it's only set while a `courier:process-drips` run has an enrollment claimed for sending; see [Overlapping runs](#overlapping-runs).

## Enrollment lifecycle

```
(subscribe or enroll()) → active ⇄ processing → [step 1] → [step 2] → ... → completed
                                                                      ↘ cancelled (unsubscribe or manual)
```
