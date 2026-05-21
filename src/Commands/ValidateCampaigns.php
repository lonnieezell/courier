<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Services\CampaignFileLoader;

/**
 * Validates YAML drip campaign definition files without touching the database.
 * Safe to run in CI pipelines pre-deploy.
 *
 * Exit code 0 = all files valid (or no files found).
 * Exit code 1 = one or more files failed validation.
 *
 * Usage:
 *   php spark courier:validate-campaigns
 */
class ValidateCampaigns extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:validate-campaigns';
    protected $description = 'Validate YAML drip campaign files without writing to the database.';

    /**
     * @param array<int|string, mixed> $params
     *
     * @return int exit code: 0 = valid, 1 = invalid
     */
    public function run(array $params): int
    {
        /** @var CampaignFileLoader $loader */
        $loader  = service('campaignFileLoader');
        $results = $loader->validateAll();

        if ($results === []) {
            CLI::write('No campaign files found in: ' . $loader->resolvedPath());

            return 0;
        }

        $failed = false;

        foreach ($results as $filename => ['errors' => $errors]) {
            if ($errors !== []) {
                foreach ($errors as $error) {
                    CLI::error('FAIL ' . $error);
                }
                $failed = true;
            } else {
                CLI::write("OK   {$filename}");
            }
        }

        return $failed ? 1 : 0;
    }
}
