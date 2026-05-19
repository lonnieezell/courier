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
<a href="https://yoursite.com/courier/click/abc123token?url=https%3A%2F%2Facme.com%2Fprice">See pricing</a>
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
