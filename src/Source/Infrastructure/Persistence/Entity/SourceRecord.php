<?php

declare(strict_types=1);

namespace App\Source\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'source')]
#[ORM\UniqueConstraint(name: 'source_url_type_unique', columns: ['url', 'type'])]
class SourceRecord
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        public string $id,
        #[ORM\Column(length: 255)]
        public string $name,
        #[ORM\Column(length: 32)]
        public string $type,
        #[ORM\Column(length: 2048)]
        public string $url,
        #[ORM\Column]
        public int $fetchIntervalMinutes,
        #[ORM\Column]
        public bool $enabled,
    ) {
    }
}
