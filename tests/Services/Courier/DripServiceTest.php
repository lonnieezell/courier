<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Services\CampaignFileLoader;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\TemplateService;
use RuntimeException;

/**
 * @internal
 */
final class DripServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private DripService $service;
    private CampaignModel $campaignModel;
    private ContactModel $contactModel;
    private DripEnrollmentModel $enrollmentModel;
    private DripStepModel $stepModel;
    private SendModel $sendModel;
    private string $campaignsDir;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignsDir = sys_get_temp_dir() . '/courier_drip_test_' . uniqid();
        mkdir($this->campaignsDir, 0777, true);

        $config                = config(CourierConfig::class);
        $config->testMode      = true;
        $config->campaignsPath = $this->campaignsDir;

        $this->campaignModel   = new CampaignModel();
        $this->contactModel    = new ContactModel();
        $this->enrollmentModel = new DripEnrollmentModel();
        $this->stepModel       = new DripStepModel();
        $this->sendModel       = new SendModel();

        $mailerService = new MailerService(
            new TemplateService(new MarkdownService(sys_get_temp_dir())),
            $this->sendModel,
            $this->campaignModel,
        );

        $this->service = new DripService(
            $this->enrollmentModel,
            $this->stepModel,
            $this->campaignModel,
            $mailerService,
            $this->contactModel,
            $config,
            new CampaignFileLoader($this->campaignsDir),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        array_map('unlink', $this->tempFiles);
        if (is_dir($this->campaignsDir)) {
            rmdir($this->campaignsDir);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createDripCampaign(): object
    {
        $id = $this->campaignModel->insert([
            'name'       => 'Drip Campaign',
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        return $this->campaignModel->find($id);
    }

    private function createStep(int $campaignId, int $position = 1, int $delayHours = 24): object
    {
        $id = $this->stepModel->insert([
            'campaign_id' => $campaignId,
            'position'    => $position,
            'view'        => self::BODY_VIEW,
            'subject'     => "Step {$position}",
            'delay_hours' => $delayHours,
        ]);

        return $this->stepModel->find($id);
    }

    private function createFileCampaign(string $slug, int $delayHours = 0): object
    {
        $filename = $slug . '.yaml';
        $path     = $this->campaignsDir . '/' . $filename;

        $yaml = implode("\n", [
            "name: {$slug}",
            'from_name: Test Sender',
            'from_email: sender@example.com',
            'steps:',
            '  - position: 1',
            '    subject: "Step 1"',
            '    view: ' . self::BODY_VIEW,
            "    delay_hours: {$delayHours}",
            '  - position: 2',
            '    subject: "Step 2"',
            '    view: ' . self::BODY_VIEW,
            '    delay_hours: 24',
        ]);

        file_put_contents($path, $yaml);
        $this->tempFiles[] = $path;

        $id = $this->campaignModel->insert([
            'name'        => $slug,
            'subject'     => 'Step 1',
            'type'        => CampaignType::DripSequence,
            'status'      => CampaignStatus::Active,
            'source_file' => $filename,
            'from_name'   => 'Test Sender',
            'from_email'  => 'sender@example.com',
        ]);

        return $this->campaignModel->find($id);
    }

    private function createSubscribedContact(string $email = 'a@example.com'): object
    {
        $id = $this->contactModel->insert([
            'email'  => $email,
            'status' => ContactStatus::Subscribed,
        ]);

        return $this->contactModel->find($id);
    }

    // -----------------------------------------------------------------------
    // enroll()
    // -----------------------------------------------------------------------

    public function testEnrollCreatesEnrollmentWithCorrectNextSendAt(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id, 1, 48);
        $contact = $this->createSubscribedContact();

        $before     = time();
        $enrollment = $this->service->enroll($contact->id, $campaign->id);
        $after      = time();

        $this->assertNotNull($enrollment);
        $this->assertSame(1, $enrollment->current_step);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        $nextSendAt = strtotime((string) $enrollment->next_send_at);
        $this->assertGreaterThanOrEqual($before + 48 * 3600, $nextSendAt);
        $this->assertLessThanOrEqual($after + 48 * 3600, $nextSendAt);
    }

    public function testEnrollReturnsNullOnDuplicate(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);
        $contact = $this->createSubscribedContact();

        $this->service->enroll($contact->id, $campaign->id);
        $result = $this->service->enroll($contact->id, $campaign->id);

        $this->assertNull($result);
    }

    public function testEnrollThrowsIfContactNotSubscribed(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);

        $id = $this->contactModel->insert([
            'email'  => 'unsub@example.com',
            'status' => ContactStatus::Unsubscribed,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->enroll($id, $campaign->id);
    }

    public function testEnrollThrowsIfCampaignHasNoSteps(): void
    {
        $campaign = $this->createDripCampaign();
        $contact  = $this->createSubscribedContact();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Campaign has no drip steps');
        $this->service->enroll($contact->id, $campaign->id);
    }

    public function testEnrollThrowsIfCampaignIsNotDripType(): void
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
        $contact = $this->createSubscribedContact();

        $this->expectException(RuntimeException::class);
        $this->service->enroll($contact->id, $id);
    }

    // -----------------------------------------------------------------------
    // enrollByName()
    // -----------------------------------------------------------------------

    public function testEnrollByNameEnrollsContactByName(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);
        $contact = $this->createSubscribedContact();

        $enrollment = $this->service->enrollByName($contact->id, 'Drip Campaign');

        $this->assertNotNull($enrollment);
        $this->assertSame($campaign->id, $enrollment->campaign_id);
    }

    public function testEnrollByNameThrowsForUnknownName(): void
    {
        $contact = $this->createSubscribedContact();

        $this->expectException(RuntimeException::class);
        $this->service->enrollByName($contact->id, 'nonexistent-campaign');
    }

    // -----------------------------------------------------------------------
    // cancel() / cancelAllForContact()
    // -----------------------------------------------------------------------

    public function testCancelSetsEnrollmentStatusToCancelled(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);
        $contact = $this->createSubscribedContact();

        $enrollment = $this->service->enroll($contact->id, $campaign->id);
        $this->assertNotNull($enrollment);

        $this->service->cancel($contact->id, $campaign->id);

        $updated = $this->enrollmentModel->find($enrollment->id);
        $this->assertSame(EnrollmentStatus::Cancelled, $updated->status);
    }

    public function testCancelAllForContactCancelsAllActiveEnrollments(): void
    {
        $campaign1 = $this->createDripCampaign();
        $this->createStep($campaign1->id, 1);

        $id2 = $this->campaignModel->insert([
            'name'       => 'Drip 2',
            'subject'    => 'Hi',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
        $this->createStep($id2, 1);

        $contact = $this->createSubscribedContact();

        $e1 = $this->service->enroll($contact->id, $campaign1->id);
        $e2 = $this->service->enroll($contact->id, $id2);
        $this->assertNotNull($e1);
        $this->assertNotNull($e2);

        $this->service->cancelAllForContact($contact->id);

        $this->assertSame(EnrollmentStatus::Cancelled, $this->enrollmentModel->find($e1->id)->status);
        $this->assertSame(EnrollmentStatus::Cancelled, $this->enrollmentModel->find($e2->id)->status);
    }

    // -----------------------------------------------------------------------
    // processDue()
    // -----------------------------------------------------------------------

    public function testProcessDueSendsEmailsAndAdvancesEnrollments(): void
    {
        $campaign = $this->createDripCampaign();
        $step1    = $this->createStep($campaign->id, 1, 24);
        $this->createStep($campaign->id, 2, 24);
        $contact = $this->createSubscribedContact();

        $this->service->enroll($contact->id, $campaign->id);

        // Force next_send_at into the past
        $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $result = $this->service->processDue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(0, $result['failed']);

        // Enrollment advanced to step 2
        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->first();
        $this->assertSame(2, (int) $enrollment->current_step);

        // Send log created
        $send = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->where('drip_step_id', $step1->id)
            ->first();
        $this->assertNotNull($send);
    }

    public function testProcessDueSkipsUnsubscribedContactsAndCancelsThem(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);
        $contact = $this->createSubscribedContact('skip@example.com');

        $this->service->enroll($contact->id, $campaign->id);

        // Unsubscribe the contact AFTER enrollment
        $this->contactModel->update($contact->id, ['status' => ContactStatus::Unsubscribed]);

        // Force due
        $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $result = $this->service->processDue();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['cancelled']);

        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->first();
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
    }

    public function testProcessDueDoesNotReprocessAlreadyAdvancedEnrollments(): void
    {
        $campaign = $this->createDripCampaign();
        $this->createStep($campaign->id);
        $contact = $this->createSubscribedContact('once@example.com');

        $this->service->enroll($contact->id, $campaign->id);

        // Force due and run once
        $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $this->service->processDue();

        // Enrollment is now completed (only 1 step); second call should find nothing due
        $result = $this->service->processDue();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['cancelled']);
    }

    // -----------------------------------------------------------------------
    // File-based campaigns
    // -----------------------------------------------------------------------

    public function testEnrollFileCampaignUsesYamlStep1DelayHours(): void
    {
        $campaign = $this->createFileCampaign('file-drip', 48);
        $contact  = $this->createSubscribedContact('file@example.com');

        $before     = time();
        $enrollment = $this->service->enroll($contact->id, $campaign->id);
        $after      = time();

        $this->assertNotNull($enrollment);
        $this->assertSame(1, $enrollment->current_step);

        $nextSendAt = strtotime((string) $enrollment->next_send_at);
        $this->assertGreaterThanOrEqual($before + 48 * 3600, $nextSendAt);
        $this->assertLessThanOrEqual($after + 48 * 3600, $nextSendAt);
    }

    public function testEnrollFileCampaignThrowsWhenYamlHasNoSteps(): void
    {
        $path = $this->campaignsDir . '/empty-steps.yaml';
        file_put_contents($path, "name: empty\nfrom_name: T\nfrom_email: t@t.com\nsteps: []\n");
        $this->tempFiles[] = $path;

        $id = $this->campaignModel->insert([
            'name'        => 'empty',
            'subject'     => 'Empty',
            'type'        => CampaignType::DripSequence,
            'status'      => CampaignStatus::Active,
            'source_file' => 'empty-steps.yaml',
            'from_name'   => 'T',
            'from_email'  => 't@t.com',
        ]);
        $contact = $this->createSubscribedContact('empty@example.com');

        $this->expectException(RuntimeException::class);
        $this->service->enroll($contact->id, $id);
    }

    public function testProcessDueFileCampaignSendsFromYamlStep(): void
    {
        $campaign = $this->createFileCampaign('file-drip-process', 0);
        $contact  = $this->createSubscribedContact('filepro@example.com');

        $this->service->enroll($contact->id, $campaign->id);

        $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        $result = $this->service->processDue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['failed']);

        // Enrollment advanced to step 2 (file campaign has 2 steps)
        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->first();
        $this->assertSame(2, (int) $enrollment->current_step);

        // Send log created with null drip_step_id (no DB step row)
        $send = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->first();
        $this->assertNotNull($send);
        $this->assertNull($send->drip_step_id);
    }
}
