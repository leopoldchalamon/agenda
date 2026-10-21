<?php

declare(strict_types=1);

namespace App\Source\Infrastructure\Admin;

use App\Source\Application\Command\CreateSource;
use App\Source\Application\Command\CreateSourceHandler;
use App\Source\Application\Command\DisableSource;
use App\Source\Application\Command\DisableSourceHandler;
use App\Source\Application\Command\EnableSource;
use App\Source\Application\Command\EnableSourceHandler;
use App\Source\Application\Command\UpdateSource;
use App\Source\Application\Command\UpdateSourceHandler;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Source\Infrastructure\Persistence\Entity\SourceRecord;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractCrudController<SourceRecord>
 */
final class SourceCrudController extends AbstractCrudController
{
    private const string TOGGLE_TOKEN_ID = 'source-toggle-';

    public function __construct(
        private readonly CreateSourceHandler $createSource,
        private readonly UpdateSourceHandler $updateSource,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SourceRecord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Source')
            ->setEntityLabelInPlural('Sources')
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions(['constraints' => [new Callback($this->validateRecord(...))]]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom')->setFormTypeOption('empty_data', '');
        yield ChoiceField::new('type', 'Type')->setChoices([
            'iCal' => SourceType::Ical->value,
            "Point d'API" => SourceType::JsonApi->value,
            'Flux RSS' => SourceType::Rss->value,
            'Site web' => SourceType::Website->value,
        ]);
        yield UrlField::new('url', 'URL')->setFormTypeOption('empty_data', '');
        yield IntegerField::new('fetchIntervalMinutes', 'Intervalle (minutes)')
            ->setHelp(\sprintf('Minimum %d minutes (12 h).', FetchInterval::MIN_MINUTES))
            ->setFormTypeOption('empty_data', (string) FetchInterval::DEFAULT_MINUTES);
        yield BooleanField::new('enabled', 'Active')->renderAsSwitch(false)->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $enable = Action::new('enable', 'Activer')
            ->linkToRoute('admin_source_enable', $this->toggleRouteParameters(...))
            ->renderAsForm()
            ->displayIf(static fn (SourceRecord $source): bool => !$source->enabled);
        $disable = Action::new('disable', 'Désactiver')
            ->linkToRoute('admin_source_disable', $this->toggleRouteParameters(...))
            ->renderAsForm()
            ->displayIf(static fn (SourceRecord $source): bool => $source->enabled);

        return $actions
            ->disable(Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $enable)
            ->add(Crud::PAGE_INDEX, $disable);
    }

    public function createEntity(string $entityFqcn): SourceRecord
    {
        return new SourceRecord('', '', SourceType::Ical->value, '', FetchInterval::DEFAULT_MINUTES, true);
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->handle(fn () => ($this->createSource)(new CreateSource(...$this->toValueObjects($entityInstance))));
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->handle(fn () => ($this->updateSource)(new UpdateSource(
            new SourceId($entityInstance->id),
            ...$this->toValueObjects($entityInstance),
        )));
    }

    #[AdminRoute('/{id}/enable', 'enable', options: ['methods' => ['POST']])]
    public function enable(string $id, Request $request, EnableSourceHandler $enableSource): Response
    {
        if ($this->isToggleTokenValid($id, $request)) {
            $this->handle(fn () => $enableSource(new EnableSource(new SourceId($id))));
        }

        return $this->redirectToRoute('admin_source_index');
    }

    #[AdminRoute('/{id}/disable', 'disable', options: ['methods' => ['POST']])]
    public function disable(string $id, Request $request, DisableSourceHandler $disableSource): Response
    {
        if ($this->isToggleTokenValid($id, $request)) {
            $this->handle(fn () => $disableSource(new DisableSource(new SourceId($id))));
        }

        return $this->redirectToRoute('admin_source_index');
    }

    /**
     * @return array{name: SourceName, type: SourceType, url: SourceUrl, fetchInterval: FetchInterval}
     */
    private function toValueObjects(SourceRecord $record): array
    {
        return [
            'name' => new SourceName($record->name),
            'type' => SourceType::from($record->type),
            'url' => new SourceUrl($record->url),
            'fetchInterval' => new FetchInterval($record->fetchIntervalMinutes),
        ];
    }

    private function validateRecord(SourceRecord $record, ExecutionContextInterface $context): void
    {
        $checks = [
            'name' => static fn () => new SourceName($record->name),
            'url' => static fn () => new SourceUrl($record->url),
            'fetchIntervalMinutes' => static fn () => new FetchInterval($record->fetchIntervalMinutes),
        ];

        foreach ($checks as $field => $check) {
            try {
                $check();
            } catch (\InvalidArgumentException $exception) {
                $context->buildViolation($exception->getMessage())->atPath($field)->addViolation();
            }
        }
    }

    /**
     * @return array{id: string, token: string}
     */
    private function toggleRouteParameters(SourceRecord $record): array
    {
        return ['id' => $record->id, 'token' => $this->csrfTokenManager->getToken(self::TOGGLE_TOKEN_ID.$record->id)->getValue()];
    }

    private function isToggleTokenValid(string $id, Request $request): bool
    {
        if ($this->isCsrfTokenValid(self::TOGGLE_TOKEN_ID.$id, $request->query->getString('token'))) {
            return true;
        }

        $this->addFlash('danger', 'Invalid CSRF token.');

        return false;
    }

    private function handle(callable $command): void
    {
        try {
            $command();
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
    }
}
