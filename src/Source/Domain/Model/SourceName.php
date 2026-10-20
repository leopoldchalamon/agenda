<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

use App\Source\Domain\Exception\InvalidSourceName;

final readonly class SourceName
{
    public const int MAX_LENGTH = 255;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidSourceName::empty();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidSourceName::tooLong(self::MAX_LENGTH);
        }

        $this->value = $value;
    }
}
