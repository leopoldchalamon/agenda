<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Exception\FetchFailed;
use App\Ingestion\Domain\Exception\InvalidFetchedContent;
use App\Ingestion\Domain\Model\FetchedEvent;

interface SourceFetcher
{
    public function supports(string $sourceType): bool;

    /**
     * @return list<FetchedEvent>
     *
     * @throws FetchFailed
     * @throws InvalidFetchedContent
     */
    public function fetch(string $url): array;
}
