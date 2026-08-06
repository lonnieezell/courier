<?php

declare(strict_types=1);

namespace Config;

use Myth\Courier\Config\Courier as CourierConfig;

/**
 * Courier configuration for this application.
 *
 * Every setting is inherited from the package's config — redeclare only the
 * properties you want to change. At minimum, set your default sender:
 *
 *     public string $fromName  = 'Acme Newsletter';
 *     public string $fromEmail = 'hello@acme.com';
 *
 * See https://lonnieezell.github.io/courier/configuration/ for the full list.
 */
class Courier extends CourierConfig
{
}
