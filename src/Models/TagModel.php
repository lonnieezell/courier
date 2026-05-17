<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;

/**
 * Manages tags used to categorise contacts.
 */
class TagModel extends Model
{
    protected $table         = 'courier_tags';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'slug',
        'label',
    ];

    protected $validationRules = [
        'slug'  => 'required|max_length[100]',
        'label' => 'required|max_length[100]',
    ];
}
