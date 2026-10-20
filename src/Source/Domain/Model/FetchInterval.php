<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

use App\Source\Domain\Exception\InvalidFetchInterval;

final readonly class FetchInterval
{
    public const int MIN_MINUTES = 720;
    public const int DEFAULT_MINUTES = 1440;

    public function __construct(public int $minutes)
    {
        if ($minutes < self::MIN_MINUTES) {
            throw InvalidFetchInterval::belowMinimum($minutes, self::MIN_MINUTES);
        }
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_MINUTES);
    }
}
