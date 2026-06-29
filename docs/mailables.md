# Mailables

A Mailable is a class that builds one email. It's an alternative to [views and markdown files](templates.md) — reach for it when an email needs real logic: pulling in order data, formatting line items, branching on the contact.

Everything else about Courier stays the same. Tracking, suppression, and unsubscribe handling all work exactly as they do for view-based sends.

## Writing a Mailable

Extend `CourierMailable` and put your composition in `build()`:

```php
<?php

namespace App\Mail;

use Myth\Courier\Mailables\CourierMailable;

class WelcomeMailable extends CourierMailable
{
    protected function build(): void
    {
        $this->from('hello@acme.com', 'Acme Team')
            ->to($this->contact->email)
            ->subject('Welcome aboard, ' . $this->contact->first_name . '!')
            ->html(view('App\Mail\Views\welcome', [
                'contact' => $this->contact,
            ]));
    }
}
```

`build()` runs once, lazily, at send time. Before it runs, Courier sets two properties for you:

| Property | Type | What it is |
|----------|------|------------|
| `$this->contact` | `ContactDTO` | The recipient — always set |
| `$this->campaign` | `?CampaignDTO` | The originating campaign, or `null` for a [transactional send](#transactional-sends) |

The fluent helpers (`from`, `to`, `subject`, `html`, `text`) come straight from postal's `Mailable`, so anything postal supports is available here.

### Tracking still works

After your Mailable renders, Courier post-processes the HTML just like a view: it rewrites links for click tracking and expands the same placeholders.

```php
$this->html(
    '<p>Thanks for joining!</p>'
    . '<p><a href="{courier_unsubscribe_url}">Unsubscribe</a></p>'
    . '{courier_tracking_pixel}'
);
```

See [Tracking placeholders](templates.md#tracking-placeholders) for the full list.

!!! warning "Include the unsubscribe link"
    The default layout adds `{courier_unsubscribe_url}` for you, but a Mailable composes its own HTML — so it's on you to include an opt-out link. CAN-SPAM and GDPR both require one.

## Using a Mailable in a campaign

Set the `mailable` field instead of `view` when you create the campaign:

```php
<?php
$campaignService->create([
    'name'       => 'Welcome Blast',
    'subject'    => 'Welcome!',
    'from_name'  => 'Acme Team',
    'from_email' => 'hello@acme.com',
    'mailable'   => \App\Mail\WelcomeMailable::class,
    'type'       => 'blast',
]);
```

When both are present, **the Mailable wins** — `view` is ignored. The Mailable also owns the subject and sender, since it sets those itself in `build()`.

## Using a Mailable in a drip step

Same idea on a step. A step always needs a `view` (it's required), so think of `mailable` as an override layered on top:

```php
<?php
$campaignService->addDripStep($campaign->id, [
    'subject'     => 'Getting started',          // ignored when mailable is set
    'view'        => 'App\Views\emails\fallback', // required, but overridden
    'mailable'    => \App\Mail\GettingStartedMailable::class,
    'delay_hours' => 24,
]);
```

The step's `mailable` takes precedence over both the step's `view` and the campaign's. Leave `mailable` off to fall back to the view.

## Transactional sends

Not every email belongs to a campaign. Receipts, password resets, and other one-offs go out through `MailerService::sendMailable()`:

```php
<?php
$contact = service('contactService')->getContact('buyer@example.com');

service('mailerService')->sendMailable(
    new \App\Mail\ReceiptMailable($order),
    $contact,
);
```

This records a send with no campaign attached (`campaign_id` is `null`), runs the same [suppression check](tracking.md#suppression), applies tracking, and logs the result. A suppressed recipient returns `false` and is recorded with the `suppressed` status — it is never delivered.

!!! note "Your Mailable can take constructor arguments"
    `sendMailable()` takes an already-built instance, so pass whatever the email needs (an order, an invoice) into the constructor. Courier sets `$contact` before calling `build()`.

## Next steps

- [Tracking](tracking.md) — opens, clicks, suppression, and one-click unsubscribe
- [Email Templates](templates.md) — the view and markdown approach
- [Campaigns](campaigns.md) — blasts and audience targeting
