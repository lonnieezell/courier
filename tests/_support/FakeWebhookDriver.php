<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\HTTP\IncomingRequest;
use Myth\Courier\Webhooks\WebhookDriverInterface;

/**
 * Test double for WebhookDriverInterface.
 * Configure before each test via static properties.
 */
class FakeWebhookDriver implements WebhookDriverInterface
{
    public static bool $signatureValid     = true;
    public static bool $isConfirmation     = false;
    public static bool $confirmationCalled = false;

    /**
     * @var list<array{type: string, email: string, message_id: string|null}>
     */
    public static array $events = [];

    public static function reset(): void
    {
        self::$signatureValid     = true;
        self::$isConfirmation     = false;
        self::$confirmationCalled = false;
        self::$events             = [];
    }

    public function verifySignature(IncomingRequest $request): bool
    {
        return self::$signatureValid;
    }

    public function isSubscriptionConfirmation(IncomingRequest $request): bool
    {
        return self::$isConfirmation;
    }

    public function confirmSubscription(IncomingRequest $request): void
    {
        self::$confirmationCalled = true;
    }

    public function parseEvents(IncomingRequest $request): array
    {
        return self::$events;
    }
}
