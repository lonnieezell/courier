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

This writes `app/Config/Courier.php`, a `Config\Courier` class extending the package's config. Because it lives in your application's namespace, CI4 loads it in place of the package default — so anything you redeclare there wins. Publishing again leaves an existing file untouched.

Redeclare only what you want to change. At minimum, set your default sender details:

```php
<?php
// app/Config/Courier.php

namespace Config;

use Myth\Courier\Config\Courier as CourierConfig;

class Courier extends CourierConfig
{
    public string $fromName  = 'Acme Newsletter';
    public string $fromEmail = 'hello@acme.com';
}
```

Everything else has sensible defaults. See [Configuration](configuration.md) for the full list.

## The mailer

Courier sends through [ci-postal](https://github.com/lonnieezell/postal) (`myth/postal`), which is pulled in automatically as a dependency — you don't install it separately. Postal is what actually talks to your transport (SMTP, SES, sendmail, and so on).

The same `php spark publish` above also writes `app/Config/Mailer.php`, postal's config. Pick a transport there and set a default mailer:

```php
<?php
// app/Config/Mailer.php

namespace Config;

use Myth\Postal\Config\Mailer as PostalMailer;

class Mailer extends PostalMailer
{
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
}
```

!!! note
    Postal's config class was named `Email` before `v1.0.0-beta.2`. It was renamed to `Mailer` because the old short name collided with CI4's own `Config\Email`, which stopped application overrides from ever being read. If you are upgrading, rename your `app/Config/Email.php` accordingly.

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
