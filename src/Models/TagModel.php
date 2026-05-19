<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
