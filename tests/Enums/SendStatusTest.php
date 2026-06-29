<?php

declare(strict_types=1);

namespace Tests\Enums;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Enums\SendStatus;

/**
 * @internal
 */
final class SendStatusTest extends CIUnitTestCase
{
    public function testSuppressedCaseExists(): void
    {
        $this->assertSame('suppressed', SendStatus::Suppressed->value);
    }

    public function testSuppressedResolvesFromValue(): void
    {
        $this->assertSame(SendStatus::Suppressed, SendStatus::from('suppressed'));
    }
}
