<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\DTO\ContactDTO;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages email contacts, including token generation and subscription status.
 */
class ContactModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_contacts';
    protected $returnType      = 'object';
    protected string $dtoClass = ContactDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'email',
        'first_name',
        'last_name',
        'status',
        'source',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
        'custom_fields',
    ];
    protected array $casts = [
        'custom_fields' => '?json',
        'status'        => 'enum[\Myth\Courier\Enums\ContactStatus]',
    ];
    protected $validationRules = [
        'email'  => 'required|valid_email|max_length[255]|is_unique[courier_contacts.email,id,{id}]',
        'status' => 'permit_empty|in_list[subscribed,unsubscribed,bounced,complained]',
    ];
    protected $beforeInsert = ['generateToken'];

    /**
     * Fires before insert to ensure every contact has an unsubscribe token.
     * Skips generation if one was supplied explicitly, so imported contacts
     * can carry their existing tokens.
     */
    protected function generateToken(array $data): array
    {
        if (! isset($data['data']['unsubscribe_token']) || $data['data']['unsubscribe_token'] === '') {
            $data['data']['unsubscribe_token'] = bin2hex(random_bytes(32));
        }

        return $data;
    }

    /**
     * Restricts the query to contacts with status = 'subscribed'.
     * Returns $this so it can be chained with other query builder calls.
     */
    public function subscribed(): static
    {
        return $this->where('status', 'subscribed');
    }

    /**
     * Excludes contacts who already have a 'sent' row for the given campaign.
     * Returns $this so it can be chained with other query builder calls.
     */
    public function excludeSentForCampaign(int $campaignId): static
    {
        $p      = $this->db->getPrefix();
        $status = $this->db->escape(SendStatus::Sent->value);
        $sql    = 'NOT EXISTS ('
            . "SELECT 1 FROM {$p}courier_sends _cs "
            . "WHERE _cs.campaign_id = {$campaignId} AND _cs.contact_id = {$p}courier_contacts.id "
            . "AND _cs.status = {$status}"
            . ')';

        return $this->where($sql, null, false);
    }
}
