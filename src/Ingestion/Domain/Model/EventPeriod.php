<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Model;

use App\Ingestion\Domain\Exception\InvalidFetchedContent;

final readonly class EventPeriod
{
    public function __construct(
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt = null,
    ) {
        if (null !== $endsAt && $endsAt < $startsAt) {
            throw InvalidFetchedContent::because('Event ends before it starts.');
        }
    }
}
