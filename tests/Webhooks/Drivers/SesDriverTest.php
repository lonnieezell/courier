<?php

declare(strict_types=1);

namespace Tests\Webhooks\Drivers;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Webhooks\Drivers\SesDriver;

/**
 * @internal
 */
final class SesDriverTest extends CIUnitTestCase
{
    private function makeRequest(array $payload): IncomingRequest
    {
        $body    = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = new IncomingRequest(config('App'), new URI('http://localhost/courier/webhook'), $body, new UserAgent());
        $request->setHeader('Content-Type', 'application/json');

        return $request;
    }

    private function notificationPayload(array $message): array
    {
        return [
            'Type'             => 'Notification',
            'MessageId'        => 'sns-msg-id',
            'TopicArn'         => 'arn:aws:sns:us-east-1:123456789:my-topic',
            'Message'          => json_encode($message, JSON_THROW_ON_ERROR),
            'Timestamp'        => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'Signature'        => 'fake',
            'SigningCertURL'   => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];
    }

    public function testParseEventsHardBounce(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest($this->notificationPayload([
            'notificationType' => 'Bounce',
            'bounce'           => [
                'bounceType'        => 'Permanent',
                'bounceSubType'     => 'General',
                'bouncedRecipients' => [['emailAddress' => 'hard@example.com']],
            ],
            'mail' => ['messageId' => 'ses-abc123', 'destination' => ['hard@example.com']],
        ]));

        $events = $driver->parseEvents($request);

        $this->assertCount(1, $events);
        $this->assertSame('bounce', $events[0]['type']);
        $this->assertSame('hard@example.com', $events[0]['email']);
        $this->assertSame('ses-abc123', $events[0]['message_id']);
    }

    public function testParseEventsSoftBounce(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest($this->notificationPayload([
            'notificationType' => 'Bounce',
            'bounce'           => [
                'bounceType'        => 'Transient',
                'bounceSubType'     => 'General',
                'bouncedRecipients' => [['emailAddress' => 'soft@example.com']],
            ],
            'mail' => ['messageId' => 'ses-def456', 'destination' => ['soft@example.com']],
        ]));

        $events = $driver->parseEvents($request);

        $this->assertCount(1, $events);
        $this->assertSame('soft_bounce', $events[0]['type']);
        $this->assertSame('soft@example.com', $events[0]['email']);
    }

    public function testParseEventsComplaint(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest($this->notificationPayload([
            'notificationType' => 'Complaint',
            'complaint'        => [
                'complainedRecipients' => [['emailAddress' => 'spam@example.com']],
            ],
            'mail' => ['messageId' => 'ses-ghi789', 'destination' => ['spam@example.com']],
        ]));

        $events = $driver->parseEvents($request);

        $this->assertCount(1, $events);
        $this->assertSame('complaint', $events[0]['type']);
        $this->assertSame('spam@example.com', $events[0]['email']);
    }

    public function testParseEventsReturnsEmptyForUnknownType(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest($this->notificationPayload([
            'notificationType' => 'Delivery',
            'mail'             => ['messageId' => 'ses-xyz', 'destination' => ['ok@example.com']],
        ]));

        $this->assertSame([], $driver->parseEvents($request));
    }

    public function testIsSubscriptionConfirmationReturnsTrueForConfirmationType(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest([
            'Type'         => 'SubscriptionConfirmation',
            'Token'        => 'some-token',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/confirm',
            'TopicArn'     => 'arn:aws:sns:us-east-1:123:topic',
            'Message'      => 'You have chosen to subscribe.',
        ]);

        $this->assertTrue($driver->isSubscriptionConfirmation($request));
    }

    public function testIsSubscriptionConfirmationReturnsFalseForNotification(): void
    {
        $driver  = new SesDriver();
        $request = $this->makeRequest($this->notificationPayload([
            'notificationType' => 'Delivery',
            'mail'             => ['messageId' => 'x', 'destination' => []],
        ]));

        $this->assertFalse($driver->isSubscriptionConfirmation($request));
    }

    public function testVerifySignatureRejectsCertFromNonAwsDomain(): void
    {
        $driver  = new SesDriver(static fn (string $url): string => 'fake-cert-content');
        $request = $this->makeRequest([
            'Type'             => 'Notification',
            'MessageId'        => 'id',
            'TopicArn'         => 'arn:aws:sns:us-east-1:123:topic',
            'Message'          => '{}',
            'Timestamp'        => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'Signature'        => base64_encode('fake'),
            'SigningCertURL'   => 'https://evil.com/cert.pem',
        ]);

        $this->assertFalse($driver->verifySignature($request));
    }

    public function testVerifySignatureRejectsHttpCertUrl(): void
    {
        $driver  = new SesDriver(static fn (string $url): string => 'fake-cert-content');
        $request = $this->makeRequest([
            'Type'             => 'Notification',
            'MessageId'        => 'id',
            'TopicArn'         => 'arn:aws:sns:us-east-1:123:topic',
            'Message'          => '{}',
            'Timestamp'        => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'Signature'        => base64_encode('fake'),
            'SigningCertURL'   => 'http://sns.us-east-1.amazonaws.com/cert.pem',
        ]);

        $this->assertFalse($driver->verifySignature($request));
    }
}
