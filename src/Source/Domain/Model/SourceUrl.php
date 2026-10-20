<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

use App\Source\Domain\Exception\InvalidSourceUrl;

final readonly class SourceUrl
{
    public function __construct(public string $value)
    {
        $scheme = parse_url($value, \PHP_URL_SCHEME);

        if (false === filter_var($value, \FILTER_VALIDATE_URL) || !\in_array($scheme, ['http', 'https'], true)) {
            throw InvalidSourceUrl::notHttp($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
