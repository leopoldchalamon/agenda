<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Exception;

final class UnsupportedSourceType extends \DomainException
{
    public static function named(string $type): self
    {
        return new self(\sprintf('No fetcher supports source type "%s".', $type));
    }
}
