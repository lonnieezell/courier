<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

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
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\LinkModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\TemplateService;
use Myth\Postal\Config\Services as PostalServices;
use Myth\Postal\Email;
use Myth\Postal\MailerManager;
use Myth\Postal\SendResult;
use RuntimeException;
use Tests\Support\Mailables\TestMailable;

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

        $this->service = $this->makeService();
    }

    protected function tearDown(): void
    {
        PostalServices::reset();

        parent::tearDown();
    }

    private function makeService(): MailerService
    {
        return new MailerService(
            new TemplateService(new MarkdownService(__DIR__ . '/../../_support/Views/')),
            $this->sendModel,
            $this->campaignModel,
            config(CourierConfig::class),
            new LinkModel(),
        );
    }

    /**
     * Injects a stub postal mailer that records the dispatched Email and returns
     * a fixed SendResult, then returns it so tests can inspect what was sent.
     */
    private function fakeMailer(SendResult $result): object
    {
        $stub = new class () extends MailerManager {
            public ?Email $sentEmail = null;
            public SendResult $result;

            public function __construct()
            {
            }

            public function send(Email $email): SendResult
            {
                $this->sentEmail = $email;

                return $this->result;
            }
        };
        $stub->result = $result;

        PostalServices::injectMock('mailer', $stub);

        return $stub;
    }

    private function makeSendLog(): object
    {
        return $this->sendModel->createPending(
            (int) $this->contact->id,
            (int) $this->campaign->id,
            null,
        );
    }

    private function enableLiveSend(): void
    {
        $config            = config(CourierConfig::class);
        $config->testMode  = false;
        $config->fromEmail = 'sender@example.com';
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
        $html           = '<a href="https://example.com/page">Click</a>';
        [$result, $map] = $this->service->wrapLinks($html);

        $this->assertCount(1, $map);
        $token = array_key_first($map);
        $this->assertSame('https://example.com/page', $map[$token]);
        $this->assertStringContainsString('https://track.example.com/courier/click/' . $token, $result);
    }

    public function testWrapLinksReturnsTokenMapWithAllLinks(): void
    {
        $html = '<a href="https://example.com/a">A</a>'
              . '<a href="https://example.com/b">B</a>';

        [, $map] = $this->service->wrapLinks($html);

        $this->assertCount(2, $map);
        $this->assertContains('https://example.com/a', $map);
        $this->assertContains('https://example.com/b', $map);
    }

    public function testWrapLinksLeavesCourierPlaceholderAlone(): void
    {
        $html           = '<a href="{courier_unsubscribe_url}">Unsubscribe</a>';
        [$result, $map] = $this->service->wrapLinks($html);

        $this->assertStringContainsString('{courier_unsubscribe_url}', $result);
        $this->assertCount(0, $map);
    }

    public function testWrapLinksHandlesSingleAndDoubleQuotes(): void
    {
        $html = "<a href='https://example.com/single'>A</a>"
              . '<a href="https://example.com/double">B</a>';

        [$result, $map] = $this->service->wrapLinks($html);

        $this->assertCount(2, $map);
        $this->assertContains('https://example.com/single', $map);
        $this->assertContains('https://example.com/double', $map);
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

    public function testSendDispatchesPostalEmailToContact(): void
    {
        $this->enableLiveSend();
        $stub = $this->fakeMailer(SendResult::ok('msg-1'));

        $sendLog = $this->makeSendLog();
        $this->makeService()->send($this->contact, $this->campaign, $sendLog);

        $this->assertInstanceOf(Email::class, $stub->sentEmail);
        $this->assertSame('test@example.com', $stub->sentEmail->to[0]->email);
        $this->assertSame('sender@example.com', $stub->sentEmail->from->email);
        $this->assertSame('Hello', $stub->sentEmail->subject);
    }

    public function testSendStoresMessageIdOnSuccess(): void
    {
        $this->enableLiveSend();
        $this->fakeMailer(SendResult::ok('provider-message-id'));

        $sendLog = $this->makeSendLog();
        $result  = $this->makeService()->send($this->contact, $this->campaign, $sendLog);

        $this->assertTrue($result);

        $updated = $this->sendModel->find($sendLog->id);
        $this->assertSame('sent', $updated->status->value);
        $this->assertSame('provider-message-id', $updated->message_id);
    }

    public function testSendMarksSuppressedWhenCancelled(): void
    {
        $this->enableLiveSend();
        $this->fakeMailer(SendResult::cancelled('All recipients are suppressed'));

        $fired = false;
        Events::on(CourierEvents::EMAIL_SENT, static function () use (&$fired): void {
            $fired = true;
        });

        $sendLog = $this->makeSendLog();
        $result  = $this->makeService()->send($this->contact, $this->campaign, $sendLog);

        Events::removeAllListeners(CourierEvents::EMAIL_SENT);

        $this->assertFalse($result);
        $this->assertFalse($fired);
        $this->assertSame('suppressed', $this->sendModel->find($sendLog->id)->status->value);
    }

    public function testSendFiresEmailFailedEvent(): void
    {
        $this->enableLiveSend();
        $this->fakeMailer(SendResult::fail('smtp blew up'));

        $fired = null;
        Events::on(CourierEvents::EMAIL_FAILED, static function ($send) use (&$fired): void {
            $fired = $send;
        });

        $sendLog = $this->makeSendLog();
        $result  = $this->makeService()->send($this->contact, $this->campaign, $sendLog);

        Events::removeAllListeners(CourierEvents::EMAIL_FAILED);

        $this->assertFalse($result);
        $this->assertNotNull($fired);
        $this->assertSame((int) $sendLog->id, (int) $fired->id);
        $this->assertSame('failed', $this->sendModel->find($sendLog->id)->status->value);
    }

    public function testSendEmbedsSendLevelUnsubscribeToken(): void
    {
        $this->enableLiveSend();
        $stub = $this->fakeMailer(SendResult::fail('not actually sending'));

        $sendLog = $this->makeSendLog();
        $this->makeService()->send($this->contact, $this->campaign, $sendLog);

        $body = $stub->sentEmail->htmlBody;
        $this->assertNotNull($body);
        $this->assertStringContainsString($sendLog->unsubscribe_token, $body);
        $this->assertStringNotContainsString($this->contact->unsubscribe_token, $body);
    }

    public function testSendPrefersCampaignMailableOverView(): void
    {
        $this->enableLiveSend();
        $this->campaignModel->update($this->campaign->id, ['mailable' => TestMailable::class]);
        $campaign = $this->campaignModel->find($this->campaign->id);

        $stub    = $this->fakeMailer(SendResult::ok('msg'));
        $sendLog = $this->makeSendLog();

        $this->makeService()->send($this->contact, $campaign, $sendLog);

        // Subject comes from the Mailable, not the campaign ("Hello").
        $this->assertSame('Mailable Subject', $stub->sentEmail->subject);
        // Tracking was applied to the Mailable's HTML.
        $this->assertStringNotContainsString('{courier_unsubscribe_url}', $stub->sentEmail->htmlBody);
        $this->assertStringContainsString('https://track.example.com/courier/click/', $stub->sentEmail->htmlBody);
    }

    public function testSendStepPrefersStepMailable(): void
    {
        $this->enableLiveSend();

        $stepModel = new DripStepModel();
        $stepId    = (int) $stepModel->insert([
            'campaign_id' => $this->campaign->id,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'mailable'    => TestMailable::class,
            'subject'     => 'Step Subject',
            'delay_hours' => 24,
        ]);
        $step = $stepModel->find($stepId);

        $stub = $this->fakeMailer(SendResult::ok('msg'));

        $this->makeService()->sendStep($this->contact, $step, $this->campaign);

        $this->assertSame('Mailable Subject', $stub->sentEmail->subject);
    }

    public function testSendMailableRecordsCampaignlessSend(): void
    {
        $mailable          = new TestMailable();
        $mailable->contact = $this->contact;

        $result = $this->service->sendMailable($mailable, $this->contact);

        $this->assertTrue($result);

        $send = $this->sendModel
            ->where('contact_id', $this->contact->id)
            ->orderBy('id', 'DESC')
            ->first();
        $this->assertNull($send->campaign_id);
        $this->assertSame('sent', $send->status->value);
    }

    public function testSendMailableMarksSuppressedWhenCancelled(): void
    {
        $this->enableLiveSend();
        $this->fakeMailer(SendResult::cancelled('suppressed'));

        $mailable = new TestMailable();

        $result = $this->makeService()->sendMailable($mailable, $this->contact);

        $this->assertFalse($result);

        $send = $this->sendModel
            ->where('contact_id', $this->contact->id)
            ->orderBy('id', 'DESC')
            ->first();
        $this->assertSame('suppressed', $send->status->value);
    }

    public function testSendMailableAppliesTrackingToHtml(): void
    {
        $this->enableLiveSend();
        $stub = $this->fakeMailer(SendResult::fail('hold'));

        $mailable = new TestMailable();

        $this->makeService()->sendMailable($mailable, $this->contact);

        $this->assertStringNotContainsString('{courier_tracking_pixel}', $stub->sentEmail->htmlBody);
        $this->assertStringContainsString('/courier/open/', $stub->sentEmail->htmlBody);
    }
}
