<?php

declare(strict_types=1);

namespace Tests\Publishers;

use CodeIgniter\Publisher\Publisher;
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

    public function testSparkPublishDiscoversThePublisher(): void
    {
        // Discovery walks every file under Publishers/ and asks the autoloader
        // about it. With the application's published config already loaded, a
        // stub inside the package's PSR-4 root would be pulled in here and
        // fatal on the redeclaration — which is why it lives in stubs/.
        $discovered = array_filter(
            Publisher::discover(),
            static fn (Publisher $publisher): bool => $publisher instanceof ConfigPublisher,
        );

        $this->assertCount(1, $discovered);
    }

    public function testPublishesConfigIntoTheApplicationNamespace(): void
    {
        mkdir($this->destination, 0775, true);

        $publisher = new ConfigPublisher(null, $this->destination);

        $this->assertTrue($publisher->publish());
        $this->assertFileExists($this->destination . 'Courier.php');

        $published = (string) file_get_contents($this->destination . 'Courier.php');

        // The application namespace and the inheritance are what make the
        // published file load at all: Factories prefers a Config\Courier over
        // the package default only because the package resolves the config by
        // its short name.
        $this->assertStringContainsString('namespace Config;', $published);
        $this->assertStringContainsString('class Courier extends CourierConfig', $published);
    }

    public function testDoesNotOverwriteAnAlreadyPublishedFile(): void
    {
        mkdir($this->destination, 0775, true);
        file_put_contents($this->destination . 'Courier.php', '<?php // customized');

        $publisher = new ConfigPublisher(null, $this->destination);
        $publisher->publish();

        $this->assertSame('<?php // customized', file_get_contents($this->destination . 'Courier.php'));
    }
}
