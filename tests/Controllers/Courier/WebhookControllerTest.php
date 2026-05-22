<?php

declare(strict_types=1);

namespace Tests\Controllers\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\EventModel;
use Tests\Support\FakeWebhookDriver;

/**
 * @internal
 */
final class WebhookControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';

    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $webhookRoute = [
        ['POST', 'courier/webhook', '\Myth\Courier\Controllers\CourierController::webhook'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FakeWebhookDriver::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $config                = config(CourierConfig::class);
        $config->webhookDriver = '';
    }

    private function postWebhook(string $body = '{}'): TestResponse
    {
        return $this->withRoutes($this->webhookRoute)
            ->withBody($body)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('courier/webhook');
    }

    public function testWebhookReturns400WhenNoDriverConfigured(): void
    {
        $result = $this->postWebhook();

        $result->assertStatus(400);
    }

    public function testWebhookReturns403WhenSignatureInvalid(): void
    {
        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$signatureValid          = false;

        $result = $this->postWebhook();

        $result->assertStatus(403);
    }

    public function testWebhookConfirmsSubscriptionAndReturns200(): void
    {
        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$isConfirmation          = true;

        $result = $this->postWebhook();

        $result->assertStatus(200);
        $this->assertTrue(FakeWebhookDriver::$confirmationCalled);
    }

    public function testWebhookHardBounceSuppressesContactAndReturns200(): void
    {
        $contactModel = new ContactModel();
        $contactModel->insert(['email' => 'bounce@example.com']);

        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$events                  = [
            ['type' => 'bounce', 'email' => 'bounce@example.com', 'message_id' => null],
        ];

        $result = $this->postWebhook();

        $result->assertStatus(200);

        $contact = $contactModel->where('email', 'bounce@example.com')->first();
        $this->assertSame(ContactStatus::Bounced, $contact->status);
    }

    public function testWebhookComplaintSuppressesContactAndReturns200(): void
    {
        $contactModel = new ContactModel();
        $contactModel->insert(['email' => 'spam@example.com']);

        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$events                  = [
            ['type' => 'complaint', 'email' => 'spam@example.com', 'message_id' => null],
        ];

        $result = $this->postWebhook();

        $result->assertStatus(200);

        $contact = $contactModel->where('email', 'spam@example.com')->first();
        $this->assertSame(ContactStatus::Complained, $contact->status);
    }

    public function testWebhookSoftBounceLogsEventAndReturns200(): void
    {
        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$events                  = [
            ['type' => 'soft_bounce', 'email' => 'soft@example.com', 'message_id' => null],
        ];

        $result = $this->postWebhook();

        $result->assertStatus(200);

        $event = (new EventModel())->where('type', 'soft_bounce')->first();
        $this->assertNotNull($event);
    }

    public function testWebhookSoftBounceDoesNotSuppressContact(): void
    {
        $contactModel = new ContactModel();
        $contactModel->insert(['email' => 'softonly@example.com']);

        config(CourierConfig::class)->webhookDriver = FakeWebhookDriver::class;
        FakeWebhookDriver::$events                  = [
            ['type' => 'soft_bounce', 'email' => 'softonly@example.com', 'message_id' => null],
        ];

        $this->postWebhook();

        $contact = $contactModel->where('email', 'softonly@example.com')->first();
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
    }
}
