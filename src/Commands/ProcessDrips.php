<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use Myth\Courier\Services\DripServiceInterface;
use Throwable;

/**
 * Processes one batch of due drip enrollment steps.
 *
 * Intentionally sends at most $config->batchSize emails per invocation to
 * avoid overwhelming the email provider. Run this command on a frequent
 * schedule (e.g. every minute via cron) so that the full queue drains over
 * successive runs.
 *
 * Usage:
 *   php spark courier:process-drips
 */
class ProcessDrips extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:process-drips';
    protected $description = 'Send due drip sequence steps to enrolled contacts.';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        /** @var DripServiceInterface $dripService */
        $dripService = service('dripService');

        try {
            $result = $dripService->processDue();

            log_message('info', '[courier:process-drips] processed={processed} cancelled={cancelled} failed={failed}', $result);
        } catch (Throwable $e) {
            log_message('error', '[courier:process-drips] Exception: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
