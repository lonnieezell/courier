<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\DripsCommand;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\TemplateService;

/**
 * @internal
 */
final class DripsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const BODY_VIEW = 'Myth\Courier\Views\tests/test_body';

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private DripsCommand $command;
    private ContactModel $contactModel;
    private CampaignModel $campaignModel;
    private DripEnrollmentModel $enrollmentModel;
    private DripService $dripService;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config('Courier');
        $config->testMode = true;

        $this->contactModel    = new ContactModel();
        $this->campaignModel   = new CampaignModel();
        $this->enrollmentModel = new DripEnrollmentModel();
        $stepModel             = new DripStepModel();

        $this->dripService = new DripService(
            $this->enrollmentModel,
            $stepModel,
            $this->campaignModel,
            new MailerService(
                new TemplateService(sys_get_temp_dir()),
                new SendModel(),
                $this->campaignModel,
                config('Courier'),
            ),
            $this->contactModel,
            $config,
        );

        Services::injectMock('dripService', $this->dripService);
        $this->command = new DripsCommand(service('logger'), $this->createStub(Commands::class));
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    private function setArgv(array $args): void
    {
        $_SERVER['argv'] = array_merge(['spark', 'courier:drips'], $args);
        CLI::init();
    }

    private function insertDripCampaign(string $name = 'Welcome Drip'): int
    {
        $campaignId = (int) $this->campaignModel->insert([
            'name'       => $name,
            'subject'    => 'Welcome',
            'type'       => CampaignType::DripSequence,
            'status'     => CampaignStatus::Active,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
        ]);

        (new DripStepModel())->insert([
            'campaign_id' => $campaignId,
            'position'    => 1,
            'view'        => self::BODY_VIEW,
            'subject'     => 'Step 1',
            'delay_hours' => 0,
        ]);

        return $campaignId;
    }

    public function testEnrollCreatesEnrollment(): void
    {
        $campaignId = $this->insertDripCampaign();
        $contactId  = (int) $this->contactModel->insert([
            'email'  => 'enroll@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $this->setArgv(['enroll', '--email', 'enroll@example.com', '--campaign', 'Welcome Drip']);
        $this->command->run(['enroll']);

        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function testCancelSetsEnrollmentCancelled(): void
    {
        $campaignId = $this->insertDripCampaign();
        $contactId  = (int) $this->contactModel->insert([
            'email'  => 'cancel@example.com',
            'status' => ContactStatus::Subscribed,
        ]);

        $this->dripService->enroll($contactId, $campaignId);

        $this->setArgv(['cancel', '--email', 'cancel@example.com', '--campaign', 'Welcome Drip', '--force']);
        $this->command->run(['cancel']);

        $enrollment = $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->first();

        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
    }

    public function testListRunsWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['list']);
        $this->command->run(['list']);
    }
}
