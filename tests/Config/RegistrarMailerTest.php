<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Config\Registrar;
use Myth\Courier\Postal\CourierSuppressionList;
use Myth\Courier\Postal\CourierUnsubscribeUrl;

/**
 * @internal
 */
final class RegistrarMailerTest extends CIUnitTestCase
{
    public function testMailerRegistrarReturnsPostalBindings(): void
    {
        $registered = Registrar::Mailer();

        $this->assertSame(CourierSuppressionList::class, $registered['suppressionList']);
        $this->assertSame(CourierUnsubscribeUrl::class, $registered['unsubscribeUrl']);
    }

    public function testPostalMailerConfigReceivesCourierBindings(): void
    {
        // The registrar method has to be named for the config's short name —
        // CI4 matches it by reflection. This is the assertion that fails if the
        // two ever drift apart again, and the binding would otherwise switch
        // off silently rather than error.
        $config = config('Mailer');

        $this->assertSame(CourierSuppressionList::class, $config->suppressionList);
        $this->assertSame(CourierUnsubscribeUrl::class, $config->unsubscribeUrl);
    }
}
