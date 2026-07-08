<?php

declare(strict_types=1);

namespace Myth\Courier\Publishers;

use CodeIgniter\Publisher\Publisher;

/**
 * Copies Courier's config file into the host app's Config directory
 * so it can be customized. Discovered and run via `php spark publish`.
 */
class ConfigPublisher extends Publisher
{
    protected $source      = __DIR__ . '/../Config';
    protected $destination = APPPATH . 'Config';

    public function publish(): bool
    {
        return $this->addPath('Courier.php', false)->copy(false);
    }
}
