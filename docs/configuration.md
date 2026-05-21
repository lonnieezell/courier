# Configuration

Courier's config file lives at `app/Config/Courier.php` after you publish it. Every option has a default that works out of the box — you only need to set what you want to change.

## Options

### `$fromName` / `$fromEmail`

```php
<?php
public string $fromName  = 'Acme Newsletter';
public string $fromEmail = 'hello@acme.com';
```

The default sender name and address for all outgoing emails. Individual campaigns can override these — these values are the fallback when a campaign doesn't specify its own.

### `$defaultLayout`

```php
<?php
public string $defaultLayout = 'Myth\Courier\Views\courier/layouts/default';
```

The email layout view used when a campaign doesn't have one set. Courier ships with a simple responsive layout at `Views/courier/layouts/default.php`. Point this at your own view to apply a custom look site-wide.

See [Email Templates](templates.md) for how layouts work.

### `$trackingHost`

```php
<?php
public string $trackingHost = '';
```

The base URL used to build tracking pixel URLs, click redirect URLs, and unsubscribe links. Leave empty and Courier will use CI4's `base_url()` automatically. Set it if you're using a custom tracking domain:

```php
<?php
public string $trackingHost = 'https://track.acme.com';
```

### `$batchSize`

```php
<?php
public int $batchSize = 200;
```

How many emails to send per `courier:send-campaign` or `courier:process-drips` command run. If a campaign has 10,000 contacts, the command works through them in chunks of 200 across successive cron runs.

Lower this if you're hitting rate limits; raise it if your email provider supports higher throughput.

### `$throttleMs`

```php
<?php
public int $throttleMs = 0;
```

Milliseconds to sleep between individual sends within a batch. `0` means no delay. Set this if your email provider has a per-second sending limit:

```php
<?php
public int $throttleMs = 100; // ~10 emails/second max
```

### `$markdownPath`

```php
public string $markdownPath = '';
```

The base directory Courier uses when resolving markdown email files. Leave empty and it defaults to `APPPATH` (your app's `app/` folder). Set an absolute path to load markdown files from a different location:

```php
public string $markdownPath = APPPATH . 'Emails/';
```

With this set, a campaign `view` of `welcome.md` resolves to `app/Emails/welcome.md`.

See [Email Templates](templates.md#creating-a-markdown-file) for the full markdown workflow.

### `$testMode`

```php
<?php
public bool $testMode = false;
```

When `true`, Courier skips the actual mailer and logs what it *would* send instead. Use this in development or CI to verify your campaign setup without delivering real emails:

```php
<?php
public bool $testMode = true;
```

You'll see log entries like:
```
[Courier] testMode: would send to ada@example.com subject "Welcome to Acme"
```

!!! tip "Environment-specific config"
    You can override any config value per-environment using CI4's `.env` file:
    ```
    courier.testMode = true
    courier.batchSize = 50
    ```
