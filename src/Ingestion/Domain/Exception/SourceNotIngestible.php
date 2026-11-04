<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Exception;

final class SourceNotIngestible extends \DomainException
{
    public static function withId(string $sourceId): self
    {
        return new self(\sprintf('Source %s does not exist or is disabled.', $sourceId));
    }
}
