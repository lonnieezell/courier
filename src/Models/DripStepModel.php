<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages individual steps within a drip campaign, including position and send delay.
 */
class DripStepModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_drip_steps';
    protected $returnType      = 'object';
    protected string $dtoClass = DripStepDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'campaign_id',
        'position',
        'view',
        'subject',
        'delay_hours',
    ];
    protected $validationRules = [
        'campaign_id' => 'required|integer',
        'position'    => 'required|integer',
        'view'        => 'required|max_length[200]',
        'subject'     => 'required|max_length[500]',
    ];
}
