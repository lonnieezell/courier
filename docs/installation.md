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
php spark publish:config Courier
```

This copies `Config/Courier.php` into your app's `app/Config/` folder. At minimum, set your default sender details:

```php
<?php
// app/Config/Courier.php

public string $fromName  = 'Acme Newsletter';
public string $fromEmail = 'hello@acme.com';
```

Everything else has sensible defaults. See [Configuration](configuration.md) for the full list.

## Tracking routes

Courier automatically registers three routes for open pixels, click redirects, and unsubscribe links:

```
GET /courier/open/(:segment)
GET /courier/click/(:segment)
GET /courier/unsubscribe/(:segment)
```

No manual wiring needed. If you're using a reverse proxy or need to verify the routes are active, run:

```bash
php spark routes
```

That's it — Courier is ready to go.

## Next steps

- [Configure Courier](configuration.md) — tune batch size, test mode, and tracking domain
- [Manage contacts](contacts.md) — start subscribing people
