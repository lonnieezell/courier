# myth/courier

Email campaigns and drip sequences for CodeIgniter 4.

- **Blast campaigns** — send a one-time email to a segment, tag-filtered audience, or all contacts
- **Drip sequences** — multi-step automated email sequences with configurable delays between steps
- **Contact management** — subscribe/unsubscribe, status tracking, tags, and custom fields
- **Audience segmentation** — target contacts by segment or tag
- **Email tracking** — open pixels, click-wrapped links, bounce and complaint logging
- **CLI automation** — spark commands for scheduled delivery and drip processing

## Requirements

- PHP 8.4+
- CodeIgniter 4.7+

## Installation

**Not registered on Packagist yet** — for now, add this to your `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/lonnieezell/courier"
    }
]
```

Then require the package:

```bash
composer require myth/courier
```

Run the package migrations:

```bash
php spark migrate --all
```

## Configuration

Publish the config file:

```bash
php spark publish:config Courier
```

Key properties in `app/Config/Courier.php`:

| Property | Default | Description |
|---|---|---|
| `$fromName` | `''` | Default sender name |
| `$fromEmail` | `''` | Default sender address |
| `$defaultLayout` | bundled layout | CI4 view path for the email wrapper |
| `$trackingHost` | `''` | Custom tracking domain (leave empty to use `base_url()`) |
| `$batchSize` | `200` | Max emails sent per CLI run |
| `$throttleMs` | `0` | Milliseconds to sleep between individual sends |
| `$testMode` | `false` | Log emails instead of sending them |

## Usage

Services are resolved via CI4's service container.

### Subscribe a contact

```php
$contactService = service('contactService');

$contact = $contactService->subscribe([
    'email'      => 'jane@example.com',
    'first_name' => 'Jane',
]);

// Optionally apply tags on subscribe
$contact = $contactService->subscribe($data, tags: ['newsletter', 'vip']);
```

### Schedule a blast campaign

```php
$campaignService = service('campaignService');

$campaign = $campaignService->create([
    'name'            => 'May Newsletter',
    'subject'         => 'What\'s new in May',
    'view'            => 'emails/newsletter',
    'audience_filter' => ['tags' => ['newsletter']],
]);

$campaignService->schedule($campaign->id, new DateTime('+1 hour'));
```

### Enroll a contact in a drip sequence

```php
$dripService = service('dripService');

$dripService->enroll(contactId: $contact->id, campaignId: $drip->id);
```

## CLI Commands

Run these on a cron schedule to process campaigns and drip steps automatically.

```bash
# Send all scheduled blast campaigns that are due
php spark courier:send-campaign

# Send a specific campaign by ID
php spark courier:send-campaign 42

# Process a batch of due drip steps
php spark courier:process-drips
```

A typical cron setup sends campaigns once per minute and processes drips every minute:

```
* * * * * php /path/to/app/spark courier:send-campaign
* * * * * php /path/to/app/spark courier:process-drips
```

## Project Structure

```
src/
  Commands/             # spark courier:send-campaign, courier:process-drips
  Config/
    Courier.php         # Package configuration
    Registrar.php       # CI4 auto-discovery hooks
    Services.php        # Service container bindings
  Database/
    Migrations/         # Schema migrations (contacts, campaigns, drips, sends, events)
  DTO/                  # Typed data transfer objects
  Enums/                # Status enums (CampaignStatus, ContactStatus, etc.)
  Models/               # CI4 models (Contact, Campaign, Send, Event, Tag, …)
  Services/             # CampaignService, DripService, ContactService, MailerService, …
  Views/                # Bundled email templates and layouts
tests/
  Commands/
  Models/
  Services/
  _support/             # Fixtures and test helpers
docs/
  index.md
  installation.md
  changelog.md
mkdocs.yml              # Material for MkDocs
```

## Running Tests

```bash
composer test                       # run PHPUnit locally
composer test:coverage              # HTML coverage report → build/phpunit/html/

composer docker:test                # run PHPUnit in Docker
composer docker:test:coverage       # coverage in Docker
```

Run a single test file:

```bash
./vendor/bin/phpunit tests/Services/Courier/CampaignServiceTest.php
```

## Code Quality

```bash
composer cs          # check coding style (php-cs-fixer, dry-run)
composer cs-fix      # auto-fix coding style
composer analyze     # PHPStan (level 5) + Rector dry-run
composer rector      # apply Rector changes
composer ci          # run all checks: cs → analyze → test

# Docker equivalents (prefix with docker:)
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
