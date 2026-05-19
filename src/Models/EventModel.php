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
 * Manages email engagement events such as opens, clicks, bounces, and unsubscribes.
 */
class EventModel extends Model
{
    protected $table         = 'courier_events';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'send_id',
        'type',
        'metadata',
    ];
    protected array $casts = [
        'metadata' => '?json',
    ];
    protected $validationRules = [
        'send_id' => 'required|integer',
        'type'    => 'required|in_list[open,click,bounce,unsubscribe]',
    ];
}
