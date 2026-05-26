# Contacts

Contacts are the people in your mailing list. Each contact has a status, optional tags, and an auto-generated unsubscribe token. The `ContactService` handles the full lifecycle.

Get the service via CI4's service locator:

```php
<?php
$contactService = service('contactService');
```

## Subscribing a contact

```php
<?php
$contact = $contactService->subscribe([
    'email'      => 'ada@example.com',
    'first_name' => 'Ada',
    'last_name'  => 'Lovelace',
]);
```

`subscribe()` returns a `ContactDTO`. It either creates a new contact or, if the email already exists and the contact was previously unsubscribed, re-subscribes them.

### Adding tags at subscribe time

Pass a list of tag slugs as the second argument:

```php
<?php
$contact = $contactService->subscribe(
    ['email' => 'ada@example.com'],
    tags: ['newsletter', 'vip']
);
```

Tags are created automatically if they don't exist yet.

### Enrolling in a drip on subscribe

Pass a `dripCampaignId` to automatically enroll the contact in a drip sequence after subscribing:

```php
<?php
$contact = $contactService->subscribe(
    ['email' => 'ada@example.com'],
    dripCampaignId: 3
);
```

## Contact statuses

| Status | Meaning |
|--------|---------|
| `subscribed` | Active — will receive emails |
| `unsubscribed` | Opted out — can be re-subscribed |
| `bounced` | Hard bounce — cannot be re-subscribed |
| `complained` | Marked as spam — cannot be re-subscribed |

!!! warning "Bounced and complained contacts"
    Calling `subscribe()` for a contact with `bounced` or `complained` status throws a `CourierValidationException`. These statuses require manual review before re-engagement.

## Unsubscribing

Courier handles unsubscribes automatically via the tracking controller when a contact clicks their unsubscribe link. You can also unsubscribe programmatically by token:

```php
<?php
use Myth\Courier\Enums\UnsubscribeResult;

$result = $contactService->unsubscribeByToken($token);

match ($result) {
    UnsubscribeResult::Success  => /* contact unsubscribed */,
    UnsubscribeResult::Expired  => /* token is older than $unsubscribeTokenExpireDays */,
    UnsubscribeResult::NotFound => /* token doesn't match any send or contact */,
};
```

Unsubscribing also cancels all active drip enrollments for that contact.

!!! tip "Token expiry"
    Each outgoing email embeds a per-send unsubscribe token that expires after `$unsubscribeTokenExpireDays` days (default: 365). Tokens from emails sent before upgrading to this version use the legacy contact-level token, which has no expiry.

## Managing tags

### Applying tags

```php
<?php
$contactService->applyTags($contact->id, ['beta-tester', 'onboarding']);
```

Tags that don't exist are created on the fly. Tags already applied are silently skipped — it's safe to call this multiple times.

### Removing tags

```php
<?php
$contactService->removeTags($contact->id, ['onboarding']);
```

Tags that aren't applied are silently ignored.

## Looking up a contact

```php
<?php
$contact = $contactService->getContact('ada@example.com');
// Returns ContactDTO or null
```

## Custom fields

Contacts support a `custom_fields` JSON column for arbitrary data. Pass these in the data array when subscribing:

```php
<?php
$contactService->subscribe([
    'email'         => 'ada@example.com',
    'custom_fields' => json_encode(['plan' => 'pro', 'signup_source' => 'homepage']),
]);
```

Custom fields can also be used in [segment rules](campaigns.md#segments).
