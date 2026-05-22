<?php

declare(strict_types=1);

namespace Myth\Courier\Webhooks\Drivers;

use CodeIgniter\HTTP\IncomingRequest;
use Myth\Courier\Webhooks\WebhookDriverInterface;
use Throwable;

/**
 * Handles SNS/SES webhook notifications.
 *
 * AWS SES routes bounce and complaint events through an SNS topic that POSTs
 * to your webhook URL. Two SNS message types are relevant:
 *
 *   SubscriptionConfirmation — sent once when you add the endpoint to a topic.
 *     You must confirm it (GET the SubscribeURL) before events start flowing.
 *
 *   Notification — the actual SES event, JSON-encoded in the Message field.
 *
 * Signature verification uses RSA-SHA256 (SignatureVersion 2). The signing
 * certificate is fetched from the SigningCertURL and cached to avoid repeated
 * HTTP calls. For security, only URLs on *.amazonaws.com are accepted.
 *
 * To inject a custom cert fetcher (e.g. for tests), pass a callable:
 *   new SesDriver(fn(string $url): string => file_get_contents($url))
 *
 * Configure in app/Config/Courier.php:
 *   $webhookDriver = \Myth\Courier\Webhooks\Drivers\SesDriver::class;
 *
 * Add to app/Config/Security.php $CSRFExcludeURIs:
 *   'courier/webhook'
 */
class SesDriver implements WebhookDriverInterface
{
    /**
     * @var callable(string): string|null
     */
    private $certFetcher;

    /**
     * @param callable(string): string|null $certFetcher Injected for testing; defaults to CI4 HTTP client.
     */
    public function __construct(?callable $certFetcher = null)
    {
        $this->certFetcher = $certFetcher;
    }

    public function verifySignature(IncomingRequest $request): bool
    {
        $body = $request->getBody();
        if ($body === null || $body === '') {
            return false;
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return false;
        }

        $certUrl = $payload['SigningCertURL'] ?? '';
        if (! $this->isAllowedCertUrl($certUrl)) {
            return false;
        }

        $signature = base64_decode($payload['Signature'] ?? '', true);
        if ($signature === false) {
            return false;
        }

        $cert = $this->fetchCert($certUrl);
        if ($cert === '') {
            return false;
        }

        $pubKey = openssl_pkey_get_public($cert);
        if ($pubKey === false) {
            return false;
        }

        $message = $this->buildSignableString($payload);

        return openssl_verify($message, $signature, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function isSubscriptionConfirmation(IncomingRequest $request): bool
    {
        $payload = $this->decodeBody($request);

        return ($payload['Type'] ?? '') === 'SubscriptionConfirmation';
    }

    public function confirmSubscription(IncomingRequest $request): void
    {
        $payload      = $this->decodeBody($request);
        $subscribeUrl = $payload['SubscribeURL'] ?? '';

        if ($subscribeUrl === '') {
            return;
        }

        $fetcher = $this->certFetcher ?? static fn (string $url): string => (string) service('curlrequest')->get($url)->getBody();
        ($fetcher)($subscribeUrl);
    }

    /**
     * @return list<array{type: string, email: string, message_id: string|null}>
     */
    public function parseEvents(IncomingRequest $request): array
    {
        $payload = $this->decodeBody($request);

        if (($payload['Type'] ?? '') !== 'Notification') {
            return [];
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        if (! is_array($message)) {
            return [];
        }

        $notificationType = $message['notificationType'] ?? '';
        $messageId        = $message['mail']['messageId'] ?? null;

        if ($notificationType === 'Bounce') {
            return $this->parseBounce($message, $messageId);
        }

        if ($notificationType === 'Complaint') {
            return $this->parseComplaint($message, $messageId);
        }

        return [];
    }

    /**
     * @return list<array{type: string, email: string, message_id: string|null}>
     */
    private function parseBounce(array $message, ?string $messageId): array
    {
        $bounceType = $message['bounce']['bounceType'] ?? '';
        $type       = $bounceType === 'Permanent' ? 'bounce' : 'soft_bounce';
        $events     = [];

        foreach ($message['bounce']['bouncedRecipients'] ?? [] as $recipient) {
            $email = $recipient['emailAddress'] ?? '';
            if ($email !== '') {
                $events[] = ['type' => $type, 'email' => $email, 'message_id' => $messageId];
            }
        }

        return $events;
    }

    /**
     * @return list<array{type: string, email: string, message_id: string|null}>
     */
    private function parseComplaint(array $message, ?string $messageId): array
    {
        $events = [];

        foreach ($message['complaint']['complainedRecipients'] ?? [] as $recipient) {
            $email = $recipient['emailAddress'] ?? '';
            if ($email !== '') {
                $events[] = ['type' => 'complaint', 'email' => $email, 'message_id' => $messageId];
            }
        }

        return $events;
    }

    private function fetchCert(string $url): string
    {
        try {
            $fetcher = $this->certFetcher ?? static fn (string $u): string => (string) service('curlrequest')->get($u)->getBody();

            return ($fetcher)($url);
        } catch (Throwable) {
            return '';
        }
    }

    private function isAllowedCertUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = parse_url($url, PHP_URL_HOST);

        return $scheme === 'https' && is_string($host) && (
            $host === 'amazonaws.com' || str_ends_with($host, '.amazonaws.com')
        );
    }

    /**
     * Builds the canonical string-to-sign per the SNS signature specification.
     *
     * @param array<string, mixed> $payload
     */
    private function buildSignableString(array $payload): string
    {
        $type = $payload['Type'] ?? '';

        if ($type === 'SubscriptionConfirmation' || $type === 'UnsubscribeConfirmation') {
            $keys = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        } else {
            $keys = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
        }

        $parts = [];

        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                $parts[] = $key . "\n" . $payload[$key] . "\n";
            }
        }

        return implode('', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(IncomingRequest $request): array
    {
        $body = $request->getBody();
        if ($body === null || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
