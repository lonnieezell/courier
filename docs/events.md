# Events

Courier fires CI4 events at key points in the contact and email lifecycle. You can listen to these to add your own logic — sync to a CRM, log to analytics, send a Slack notification, whatever you need.

## Available events

| Constant | Fires when... | Payload |
|----------|---------------|---------|
| `CourierEvents::CONTACT_SUBSCRIBED` | A contact subscribes (new or re-subscribe) | `ContactDTO` |
| `CourierEvents::CONTACT_UNSUBSCRIBED` | A contact unsubscribes | `ContactDTO` |
| `CourierEvents::CONTACT_BOUNCED` | A hard bounce is received via webhook | `ContactDTO` |
| `CourierEvents::CONTACT_COMPLAINED` | A spam complaint is received via webhook | `ContactDTO` |
| `CourierEvents::EMAIL_SENT` | An email is successfully delivered (or logged in test mode) | `SendDTO` |
| `CourierEvents::EMAIL_FAILED` | An email fails to deliver | `SendDTO` |

## Registering a listener

Add listeners in `app/Config/Events.php`:

```php
<?php
use Myth\Courier\Events\CourierEvents;

Events::on(CourierEvents::CONTACT_SUBSCRIBED, static function ($contact): void {
    // $contact is a ContactDTO
    log_message('info', "New subscriber: {$contact->email}");
});
```

### Syncing to a CRM on subscribe

```php
<?php
Events::on(CourierEvents::CONTACT_SUBSCRIBED, static function ($contact): void {
    service('crmService')->upsertContact([
        'email'      => $contact->email,
        'first_name' => $contact->first_name,
        'tags'       => $contact->tags ?? [],
    ]);
});
```

### Alerting on delivery failures

```php
<?php
Events::on(CourierEvents::EMAIL_FAILED, static function ($send): void {
    // $send is a SendDTO with contact_id, campaign_id, and status
    log_message('error', "Email delivery failed for send #{$send->id}");
    // notify your team, update a dashboard, etc.
});
```

### Tracking unsubscribes externally

```php
<?php
Events::on(CourierEvents::CONTACT_UNSUBSCRIBED, static function ($contact): void {
    service('analyticsService')->track('email.unsubscribed', [
        'email' => $contact->email,
    ]);
});
```

### Syncing suppressions to a CRM

Bounce and complaint events fire after Courier has already updated the contact's status and cancelled their drip enrollments. The `$contact` payload reflects the new suppressed status.

```php
<?php
Events::on(CourierEvents::CONTACT_BOUNCED, static function ($contact): void {
    // $contact->status is ContactStatus::Bounced at this point
    service('crmService')->suppressContact($contact->email, reason: 'bounce');
});

Events::on(CourierEvents::CONTACT_COMPLAINED, static function ($contact): void {
    // $contact->status is ContactStatus::Complained at this point
    service('crmService')->suppressContact($contact->email, reason: 'complaint');
});
```

!!! note "Webhook required"
    These events only fire when Courier receives a bounce or complaint via the webhook endpoint. They won't fire for addresses that bounce silently at the SMTP level without ESP feedback. See [Tracking — Bounce and complaint webhooks](tracking.md#bounce-and-complaint-webhooks) for setup instructions.

## Error handling in listeners

Courier wraps each event trigger in a `try/catch`. If your listener throws an exception, Courier logs the error and continues — it won't interrupt the send or unsubscribe flow. That said, it's still a good idea to keep listeners fast and handle their own errors gracefully.
