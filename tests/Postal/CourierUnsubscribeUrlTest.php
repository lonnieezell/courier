<?php

declare(strict_types=1);

namespace Tests\Postal;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Postal\CourierUnsubscribeUrl;
use Myth\Postal\Address;
use Myth\Postal\UnsubscribeUrlInterface;

/**
 * @internal
 */
final class CourierUnsubscribeUrlTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private int $contactId;

    protected function setUp(): void
    {
        parent::setUp();

        config(CourierConfig::class)->trackingHost = 'https://track.example.com';

        $this->contactId = (int) (new ContactModel())->insert([
            'email' => 'reader@example.com',
        ]);
    }

    private function seedPending(string $token): void
    {
        (new SendModel())->insert([
            'contact_id'        => $this->contactId,
            'campaign_id'       => null,
            'status'            => SendStatus::Pending,
            'open_token'        => bin2hex(random_bytes(16)),
            'unsubscribe_token' => $token,
        ]);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(UnsubscribeUrlInterface::class, new CourierUnsubscribeUrl());
    }

    public function testIsOneClickIsTrue(): void
    {
        $this->assertTrue((new CourierUnsubscribeUrl())->isOneClick());
    }

    public function testUrlForReturnsTokenUrlOfPendingSend(): void
    {
        $this->seedPending('token-abc');

        $url = (new CourierUnsubscribeUrl())->urlFor(new Address('reader@example.com'));

        $this->assertSame(
            'https://track.example.com/courier/unsubscribe/token-abc',
            $url,
        );
    }

    public function testUrlForUsesMostRecentPendingSend(): void
    {
        $this->seedPending('token-old');
        $this->seedPending('token-new');

        $url = (new CourierUnsubscribeUrl())->urlFor(new Address('reader@example.com'));

        $this->assertStringContainsString('token-new', $url);
        $this->assertStringNotContainsString('token-old', $url);
    }

    public function testUrlForReturnsEmptyStringWhenNoPendingSend(): void
    {
        $this->assertSame('', (new CourierUnsubscribeUrl())->urlFor(new Address('reader@example.com')));
    }
}
