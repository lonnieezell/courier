# myth/courier

Email campaigns and drip sequences for CodeIgniter 4.

**[Full documentation →](https://lonnieezell.github.io/courier/)**

- **Blast campaigns** — send a one-time email to a segment, tag-filtered audience, or all contacts
- **Drip sequences** — multi-step automated email sequences with configurable delays between steps
- **Contact management** — subscribe/unsubscribe, status tracking, tags, and custom fields
- **Audience segmentation** — target contacts by segment or tag
- **Email tracking** — open pixels, click-wrapped links, bounce and complaint logging
- **CLI automation** — spark commands for scheduled delivery and drip processing

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+

## Installation

```bash
composer require myth/courier
```

Run the package migrations:

```bash
php spark migrate --all
```

### Register tracking routes

The package ships a `Config/Routes.php` that registers the tracking endpoints automatically via CI4's module discovery. Enable it by adding `'routes'` to your `app/Config/Modules.php`:

```php
// app/Config/Modules.php
public $aliases = ['routes'];
```

That's it — the following routes are then available with no further configuration:

| Route | Purpose |
|---|---|
| `GET /courier/open/{token}` | Open-tracking pixel (returns 1×1 GIF) |
| `GET /courier/click/{token}` | Click redirect (tracks click, redirects to target URL) |
| `GET /courier/unsubscribe/{token}` | One-click unsubscribe |
| `POST /courier/capture` | Signup form capture (used by `courier_form()`) |

**Custom prefix or manual control:** If you want a different URL prefix or to disable the routes entirely, keep `'routes'` out of `$aliases` and add the group yourself in `app/Config/Routes.php`:

```php
$routes->group('my-prefix', ['namespace' => 'Myth\Courier\Controllers'], static function ($routes): void {
    $routes->get('open/(:segment)',        'CourierController::open/$1');
    $routes->get('click/(:segment)',       'CourierController::click/$1');
    $routes->get('unsubscribe/(:segment)', 'CourierController::unsubscribe/$1');
    $routes->post('capture',              'CourierController::capture');
});
```

## Configuration

Publish the config file:

```bash
php spark publish:config Courier
```

Edit `app/Config/Courier.php`:

| Property | Type | Default | Description |
|---|---|---|---|
| `$fromName` | `string` | `''` | Default sender display name |
| `$fromEmail` | `string` | `''` | Default sender address |
| `$defaultLayout` | `string` | bundled default | CI4 view path for the email wrapper layout |
| `$trackingHost` | `string` | `''` | Custom tracking domain (e.g. `https://track.yoursite.com`); leave empty to use `base_url()` |
| `$batchSize` | `int` | `200` | Max emails sent per CLI run |
| `$throttleMs` | `int` | `0` | Milliseconds to sleep between individual sends (`0` = no throttle) |
| `$testMode` | `bool` | `false` | When `true`, logs emails instead of sending them |

## Quick Start

### Subscribe a contact

```php
$contactService = service('contactService');

// Basic subscribe
$contact = $contactService->subscribe([
    'email'      => 'jane@example.com',
    'first_name' => 'Jane',
]);

// Subscribe with tags
$contact = $contactService->subscribe($data, tags: ['newsletter', 'vip']);

// Subscribe and immediately enroll in a drip campaign
$contact = $contactService->subscribe($data, tags: ['trial'], dripCampaignId: $drip->id);
```

### Send a blast campaign

```php
$campaignService = service('campaignService');

$campaign = $campaignService->create([
    'name'       => 'May Newsletter',
    'subject'    => 'What\'s new in May',
    'view'       => 'emails/newsletter',
    'from_name'  => 'ACME Co.',
    'from_email' => 'news@acme.com',
    'tag_filter' => ['newsletter'],  // or 'segment_id' => 3
]);

$campaignService->schedule($campaign->id, new DateTime('+1 hour'));
// Courier CLI will pick it up on the next run
```

### Set up a drip sequence

```php
$campaignService = service('campaignService');
$dripService     = service('dripService');

// 1. Create the campaign
$campaign = $campaignService->create([
    'name'    => 'Welcome Drip',
    'subject' => 'Welcome!',
    'type'    => 'drip_sequence',
]);

// 2. Add steps
$campaignService->addDripStep($campaign->id, [
    'view'        => 'emails/welcome_step1',
    'subject'     => 'Welcome to the family',
    'delay_hours' => 0,   // send immediately on enroll
]);
$campaignService->addDripStep($campaign->id, [
    'view'        => 'emails/welcome_step2',
    'subject'     => 'How are you settling in?',
    'delay_hours' => 48,
]);

// 3. Enroll a contact
$dripService->enroll(contactId: $contact->id, campaignId: $campaign->id);
```

## Segmentation

Courier supports two audience targeting strategies.

### Tag filtering

Tag-filter campaigns send to all contacts who have ALL of the specified tags:

```php
$campaignService->create([
    'name'       => 'Trial Users',
    'tag_filter' => ['trial', 'active'],  // stored as JSON
    // ...
]);
```

### Segment rules

Segments let you save reusable audience definitions. The `rules` column is a JSON array of conditions matched against contact fields:

```json
[
    {"field": "custom_fields->plan", "op": "=",    "value": "pro"},
    {"field": "status",              "op": "=",    "value": "subscribed"},
    {"field": "subscribed_at",       "op": ">=",   "value": "2024-01-01"}
]
```

`match_mode` controls whether all rules must match (`AND`) or any rule may match (`OR`).

Create segments via `SegmentService`:

```php
$segmentService = service('segmentService');
$contacts = $segmentService->resolve($segment->id);        // array of ContactDTO
$count    = $segmentService->previewCount($segment->id);   // int
```

## Template Authoring

Email templates are standard CI4 views. The view path is stored on each campaign or drip step and resolved via `TemplateService::render()`.

### Available variables

Inside a campaign body view:

| Variable | Description |
|---|---|
| `$contact` | `ContactDTO` — email, first_name, last_name, custom_fields, etc. |
| `$campaign` | `CampaignDTO` — name, subject, from_name, from_email |
| `$unsubscribeUrl` | Pre-generated one-click unsubscribe URL |

Inside a layout view:

| Variable | Description |
|---|---|
| `$content` | The rendered body view HTML |
| `$contact` | Same `ContactDTO` as above |
| `$campaign` | Same `CampaignDTO` as above |
| `$unsubscribeUrl` | One-click unsubscribe URL |

### Example body view

```php
// app/Views/emails/welcome.php
<p>Hi <?= esc($contact->first_name) ?>,</p>
<p>Thanks for joining!</p>
<p><a href="<?= $unsubscribeUrl ?>">Unsubscribe</a></p>
```

### Bundled views

The package ships with example templates under `src/Views/courier/emails/` and a default layout under `src/Views/courier/layouts/default.php`. Copy and adapt them as starting points.

## Tracking

The three tracking endpoints are registered under the `/courier/` route group. Routes are auto-discoverable — see [Register tracking routes](#register-tracking-routes) for setup.

| Endpoint | Behavior |
|---|---|
| `GET /courier/open/{token}` | Records an open event; returns a 1×1 transparent GIF. Always returns the GIF — never 404 — so broken pixels stay silent in email clients. |
| `GET /courier/click/{token}?url={encoded-url}` | Records a click event (stores URL + client IP) and redirects to the target URL. Validates that the URL starts with `http://` or `https://` to prevent open-redirect abuse. |
| `GET /courier/unsubscribe/{token}` | Calls `ContactService::unsubscribeByToken()` and renders a confirmation page. Returns 404 for unknown tokens. |

Click tracking is injected automatically when `MailerService` sends: every `href` in the email body is rewritten through the click endpoint.

The unsubscribe URL is injected as the `$unsubscribeUrl` template variable automatically.

## Scheduled Tasks

Run these on a cron schedule to process campaigns and drip steps automatically.

| Command | What it does | Recommended schedule |
|---|---|---|
| `courier:send-campaign` | Sends all blast campaigns that are in `scheduled` status and past their `scheduled_at` time. Accepts an optional campaign ID to process a single campaign. | Every minute |
| `courier:process-drips` | Sends one batch of due drip steps (`$batchSize` emails). | Every minute |
| `courier:track-events` | Stub for processing bounce webhooks or SMTP feedback loops. Does nothing by default — extend to add real handling. | As needed |

Example crontab:

```
* * * * * php /path/to/app/spark courier:send-campaign
* * * * * php /path/to/app/spark courier:process-drips
```

## Extending — Bounce Webhook Handler

To process bounces from your email provider's webhook, extend `TrackEvents`:

```php
// app/Commands/HandleBounces.php
namespace App\Commands;

use Myth\Courier\Commands\TrackEvents;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\EventModel;

class HandleBounces extends TrackEvents
{
    protected $name        = 'courier:handle-bounces';
    protected $description = 'Process bounce webhooks from email provider.';

    public function run(array $params): void
    {
        // Fetch bounce data from your provider's API or queue…
        foreach ($this->fetchBounces() as $email) {
            $this->processBounce($email);
        }
    }

    protected function processBounce(string $email): void
    {
        $contact = model(ContactModel::class)->where('email', $email)->first();
        if ($contact === null) {
            return;
        }

        model(ContactModel::class)->update($contact->id, ['status' => 'bounced']);
        service('contactService')->cancelAllDrips($contact->id);
        model(EventModel::class)->insert(['send_id' => 0, 'type' => 'bounce']);
    }
}
```

## Project Structure

```
src/
  Commands/             # courier:send-campaign, courier:process-drips, courier:track-events
  Config/
    Courier.php         # Package configuration
    Registrar.php       # CI4 auto-discovery hooks
    Routes.php          # Auto-discoverable tracking routes (opt-in via Modules::$aliases)
    Services.php        # Service container bindings
  Controllers/
    CourierController.php   # Tracking pixel, click redirect, unsubscribe
  Database/
    Migrations/         # Schema: contacts, campaigns, drips, sends, events, tags
  DTO/                  # Typed data transfer objects (ContactDTO, SendDTO, …)
  Enums/                # Status enums (CampaignStatus, ContactStatus, SendStatus, …)
  Models/               # CI4 models (Contact, Campaign, Send, Event, Tag, …)
  Services/             # CampaignService, DripService, ContactService, MailerService, …
  Views/
    courier/
      emails/           # Bundled example email body views
      layouts/          # Bundled default layout
      unsubscribe_success.php
      unsubscribe_invalid.php
tests/
  Commands/
  Controllers/
  Integration/
  Models/
  Services/
  _support/             # Fixtures and test helpers
docs/
mkdocs.yml
```

## Running Tests

```bash
composer test                       # run PHPUnit locally
composer test:coverage              # HTML coverage report → build/phpunit/html/

composer docker:test                # run PHPUnit in Docker
composer docker:test:coverage       # coverage in Docker
```

## Code Quality

```bash
composer cs          # check coding style (php-cs-fixer, dry-run)
composer cs-fix      # auto-fix coding style
composer analyze     # PHPStan (level 5) + Rector dry-run
composer rector      # apply Rector changes
composer ci          # run all checks: cs → analyze → test

# Docker equivalents
composer docker:cs
composer docker:ci
```

## Docker

```bash
docker compose up         # start dev environment at http://localhost:8080
composer docker:build     # rebuild image after Dockerfile changes
composer docker:shell     # bash shell inside the container
```

## Documentation (MkDocs)

Docs live in `docs/` and are built with [Material for MkDocs](https://squidfunk.github.io/mkdocs-material/).

```bash
pip3 install mkdocs mkdocs-material
mkdocs serve        # live-reload preview at http://127.0.0.1:8000
mkdocs build        # build static output to site/
mkdocs gh-deploy    # deploy to GitHub Pages
```

## License

MIT — see [LICENSE](LICENSE).
