<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Myth\Courier\Commands\SyncCampaigns;
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
}
