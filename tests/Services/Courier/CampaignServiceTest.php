<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Services\Courier;

use BackedEnum;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTime;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Exceptions\CourierValidationException;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\CampaignService;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\SegmentService;
use Myth\Courier\Services\TemplateService;

/**
 * @internal
 */
final class CampaignServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * View constant reused from existing test fixtures
     */
    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private CampaignService $service;
    private CampaignModel $campaignModel;
    private ContactModel $contactModel;
    private SendModel $sendModel;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config(CourierConfig::class);
        $config->testMode = true;

        $this->campaignModel = new CampaignModel();
        $this->contactModel  = new ContactModel();
        $this->sendModel     = new SendModel();

        $segmentService = new SegmentService(
            $this->contactModel,
            new SegmentModel(),
        );

        $mailerService = new MailerService(
            new TemplateService(),
            $this->sendModel,
            $this->campaignModel,
        );

        $this->service = new CampaignService(
            $this->campaignModel,
            new DripStepModel(),
            $segmentService,
            $mailerService,
            $this->sendModel,
            $this->contactModel,
            $config,
        );
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCreateSavesCampaignWithBlastDefault(): void
    {
        $campaign = $this->service->create([
            'name'       => 'My Campaign',
            'subject'    => 'Hello World',
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $this->assertSame('My Campaign', $campaign->name);
        $this->assertSame(CampaignType::Blast, $campaign->type);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function testCreateThrowsOnMissingName(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->create([
            'subject'    => 'Hello',
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testCreateThrowsOnMissingSubject(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->create([
            'name'       => 'My Campaign',
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
    }

    public function testCreateThrowsOnMissingFromFields(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->create([
            'name'    => 'My Campaign',
            'subject' => 'Hello',
        ]);
    }

    // -------------------------------------------------------------------------
    // schedule()
    // -------------------------------------------------------------------------

    public function testScheduleSetsStatusAndDatetime(): void
    {
        $id     = $this->insertDraftCampaign();
        $sendAt = new DateTime('2026-06-01 10:00:00');

        $this->service->schedule($id, $sendAt);

        $campaign = $this->campaignModel->find($id);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertSame('2026-06-01 10:00:00', $campaign->scheduled_at);
    }

    public function testScheduleThrowsIfNotDraft(): void
    {
        $id = $this->insertDraftCampaign(['status' => CampaignStatus::Sent]);

        $this->expectException(CourierValidationException::class);
        $this->service->schedule($id, new DateTime());
    }

    public function testScheduleThrowsIfViewMissing(): void
    {
        $id = (int) $this->campaignModel->insert([
            'name'       => 'No View',
            'subject'    => 'Hello',
            'type'       => CampaignType::Blast,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $this->expectException(CourierValidationException::class);
        $this->service->schedule($id, new DateTime());
    }

    // -------------------------------------------------------------------------
    // resume()
    // -------------------------------------------------------------------------

    public function testResumeTransitionsPausedToScheduled(): void
    {
        $id = $this->insertDraftCampaign(['status' => CampaignStatus::Paused]);

        $this->service->resume($id);

        $campaign = $this->campaignModel->find($id);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
    }

    public function testResumeThrowsIfNotPaused(): void
    {
        $id = $this->insertDraftCampaign();

        $this->expectException(CourierValidationException::class);
        $this->service->resume($id);
    }

    // -------------------------------------------------------------------------
    // addDripStep()
    // -------------------------------------------------------------------------

    public function testAddDripStepAutoIncrementsPosition(): void
    {
        $campaignId = (int) $this->campaignModel->insert([
            'name'       => 'Drip',
            'subject'    => 'Drip Subject',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        $step1 = $this->service->addDripStep($campaignId, [
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);

        $step2 = $this->service->addDripStep($campaignId, [
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 2',
            'delay_hours' => 48,
        ]);

        $this->assertSame(1, (int) $step1->position);
        $this->assertSame(2, (int) $step2->position);
    }

    public function testAddDripStepThrowsForNonDripCampaign(): void
    {
        $id = $this->insertDraftCampaign();

        $this->expectException(CourierValidationException::class);
        $this->service->addDripStep($id, [
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);
    }

    public function testAddDripStepThrowsForMissingCampaign(): void
    {
        $this->expectException(CourierValidationException::class);
        $this->service->addDripStep(99999, [
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);
    }

    // -------------------------------------------------------------------------
    // resolveAudience()
    // -------------------------------------------------------------------------

    public function testResolveAudienceWithNoFiltersReturnsAllSubscribed(): void
    {
        $this->insertContact('a@example.com');
        $this->insertContact('b@example.com');
        $this->insertContact('unsub@example.com', ContactStatus::Unsubscribed);

        $campaign = $this->makeCampaignObject();
        $contacts = $this->service->resolveAudience($campaign);

        $this->assertCount(2, $contacts);
    }

    public function testResolveAudienceWithTagFilterReturnsTaggedContacts(): void
    {
        $contactService = new ContactService(
            $this->contactModel,
            new TagModel(),
            new DripEnrollmentModel(),
            new ContactTagModel(),
        );

        $contactService->subscribe(['email' => 'tagged@example.com'], ['vip']);
        $contactService->subscribe(['email' => 'other@example.com']);

        $campaign = $this->makeCampaignObject(['tag_filter' => ['vip']]);
        $contacts = $this->service->resolveAudience($campaign);

        $this->assertCount(1, $contacts);
        $this->assertSame('tagged@example.com', $contacts[0]->email);
    }

    public function testResolveAudienceWithBothNarrowsResult(): void
    {
        $segmentModel = new SegmentModel();
        $segmentId    = (int) $segmentModel->insert([
            'name'       => 'All',
            'rules'      => [],
            'match_mode' => 'all',
        ]);

        $contactService = new ContactService(
            $this->contactModel,
            new TagModel(),
            new DripEnrollmentModel(),
            new ContactTagModel(),
        );

        $contactService->subscribe(['email' => 'vip@example.com'], ['vip']);
        $contactService->subscribe(['email' => 'regular@example.com']);

        $campaign = $this->makeCampaignObject([
            'segment_id' => $segmentId,
            'tag_filter' => ['vip'],
        ]);
        $contacts = $this->service->resolveAudience($campaign);

        $this->assertCount(1, $contacts);
        $this->assertSame('vip@example.com', $contacts[0]->email);
    }

    public function testResolveAudienceExcludesUnsubscribed(): void
    {
        $this->insertContact('sub@example.com');
        $this->insertContact('unsub@example.com', ContactStatus::Unsubscribed);

        $campaign = $this->makeCampaignObject();
        $contacts = $this->service->resolveAudience($campaign);

        $emails = array_column($contacts, 'email');
        $this->assertNotContains('unsub@example.com', $emails);
    }

    // -------------------------------------------------------------------------
    // prepareBatch()
    // -------------------------------------------------------------------------

    public function testPrepareBatchCreatesExpectedSendRows(): void
    {
        $campaign = $this->insertAndFetchCampaign();
        $contact  = $this->insertContact('p@example.com');
        $contacts = [$contact];

        $sends = $this->service->prepareBatch($campaign, $contacts, 0);

        $this->assertCount(1, $sends);
        $this->assertSame('pending', $sends[0]->status instanceof BackedEnum ? $sends[0]->status->value : $sends[0]->status);
    }

    public function testPrepareBatchIsIdempotentForPendingSends(): void
    {
        $campaign = $this->insertAndFetchCampaign();
        $contact  = $this->insertContact('idm@example.com');
        $contacts = [$contact];

        $this->service->prepareBatch($campaign, $contacts, 0);
        $this->service->prepareBatch($campaign, $contacts, 0);

        $count = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->countAllResults();

        $this->assertSame(1, $count);
    }

    public function testPrepareBatchIsIdempotentForSentSends(): void
    {
        $campaign = $this->insertAndFetchCampaign();
        $contact  = $this->insertContact('sent@example.com');
        $contacts = [$contact];

        // Create a send row marked as sent
        $send = $this->sendModel->createPending($contact->id, $campaign->id, null);
        $this->sendModel->update($send->id, ['status' => SendStatus::Sent]);

        $sends = $this->service->prepareBatch($campaign, $contacts, 0);

        // Still one row, status unchanged
        $count = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->countAllResults();

        $this->assertSame(1, $count);
        // The row returned is the existing one
        $this->assertSame((int) $send->id, (int) $sends[0]->id);
    }

    public function testPrepareBatchResetsFailed(): void
    {
        $campaign = $this->insertAndFetchCampaign();
        $contact  = $this->insertContact('fail@example.com');
        $contacts = [$contact];

        $send = $this->sendModel->createPending($contact->id, $campaign->id, null);
        $this->sendModel->update($send->id, ['status' => SendStatus::Failed]);

        $sends = $this->service->prepareBatch($campaign, $contacts, 0);

        $this->assertCount(1, $sends);
        $refreshed = $this->sendModel->where('id', $send->id)->first();
        $this->assertNotNull($refreshed);
        $statusVal = $refreshed->status instanceof BackedEnum ? $refreshed->status->value : $refreshed->status;
        $this->assertSame('pending', $statusVal);
    }

    // -------------------------------------------------------------------------
    // getCampaignStats()
    // -------------------------------------------------------------------------

    public function testGetCampaignStatsReturnsCorrectCounts(): void
    {
        $campaign = $this->insertAndFetchCampaign();
        $c1       = $this->insertContact('s1@example.com');
        $c2       = $this->insertContact('s2@example.com');
        $c3       = $this->insertContact('s3@example.com');

        $send1 = $this->sendModel->createPending($c1->id, $campaign->id, null);
        $send2 = $this->sendModel->createPending($c2->id, $campaign->id, null);
        $send3 = $this->sendModel->createPending($c3->id, $campaign->id, null);

        $this->sendModel->update($send1->id, ['status' => SendStatus::Sent, 'opened_at' => date('Y-m-d H:i:s')]);
        $this->sendModel->update($send2->id, ['status' => SendStatus::Sent, 'opened_at' => date('Y-m-d H:i:s'), 'clicked_at' => date('Y-m-d H:i:s')]);
        $this->sendModel->update($send3->id, ['status' => SendStatus::Failed]);

        $stats = $this->service->getCampaignStats($campaign->id);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['sent']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(2, $stats['opened']);
        $this->assertSame(1, $stats['clicked']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function insertDraftCampaign(array $overrides = []): int
    {
        return (int) $this->campaignModel->insert(array_merge([
            'name'       => 'Test Campaign',
            'subject'    => 'Test Subject',
            'type'       => CampaignType::Blast,
            'view'       => self::BODY_VIEW,
            'status'     => CampaignStatus::Draft,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ], $overrides));
    }

    private function insertAndFetchCampaign(array $overrides = []): object
    {
        $id = $this->insertDraftCampaign($overrides);

        return $this->campaignModel->find($id);
    }

    private function insertContact(string $email, ContactStatus $status = ContactStatus::Subscribed): object
    {
        $id = (int) $this->contactModel->insert([
            'email'  => $email,
            'status' => $status,
        ]);

        return $this->contactModel->find($id);
    }

    /**
     * Build a plain object resembling a campaign row (no DB insert needed for
     * resolveAudience tests that don't require an FK).
     *
     * @param array<string, mixed> $overrides
     */
    private function makeCampaignObject(array $overrides = []): object
    {
        return (object) array_merge([
            'id'         => 0,
            'segment_id' => null,
            'tag_filter' => null,
        ], $overrides);
    }
}
