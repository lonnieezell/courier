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

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\Enums\SendStatus;

/**
 * Manages individual email sends, including tracking tokens and delivery status.
 */
class SendModel extends Model
{
    protected $table         = 'courier_sends';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'contact_id',
        'campaign_id',
        'drip_step_id',
        'status',
        'message_id',
        'open_token',
        'click_token',
        'sent_at',
        'opened_at',
        'clicked_at',
    ];
    protected array $casts = [
        'status' => 'enum[\Myth\Courier\Enums\SendStatus]',
    ];
    protected $validationRules = [
        'contact_id'  => 'required|integer',
        'campaign_id' => 'required|integer',
        'status'      => 'permit_empty|in_list[pending,sent,failed,bounced]',
    ];

    /**
     * Inserts a new send record in 'pending' status with freshly generated
     * open and click tracking tokens, then returns the hydrated object.
     * Pass null for $stepId on blast campaigns that have no drip step.
     */
    public function createPending(int $contactId, int $campaignId, ?int $stepId): object
    {
        $id = $this->insert([
            'contact_id'   => $contactId,
            'campaign_id'  => $campaignId,
            'drip_step_id' => $stepId,
            'status'       => SendStatus::Pending,
            'open_token'   => bin2hex(random_bytes(16)),
            'click_token'  => bin2hex(random_bytes(16)),
        ]);

        return $this->find($id);
    }
}
