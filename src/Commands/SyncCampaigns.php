<?php

declare(strict_types=1);

namespace Myth\Courier\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Services\CampaignFileLoader;
use Throwable;

/**
 * Syncs YAML campaign definition files into the courier_campaigns table.
 * Drip sequence steps are not stored in the database — the YAML file is the
 * runtime source of truth for step content. Blast campaigns store their
 * subject/view/tag_filter directly on the row, same as one created via
 * `courier:campaigns create`. Re-running this command upserts campaigns by
 * name; a Blast's status/scheduled_at/sent_at are left alone on update so a
 * content-only re-sync can't clobber a send an operator already scheduled.
 *
 * Usage:
 *   php spark courier:sync-campaigns
 */
class SyncCampaigns extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:sync-campaigns';
    protected $description = 'Sync YAML campaign files into the database.';

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        /** @var CampaignFileLoader $loader */
        $loader  = service('campaignFileLoader');
        $results = $loader->validateAll();

        if ($results === []) {
            CLI::write('No campaign files found in: ' . $loader->resolvedPath());

            return;
        }

        $campaignModel = new CampaignModel();

        foreach ($results as $filename => ['errors' => $errors, 'parsed' => $parsed]) {
            if ($errors !== []) {
                foreach ($errors as $error) {
                    CLI::error('SKIP ' . $error);
                }

                continue;
            }

            $campaign = $parsed['campaign'];
            $existing = $campaignModel->where('name', $campaign['name'])->first();
            $type     = CampaignType::tryFrom((string) ($campaign['type'] ?? CampaignType::DripSequence->value)) ?? CampaignType::DripSequence;

            $row = [
                'name'        => $campaign['name'],
                'type'        => $type,
                'source_file' => $filename,
                'from_name'   => $campaign['from_name'],
                'from_email'  => $campaign['from_email'],
            ];

            if ($type === CampaignType::Blast) {
                $row['subject']    = $campaign['subject'];
                $row['view']       = $campaign['view'];
                $row['tag_filter'] = $campaign['tag_filter'] ?? null;
            } else {
                $row['subject'] = $campaign['name'];
                $row['status']  = CampaignStatus::Active;
            }

            if (isset($campaign['layout'])) {
                $row['layout'] = $campaign['layout'];
            }

            try {
                if ($existing !== null) {
                    $campaignModel->update($existing->id, $row);
                    CLI::write("UPDATED {$filename} → campaign '{$campaign['name']}'");
                } else {
                    if ($type === CampaignType::Blast) {
                        $row['status'] = CampaignStatus::Draft;
                    }

                    $campaignModel->insert($row);
                    CLI::write("CREATED {$filename} → campaign '{$campaign['name']}'");
                }
            } catch (Throwable $e) {
                CLI::error("FAIL {$filename}: " . $e->getMessage());
            }
        }
    }
}
