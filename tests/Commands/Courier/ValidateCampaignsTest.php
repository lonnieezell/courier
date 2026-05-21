<?php

declare(strict_types=1);

namespace Tests\Commands\Courier;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Myth\Courier\Commands\ValidateCampaigns;
use Myth\Courier\Services\CampaignFileLoader;

/**
 * @internal
 */
final class ValidateCampaignsTest extends CIUnitTestCase
{
    private ValidateCampaigns $command;
    private string $campaignsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignsDir = sys_get_temp_dir() . '/courier_validate_test_' . uniqid();
        mkdir($this->campaignsDir, 0777, true);

        Services::injectMock('campaignFileLoader', new CampaignFileLoader($this->campaignsDir));

        $this->command = new ValidateCampaigns(service('logger'), $this->createStub(Commands::class));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $files = glob($this->campaignsDir . '/*.yaml');
        array_map('unlink', $files !== false ? $files : []);
        rmdir($this->campaignsDir);
    }

    private function writeValidYaml(string $name): void
    {
        file_put_contents($this->campaignsDir . "/{$name}.yaml", implode("\n", [
            "name: {$name}",
            'from_name: Test',
            'from_email: test@example.com',
            'steps:',
            '  - position: 1',
            '    subject: "Hello"',
            '    view: emails/step1',
            '    delay_hours: 0',
        ]));
    }

    public function testValidateExitsZeroWhenAllFilesAreValid(): void
    {
        $this->writeValidYaml('welcome-sequence');

        $exitCode = $this->command->run([]);

        $this->assertSame(0, $exitCode);
    }

    public function testValidateExitsOneWhenAFileIsInvalid(): void
    {
        $this->writeValidYaml('good-campaign');
        file_put_contents($this->campaignsDir . '/bad-campaign.yaml', "name: bad\nfrom_name: Test\n");

        $exitCode = $this->command->run([]);

        $this->assertSame(1, $exitCode);
    }

    public function testValidateExitsZeroWithNoFiles(): void
    {
        $exitCode = $this->command->run([]);

        $this->assertSame(0, $exitCode);
    }
}
