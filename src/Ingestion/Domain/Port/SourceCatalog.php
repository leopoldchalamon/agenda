<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Exception\SourceNotIngestible;
use App\Ingestion\Domain\Model\SourceToIngest;

interface SourceCatalog
{
    /**
     * Enabled sources never ingested, or whose fetch interval has elapsed since the last ingestion.
     *
     * @return list<string>
     */
    public function dueForIngestion(\DateTimeImmutable $now): array;

    /**
     * @throws SourceNotIngestible
     */
    public function get(string $sourceId): SourceToIngest;

    public function markIngested(string $sourceId, \DateTimeImmutable $at): void;
}
