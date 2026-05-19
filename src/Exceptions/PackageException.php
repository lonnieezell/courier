<?php

declare(strict_types=1);

namespace Myth\Courier\Exceptions;

use RuntimeException;

class PackageException extends RuntimeException
{
    public static function forExample(string $message): self
    {
        return new self($message);
    }
}
