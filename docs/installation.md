# Installation

## Install via Composer

```bash
composer require myth/courier
```

CI4's module auto-discovery picks up Courier automatically — no manual wiring needed.

## Run the migrations

Courier creates its own tables prefixed with `courier_`. Run the migrations just like any other CI4 migration:

```bash
php spark migrate --all
```

This creates the following tables: `courier_contacts`, `courier_tags`, `courier_contact_tags`, `courier_segments`, `courier_campaigns`, `courier_drip_steps`, `courier_drip_enrollments`, `courier_sends`, and `courier_events`.

## Configure the basics

Publish Courier's config file so you can customize it:

```bash
php spark publish
```

This copies `Config/Courier.php` into your app's `app/Config/` folder. At minimum, set your default sender details:

```php
<?php
// app/Config/Courier.php

public string $fromName  = 'Acme Newsletter';
public string $fromEmail = 'hello@acme.com';
```

Everything else has sensible defaults. See [Configuration](configuration.md) for the full list.

## The mailer

Courier sends through [ci-postal](https://github.com/lonnieezell/postal) (`myth/postal`), which is pulled in automatically as a dependency — you don't install it separately. Postal is what actually talks to your transport (SMTP, SES, sendmail, and so on).

Pick a transport by creating `app/Config/Email.php` to override postal's config and set a default mailer:

```php
<?php

namespace Config;

use Myth\Postal\Config\Email as BaseEmail;

class Email extends BaseEmail
{
}
```

```php
<?php
// app/Config/Email.php (Myth\Postal\Config\Email)

public string $default = 'smtp';

public array $mailers = [
    'smtp' => [
        'transport' => 'smtp',
        'host'      => 'smtp.acme.com',
        'port'      => 587,
        'username'  => 'postmaster@acme.com',
        'password'  => 'super-secret',
    ],
];
```

Courier wires two things into postal for you, with no extra setup:

- **Suppression filtering** — unsubscribed, bounced, and complained contacts are dropped before dispatch.
- **One-click unsubscribe** — a `List-Unsubscribe` header (RFC 8058) is injected per recipient.

See [Tracking](tracking.md#unsubscribes) for the details.

!!! tip "Try it without a real transport first"
    Leave postal's `$default = 'log'` (or set Courier's `$testMode = true`) while you're wiring things up. Nothing leaves your server, but Courier still records every send.

## Tracking routes

Courier automatically registers routes for open pixels, click redirects, and unsubscribe links:

```
GET  /courier/open/(:segment)
GET  /courier/click/(:segment)
GET  /courier/unsubscribe/(:segment)
POST /courier/unsubscribe/(:segment)
```

The `POST` variant handles RFC 8058 one-click unsubscribes triggered from the mail client's own UI.

No manual wiring needed. If you're using a reverse proxy or need to verify the routes are active, run:

```bash
php spark routes
```

That's it — Courier is ready to go.

## Next steps

- [Configure Courier](configuration.md) — tune batch size, test mode, and tracking domain
- [Manage contacts](contacts.md) — start subscribing people
