<?php

declare(strict_types=1);

namespace App\Tests\Source\Integration;

use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\Source;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Source\Domain\Port\SourceRepository;
use App\Source\Infrastructure\Persistence\DoctrineSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineSourceRepositoryTest extends KernelTestCase
{
    private SourceRepository $sources;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->sources = new DoctrineSourceRepository($this->entityManager);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavesAndReloadsSource(): void
    {
        $source = $this->newSource(SourceType::Ical, 'https://example.org/agenda.ics');
        $source->disable();

        $this->sources->save($source);
        $this->entityManager->clear();

        $reloaded = $this->sources->get($source->id());
        self::assertEquals($source->id(), $reloaded->id());
        self::assertEquals($source->name(), $reloaded->name());
        self::assertSame($source->type(), $reloaded->type());
        self::assertEquals($source->url(), $reloaded->url());
        self::assertEquals($source->fetchInterval(), $reloaded->fetchInterval());
        self::assertFalse($reloaded->isEnabled());
        self::assertSame([], $reloaded->releaseEvents());
    }

    public function testSavesChangesToExistingSource(): void
    {
        $source = $this->newSource(SourceType::Ical, 'https://example.org/agenda.ics');
        $this->sources->save($source);

        $source->update(new SourceName('Town hall'), SourceType::Rss, new SourceUrl('https://example.org/feed'), new FetchInterval(720));
        $this->sources->save($source);
        $this->entityManager->clear();

        $reloaded = $this->sources->get($source->id());
        self::assertSame('Town hall', $reloaded->name()->value);
        self::assertSame(SourceType::Rss, $reloaded->type());
        self::assertSame('https://example.org/feed', $reloaded->url()->value);
        self::assertSame(720, $reloaded->fetchInterval()->minutes);
    }

    public function testThrowsWhenSourceDoesNotExist(): void
    {
        $this->expectException(SourceNotFound::class);

        $this->sources->get($this->sources->nextIdentity());
    }

    public function testFindsExistingUrlAndType(): void
    {
        $source = $this->newSource(SourceType::Ical, 'https://example.org/agenda.ics');
        $this->sources->save($source);
        $url = new SourceUrl('https://example.org/agenda.ics');

        self::assertTrue($this->sources->existsWithUrlAndType($url, SourceType::Ical));
        self::assertFalse($this->sources->existsWithUrlAndType($url, SourceType::Rss));
        self::assertFalse($this->sources->existsWithUrlAndType($url, SourceType::Ical, $source->id()));
    }

    private function newSource(SourceType $type, string $url): Source
    {
        return Source::create($this->sources->nextIdentity(), new SourceName('Mairie'), $type, new SourceUrl($url), FetchInterval::default());
    }
}
