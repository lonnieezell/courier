<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\Enums\CampaignType;

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
        'view',
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
        'status'     => 'enum[\Myth\Courier\Enums\CampaignStatus]',
        'type'       => 'enum[\Myth\Courier\Enums\CampaignType]',
    ];
    protected $validationRules = [
        'name'       => 'required|max_length[200]',
        'subject'    => 'required|max_length[500]',
        'type'       => 'required|in_list[blast,drip_sequence]',
        'status'     => 'required|in_list[draft,scheduled,sending,sent,paused,cancelled]',
        'from_name'  => 'required|max_length[100]',
        'from_email' => 'required|max_length[200]|valid_email',
    ];
}
