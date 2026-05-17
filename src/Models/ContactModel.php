<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;

/**
 * Manages email contacts, including token generation and subscription status.
 */
class ContactModel extends Model
{
    protected $table         = 'courier_contacts';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
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
}
