<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
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
        'campaign_id' => 'permit_empty|integer',
        'status'      => 'permit_empty|in_list[pending,sent,failed,bounced,suppressed]',
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
     * Finds the most recent pending send addressed to the given contact email,
     * used to resolve a per-recipient unsubscribe URL at dispatch time.
     */
    public function findLatestPendingByEmail(string $email): ?SendDTO
    {
        return $this->select('courier_sends.*')
            ->join('courier_contacts', 'courier_contacts.id = courier_sends.contact_id')
            ->where('courier_contacts.email', $email)
            ->where('courier_sends.status', SendStatus::Pending->value)
            ->orderBy('courier_sends.id', 'DESC')
            ->first();
    }

    /**
     * Inserts a new send record in 'pending' status with freshly generated
     * open and unsubscribe tracking tokens, then returns the hydrated object.
     * Pass null for $stepId on blast campaigns that have no drip step, and null
     * for $campaignId on transactional (one-off) sends that belong to no campaign.
     */
    public function createPending(int $contactId, ?int $campaignId, ?int $stepId): SendDTO
    {
        $days   = config('Courier')->unsubscribeTokenExpireDays;
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
