# Email Templates

Courier uses CI4's standard view system for email content. Each campaign points to a view file for its body, and optionally a separate layout that wraps it.

## How layouts work

A layout is an outer HTML shell — the `<html>`, `<head>`, and structural table markup. The campaign body view provides just the inner content. Courier renders the body first, then injects it into the layout via a `$content` variable.

```
layout.php          ← outer HTML, header, footer
  └─ body view.php  ← your campaign's content
```

The default layout is at `Views/courier/layouts/default.php`. It's a simple 600px responsive email with a dark header, white body, and light footer.

## Creating a body view

A body view is a plain PHP view file that outputs HTML email content. Keep it simple — inline styles, table-based layout if you need columns.

```php
<?php
<!-- app/Views/emails/may_newsletter.php -->

<p>Hi <?= esc($contact->first_name ?? 'there') ?>,</p>

<p>Here's what's new this month at Acme.</p>

<p>
    <a href="https://acme.com/blog/may-update"
       style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;
              text-decoration:none;border-radius:4px;font-weight:bold;">
        Read the update
    </a>
</p>

<p>Talk soon,<br>The Acme Team</p>
```

### Available variables

| Variable | Type | Description |
|----------|------|-------------|
| `$contact` | `ContactDTO` | The recipient — has `email`, `first_name`, `last_name`, `custom_fields`, etc. |
| `$subject` | `string` | The campaign or drip step subject line |

Any additional data you pass to `CampaignService::create()` or `addDripStep()` is **not** automatically available in views — pass extra data via a custom service or use `$contact->custom_fields`.

## Tracking placeholders

Two placeholders are replaced automatically in the rendered HTML before sending:

| Placeholder | Replaced with |
|-------------|---------------|
| `{courier_unsubscribe_url}` | A unique one-click unsubscribe URL for this contact |
| `{courier_tracking_pixel}` | A 1×1 invisible image that records opens |

!!! warning "Include the unsubscribe link"
    CAN-SPAM and GDPR both require a way to opt out. Make sure `{courier_unsubscribe_url}` appears in every email. The default layout already includes it in the footer — if you write a custom layout, add it yourself.

Place them in your layout (not the body view) so every campaign gets them:

```html
<!-- in your layout footer -->
<a href="{courier_unsubscribe_url}">Unsubscribe</a>

<!-- at the very end of <body> -->
{courier_tracking_pixel}
```

## Using a custom layout

Point a campaign at your own layout view:

```php
<?php
$campaignService->create([
    ...
    'view'   => 'App\Views\emails\may_newsletter',
    'layout' => 'App\Views\emails\layouts\branded',
]);
```

Your layout needs to output `<?= $content ?>` where the body should appear.

To change the default for all campaigns, update `$defaultLayout` in your config:

```php
<?php
public string $defaultLayout = 'App\Views\emails\layouts\branded';
```

## Plain-text fallback

Courier generates a plain-text alternative automatically by rendering your body view without the layout and stripping HTML. It appends the unsubscribe URL at the bottom. You don't need to maintain a separate plain-text view.

## Testing your templates

Set `testMode = true` in your config and trigger a send — Courier logs the recipient and subject instead of sending. To preview the rendered HTML, use CI4's `TemplateService` directly:

```php
<?php
$html = service('templateService')->render(
    'App\Views\emails\may_newsletter',
    'App\Views\emails\layouts\branded',
    ['contact' => $contact, 'subject' => 'Preview']
);
```
