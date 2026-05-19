<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Courier\Exceptions\PackageException;

/**
 * @internal
 */
final class ExampleTest extends CIUnitTestCase
{
    public function testPackageException(): void
    {
        $e = PackageException::forExample('something went wrong');
        $this->assertSame('something went wrong', $e->getMessage());
    }
}
