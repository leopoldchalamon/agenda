<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;

final readonly class UpdateSource
{
    public function __construct(
        public SourceId $id,
        public SourceName $name,
        public SourceType $type,
        public SourceUrl $url,
        public FetchInterval $fetchInterval,
    ) {
    }
}
