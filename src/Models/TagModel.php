<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\DTO\TagDTO;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages tags used to categorise contacts.
 */
class TagModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_tags';
    protected $returnType      = 'object';
    protected string $dtoClass = TagDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'slug',
        'label',
    ];
    protected $validationRules = [
        'slug'  => 'required|max_length[100]',
        'label' => 'required|max_length[100]',
    ];
}
