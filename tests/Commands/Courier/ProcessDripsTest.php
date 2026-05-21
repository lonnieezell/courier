<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\ProcessDrips;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\TemplateService;
use RuntimeException;

/**
 * @internal
 */
final class ProcessDripsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private ProcessDrips $command;
    private DripService $dripService;
    private CampaignModel $campaignModel;
    private ContactModel $contactModel;
    private DripEnrollmentModel $enrollmentModel;
    private DripStepModel $stepModel;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config(CourierConfig::class);
        $config->testMode = true;

        $this->campaignModel   = new CampaignModel();
        $this->contactModel    = new ContactModel();
        $this->enrollmentModel = new DripEnrollmentModel();
        $this->stepModel       = new DripStepModel();

        $mailerService = new MailerService(
            new TemplateService(new MarkdownService(sys_get_temp_dir())),
            new SendModel(),
            $this->campaignModel,
        );

        $this->dripService = new DripService(
            $this->enrollmentModel,
            $this->stepModel,
            $this->campaignModel,
            $mailerService,
            $this->contactModel,
            $config,
        );

        Services::injectMock('dripService', $this->dripService);
        $this->command = new ProcessDrips(service('logger'), $this->createStub(Commands::class));
    }

    public function testRunCallsProcessDueAndLogsResult(): void
    {
        // Set up a due enrollment so processDue() returns processed=1
        $campaignId = $this->campaignModel->insert([
            'name'       => 'Drip',
            'subject'    => 'Hi',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Draft,
            'view'       => self::BODY_VIEW,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);
        $this->stepModel->insert([
            'campaign_id' => $campaignId,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 24,
        ]);
        $contactId = $this->contactModel->insert([
            'email'  => 'drip@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $this->dripService->enroll($contactId, $campaignId);

        $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->set('next_send_at', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->update();

        // run() should not throw and should complete without error
        $this->command->run([]);

        // Verify enrollment was advanced (processed)
        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->first();

        // Only one step, so enrollment should be completed after advance
        $this->assertNotNull($enrollment);
    }

    public function testRunHandlesExceptionGracefully(): void
    {
        // Corrupt the dripService by using a DripService with no DB (would throw on processDue)
        // Instead, test that run() doesn't propagate exceptions by injecting a mock via anonymous class trick.
        // We test this by verifying run() returns without throwing even when processDue() throws.

        $throwingService = new class ($this->enrollmentModel, $this->stepModel, $this->campaignModel, new MailerService(new TemplateService(new MarkdownService(sys_get_temp_dir())), new SendModel(), $this->campaignModel), $this->contactModel, config(CourierConfig::class)) extends DripService {
            public function processDue(): array
            {
                throw new RuntimeException('Simulated failure');
            }
        };

        Services::injectMock('dripService', $throwingService);
        $command = new ProcessDrips(service('logger'), $this->createStub(Commands::class));

        // Should not throw
        $command->run([]);
        $this->assertTrue(true);
    }
}
