<?php

declare(strict_types=1);

namespace App\Source\Infrastructure\Persistence;

use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\Source;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Source\Domain\Port\SourceRepository;
use App\Source\Infrastructure\Persistence\Entity\SourceRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineSourceRepository implements SourceRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): SourceId
    {
        return new SourceId(Uuid::v7()->toRfc4122());
    }

    public function save(Source $source): void
    {
        $record = $this->entityManager->find(SourceRecord::class, $source->id()->value);

        if (null === $record) {
            $record = new SourceRecord(
                $source->id()->value,
                $source->name()->value,
                $source->type()->value,
                $source->url()->value,
                $source->fetchInterval()->minutes,
                $source->isEnabled(),
            );
            $this->entityManager->persist($record);
        } else {
            $record->name = $source->name()->value;
            $record->type = $source->type()->value;
            $record->url = $source->url()->value;
            $record->fetchIntervalMinutes = $source->fetchInterval()->minutes;
            $record->enabled = $source->isEnabled();
        }

        $this->entityManager->flush();
    }

    public function get(SourceId $id): Source
    {
        $record = $this->entityManager->find(SourceRecord::class, $id->value) ?? throw SourceNotFound::withId($id);

        return Source::reconstitute(
            new SourceId($record->id),
            new SourceName($record->name),
            SourceType::from($record->type),
            new SourceUrl($record->url),
            new FetchInterval($record->fetchIntervalMinutes),
            $record->enabled,
        );
    }

    public function existsWithUrlAndType(SourceUrl $url, SourceType $type, ?SourceId $excluding = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(SourceRecord::class, 's')
            ->where('s.url = :url')
            ->andWhere('s.type = :type')
            ->setParameter('url', $url->value)
            ->setParameter('type', $type->value);

        if (null !== $excluding) {
            $query->andWhere('s.id != :id')->setParameter('id', $excluding->value);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }
}
