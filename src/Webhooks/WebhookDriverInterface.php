<?php

declare(strict_types=1);

namespace Myth\Courier\Webhooks;

use CodeIgniter\HTTP\IncomingRequest;

interface WebhookDriverInterface
{
    /**
     * Verifies the request's authenticity (e.g. HMAC or RSA signature).
     * Returns false if verification fails or cannot be completed.
     */
    public function verifySignature(IncomingRequest $request): bool;

    /**
     * Returns true when the request is an ESP subscription confirmation
     * that must be acknowledged before events will flow.
     */
    public function isSubscriptionConfirmation(IncomingRequest $request): bool;

    /**
     * Confirms the subscription by making the required callback.
     * Only called when isSubscriptionConfirmation() returns true.
     */
    public function confirmSubscription(IncomingRequest $request): void;

    /**
     * Parses the request body into a list of normalized events.
     *
     * Each entry contains:
     *   'type'       => 'bounce' | 'soft_bounce' | 'complaint'
     *   'email'      => string
     *   'message_id' => string|null  (ESP's message identifier, if available)
     *
     * @return list<array{type: string, email: string, message_id: string|null}>
     */
    public function parseEvents(IncomingRequest $request): array;
}
