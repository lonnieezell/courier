<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\Config\Courier;
use Myth\Courier\DTO\SendDTO;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages individual email sends, including tracking tokens and delivery status.
 */
class SendModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_sends';
    protected $returnType      = 'object';
    protected string $dtoClass = SendDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'contact_id',
        'campaign_id',
        'drip_step_id',
        'status',
        'message_id',
        'open_token',
        'unsubscribe_token',
        'unsubscribe_token_expires_at',
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

    public function findByOpenToken(string $token): ?SendDTO
    {
        return $this->where('open_token', $token)->first();
    }

    /**
     * Finds a send record by its per-send unsubscribe token.
     */
    public function findByUnsubscribeToken(string $token): ?SendDTO
    {
        return $this->where('unsubscribe_token', $token)->first();
    }

    /**
     * Inserts a new send record in 'pending' status with freshly generated
     * open and unsubscribe tracking tokens, then returns the hydrated object.
     * Pass null for $stepId on blast campaigns that have no drip step.
     */
    public function createPending(int $contactId, int $campaignId, ?int $stepId): SendDTO
    {
        $days   = config(Courier::class)->unsubscribeTokenExpireDays;
        $expiry = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

        $id = $this->insert([
            'contact_id'                   => $contactId,
            'campaign_id'                  => $campaignId,
            'drip_step_id'                 => $stepId,
            'status'                       => SendStatus::Pending,
            'open_token'                   => bin2hex(random_bytes(16)),
            'unsubscribe_token'            => bin2hex(random_bytes(16)),
            'unsubscribe_token_expires_at' => $expiry,
        ]);

        return $this->find($id);
    }
}
