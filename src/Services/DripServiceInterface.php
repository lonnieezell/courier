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

namespace Myth\Courier\Services;

interface DripServiceInterface
{
    public function enroll(int $contactId, int $campaignId): ?object;

    /**
     * @return array{processed: int, cancelled: int, failed: int}
     */
    public function processDue(): array;
}
