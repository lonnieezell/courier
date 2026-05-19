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
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Services\CampaignService;
use Throwable;

/**
 * Sends scheduled blast campaigns.
 *
 * Usage:
 *   php spark courier:send-campaign           # processes all due scheduled campaigns
 *   php spark courier:send-campaign 42        # processes campaign ID 42 only
 */
class SendCampaign extends BaseCommand
{
    protected $group       = 'Courier';
    protected $name        = 'courier:send-campaign';
    protected $description = 'Send scheduled blast campaigns.';
    protected $arguments   = [
        'campaignId' => '[Optional] ID of a specific campaign to send.',
    ];

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(array $params): void
    {
        /** @var CampaignService $campaignService */
        $campaignService = service('campaignService');
        $campaignModel   = model(CampaignModel::class);

        $campaignId = isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null;

        if ($campaignId !== null) {
            $campaigns = [$campaignModel->find($campaignId)];
            $campaigns = array_filter($campaigns);
        } else {
            $campaigns = $campaignModel
                ->where('status', CampaignStatus::Scheduled->value)
                ->where('scheduled_at <=', date('Y-m-d H:i:s'))
                ->findAll();
        }

        $batchSize = (int) config('Courier')->batchSize;

        foreach ($campaigns as $campaign) {
            try {
                $campaignModel->update($campaign->id, ['status' => CampaignStatus::Sending]);

                $contacts = $campaignService->resolveAudience($campaign);
                $offset   = 0;

                do {
                    $sends = $campaignService->prepareBatch($campaign, $contacts, $offset);
                    $campaignService->sendBatch($sends);
                    $offset += $batchSize;
                } while (count($sends) === $batchSize);

                $campaignModel->update($campaign->id, [
                    'status'  => CampaignStatus::Sent,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $e) {
                $campaignModel->update($campaign->id, ['status' => CampaignStatus::Paused]);
                log_message('error', '[courier:send-campaign] Campaign {id} failed: {message}', [
                    'id'      => $campaign->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
