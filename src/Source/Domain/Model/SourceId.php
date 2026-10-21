<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

use App\Source\Domain\Exception\InvalidSourceId;

final readonly class SourceId
{
    public function __construct(public string $value)
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
            throw InvalidSourceId::notAUuid($value);
        }
    }

    public function equals(?self $other): bool
    {
        return $this->value === $other?->value;
    }
}
