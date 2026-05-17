<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;

/**
 * Manages email campaigns, both one-time blasts and multi-step drip sequences.
 */
class CampaignModel extends Model
{
    protected $table         = 'courier_campaigns';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'subject',
        'type',
        'layout',
        'status',
        'segment_id',
        'tag_filter',
        'from_name',
        'from_email',
        'scheduled_at',
        'sent_at',
    ];

    protected array $casts = [
        'tag_filter' => '?json',
    ];

    protected $validationRules = [
        'name'       => 'required|max_length[200]',
        'subject'    => 'required|max_length[500]',
        'type'       => 'required|in_list[blast,drip]',
        'status'     => 'required|in_list[draft,scheduled,sent,cancelled]',
        'from_name'  => 'required|max_length[100]',
        'from_email' => 'required|max_length[200]|valid_email',
    ];
}
