<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Fake;

use App\Ingestion\Domain\Exception\SourceNotIngestible;
use App\Ingestion\Domain\Model\SourceToIngest;
use App\Ingestion\Domain\Port\SourceCatalog;

final class InMemorySourceCatalog implements SourceCatalog
{
    /** @var array<string, SourceToIngest> */
    private array $sources = [];

    /** @var array<string, \DateTimeImmutable> */
    public array $ingestedAt = [];

    public function add(SourceToIngest $source): void
    {
        $this->sources[$source->sourceId] = $source;
    }

    public function dueForIngestion(\DateTimeImmutable $now): array
    {
        return array_keys(array_diff_key($this->sources, $this->ingestedAt));
    }

    public function get(string $sourceId): SourceToIngest
    {
        return $this->sources[$sourceId] ?? throw SourceNotIngestible::withId($sourceId);
    }

    public function markIngested(string $sourceId, \DateTimeImmutable $at): void
    {
        $this->ingestedAt[$sourceId] = $at;
    }
}
