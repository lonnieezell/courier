<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Events\CourierEvents;
use Myth\Courier\Exceptions\CourierValidationException;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\TemplateService;
use RuntimeException;

/**
 * @internal
 */
final class ContactServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ContactService $service;
    private CampaignModel $campaignModel;
    private DripEnrollmentModel $enrollmentModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignModel   = new CampaignModel();
        $this->enrollmentModel = new DripEnrollmentModel();

        $this->service = new ContactService(
            new ContactModel(),
            new TagModel(),
            $this->enrollmentModel,
            new ContactTagModel(),
        );
    }

    private function makeDripService(): DripService
    {
        $config           = config(CourierConfig::class);
        $config->testMode = true;

        return new DripService(
            $this->enrollmentModel,
            new DripStepModel(),
            $this->campaignModel,
            new MailerService(new TemplateService(new MarkdownService(sys_get_temp_dir())), new SendModel(), $this->campaignModel),
            new ContactModel(),
            $config,
        );
    }

    private function createDripCampaign(): object
    {
        $id = $this->campaignModel->insert([
            'name'       => 'Welcome Drip',
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        return $this->campaignModel->find($id);
    }

    public function testSubscribeCreatesNewContact(): void
    {
        $contact = $this->service->subscribe(['email' => 'new@example.com']);

        $this->assertSame('new@example.com', $contact->email);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
        $this->assertNotEmpty($contact->unsubscribe_token);
        $this->assertSame(64, strlen($contact->unsubscribe_token));
        $this->assertNotNull($contact->subscribed_at);
    }

    public function testSubscribeResubscribesUnsubscribedContact(): void
    {
        $model = new ContactModel();
        $id    = $model->insert([
            'email'  => 'old@example.com',
            'status' => ContactStatus::Unsubscribed,
        ]);

        $contact = $this->service->subscribe(['email' => 'old@example.com']);

        $this->assertSame((int) $id, $contact->id);
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
        $this->assertCount(1, $model->findAll());
    }

    public function testSubscribeThrowsOnMissingEmail(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->subscribe([]);
    }

    public function testSubscribeAppliesTagsCorrectly(): void
    {
        $contact = $this->service->subscribe(
            ['email' => 'tagged@example.com'],
            ['newsletter', 'vip'],
        );

        $pivotCount = (new ContactTagModel())
            ->where('contact_id', $contact->id)
            ->countAllResults();

        $this->assertSame(2, $pivotCount);
    }

    public function testSubscribeWithDripCampaignEnrollsContact(): void
    {
        $campaign = $this->createDripCampaign();
        (new DripStepModel())->insert([
            'campaign_id' => $campaign->id,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);

        $this->service->setDripService($this->makeDripService());
        $contact = $this->service->subscribe(['email' => 'drip@example.com'], [], $campaign->id);

        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function testSubscribeThrowsBeforeCreatingContactIfCampaignNotFound(): void
    {
        $this->service->setDripService($this->makeDripService());

        $this->expectException(CourierValidationException::class);
        $this->service->subscribe(['email' => 'drip@example.com'], [], 99999);

        $this->assertCount(0, (new ContactModel())->findAll());
    }

    public function testSubscribeThrowsBeforeCreatingContactIfCampaignIsNotDripType(): void
    {
        $id = $this->campaignModel->insert([
            'name'       => 'Blast',
            'subject'    => 'Hi',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $this->service->setDripService($this->makeDripService());

        $this->expectException(CourierValidationException::class);
        $this->service->subscribe(['email' => 'drip@example.com'], [], $id);

        $this->assertCount(0, (new ContactModel())->findAll());
    }

    public function testUnsubscribeByTokenUpdatesStatusAndCancelsEnrollments(): void
    {
        $contactModel = new ContactModel();
        $campaign     = $this->createDripCampaign();
        (new DripStepModel())->insert([
            'campaign_id' => $campaign->id,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);

        $this->service->setDripService($this->makeDripService());
        $contact = $this->service->subscribe(['email' => 'unsub@example.com'], [], $campaign->id);

        $result     = $this->service->unsubscribeByToken($contact->unsubscribe_token);
        $updated    = $contactModel->find($contact->id);
        $enrollment = $this->enrollmentModel->where('contact_id', $contact->id)->first();

        $this->assertTrue($result);
        $this->assertSame(ContactStatus::Unsubscribed, $updated->status);
        $this->assertNotNull($updated->unsubscribed_at);
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
    }

    public function testUnsubscribeByTokenReturnsFalseForInvalidToken(): void
    {
        $result = $this->service->unsubscribeByToken('no-such-token');

        $this->assertFalse($result);
    }

    public function testSubscribeFiresContactSubscribedEvent(): void
    {
        $fired = null;
        Events::on(CourierEvents::CONTACT_SUBSCRIBED, static function ($contact) use (&$fired): void {
            $fired = $contact;
        });

        $this->service->subscribe(['email' => 'event@example.com']);

        Events::removeAllListeners(CourierEvents::CONTACT_SUBSCRIBED);

        $this->assertNotNull($fired);
        $this->assertSame('event@example.com', $fired->email);
    }

    public function testSubscribeEventListenerExceptionDoesNotHaltSubscription(): void
    {
        Events::on(CourierEvents::CONTACT_SUBSCRIBED, static function (): void {
            throw new RuntimeException('listener exploded');
        });

        $contact = $this->service->subscribe(['email' => 'safe@example.com']);

        Events::removeAllListeners(CourierEvents::CONTACT_SUBSCRIBED);

        $this->assertSame('safe@example.com', $contact->email);
    }

    public function testUnsubscribeFiresContactUnsubscribedEvent(): void
    {
        $contact = $this->service->subscribe(['email' => 'willleave@example.com']);

        $fired = null;
        Events::on(CourierEvents::CONTACT_UNSUBSCRIBED, static function ($c) use (&$fired): void {
            $fired = $c;
        });

        $this->service->unsubscribeByToken($contact->unsubscribe_token);

        Events::removeAllListeners(CourierEvents::CONTACT_UNSUBSCRIBED);

        $this->assertNotNull($fired);
        $this->assertSame('willleave@example.com', $fired->email);
    }

    public function testUnsubscribeEventListenerExceptionDoesNotHaltUnsubscribe(): void
    {
        $contact = $this->service->subscribe(['email' => 'safeleave@example.com']);

        Events::on(CourierEvents::CONTACT_UNSUBSCRIBED, static function (): void {
            throw new RuntimeException('listener exploded');
        });

        $result = $this->service->unsubscribeByToken($contact->unsubscribe_token);

        Events::removeAllListeners(CourierEvents::CONTACT_UNSUBSCRIBED);

        $this->assertTrue($result);
    }

    public function testServiceLocatorContactServiceCancelsDripEnrollmentsOnUnsubscribe(): void
    {
        $campaign = $this->createDripCampaign();
        (new DripStepModel())->insert([
            'campaign_id' => $campaign->id,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);

        // Subscribe and enroll the contact manually
        $dripService = $this->makeDripService();
        $contact     = $this->service->subscribe(['email' => 'svc-unsub@example.com']);
        $dripService->enroll($contact->id, $campaign->id);

        // Unsubscribe via the service locator — no manual setDripService() call
        $contactService = service('contactService', false);
        $contactService->unsubscribeByToken($contact->unsubscribe_token);

        $enrollment = $this->enrollmentModel->where('contact_id', $contact->id)->first();
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
    }
}
