<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Config\Registrar;
use Myth\Courier\Postal\CourierSuppressionList;
use Myth\Courier\Postal\CourierUnsubscribeUrl;
use Myth\Postal\Config\Email as PostalEmailConfig;

/**
 * @internal
 */
final class RegistrarEmailTest extends CIUnitTestCase
{
    public function testEmailRegistrarReturnsPostalBindings(): void
    {
        $registered = Registrar::Email();

        $this->assertSame(CourierSuppressionList::class, $registered['suppressionList']);
        $this->assertSame(CourierUnsubscribeUrl::class, $registered['unsubscribeUrl']);
    }

    public function testPostalEmailConfigReceivesCourierBindings(): void
    {
        $config = config(PostalEmailConfig::class);

        $this->assertSame(CourierSuppressionList::class, $config->suppressionList);
        $this->assertSame(CourierUnsubscribeUrl::class, $config->unsubscribeUrl);
    }
}
