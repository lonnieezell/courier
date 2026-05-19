# Courier

Courier is a CodeIgniter 4 package that adds email marketing to your CI4 app. It handles the full lifecycle — subscribing contacts, sending one-time blast campaigns, drip sequences, open/click tracking, and unsubscribes — all without leaving your CI4 stack.

## What's included

- **Contact management** — subscribe, unsubscribe, tag, and segment contacts
- **Blast campaigns** — one-time emails sent to a targeted audience on a schedule
- **Drip sequences** — multi-step automated email sequences with configurable delays
- **Email tracking** — open and click tracking via a built-in pixel and redirect controller
- **CI4 events** — lifecycle hooks for subscriptions, unsubscribes, and email delivery
- **Spark commands** — cron-friendly CLI commands to send campaigns and process drip steps

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- A configured CI4 email service (SMTP, Sendmail, etc.)

## Quick look

```php
<?php
// Subscribe a contact and enroll them in a welcome drip
$contact = service('contactService')->subscribe([
    'email'      => 'ada@example.com',
    'first_name' => 'Ada',
], tags: ['newsletter'], dripCampaignId: 3);
```

Ready to get started? Head to [Installation](installation.md).
