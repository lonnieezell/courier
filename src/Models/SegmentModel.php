<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;

/**
 * Manages rule-based segments for targeting groups of contacts.
 */
class SegmentModel extends Model
{
    protected $table         = 'courier_segments';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'rules',
        'match_mode',
    ];

    protected array $casts = [
        'rules' => '?json',
    ];

    protected $validationRules = [
        'name'       => 'required|max_length[100]',
        'rules'      => 'required',
        'match_mode' => 'required|in_list[all,any]',
    ];
}
