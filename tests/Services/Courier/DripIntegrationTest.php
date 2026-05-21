<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\EnrollmentStatus;
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

/**
 * Full drip flow integration test.
 *
 * subscribe → enroll → time-travel → processDue → verify send log + enrollment advanced
 *
 * @internal
 */
final class DripIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ContactService $contactService;
    private DripService $dripService;
    private CampaignModel $campaignModel;
    private DripEnrollmentModel $enrollmentModel;
    private DripStepModel $stepModel;
    private SendModel $sendModel;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config(CourierConfig::class);
        $config->testMode = true;

        $this->campaignModel   = new CampaignModel();
        $contactModel          = new ContactModel();
        $this->enrollmentModel = new DripEnrollmentModel();
        $this->stepModel       = new DripStepModel();
        $this->sendModel       = new SendModel();

        $mailerService = new MailerService(
            new TemplateService(new MarkdownService(sys_get_temp_dir())),
            $this->sendModel,
            $this->campaignModel,
        );

        $this->dripService = new DripService(
            $this->enrollmentModel,
            $this->stepModel,
            $this->campaignModel,
            $mailerService,
            $contactModel,
            $config,
        );

        $this->contactService = new ContactService(
            $contactModel,
            new TagModel(),
            $this->enrollmentModel,
            new ContactTagModel(),
        );
        $this->contactService->setDripService($this->dripService);
    }

    public function testFullDripFlow(): void
    {
        // 1. Create a drip campaign with two steps
        $campaignId = $this->campaignModel->insert([
            'name'       => 'Welcome Drip',
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
        $step1Id = $this->stepModel->insert([
            'campaign_id' => $campaignId,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1: Welcome',
            'delay_hours' => 24,
        ]);
        $this->stepModel->insert([
            'campaign_id' => $campaignId,
            'position'    => 2,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 2: Follow-up',
            'delay_hours' => 48,
        ]);

        // 2. Subscribe contact with drip campaign → auto-enroll
        $contact = $this->contactService->subscribe(
            ['email' => 'integration@example.com'],
            [],
            $campaignId,
        );

        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaignId)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(1, (int) $enrollment->current_step);

        // 3. Time-travel: push next_send_at into the past
        $this->enrollmentModel
            ->where('id', $enrollment->id)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        // 4. processDue() — should send step 1 and advance to step 2
        $result = $this->dripService->processDue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(0, $result['failed']);

        // 5. Verify send log created for step 1
        $send = $this->sendModel
            ->where('contact_id', $contact->id)
            ->where('campaign_id', $campaignId)
            ->where('drip_step_id', $step1Id)
            ->first();

        $this->assertNotNull($send);

        // 6. Verify enrollment advanced to step 2
        $enrollment = $this->enrollmentModel->find($enrollment->id);
        $this->assertSame(2, (int) $enrollment->current_step);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }
}
