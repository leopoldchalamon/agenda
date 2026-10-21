<?php

declare(strict_types=1);

namespace App\Tests\Source\Functional;

use App\Source\Infrastructure\Persistence\Entity\SourceRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SourceAdminTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testListsSources(): void
    {
        $this->insertSource('Mairie', enabled: true);

        $this->client->request('GET', '/admin/source');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Mairie');
    }

    public function testCreatesSource(): void
    {
        $this->submitNewForm('Mairie', 'ical', 'https://example.org/agenda.ics', '720');

        self::assertResponseRedirects();
        $record = $this->findByName('Mairie');
        self::assertNotNull($record);
        self::assertSame('ical', $record->type);
        self::assertSame(720, $record->fetchIntervalMinutes);
        self::assertTrue($record->enabled);
    }

    public function testRejectsIntervalBelow12Hours(): void
    {
        $this->submitNewForm('Mairie', 'ical', 'https://example.org/agenda.ics', '60');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name=SourceRecord]', 'at least 720 minutes');
        self::assertInputValueSame('SourceRecord[name]', 'Mairie');
        self::assertNull($this->findByName('Mairie'));
    }

    public function testRejectsDuplicateUrlAndType(): void
    {
        $this->insertSource('Mairie', enabled: true);

        $this->client->followRedirects();
        $this->submitNewForm('Duplicate', 'ical', 'https://example.org/agenda.ics', '1440');

        self::assertSelectorTextContains('.alert-danger', 'already exists');
        self::assertNull($this->findByName('Duplicate'));
    }

    public function testUpdatesSource(): void
    {
        $id = $this->insertSource('Mairie', enabled: true);

        $crawler = $this->client->request('GET', '/admin/source/'.$id.'/edit');
        $form = $crawler->filter('form[name=SourceRecord]')->form([
            'SourceRecord[name]' => 'Town hall',
            'SourceRecord[type]' => 'rss',
            'SourceRecord[url]' => 'https://example.org/feed',
            'SourceRecord[fetchIntervalMinutes]' => '2880',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $record = $this->reload($id);
        self::assertSame('Town hall', $record->name);
        self::assertSame('rss', $record->type);
        self::assertSame('https://example.org/feed', $record->url);
        self::assertSame(2880, $record->fetchIntervalMinutes);
    }

    public function testDoesNotStoreInvalidUpdate(): void
    {
        $id = $this->insertSource('Mairie', enabled: true);

        $crawler = $this->client->request('GET', '/admin/source/'.$id.'/edit');
        $form = $crawler->filter('form[name=SourceRecord]')->form(['SourceRecord[fetchIntervalMinutes]' => '60']);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1440, $this->reload($id)->fetchIntervalMinutes);
    }

    public function testDisablesAndEnablesSource(): void
    {
        $id = $this->insertSource('Mairie', enabled: true);

        $this->client->request('POST', $this->toggleUrl('disable'));
        self::assertResponseRedirects('/admin/source');
        self::assertFalse($this->reload($id)->enabled);

        $this->client->request('POST', $this->toggleUrl('enable'));
        self::assertResponseRedirects('/admin/source');
        self::assertTrue($this->reload($id)->enabled);
    }

    public function testRejectsToggleWithoutCsrfToken(): void
    {
        $id = $this->insertSource('Mairie', enabled: true);

        $this->client->request('POST', '/admin/source/'.$id.'/disable');

        self::assertResponseRedirects('/admin/source');
        self::assertTrue($this->reload($id)->enabled);
    }

    public function testRejectsToggleWithInvalidCsrfToken(): void
    {
        $id = $this->insertSource('Mairie', enabled: true);

        $this->client->request('POST', '/admin/source/'.$id.'/disable?token=invalid');

        self::assertResponseRedirects('/admin/source');
        self::assertTrue($this->reload($id)->enabled);
    }

    private function toggleUrl(string $action): string
    {
        $crawler = $this->client->request('GET', '/admin/source');
        $url = $crawler->filter('form[action*="/'.$action.'?"]')->attr('action');
        self::assertNotNull($url);

        return $url;
    }

    private function submitNewForm(string $name, string $type, string $url, string $interval): void
    {
        $crawler = $this->client->request('GET', '/admin/source/new');
        $form = $crawler->filter('form[name=SourceRecord]')->form([
            'SourceRecord[name]' => $name,
            'SourceRecord[type]' => $type,
            'SourceRecord[url]' => $url,
            'SourceRecord[fetchIntervalMinutes]' => $interval,
        ]);
        $this->client->submit($form);
    }

    private function insertSource(string $name, bool $enabled): string
    {
        $id = '0199f1c2-7a3b-7c4d-8e5f-0a1b2c3d4e5f';
        $this->entityManager->persist(new SourceRecord($id, $name, 'ical', 'https://example.org/agenda.ics', 1440, $enabled));
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $id;
    }

    private function findByName(string $name): ?SourceRecord
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(SourceRecord::class)->findOneBy(['name' => $name]);
    }

    private function reload(string $id): SourceRecord
    {
        $this->entityManager->clear();
        $record = $this->entityManager->find(SourceRecord::class, $id);
        self::assertNotNull($record);

        return $record;
    }
}
