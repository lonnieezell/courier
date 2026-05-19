<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

interface DripServiceInterface
{
    public function enroll(int $contactId, int $campaignId): ?object;

    /**
     * @return array{processed: int, cancelled: int, failed: int}
     */
    public function processDue(): array;
}
