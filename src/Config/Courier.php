<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

use CodeIgniter\Config\BaseConfig;
use Myth\Courier\Exceptions\PackageException;

class Courier extends BaseConfig
{
    /**
     * Default from name used when sending emails.
     * .env: Courier.fromName
     */
    public string $fromName = '';

    /**
     * Default from email address used when sending emails.
     * .env: Courier.fromEmail
     */
    public string $fromEmail = '';

    /**
     * Default layout view path. Used when a campaign has no layout set.
     * Must be a CI4-resolvable view string (short name, namespaced, or full path).
     */
    public string $defaultLayout = 'Myth\Courier\Views\courier/layouts/default';

    /**
     * Host used for tracking links, unsubscribe links, and pixel URLs.
     * Set this only if you use a custom tracking domain (e.g. 'https://track.yoursite.com').
     * Leave empty to use CI4's base_url() automatically.
     * .env: Courier.trackingHost
     */
    public string $trackingHost = '';

    /**
     * Maximum number of emails to send per task/queue run.
     * .env: Courier.batchSize
     */
    public int $batchSize = 200;

    /**
     * Minutes to wait before retrying a failed drip step send.
     * .env: Courier.retryDelayMinutes
     */
    public int $retryDelayMinutes = 5;

    /**
     * Maximum send attempts per drip step before marking enrollment as failed.
     * .env: Courier.maxRetries
     */
    public int $maxRetries = 3;

    /**
     * Milliseconds to sleep between individual sends (0 = no throttle).
     * .env: Courier.throttleMs
     */
    public int $throttleMs = 0;

    /**
     * Days until a per-send unsubscribe token expires.
     * .env: Courier.unsubscribeTokenExpireDays
     */
    public int $unsubscribeTokenExpireDays = 365;

    /**
     * When true, emails are logged instead of sent via the mailer.
     * .env: Courier.testMode
     */
    public bool $testMode = false;

    /**
     * Max capture form submissions per IP per minute. 0 disables rate limiting.
     * .env: Courier.captureRateLimit
     */
    public int $captureRateLimit = 15;

    /**
     * When true, courier_form renders a hidden honeypot field to reject bots.
     * .env: Courier.honeypot
     */
    public bool $honeypot = true;

    /**
     * Base directory for resolving markdown email files.
     * Leave empty to use APPPATH . 'courier/emails/'. Set an absolute path to override (useful in tests).
     */
    public string $markdownPath = '';

    /**
     * Base directory for YAML drip campaign definition files.
     * Leave empty to use APPPATH . 'courier/campaigns/'. Set an absolute path to override.
     */
    public string $campaignsPath = '';

    /**
     * Fully-qualified class name of the WebhookDriverInterface implementation.
     * Leave empty to disable the webhook endpoint (returns 400).
     * Example: \Myth\Courier\Webhooks\Drivers\SesDriver::class
     */
    public string $webhookDriver = '';

    /**
     * Throws if required production settings are missing.
     * Skipped when testMode is true.
     */
    public function validate(): void
    {
        if ($this->testMode) {
            return;
        }

        if ($this->fromEmail === '') {
            throw new PackageException('Courier: $fromEmail must be set in production. Add "Courier.fromEmail = you@example.com" to your .env file.');
        }
    }
}
