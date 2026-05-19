# CLI Commands

Courier ships three Spark commands. They're designed to run via cron — each one is stateless, processes a bounded batch, and logs its results.

## `courier:send-campaign`

Sends all scheduled blast campaigns whose `scheduled_at` time has passed.

```bash
php spark courier:send-campaign
```

To send a specific campaign by ID (useful for testing or manual sends):

```bash
php spark courier:send-campaign 42
```

**What it does:**

1. Finds all campaigns with status `scheduled` and `scheduled_at <= now` (or just the one you specified)
2. Sets each campaign to `sending`
3. Resolves the audience (all subscribers, or filtered by segment/tags)
4. Sends emails in batches of `$batchSize`
5. Marks the campaign `sent` when done, or `paused` if an exception occurs

A paused campaign can be resumed via `CampaignService::resume()` or by fixing the issue and calling `courier:send-campaign <id>` again.

**Recommended cron schedule:**

```bash
# Check for scheduled campaigns every 5 minutes
*/5 * * * * php /path/to/app/spark courier:send-campaign
```

## `courier:process-drips`

Sends due drip sequence steps to enrolled contacts.

```bash
php spark courier:process-drips
```

**What it does:**

1. Finds active enrollments where `next_send_at <= now` (up to `$batchSize`)
2. Sends the current step email to each contact
3. Advances each enrollment to the next step (updating `next_send_at` based on the next step's `delay_hours`)
4. Marks the enrollment `completed` when the contact finishes all steps
5. Cancels the enrollment if the contact is no longer subscribed

**Recommended cron schedule:**

Run this frequently so drip steps go out close to their scheduled time:

```bash
# Process due drip steps every minute
* * * * * php /path/to/app/spark courier:process-drips
```

If your batch size is smaller than your active enrollment count, the queue drains across successive runs. That's by design — it prevents overwhelming your email provider in a single burst.

## `courier:track-events`

A stub command for processing bounce webhooks or SMTP feedback loops.

```bash
php spark courier:track-events
```

Out of the box this command does nothing but log a warning. It's a placeholder — extend it when you're ready to handle bounce webhooks from your email provider:

```php
<?php
// app/Commands/ProcessBounces.php
namespace App\Commands;

use Myth\Courier\Commands\TrackEvents;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Enums\ContactStatus;

class ProcessBounces extends TrackEvents
{
    protected $name = 'courier:track-events'; // override the base command

    protected function processBounce(string $email): void
    {
        $contact = model(ContactModel::class)->where('email', $email)->first();
        if ($contact === null) { return; }

        model(ContactModel::class)->update($contact->id, [
            'status' => ContactStatus::Bounced->value,
        ]);
    }
}
```

## Logging

All three commands write to CI4's log system using the `[Courier]` prefix. Check your `writable/logs/` directory if something isn't sending as expected.

```
[courier:send-campaign] Campaign 42 failed: Connection refused
[courier:process-drips] processed=14 cancelled=1 failed=0
```
