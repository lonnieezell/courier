<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\DTO\SegmentDTO;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages rule-based segments for targeting groups of contacts.
 */
class SegmentModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_segments';
    protected $returnType      = 'object';
    protected string $dtoClass = SegmentDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
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
