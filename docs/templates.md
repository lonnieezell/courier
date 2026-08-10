# Email Templates

Courier supports two ways to write email content: **PHP view files** (full CI4 view system, great for complex HTML) and **markdown files** (simpler syntax, ideal for plain prose emails). Both work with layouts and tracking placeholders.

## How layouts work

A layout is an outer HTML shell — the `<html>`, `<head>`, and structural table markup. The campaign body provides just the inner content. Courier renders the body first, then injects it into the layout via a `$content` variable.

```
layout.php          ← outer HTML, header, footer
  └─ body view.php  ← your campaign's content
```

The default layout is at `Views/courier/layouts/default.php`. It's a simple 600px responsive email with a dark header, white body, and light footer.

Courier inlines the layout's stylesheet automatically: any rules in a `<style>` block are copied onto the matching elements as `style="…"` attributes before the email goes out, which is what most email clients need. You can keep writing ordinary CSS in your layout and let Courier do the inlining.

## Creating a PHP view

A body view is a plain PHP view file that outputs HTML email content. Keep it simple — inline styles, table-based layout if you need columns.

```php
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

## Creating a markdown file

If your email is mostly prose, markdown is often easier to write and maintain than a PHP view. Set the `view` field to a path ending in `.md` and Courier handles the rest.

```php
$campaignService->create([
    'name'       => 'May Newsletter',
    'subject'    => 'What\'s new at Acme',
    'from_name'  => 'Acme',
    'from_email' => 'hello@acme.com',
    'view'       => 'emails/may_newsletter.md',  // ← .md extension = markdown mode
]);
```

By default, Courier resolves markdown paths relative to `APPPATH`, so `emails/may_newsletter.md` maps to `app/emails/may_newsletter.md`. You can change this with the `$markdownPath` config option — see [Configuration](configuration.md).

### Writing markdown email content

Use standard [GitHub Flavored Markdown](https://github.github.com/gfm/): headings, bold, italic, links, bullet lists, and tables all work.

```markdown
Hi {first_name}!

Here's what's new this month at Acme.

**New features this month:**

- Faster dashboard loading
- Dark mode support
- Improved CSV export

[Read the full update](https://acme.com/blog/may-update)

Talk soon,
The Acme Team

[Unsubscribe]({courier_unsubscribe_url})
{courier_tracking_pixel}
```

### Mail components

Markdown bodies can use the mail components that ship with `myth/postal`, so you can drop in a styled call-to-action or a callout without hand-writing inline-styled HTML.

```markdown
Hi {first_name}!

Your report for this month is ready.

<mail-button url="https://acme.com/reports/may">View the report</mail-button>

<mail-panel>
Heads up: reports are archived after 90 days.
</mail-panel>
```

`<mail-button>` renders a table-based button, and `<mail-panel>` renders a highlighted callout box — both with inline styles that survive the major email clients. A component tag has to start on its own line. Component links are click-tracked like any other link, and the plain-text alternative keeps the inner text without the surrounding tags.

Components are rendered at postal's default styling. To restyle them, publish postal's component views — see the [Postal documentation](https://github.com/lonnieezell/postal).

### Token substitution

PHP views use `$contact->first_name`. Markdown files use `{token}` placeholders instead. Courier replaces them before rendering.

**Available tokens:**

| Token | Source |
|-------|--------|
| `{first_name}` | `$contact->first_name` |
| `{last_name}` | `$contact->last_name` |
| `{email}` | `$contact->email` |
| `{unsubscribe_token}` | `$contact->unsubscribe_token` |
| `{source}` | `$contact->source` |
| `{subject}` | The campaign or drip step subject line |
| Any other scalar `ContactDTO` field | Automatically available |

Tokens with no matching value are left as-is in the rendered output.

!!! tip "Unsubscribe links in markdown"
    You can use `{courier_unsubscribe_url}` directly in a markdown link:
    ```markdown
    [Unsubscribe]({courier_unsubscribe_url})
    ```
    Courier automatically restores the placeholder after markdown rendering so it gets replaced correctly before sending.

## Tracking placeholders

These work the same in both PHP views and markdown files:

| Placeholder | Replaced with |
|-------------|---------------|
| `{courier_unsubscribe_url}` | A unique one-click unsubscribe URL for this contact |
| `{courier_tracking_pixel}` | A 1×1 invisible image that records opens |

!!! warning "Include the unsubscribe link"
    CAN-SPAM and GDPR both require a way to opt out. Make sure `{courier_unsubscribe_url}` appears in every email. The default layout already includes it in the footer — if you write a custom layout or a self-contained markdown file, add it yourself.

Place them in your layout so every campaign gets them automatically:

```html
<!-- in your layout footer -->
<a href="{courier_unsubscribe_url}">Unsubscribe</a>

<!-- at the very end of <body> -->
{courier_tracking_pixel}
```

## Using a custom layout

Point a campaign at your own layout view — this works for both PHP views and markdown files:

```php
$campaignService->create([
    // ...
    'view'   => 'emails/may_newsletter.md',          // markdown body
    'layout' => 'App\Views\emails\layouts\branded',  // PHP layout wraps it
]);
```

Your layout needs to output `<?= $content ?>` where the body should appear.

To change the default for all campaigns, update `$defaultLayout` in your config:

```php
public string $defaultLayout = 'App\Views\emails\layouts\branded';
```

## Plain-text fallback

Courier generates a plain-text alternative automatically. The behavior differs slightly by template type:

- **PHP views** — Courier renders the body view, strips HTML tags, and collapses whitespace.
- **Markdown files** — Courier strips the markdown syntax from the source, so headings, `**bold**`, and `` `code` `` markers don't show up as literal characters in the text part. Links are rendered as `text (url)`.

Either way, the unsubscribe URL is appended at the bottom. You don't need to maintain a separate plain-text file.

## Testing your templates

Set `testMode = true` in your config and trigger a send — Courier logs the recipient and subject instead of sending. To preview the rendered HTML directly, use `TemplateService`:

=== "PHP view"
    ```php
    $html = service('templateService')->render(
        'App\Views\emails\may_newsletter',
        'App\Views\emails\layouts\branded',
        ['contact' => $contact, 'subject' => 'Preview']
    );
    ```

=== "Markdown file"
    ```php
    $html = service('templateService')->render(
        'emails/may_newsletter.md',
        'App\Views\emails\layouts\branded',
        ['contact' => $contact, 'subject' => 'Preview']
    );
    ```

## Next steps

- [Configuration](configuration.md) — set `$markdownPath` to control where markdown files are resolved from
- [Campaigns](campaigns.md) — create a blast or drip campaign that uses your template
- [Tracking](tracking.md) — how open and click tracking work under the hood
