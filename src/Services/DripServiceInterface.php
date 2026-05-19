<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use Myth\Courier\DTO\DripEnrollmentDTO;

interface DripServiceInterface
{
    public function enroll(int $contactId, int $campaignId): ?DripEnrollmentDTO;

    public function cancel(int $contactId, int $campaignId): void;

    public function cancelAllForContact(int $contactId): void;

    /**
     * @return array{processed: int, cancelled: int, failed: int}
     */
    public function processDue(): array;

    public function getEnrollmentStatus(int $contactId, int $campaignId): ?DripEnrollmentDTO;
}
