<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Model\SourceId;

final readonly class DisableSource
{
    public function __construct(public SourceId $id)
    {
    }
}
