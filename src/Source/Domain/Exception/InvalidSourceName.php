<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

final class InvalidSourceName extends \InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Source name cannot be empty.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('Source name cannot exceed %d characters.', $maxLength));
    }
}
