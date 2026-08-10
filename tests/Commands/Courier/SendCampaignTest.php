<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\SendCampaign;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Services\CampaignService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\SegmentService;
use Myth\Courier\Services\TemplateService;

/**
 * @internal
 */
final class SendCampaignTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private CampaignModel $campaignModel;
    private ContactModel $contactModel;
    private SendModel $sendModel;
    private SendCampaign $command;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config('Courier');
        $config->testMode = true;

        $this->campaignModel = new CampaignModel();
        $this->contactModel  = new ContactModel();
        $this->sendModel     = new SendModel();

        $segmentService = new SegmentService(
            $this->contactModel,
            new SegmentModel(),
        );

        $mailerService = new MailerService(
            new TemplateService(sys_get_temp_dir()),
            $this->sendModel,
            $this->campaignModel,
            config('Courier'),
        );

        $campaignService = new CampaignService(
            $this->campaignModel,
            new DripStepModel(),
            $segmentService,
            $mailerService,
            $this->sendModel,
            $this->contactModel,
            $config,
        );

        $this->command = new SendCampaign(service('logger'), $this->createStub(Commands::class));
        Services::injectMock('campaignService', $campaignService);
    }

    public function testRunProcessesScheduledCampaignAndSetsStatusSent(): void
    {
        // Create a contact and a scheduled campaign
        $this->contactModel->insert([
            'email'  => 'r@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $campaignId = (int) $this->campaignModel->insert([
            'name'         => 'Blast',
            'subject'      => 'Hello',
            'type'         => CampaignType::Blast,
            'view'         => self::BODY_VIEW,
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'from_name'    => 'Sender',
            'from_email'   => 'sender@example.com',
        ]);

        $this->command->run([$campaignId]);

        $campaign = $this->campaignModel->find($campaignId);
        $this->assertSame(CampaignStatus::Sent, $campaign->status);
        $this->assertNotNull($campaign->sent_at);

        // Verify a Send row was created and processed
        $sendCount = $this->sendModel
            ->where('campaign_id', $campaignId)
            ->countAllResults();
        $this->assertSame(1, $sendCount);
    }

    public function testRunSetsStatusPausedOnExceptionAndContinuesToNextCampaign(): void
    {
        $this->contactModel->insert([
            'email'  => 'p@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        // First campaign: will throw because view doesn't exist
        $badCampaignId = (int) $this->campaignModel->insert([
            'name'         => 'Bad',
            'subject'      => 'Hello',
            'type'         => CampaignType::Blast,
            'view'         => 'nonexistent/view/that/will/fail',
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'from_name'    => 'Sender',
            'from_email'   => 'sender@example.com',
        ]);

        // Second campaign: valid, should still process
        $goodCampaignId = (int) $this->campaignModel->insert([
            'name'         => 'Good',
            'subject'      => 'Hello',
            'type'         => CampaignType::Blast,
            'view'         => self::BODY_VIEW,
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'from_name'    => 'Sender',
            'from_email'   => 'sender@example.com',
        ]);

        $this->command->run([]);

        $bad  = $this->campaignModel->find($badCampaignId);
        $good = $this->campaignModel->find($goodCampaignId);

        $this->assertSame(CampaignStatus::Paused, $bad->status);
        $this->assertSame(CampaignStatus::Sent, $good->status);
    }

    public function testRunWithSpecificCampaignIdOnlyProcessesThatCampaign(): void
    {
        $this->contactModel->insert([
            'email'  => 'sp@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $campaign1Id = (int) $this->campaignModel->insert([
            'name'         => 'Campaign 1',
            'subject'      => 'Hello',
            'type'         => CampaignType::Blast,
            'view'         => self::BODY_VIEW,
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'from_name'    => 'Sender',
            'from_email'   => 'sender@example.com',
        ]);

        $campaign2Id = (int) $this->campaignModel->insert([
            'name'         => 'Campaign 2',
            'subject'      => 'Hello',
            'type'         => CampaignType::Blast,
            'view'         => self::BODY_VIEW,
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'from_name'    => 'Sender',
            'from_email'   => 'sender@example.com',
        ]);

        // Only process campaign 1
        $this->command->run([$campaign1Id]);

        $c1 = $this->campaignModel->find($campaign1Id);
        $c2 = $this->campaignModel->find($campaign2Id);

        $this->assertSame(CampaignStatus::Sent, $c1->status);
        $this->assertSame(CampaignStatus::Scheduled, $c2->status);
    }
}
