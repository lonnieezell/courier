<?php

declare(strict_types=1);

namespace Tests\Filters\Courier;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Filters\CaptureThrottleFilter;

/**
 * @internal
 */
final class CaptureThrottleFilterTest extends CIUnitTestCase
{
    private CaptureThrottleFilter $filter;
    private CourierConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        \CodeIgniter\Config\Services::reset(true);
        cache()->clean();
        $this->filter = new CaptureThrottleFilter();
        $this->config = config(CourierConfig::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->config->captureRateLimit = 15;
        cache()->clean();
    }

    public function testPassesThroughWhenRateLimitIsZero(): void
    {
        $this->config->captureRateLimit = 0;

        $result = $this->filter->before(service('request'));

        $this->assertNull($result);
    }

    public function testPassesThroughWhenUnderLimit(): void
    {
        $this->config->captureRateLimit = 15;

        $result = $this->filter->before(service('request'));

        $this->assertNull($result);
    }

    public function testReturns429JsonWhenLimitExceededForAjax(): void
    {
        $this->config->captureRateLimit = 2;

        $request = service('request');
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');

        $this->filter->before($request);
        $this->filter->before($request);
        $result = $this->filter->before($request);

        $this->assertNotNull($result);
        $this->assertSame(429, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('rate_limit', $body['errors']);
    }

    public function testRedirectsBackWhenLimitExceededForStandardForm(): void
    {
        $this->config->captureRateLimit = 2;

        // Fresh request with no AJAX header
        $request = Services::request();

        $this->filter->before($request);
        $this->filter->before($request);
        $result = $this->filter->before($request);

        $this->assertNotNull($result);
        $this->assertSame(302, $result->getStatusCode());
    }
}
