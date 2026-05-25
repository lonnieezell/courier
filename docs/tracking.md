# Tracking

Courier tracks email opens and link clicks automatically for every email it sends. It also handles unsubscribe links. All three are served by `CourierController`, which is registered under the `/courier/` URL prefix.

## How open tracking works

When Courier renders an email, it injects a `{courier_tracking_pixel}` placeholder. Before delivery, this is replaced with a 1×1 transparent GIF served from your app:

```html
<img src="https://yoursite.com/courier/open/abc123token" width="1" height="1" alt="">
```

When the recipient's email client loads that image, Courier records the open event and timestamps `opened_at` on the send record. If the same pixel loads more than once, only the first open is timestamped — but all load events are recorded in `courier_events`.

!!! note "Open tracking limitations"
    Many email clients block remote images by default, so open rates are an undercount. That's true across the industry — it's still a useful relative metric.

## How click tracking works

Every `http://` or `https://` link in your email body is automatically rewritten through Courier's click redirect:

```html
<!-- Original -->
<a href="https://acme.com/pricing">See pricing</a>

<!-- After wrapping -->
<a href="https://yoursite.com/courier/click/abc123token">See pricing</a>
```

When a recipient clicks, Courier records the click event and redirects them to the original URL. Only the first click is timestamped on the send record; subsequent clicks are still logged as events.

!!! note "Unsubscribe links are not rewritten"
    The `{courier_unsubscribe_url}` placeholder is excluded from link rewriting — it goes directly to the unsubscribe handler.

## Unsubscribes

When a contact clicks their unsubscribe link, Courier's controller handles it:

1. Looks up the contact by their unique `unsubscribe_token`
2. Sets their status to `unsubscribed`
3. Cancels all active drip enrollments
4. Triggers the `courier:contact.unsubscribed` event
5. Renders a confirmation view (`Views/courier/unsubscribe_success.php`)

You can customize the unsubscribe success and invalid-token views by publishing them:

```bash
php spark publish:views Courier
```

## Using a custom tracking domain

By default, tracking URLs use your app's `base_url()`. If you want them to come from a separate domain (better deliverability, branded links), set `$trackingHost` in your config:

```php
<?php
public string $trackingHost = 'https://track.acme.com';
```

Point that domain at your CI4 app, and make sure the `/courier/*` routes are reachable there.

## Reading stats

Aggregate stats for a campaign are available via `CampaignService`:

```php
<?php
$stats = service('campaignService')->getCampaignStats($campaignId);

// [
//   'total'   => 2500,   // total send records
//   'sent'    => 2487,   // successfully delivered
//   'failed'  => 13,     // delivery failures
//   'opened'  => 891,    // unique opens (first open only)
//   'clicked' => 234,    // unique clicks (first click only)
// ]
```

Raw event data (every open and click, with timestamps and metadata) is in the `courier_events` table.

## Bounce and complaint webhooks

Open and click tracking tell you what contacts do with your emails. Bounce and complaint tracking tells you when emails *can't* be delivered — or when recipients mark them as spam. Without it, you'll keep sending to bad addresses until your ESP suspends your account.

Courier handles this via a webhook endpoint: `POST /courier/webhook`. Your ESP calls it whenever a bounce or complaint occurs, and Courier automatically suppresses the contact.

### How it works

1. A hard bounce or spam complaint arrives at `POST /courier/webhook`
2. Courier verifies the request is genuinely from your ESP (RSA signature check for SES/SNS)
3. The contact's status is updated to `bounced` or `complained`
4. All active drip enrollments for that contact are cancelled
5. The `courier:contact.bounced` or `courier:contact.complained` event fires

Soft bounces (temporary failures like a full mailbox) are logged as events but don't suppress the contact.

### Setting up with AWS SES

Courier ships with a driver for AWS SES, which routes notifications through SNS.

**Step 1 — Configure the driver** in `app/Config/Courier.php`:

```php
<?php
public string $webhookDriver = \Myth\Courier\Webhooks\Drivers\SesDriver::class;
```

**Step 2 — Exempt the route from CSRF** in `app/Config/Security.php`:

```php
<?php
public $CSRFExcludeURIs = [
    'courier/webhook',
];
```

SNS sends machine-to-machine POST requests with no browser session, so they'll never carry a CSRF token.

**Step 3 — Create an SNS topic** in your AWS console and subscribe your webhook URL:

```
https://yoursite.com/courier/webhook
```

SNS will send a `SubscriptionConfirmation` request to that URL. Courier confirms it automatically — you don't need to do anything.

**Step 4 — Configure SES notifications** to publish bounces and complaints to your SNS topic. In the SES console, go to your verified identity → Notifications → set the Bounce and Complaint SNS topics to the one you just created.

!!! tip ".env override"
    ```
    courier.webhookDriver = \Myth\Courier\Webhooks\Drivers\SesDriver::class
    ```

### Custom drivers

If you're using a different ESP, implement `WebhookDriverInterface`:

```php
<?php
use Myth\Courier\Webhooks\WebhookDriverInterface;
use CodeIgniter\HTTP\IncomingRequest;

class MailgunDriver implements WebhookDriverInterface
{
    public function verifySignature(IncomingRequest $request): bool
    {
        // verify HMAC-SHA256 signature from Mailgun headers
    }

    public function isSubscriptionConfirmation(IncomingRequest $request): bool
    {
        return false; // Mailgun doesn't use subscription confirmations
    }

    public function confirmSubscription(IncomingRequest $request): void {}

    public function parseEvents(IncomingRequest $request): array
    {
        // return [['type' => 'bounce'|'soft_bounce'|'complaint', 'email' => '...', 'message_id' => '...']]
    }
}
```

Then set `$webhookDriver = MailgunDriver::class` in your config.
