<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

final class InvalidSourceUrl extends \InvalidArgumentException
{
    public static function notHttp(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid http(s) URL.', $value));
    }
}
