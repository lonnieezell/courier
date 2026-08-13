<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\SyncCampaigns;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Services\CampaignFileLoader;

/**
 * @internal
 */
final class SyncCampaignsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';
    private SyncCampaigns $command;
    private CampaignModel $campaignModel;
    private string $campaignsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignsDir = sys_get_temp_dir() . '/courier_campaigns_test_' . uniqid();
        mkdir($this->campaignsDir, 0777, true);

        Services::injectMock('campaignFileLoader', new CampaignFileLoader($this->campaignsDir));

        $this->campaignModel = new CampaignModel();
        $this->command       = new SyncCampaigns(service('logger'), $this->createStub(Commands::class));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $files = glob($this->campaignsDir . '/*.yaml');
        array_map(unlink(...), $files !== false ? $files : []);
        rmdir($this->campaignsDir);
    }

    private function writeYaml(string $name, array $extra = []): string
    {
        $content = array_merge([
            'name'       => $name,
            'from_name'  => 'Test Sender',
            'from_email' => 'test@example.com',
            'steps'      => [
                ['position' => 1, 'subject' => 'Welcome!', 'view' => 'emails/step1', 'delay_hours' => 0],
            ],
        ], $extra);

        $yaml = '';

        foreach ($content as $key => $value) {
            if ($key === 'steps') {
                $yaml .= "steps:\n";

                foreach ($value as $step) {
                    $yaml .= "  - position: {$step['position']}\n";
                    $yaml .= "    subject: \"{$step['subject']}\"\n";
                    $yaml .= "    view: {$step['view']}\n";
                    $yaml .= "    delay_hours: {$step['delay_hours']}\n";
                }
            } else {
                $yaml .= "{$key}: {$value}\n";
            }
        }

        $filename = $this->campaignsDir . '/' . $name . '.yaml';
        file_put_contents($filename, $yaml);

        return basename($filename);
    }

    public function testSyncCreatesCampaignRow(): void
    {
        $this->writeYaml('welcome-sequence');

        $this->command->run([]);

        $campaign = $this->campaignModel->where('name', 'welcome-sequence')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('welcome-sequence.yaml', $campaign->source_file);
        $this->assertSame('test@example.com', $campaign->from_email);
    }

    public function testSyncUpdatesCampaignRowOnRerun(): void
    {
        $this->writeYaml('welcome-sequence');
        $this->command->run([]);

        // Update the YAML with a new from_email
        $this->writeYaml('welcome-sequence', ['from_email' => 'updated@example.com']);
        $this->command->run([]);

        $campaigns = $this->campaignModel->where('name', 'welcome-sequence')->findAll();
        $this->assertCount(1, $campaigns);
        $this->assertSame('updated@example.com', $campaigns[0]->from_email);
    }

    public function testSyncSkipsInvalidFiles(): void
    {
        // Valid file
        $this->writeYaml('valid-campaign');

        // Invalid file: missing from_email
        file_put_contents($this->campaignsDir . '/bad-campaign.yaml', "name: bad\nfrom_name: Test\nsteps:\n  - position: 1\n    subject: Hi\n    view: v\n    delay_hours: 0\n");

        $this->command->run([]);

        $this->assertNotNull($this->campaignModel->where('name', 'valid-campaign')->first());
        $this->assertNull($this->campaignModel->where('name', 'bad')->first());
    }

    private function writeBlastYaml(string $name): string
    {
        $yaml = <<<YAML
            name: {$name}
            type: blast
            subject: "20% off this week"
            from_name: Test Sender
            from_email: test@example.com
            view: emails/spring-sale
            tag_filter:
              - customer
              - newsletter

            YAML;

        $filename = $this->campaignsDir . '/' . $name . '.yaml';
        file_put_contents($filename, $yaml);

        return basename($filename);
    }

    public function testSyncCreatesBlastCampaignRowAsDraft(): void
    {
        $this->writeBlastYaml('spring-sale');

        $this->command->run([]);

        $campaign = $this->campaignModel->where('name', 'spring-sale')->first();
        $this->assertNotNull($campaign);
        $this->assertSame(CampaignType::Blast, $campaign->type);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame('20% off this week', $campaign->subject);
        $this->assertSame('emails/spring-sale', $campaign->view);
        $this->assertSame(['customer', 'newsletter'], (array) $campaign->tag_filter);
    }

    public function testSyncUpdatesBlastContentOnRerun(): void
    {
        $this->writeBlastYaml('spring-sale');
        $this->command->run([]);

        file_put_contents(
            $this->campaignsDir . '/spring-sale.yaml',
            "name: spring-sale\ntype: blast\nsubject: \"30% off, last day\"\nfrom_name: Test Sender\nfrom_email: test@example.com\nview: emails/spring-sale\n",
        );
        $this->command->run([]);

        $campaigns = $this->campaignModel->where('name', 'spring-sale')->findAll();
        $this->assertCount(1, $campaigns);
        $this->assertSame('30% off, last day', $campaigns[0]->subject);
        $this->assertNull($campaigns[0]->tag_filter);
    }

    public function testSyncDoesNotResetStatusOfAScheduledBlast(): void
    {
        $this->writeBlastYaml('spring-sale');
        $this->command->run([]);

        $campaign = $this->campaignModel->where('name', 'spring-sale')->first();
        $this->campaignModel->update($campaign->id, [
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => '2026-06-01 09:00:00',
        ]);

        // Re-sync after an operator has scheduled the send — content changes,
        // but the schedule an operator set must survive.
        $this->writeBlastYaml('spring-sale');
        $this->command->run([]);

        $reloaded = $this->campaignModel->where('name', 'spring-sale')->first();
        $this->assertSame(CampaignStatus::Scheduled, $reloaded->status);
        $this->assertSame('2026-06-01 09:00:00', $reloaded->scheduled_at);
    }

    public function testSyncSkipsBlastMissingView(): void
    {
        file_put_contents(
            $this->campaignsDir . '/broken-blast.yaml',
            "name: broken-blast\ntype: blast\nsubject: \"Hi\"\nfrom_name: Test Sender\nfrom_email: test@example.com\n",
        );

        $this->command->run([]);

        $this->assertNull($this->campaignModel->where('name', 'broken-blast')->first());
    }
}
