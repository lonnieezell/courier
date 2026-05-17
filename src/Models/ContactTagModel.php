<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;

/**
 * Manages the many-to-many relationship between contacts and tags.
 */
class ContactTagModel extends Model
{
    protected $table         = 'courier_contact_tags';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'contact_id',
        'tag_id',
    ];

    protected $validationRules = [
        'contact_id' => 'required|integer',
        'tag_id'     => 'required|integer',
    ];
}
