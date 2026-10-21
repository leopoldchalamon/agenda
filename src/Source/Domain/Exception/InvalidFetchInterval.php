<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

final class InvalidFetchInterval extends \InvalidArgumentException
{
    public static function belowMinimum(int $minutes, int $minimum): self
    {
        return new self(\sprintf('Fetch interval must be at least %d minutes, %d given.', $minimum, $minutes));
    }
}
