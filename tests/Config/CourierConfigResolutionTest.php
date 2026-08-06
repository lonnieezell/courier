<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Courier as AppCourierConfig;

/**
 * @internal
 */
final class CourierConfigResolutionTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stand in for an application that has published the config: loading the
        // stub puts the very class `spark publish` writes into the Config
        // namespace, where Factories should prefer it over the package default.
        require_once __DIR__ . '/../../stubs/Courier.php';

        Factories::reset('config');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Factories::reset('config');
    }

    public function testApplicationConfigTakesPrecedenceOverPackageDefault(): void
    {
        $this->assertSame(AppCourierConfig::class, config('Courier')::class);
    }

    public function testServicesReadTheApplicationConfig(): void
    {
        $config                = config('Courier');
        $config->campaignsPath = '/tmp/app-only-campaigns';

        $loader = service('campaignFileLoader', false);

        // The package config's campaignsPath is empty, which would resolve to a
        // path under APPPATH. Getting the value set above back is only possible
        // if the service read the application's copy.
        $this->assertSame('/tmp/app-only-campaigns', $loader->resolvedPath());
    }
}
