<?php

declare(strict_types=1);

namespace App\Tests\Source\Fake;

use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\Source;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Source\Domain\Port\SourceRepository;
use Symfony\Component\Uid\Uuid;

final class InMemorySourceRepository implements SourceRepository
{
    /** @var array<string, Source> */
    private array $sources = [];

    public function nextIdentity(): SourceId
    {
        return new SourceId(Uuid::v7()->toRfc4122());
    }

    public function save(Source $source): void
    {
        $this->sources[$source->id()->value] = $source;
    }

    public function get(SourceId $id): Source
    {
        return $this->sources[$id->value] ?? throw SourceNotFound::withId($id);
    }

    public function existsWithUrlAndType(SourceUrl $url, SourceType $type, ?SourceId $excluding = null): bool
    {
        foreach ($this->sources as $source) {
            if ($source->url()->equals($url) && $source->type() === $type && !$source->id()->equals($excluding)) {
                return true;
            }
        }

        return false;
    }
}
