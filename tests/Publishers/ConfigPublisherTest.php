<?php

declare(strict_types=1);

namespace Tests\Publishers;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Publishers\ConfigPublisher;

/**
 * @internal
 */
final class ConfigPublisherTest extends CIUnitTestCase
{
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['filesystem']);

        $this->destination = WRITEPATH . 'ConfigPublisherTest' . DIRECTORY_SEPARATOR;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->destination)) {
            delete_files($this->destination, true, false, true);
            @rmdir($this->destination);
        }
    }

    public function testPublishCopiesCourierConfigToDestination(): void
    {
        mkdir($this->destination, 0775, true);

        $publisher = new ConfigPublisher(null, $this->destination);

        $result = $publisher->publish();

        $this->assertTrue($result);
        $this->assertFileExists($this->destination . 'Courier.php');
        $this->assertStringContainsString('class Courier extends BaseConfig', (string) file_get_contents($this->destination . 'Courier.php'));
    }

    public function testPublishDoesNotOverwriteExistingFile(): void
    {
        mkdir($this->destination, 0775, true);
        file_put_contents($this->destination . 'Courier.php', '<?php // customized');

        $publisher = new ConfigPublisher(null, $this->destination);
        $publisher->publish();

        $this->assertSame('<?php // customized', file_get_contents($this->destination . 'Courier.php'));
    }
}
