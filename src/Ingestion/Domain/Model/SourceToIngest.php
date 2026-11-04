<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Model;

final readonly class SourceToIngest
{
    public function __construct(
        public string $sourceId,
        public string $type,
        public string $url,
    ) {
    }
}
