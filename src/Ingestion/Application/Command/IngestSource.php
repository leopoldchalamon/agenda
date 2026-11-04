<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Command;

final readonly class IngestSource
{
    public function __construct(public string $sourceId)
    {
    }
}
