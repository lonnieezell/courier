<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\CampaignsCommand;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Services\CampaignService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\MarkdownService;
use Myth\Courier\Services\SegmentService;
use Myth\Courier\Services\TemplateService;

/**
 * @internal
 */
final class CampaignsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private CampaignsCommand $command;
    private CampaignModel $campaignModel;

    protected function setUp(): void
    {
        parent::setUp();

        $config           = config(CourierConfig::class);
        $config->testMode = true;

        $this->campaignModel = new CampaignModel();

        $campaignService = new CampaignService(
            $this->campaignModel,
            new DripStepModel(),
            new SegmentService(new ContactModel(), new SegmentModel()),
            new MailerService(
                new TemplateService(new MarkdownService(sys_get_temp_dir())),
                new SendModel(),
                $this->campaignModel,
            ),
            new SendModel(),
            new ContactModel(),
            $config,
        );

        Services::injectMock('campaignService', $campaignService);
        $this->command = new CampaignsCommand(service('logger'), $this->createStub(Commands::class));
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    private function setArgv(array $args): void
    {
        $_SERVER['argv'] = array_merge(['spark', 'courier:campaigns'], $args);
        CLI::init();
    }

    private function insertBlastCampaign(string $name = 'Test Blast', CampaignStatus $status = CampaignStatus::Draft): int
    {
        return (int) $this->campaignModel->insert([
            'name'       => $name,
            'subject'    => 'Test Subject',
            'type'       => CampaignType::Blast,
            'status'     => $status,
            'from_name'  => 'Sender',
            'from_email' => 'sender@example.com',
            'view'       => 'Myth\Courier\Views\tests/test_body',
        ]);
    }

    public function testCreateInsertsDraftBlastCampaign(): void
    {
        $this->setArgv([
            'create',
            '--name', 'My Campaign',
            '--subject', 'Hello World',
            '--from-name', 'Sender',
            '--from-email', 'sender@example.com',
        ]);
        $this->command->run(['create']);

        $campaign = $this->campaignModel->where('name', 'My Campaign')->first();
        $this->assertNotNull($campaign);
        $this->assertSame(CampaignType::Blast, $campaign->type);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function testScheduleSetsStatusAndTime(): void
    {
        $id = $this->insertBlastCampaign();

        $this->setArgv(['schedule', '--id', (string) $id, '--at', '2026-12-01 09:00:00']);
        $this->command->run(['schedule']);

        $campaign = $this->campaignModel->find($id);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertNotNull($campaign->scheduled_at);
    }

    public function testPauseSetsStatusPaused(): void
    {
        $id = $this->insertBlastCampaign('Pausing', CampaignStatus::Scheduled);

        $this->setArgv(['pause', '--id', (string) $id, '--force']);
        $this->command->run(['pause']);

        $this->assertSame(CampaignStatus::Paused, $this->campaignModel->find($id)->status);
    }

    public function testResumeSetsStatusScheduled(): void
    {
        $id = $this->insertBlastCampaign('Resuming', CampaignStatus::Paused);

        $this->setArgv(['resume', '--id', (string) $id]);
        $this->command->run(['resume']);

        $this->assertSame(CampaignStatus::Scheduled, $this->campaignModel->find($id)->status);
    }

    public function testCancelSetsStatusCancelled(): void
    {
        $id = $this->insertBlastCampaign('Cancelling', CampaignStatus::Scheduled);

        $this->setArgv(['cancel', '--id', (string) $id, '--force']);
        $this->command->run(['cancel']);

        $this->assertSame(CampaignStatus::Cancelled, $this->campaignModel->find($id)->status);
    }

    public function testStatsRunsWithoutThrowingForKnownId(): void
    {
        $id = $this->insertBlastCampaign();

        $this->setArgv(['stats', '--id', (string) $id]);
        $this->expectNotToPerformAssertions();
        $this->command->run(['stats']);
    }

    public function testListRunsWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->setArgv(['list']);
        $this->command->run(['list']);
    }
}
