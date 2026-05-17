<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Courier.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
