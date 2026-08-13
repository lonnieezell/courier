<?php

declare(strict_types=1);

namespace Tests\Services\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Services\CampaignFileLoader;
use RuntimeException;

/**
 * @internal
 */
final class CampaignFileLoaderTest extends CIUnitTestCase
{
    private CampaignFileLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new CampaignFileLoader();
    }

    private function validData(): array
    {
        return [
            'name'       => 'welcome-sequence',
            'from_name'  => 'Acme Team',
            'from_email' => 'hello@acme.com',
            'steps'      => [
                ['position' => 1, 'subject' => 'Welcome!', 'view' => 'emails/step1', 'delay_hours' => 0],
                ['position' => 2, 'subject' => 'Tips', 'view' => 'emails/step2', 'delay_hours' => 24],
            ],
        ];
    }

    public function testValidateReturnsEmptyArrayForValidData(): void
    {
        $errors = $this->loader->validate($this->validData(), 'welcome-sequence.yaml');
        $this->assertSame([], $errors);
    }

    public function testValidateReturnsMissingNameError(): void
    {
        $data = $this->validData();
        unset($data['name']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("missing required field 'name'", $errors[0]);
    }

    public function testValidateReturnsMissingFromNameError(): void
    {
        $data = $this->validData();
        unset($data['from_name']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("missing required field 'from_name'", $errors[0]);
    }

    public function testValidateReturnsMissingFromEmailError(): void
    {
        $data = $this->validData();
        unset($data['from_email']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("missing required field 'from_email'", $errors[0]);
    }

    public function testValidateReturnsInvalidEmailError(): void
    {
        $data               = $this->validData();
        $data['from_email'] = 'not-an-email';
        $errors             = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('from_email', $errors[0]);
    }

    public function testValidateReturnsDuplicatePositionError(): void
    {
        $data          = $this->validData();
        $data['steps'] = [
            ['position' => 1, 'subject' => 'A', 'view' => 'v1', 'delay_hours' => 0],
            ['position' => 1, 'subject' => 'B', 'view' => 'v2', 'delay_hours' => 24],
        ];
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate position', $errors[0]);
    }

    public function testValidateReturnsErrorWhenPositionsDontStartAtOne(): void
    {
        $data          = $this->validData();
        $data['steps'] = [
            ['position' => 2, 'subject' => 'A', 'view' => 'v1', 'delay_hours' => 0],
        ];
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('start at 1', $errors[0]);
    }

    public function testLoadFromFileReturnsCampaignAndSteps(): void
    {
        $yaml = sys_get_temp_dir() . '/test-campaign.yaml';
        file_put_contents($yaml, "name: welcome\nfrom_name: Acme\nfrom_email: hi@acme.com\nsteps:\n  - position: 1\n    subject: Hi\n    view: emails/step1\n    delay_hours: 0\n");

        $result = $this->loader->loadFromFile($yaml);

        $this->assertArrayHasKey('campaign', $result);
        $this->assertArrayHasKey('steps', $result);
        $this->assertSame('welcome', $result['campaign']['name']);
        $this->assertCount(1, $result['steps']);
        $this->assertSame(1, $result['steps'][0]['position']);

        unlink($yaml);
    }

    public function testLoadFromFileThrowsOnInvalidYaml(): void
    {
        $yaml = sys_get_temp_dir() . '/bad-campaign.yaml';
        file_put_contents($yaml, "name: [unclosed\nfoo: bar: baz:\n");

        $this->expectException(RuntimeException::class);
        $this->loader->loadFromFile($yaml);

        unlink($yaml);
    }

    public function testValidateReturnsMissingStepFieldError(): void
    {
        $data          = $this->validData();
        $data['steps'] = [
            ['position' => 1, 'view' => 'v1', 'delay_hours' => 0],  // missing subject
        ];
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("missing required field 'subject'", $errors[0]);
    }

    private function validBlastData(): array
    {
        return [
            'name'       => 'spring-sale',
            'type'       => 'blast',
            'subject'    => '20% off this week',
            'view'       => 'emails/spring-sale',
            'from_name'  => 'Acme Team',
            'from_email' => 'hello@acme.com',
        ];
    }

    public function testValidateReturnsEmptyArrayForValidBlastData(): void
    {
        $errors = $this->loader->validate($this->validBlastData(), 'spring-sale.yaml');
        $this->assertSame([], $errors);
    }

    public function testValidateBlastDoesNotRequireSteps(): void
    {
        $data = $this->validBlastData();
        $this->assertArrayNotHasKey('steps', $data);

        $errors = $this->loader->validate($data, 'spring-sale.yaml');
        $this->assertSame([], $errors);
    }

    public function testValidateReturnsMissingSubjectErrorForBlast(): void
    {
        $data = $this->validBlastData();
        unset($data['subject']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("missing required field 'subject'", $errors[0]);
    }

    public function testValidateReturnsMissingViewErrorForBlast(): void
    {
        $data = $this->validBlastData();
        unset($data['view']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("missing required field 'view'", $errors[0]);
    }

    public function testValidateAcceptsOptionalTagFilterForBlast(): void
    {
        $data               = $this->validBlastData();
        $data['tag_filter'] = ['customer', 'newsletter'];
        $errors             = $this->loader->validate($data, 'spring-sale.yaml');
        $this->assertSame([], $errors);
    }

    public function testValidateReturnsInvalidTypeError(): void
    {
        $data         = $this->validBlastData();
        $data['type'] = 'not-a-real-type';
        $errors       = $this->loader->validate($data, 'test.yaml');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("invalid type 'not-a-real-type'", $errors[0]);
    }

    public function testValidateDefaultsToDripSequenceWhenTypeOmitted(): void
    {
        $data = $this->validData();
        $this->assertArrayNotHasKey('type', $data);

        // Valid drip data passes; missing 'steps' should still fail as before.
        unset($data['steps']);
        $errors = $this->loader->validate($data, 'test.yaml');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("missing required field 'steps'", $errors[0]);
    }
}
