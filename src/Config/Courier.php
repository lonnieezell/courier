<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

use CodeIgniter\Config\BaseConfig;

class Courier extends BaseConfig
{
    /**
     * Default from name used when sending emails.
     */
    public string $fromName = '';

    /**
     * Default from email address used when sending emails.
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
     */
    public string $trackingHost = '';

    /**
     * Maximum number of emails to send per task/queue run.
     */
    public int $batchSize = 200;

    /**
     * Milliseconds to sleep between individual sends (0 = no throttle).
     */
    public int $throttleMs = 0;

    /**
     * When true, emails are logged instead of sent via the mailer.
     */
    public bool $testMode = false;
}
