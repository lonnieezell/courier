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

namespace Myth\Courier\Exceptions;

use RuntimeException;

class PackageException extends RuntimeException
{
    public static function forExample(string $message): self
    {
        return new self($message);
    }
}
