<?php

declare(strict_types=1);

namespace App\Source\Domain\Event;

use App\Source\Domain\Model\SourceId;

final readonly class SourceUpdated
{
    public function __construct(public SourceId $sourceId)
    {
    }
}
