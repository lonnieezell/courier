<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Email\Email;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Events\CourierEvents;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\TemplateService;
use RuntimeException;

/**
 * @internal
 */
final class MailerServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Test fixture view under src/Views/tests/ — resolved via Myth\Courier namespace
     */
    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private MailerService $service;
    private SendModel $sendModel;
    private CampaignModel $campaignModel;
    private object $contact;
    private object $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $config               = config(CourierConfig::class);
        $config->testMode     = true;
        $config->trackingHost = 'https://track.example.com';

        // Email mock — must never be called in testMode
        $emailMock = $this->createMock(Email::class);
        $emailMock->expects($this->never())->method('send');

        $this->sendModel     = new SendModel();
        $this->campaignModel = new CampaignModel();

        // Create a real contact (satisfies FK constraint on courier_sends)
        $contactService = new ContactService(
            new ContactModel(),
            new TagModel(),
            new DripEnrollmentModel(),
            new ContactTagModel(),
        );
        $this->contact = $contactService->subscribe([
            'email'      => 'test@example.com',
            'first_name' => 'Test',
        ]);

        // Create a real campaign
        $campaignId = (int) $this->campaignModel->insert([
            'name'       => 'Test Campaign',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'view'       => self::BODY_VIEW,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
        $this->campaign = $this->campaignModel->find($campaignId);

        $this->service = new MailerService(
            new TemplateService(),
            $this->sendModel,
            $this->campaignModel,
            $emailMock,
        );
    }

    private function makeSendLog(): object
    {
        return $this->sendModel->createPending(
            (int) $this->contact->id,
            (int) $this->campaign->id,
            null,
        );
    }

    public function testSendMarksSendLogAsSent(): void
    {
        $sendLog = $this->makeSendLog();
        $result  = $this->service->send($this->contact, $this->campaign, $sendLog);

        $this->assertTrue($result);

        $updated = $this->sendModel->find($sendLog->id);
        $this->assertSame('sent', $updated->status->value);
        $this->assertNotNull($updated->sent_at);
    }

    public function testSendWithSubjectOverrideUsesOverride(): void
    {
        $sendLog = $this->makeSendLog();
        $result  = $this->service->send($this->contact, $this->campaign, $sendLog, 'Custom Subject');

        $this->assertTrue($result);
    }

    public function testWrapLinksRewritesHttpLinks(): void
    {
        $html   = '<a href="https://example.com/page">Click</a>';
        $result = $this->service->wrapLinks($html, 'tok123');

        $this->assertStringContainsString('https://track.example.com/courier/click/tok123', $result);
        $this->assertStringContainsString(urlencode('https://example.com/page'), $result);
    }

    public function testWrapLinksLeavesCourierPlaceholderAlone(): void
    {
        $html   = '<a href="{courier_unsubscribe_url}">Unsubscribe</a>';
        $result = $this->service->wrapLinks($html, 'tok123');

        $this->assertStringContainsString('{courier_unsubscribe_url}', $result);
    }

    public function testWrapLinksHandlesSingleAndDoubleQuotes(): void
    {
        $html = "<a href='https://example.com/single'>A</a>"
              . '<a href="https://example.com/double">B</a>';

        $result = $this->service->wrapLinks($html, 'tok456');

        $this->assertStringContainsString(urlencode('https://example.com/single'), $result);
        $this->assertStringContainsString(urlencode('https://example.com/double'), $result);
    }

    public function testSendFiresEmailSentEvent(): void
    {
        $fired = null;
        Events::on(CourierEvents::EMAIL_SENT, static function ($send) use (&$fired): void {
            $fired = $send;
        });

        $sendLog = $this->makeSendLog();
        $this->service->send($this->contact, $this->campaign, $sendLog);

        Events::removeAllListeners(CourierEvents::EMAIL_SENT);

        $this->assertNotNull($fired);
        $this->assertSame((int) $sendLog->id, (int) $fired->id);
    }

    public function testSendEmailSentListenerExceptionDoesNotHaltSend(): void
    {
        Events::on(CourierEvents::EMAIL_SENT, static function (): void {
            throw new RuntimeException('listener exploded');
        });

        $sendLog = $this->makeSendLog();
        $result  = $this->service->send($this->contact, $this->campaign, $sendLog);

        Events::removeAllListeners(CourierEvents::EMAIL_SENT);

        $this->assertTrue($result);
    }

    public function testSendFiresEmailFailedEvent(): void
    {
        $config           = config(CourierConfig::class);
        $config->testMode = false;

        $emailMock = $this->createMock(Email::class);
        $emailMock->method('clear')->willReturnSelf();
        $emailMock->method('setFrom')->willReturnSelf();
        $emailMock->method('setTo')->willReturnSelf();
        $emailMock->method('setSubject')->willReturnSelf();
        $emailMock->method('setMessage')->willReturnSelf();
        $emailMock->method('setAltMessage')->willReturnSelf();
        $emailMock->method('send')->willReturn(false);

        $service = new MailerService(
            new TemplateService(),
            $this->sendModel,
            $this->campaignModel,
            $emailMock,
        );

        $fired = null;
        Events::on(CourierEvents::EMAIL_FAILED, static function ($send) use (&$fired): void {
            $fired = $send;
        });

        $sendLog = $this->makeSendLog();
        $result  = $service->send($this->contact, $this->campaign, $sendLog);

        Events::removeAllListeners(CourierEvents::EMAIL_FAILED);

        $config->testMode = true;

        $this->assertFalse($result);
        $this->assertNotNull($fired);
        $this->assertSame((int) $sendLog->id, (int) $fired->id);
    }
}
