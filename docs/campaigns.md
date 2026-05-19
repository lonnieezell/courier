# Campaigns

A campaign is an email (or series of emails) sent to a set of contacts. Courier has two campaign types:

- **Blast** — a single email sent to an audience at a scheduled time
- **Drip sequence** — a series of emails sent to individually-enrolled contacts on a delay schedule

This page covers blast campaigns. For drip sequences, see [Drip Sequences](drip-sequences.md).

## Creating a campaign

```php
<?php
$campaignService = service('campaignService');

$campaign = $campaignService->create([
    'name'       => 'May Newsletter',
    'subject'    => 'What\'s new this month',
    'from_name'  => 'Acme Team',
    'from_email' => 'hello@acme.com',
    'view'       => 'App\Views\emails\may_newsletter',
    'type'       => 'blast',
]);
```

Required fields: `name`, `subject`, `from_name`, `from_email`. The `type` defaults to `blast` if omitted. The campaign is created in `draft` status.

## Targeting your audience

By default, a campaign sends to all subscribed contacts. You can narrow the audience three ways:

### By segment

Assign a saved segment by ID:

```php
<?php
$campaignService->create([
    ...
    'segment_id' => 4,
]);
```

### By tags

Pass a JSON-encoded array of tag slugs — only contacts with **all** listed tags will receive the email:

```php
<?php
$campaignService->create([
    ...
    'tag_filter' => json_encode(['newsletter', 'vip']),
]);
```

### Combining segment + tags

Set both `segment_id` and `tag_filter` to intersect them — contacts must be in the segment **and** have all the specified tags.

## Segments

Segments are saved rule sets that filter the contact list. A segment can match contacts by any contact column, tag, or custom field.

### Rule structure

Each rule is an object with `field`, `op`, and `value`:

```json
{
  "rules": [
    { "field": "status",       "op": "eq",  "value": "subscribed" },
    { "field": "subscribed_at","op": "gte", "value": "2025-01-01" }
  ],
  "match_mode": "all"
}
```

**Supported fields:**

| Field | Description |
|-------|-------------|
| `email` | Contact's email address |
| `first_name` / `last_name` | Name fields |
| `status` | Contact status (`subscribed`, `unsubscribed`, etc.) |
| `source` | How the contact was acquired |
| `subscribed_at` / `unsubscribed_at` | Subscription timestamps |
| `tag` | Has (or doesn't have) a specific tag slug |
| `custom:<key>` | A key inside `custom_fields` JSON (MySQL/SQLite only) |

**Operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`

**`match_mode`:** `all` (AND logic) or `any` (OR logic)

### Previewing segment size

```php
<?php
$segmentService = service('segmentService');
$count = $segmentService->previewCount($segmentId);
```

## Scheduling a campaign

Once your campaign is in draft and has a view set, schedule it for delivery:

```php
<?php
$sendAt = new DateTime('2025-06-01 09:00:00');
$campaignService->schedule($campaign->id, $sendAt);
```

The campaign moves to `scheduled` status. The `courier:send-campaign` command picks it up when the scheduled time arrives.

## Campaign lifecycle

```
draft → scheduled → sending → sent
                 ↘ paused (on error)
```

A paused campaign can be resumed:

```php
<?php
$campaignService->resume($campaign->id);
```

This moves it back to `scheduled` so the next command run picks it up.

## Checking campaign stats

```php
<?php
$stats = $campaignService->getCampaignStats($campaign->id);

// Returns:
// [
//   'total'   => 1500,
//   'sent'    => 1487,
//   'failed'  => 13,
//   'opened'  => 423,
//   'clicked' => 89,
// ]
```

Open and click counts depend on the [tracking controller](tracking.md) being set up.
