# File-Based Campaigns

File-based campaigns let you define drip sequences or blasts as YAML files in your project instead of creating them programmatically. The files live alongside your code, get reviewed in pull requests, and are synced into the database with a single Spark command.

Use this approach when a campaign is long-lived and stable — onboarding flows, welcome series, re-engagement tracks, or a one-time blast whose audience is a tag/segment filter. If you need to create campaigns dynamically at runtime, use the [programmatic API](drip-sequences.md) instead.

## Directory setup

By default, Courier looks for YAML files in:

```
app/courier/campaigns/
```

Create this directory if it doesn't exist:

```bash
mkdir -p app/courier/campaigns
```

### Customizing the path

Set `$campaignsPath` in your `app/Config/Courier.php` to use a different location:

```php
<?php
// app/Config/Courier.php
namespace Config;

use Myth\Courier\Config\Courier as CourierConfig;

class Courier extends CourierConfig
{
    public string $campaignsPath = ROOTPATH . 'campaigns/';
}
```

Set an absolute path. Relative paths are not supported.

## YAML file format

Each `.yaml` file defines one campaign — a drip sequence or a blast, chosen by `type`. The filename becomes the campaign's `source_file` identifier in the database.

### Drip sequences

`type` defaults to `drip_sequence` when omitted, so existing files need no change.

```yaml
# app/courier/campaigns/welcome-sequence.yaml

name: Welcome Sequence
from_name: Acme Team
from_email: hello@acme.com
layout: App\Views\emails\layouts\default   # optional

steps:
  - position: 1
    subject: "Welcome to Acme!"
    view: App\Views\emails\welcome_step1
    delay_hours: 0

  - position: 2
    subject: "Getting started with Acme"
    view: App\Views\emails\welcome_step2
    delay_hours: 24

  - position: 3
    subject: "Pro tips you'll love"
    view: App\Views\emails\welcome_step3
    delay_hours: 72
```

#### Campaign fields

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | Unique campaign name. Used to match existing DB rows on sync. |
| `from_name` | Yes | Sender display name |
| `from_email` | Yes | Sender email address (must be valid) |
| `type` | No | `drip_sequence` (default) or `blast` |
| `layout` | No | CI4 view string for the email layout wrapper |
| `steps` | Yes | Array of step definitions (at least one) |

#### Step fields

| Field | Required | Description |
|-------|----------|-------------|
| `position` | Yes | Step order. Must start at 1; no duplicates allowed. |
| `subject` | Yes | Email subject line for this step |
| `view` | Yes | CI4 view string rendered as the email body |
| `delay_hours` | Yes | Hours to wait after the previous step (or enrollment) before sending |

!!! tip "Step 1 delay"
    Set `delay_hours: 0` on your first step to send immediately when a contact enrolls.

### Blasts

A blast is a single send to an audience resolved from a tag filter and/or segment, with no steps and no per-contact enrollment.

```yaml
# app/courier/campaigns/spring-sale.yaml

name: Spring Sale
type: blast
subject: "20% off this week only"
from_name: Acme Team
from_email: hello@acme.com
view: App\Views\emails\spring_sale
layout: App\Views\emails\layouts\default   # optional
tag_filter:                                 # optional; omit to target all subscribed contacts
  - customer
  - newsletter
```

#### Campaign fields

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | Unique campaign name. Used to match existing DB rows on sync. |
| `type` | Yes | Must be `blast` |
| `subject` | Yes | Email subject line |
| `view` | Yes | CI4 view string rendered as the email body |
| `from_name` | Yes | Sender display name |
| `from_email` | Yes | Sender email address (must be valid) |
| `layout` | No | CI4 view string for the email layout wrapper |
| `tag_filter` | No | List of tag slugs; audience is contacts carrying all of them. Omit to target every subscribed contact. |

A synced blast is only ever inserted as `draft` — re-running sync after an operator has scheduled or sent it (via `courier:campaigns schedule` / `courier:send-campaign`) updates its content fields but leaves `status`, `scheduled_at`, and `sent_at` untouched. Scheduling and sending stay manual steps; see [CLI Commands](commands.md).

## Authoring workflow

**1. Write your YAML files** in `app/courier/campaigns/`.

**2. Validate them** — catches errors before touching the database:

```bash
php spark courier:validate-campaigns
```

```
OK   welcome-sequence.yaml
OK   re-engagement.yaml
FAIL onboarding.yaml: step[1]: missing required field 'subject'
```

Exit code is `0` if everything passes, `1` if any file fails. This makes it safe to run in CI before deploying.

**3. Sync to the database** — upserts campaigns by name:

```bash
php spark courier:sync-campaigns
```

```
CREATED welcome-sequence.yaml → campaign 'Welcome Sequence'
UPDATED re-engagement.yaml → campaign 'Re-engagement Track'
SKIP    onboarding.yaml: step[1]: missing required field 'subject'
```

Run sync whenever you add or update a campaign file. Invalid files are skipped — other files in the batch still sync.

## Enrolling contacts (drip sequences)

Once a drip campaign is synced, enroll contacts the same way as any other drip campaign:

```php
<?php
$campaign = service('campaignService')->getByName('Welcome Sequence');

service('dripService')->enroll($contact->id, $campaign->id);
```

Or enroll at subscribe time:

```php
<?php
service('contactService')->subscribe(
    ['email' => 'ada@example.com'],
    dripCampaignId: $campaign->id,
);
```

The drip processor reads step content directly from the YAML file at send time — steps are not stored in the database.

## Sending a blast

A synced blast sits in `draft` until an operator sends it — nothing sends automatically just because the file synced. Either send it immediately by ID:

```bash
php spark courier:send-campaign <id>
```

or schedule it for later, which `courier:send-campaign` (run with no ID, e.g. from a scheduled task) picks up once due:

```bash
php spark courier:campaigns schedule --id=<id> --at="2026-06-01 09:00:00"
```

`courier:campaigns list` shows the ID once the campaign has synced.

## CI pipeline integration

Add a validate step to your CI pipeline to catch broken campaign files before they reach production:

```yaml
# .github/workflows/ci.yml
- name: Validate campaign files
  run: php spark courier:validate-campaigns
```

Because `courier:validate-campaigns` is read-only and exits non-zero on failure, it integrates cleanly as a pre-deploy gate.

## Next steps

- [Drip Sequences](drip-sequences.md) — programmatic campaign creation and enrollment API
- [CLI Commands](commands.md) — full reference for all Courier Spark commands
