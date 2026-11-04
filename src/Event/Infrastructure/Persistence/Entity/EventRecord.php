<?php

declare(strict_types=1);

namespace App\Event\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'event_source_external_unique', columns: ['source_id', 'external_id'])]
class EventRecord
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        public string $id,
        #[ORM\Column(type: Types::GUID)]
        public string $sourceId,
        #[ORM\Column(length: 255)]
        public string $externalId,
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        public \DateTimeImmutable $startsAt,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?\DateTimeImmutable $endsAt,
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $location,
        #[ORM\Column(length: 2048, nullable: true)]
        public ?string $url,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        public ?string $description,
    ) {
    }
}
