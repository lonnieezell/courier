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

### `$retryDelayMinutes`

```php
<?php
public int $retryDelayMinutes = 5;
```

How long to wait before retrying a drip step send that failed. When `courier:process-drips` can't deliver a step (the mailer returns false or throws), it pushes the enrollment's `next_send_at` forward by this many minutes instead of retrying immediately.

5 minutes is a sensible default — long enough to survive a brief ESP blip, short enough that recipients aren't delayed noticeably. For high-volume setups you may want a longer window to avoid hammering a struggling provider:

```php
<?php
public int $retryDelayMinutes = 15;
```

!!! tip ".env override"
    ```
    courier.retryDelayMinutes = 10
    ```

### `$maxRetries`

```php
<?php
public int $maxRetries = 3;
```

Maximum number of send attempts per drip step before Courier gives up. After this many failures, the enrollment is marked `failed` and a `courier_enrollment_failed` event fires so your app can take action.

With the defaults (`retryDelayMinutes = 5`, `maxRetries = 3`), a step that keeps failing will be abandoned after roughly 15 minutes and 3 attempts. Raise this if you expect longer ESP outages:

```php
<?php
public int $maxRetries = 5;
```

!!! tip ".env override"
    ```
    courier.maxRetries = 5
    ```

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

### `$captureRateLimit`

```php
<?php
public int $captureRateLimit = 15;
```

Maximum number of POST submissions allowed per IP address per minute on the `/courier/capture` endpoint. Requests over the limit receive a `429 Too Many Requests` response — a redirect back with a `courier_errors` flash message for standard form submissions, or a JSON error for AJAX requests.

Set to `0` to disable rate limiting entirely:

```php
<?php
public int $captureRateLimit = 0; // no limit
```

For high-traffic sites or shared hosting environments where many users may share an IP, you can raise the limit:

```php
<?php
public int $captureRateLimit = 60;
```

!!! tip ".env override"
    ```
    courier.captureRateLimit = 30
    ```

### `$honeypot`

```php
<?php
public bool $honeypot = true;
```

When `true`, Courier renders a hidden `courier_hp` field in forms generated by `courier_form()` and `courier_form_open()`. Real users never see or fill this field. Bots that blindly populate all inputs get silently rejected — Courier returns a success response without saving anything.

Set to `false` to opt out:

```php
<?php
public bool $honeypot = false;
```

!!! warning "Custom form layouts"
    If you build your own form markup and post to `/courier/capture`, the honeypot check still runs server-side. To avoid false rejections, make sure your form doesn't submit a `courier_hp` field — or disable the honeypot if you can't control the submitted fields.

### `$trackIpAddress`

```php
<?php
public bool $trackIpAddress = false;
```

When `true`, the IP address of the recipient who clicked a tracked link is stored in the `metadata` column of `courier_events`.

**Default is `false`.** IP addresses are personal data under GDPR and CCPA. Only enable this setting if:

- Your privacy policy discloses that click IP addresses are collected and processed, and
- You have a data-retention policy that covers `courier_events` rows.

To enable:

```php
<?php
public bool $trackIpAddress = true;
```

!!! tip ".env override"
    ```
    courier.trackIpAddress = true
    ```

### `$webhookDriver`

```php
<?php
public string $webhookDriver = '';
```

The fully-qualified class name of a `WebhookDriverInterface` implementation that handles incoming ESP webhook notifications (bounces, complaints, subscription confirmations). Leave empty to disable the `POST /courier/webhook` endpoint — it returns `400` when no driver is configured.

Courier ships with a driver for AWS SES (routed through SNS):

```php
<?php
public string $webhookDriver = \Myth\Courier\Webhooks\Drivers\SesDriver::class;
```

To use a different ESP, implement `WebhookDriverInterface` and point this at your class.

!!! warning "CSRF exemption required"
    The webhook endpoint receives machine-to-machine POST requests from your ESP. You must add `'courier/webhook'` to `$CSRFExcludeURIs` in `app/Config/Security.php`, otherwise all webhook calls will be rejected.

!!! tip ".env override"
    ```
    courier.webhookDriver = \Myth\Courier\Webhooks\Drivers\SesDriver::class
    ```

See [Tracking — Bounce and complaint webhooks](tracking.md#bounce-and-complaint-webhooks) for the full setup walkthrough.
