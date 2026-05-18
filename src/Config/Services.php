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

namespace Myth\Courier\Config;

use CodeIgniter\Config\BaseService;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\SegmentService;

class Services extends BaseService
{
    public static function contactService(bool $getShared = true): ContactService
    {
        if ($getShared) {
            return static::getSharedInstance('contactService');
        }

        return new ContactService(
            model(ContactModel::class),
            model(TagModel::class),
            model(DripEnrollmentModel::class),
            model(ContactTagModel::class),
        );
    }

    public static function segmentService(bool $getShared = true): SegmentService
    {
        if ($getShared) {
            return static::getSharedInstance('segmentService');
        }

        return new SegmentService(
            model(ContactModel::class),
            model(SegmentModel::class),
        );
    }
}
