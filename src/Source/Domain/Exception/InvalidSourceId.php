<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

final class InvalidSourceId extends \InvalidArgumentException
{
    public static function notAUuid(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid source id.', $value));
    }
}
