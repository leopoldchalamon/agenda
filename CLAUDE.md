# CLAUDE.md

@AGENTS.md

## Project

Agenda partagé. Les événements sont agrégés depuis des sources externes et exposés via une API.

- **Back-office** : les administrateurs gèrent les sources (ajout, modification, activation ou désactivation).
- **Ingestion** : une tâche planifiée récupère les nouveaux événements de chaque source et les enregistre en base.
- **API** : expose les événements aux clients.
- **Comptes utilisateurs** : les utilisateurs ont des données personnelles, comme leurs événements favoris.

## Stack

Installé (mesuré dans le conteneur `php`) :

| Outil | Version |
|-------|---------|
| PHP | 8.5.11 (ZTS) |
| Symfony | 8.1 |
| Composer | 2.10 |
| FrankenPHP | 1.13 (Caddy 2.11, mode worker) |
| Image Docker de base | `dunglas/frankenphp:1-php8.5` (template symfony-docker) |
| Symfony AI Mate | 0.14 (dev) |

Prévu, pas encore installé. Installer chaque paquet avec Composer dans le conteneur au démarrage de la fonctionnalité concernée :

| Besoin | Paquet |
|--------|--------|
| Persistance | Doctrine ORM + PostgreSQL (`symfony/orm-pack`) |
| Identifiants | `symfony/uid` |
| API | API Platform (`api-platform/symfony`) |
| Back-office | EasyAdmin (`easycorp/easyadmin-bundle`) |
| Ingestion planifiée | Symfony Scheduler + Messenger |
| Comptes et authentification | SecurityBundle + JWT (`lexik/jwt-authentication-bundle`) |
| Tests | PHPUnit (`symfony/test-pack`) |
| Analyse statique | PHPStan + `phpstan/phpstan-symfony` + `phpstan/phpstan-doctrine` |
| Style de code | PHP-CS-Fixer (`friendsofphp/php-cs-fixer`) |
| Règles de couches | Deptrac (`deptrac/deptrac`) |

Ne jamais ajouter une dépendance Composer absente de ces tableaux sans demander d'abord.

## Environnement

- Tout l'outillage PHP tourne dans le service Docker Compose `php`. Utiliser `docker compose exec -T php <cmd>`. PHP et Composer ne sont pas installés sur la machine hôte.
- L'application est servie sur `https://localhost`.
- FrankenPHP tourne en mode worker : le kernel reste en mémoire entre les requêtes. Les services doivent être sans état d'une requête à l'autre (pas de cache statique, pas de données de requête gardées dans des propriétés). Implémenter `ResetInterface` quand un service doit garder un état.
- Utiliser Symfony AI Mate (`docker compose exec -T php vendor/bin/mate`) pour inspecter le conteneur, le profiler et les logs plutôt que de deviner.

## Commandes

```
docker compose exec -T php vendor/bin/phpunit
docker compose exec -T php vendor/bin/phpstan analyse
docker compose exec -T php vendor/bin/php-cs-fixer fix
docker compose exec -T php vendor/bin/deptrac
docker compose exec -T php bin/console doctrine:migrations:diff
docker compose exec -T php bin/console doctrine:schema:validate
```

Une tâche n'est terminée que lorsque PHPUnit, PHPStan, PHP-CS-Fixer et Deptrac passent tous.

## Architecture

Hexagonale. Un répertoire par bounded context :

| Contexte | Responsabilité |
|----------|----------------|
| `Source` | Sources externes et leur configuration (back-office) |
| `Ingestion` | Récupération des événements depuis les sources, dédoublonnage, planification |
| `Event` | Catalogue d'événements exposé par l'API |
| `Account` | Utilisateurs, authentification, favoris |
| `Shared` | Noyau partagé : Value Objects communs (identifiants, horloge). Le garder petit |

```
src/{Context}/
├── Domain/
│   ├── Model/        # Agrégats, entités, Value Objects. PHP pur, sans framework
│   ├── Port/         # Interfaces : repositories, services externes
│   ├── Event/        # Événements domaine
│   └── Exception/
├── Application/
│   ├── Command/      # Use cases d'écriture : CreateSource + CreateSourceHandler
│   └── Query/        # Use cases de lecture hors API : GetEvent + GetEventHandler
└── Infrastructure/
    ├── Persistence/
    │   ├── Entity/   # Records Doctrine (attributs #[ORM\...]), données uniquement
    │   └── ...       # Repositories : implémentent les ports, convertissent domaine <-> records
    ├── Api/          # Ressources, providers, processors et extensions API Platform
    ├── Admin/        # Contrôleurs EasyAdmin
    ├── Messenger/    # Scheduler et message handlers
    └── Fetcher/      # Un adaptateur par type de source (iCal, API JSON, ...)
```

Règles :
- `Domain` n'importe rien en dehors de son propre contexte et de `Shared/Domain`. Ni Symfony, ni Doctrine, ni API Platform.
- `Application` ne dépend que de `Domain` (les ports), jamais d'une classe concrète d'Infrastructure.
- Les adaptateurs d'Infrastructure (API, admin, scheduler) appellent un handler d'Application pour écrire. Ils n'appellent jamais un repository directement pour écrire.
- Un contexte n'importe jamais les classes Domain d'un autre contexte. Référencer par identifiant (`SourceId`, `EventId`) ou passer par un port.
- Chaque use case : un objet commande ou requête (readonly) et un handler avec une seule méthode `__invoke()`. Nommage verbe + nom : `CreateSource`, `DisableSource`, `IngestSource`, `AddFavorite`.

## Doctrine

- Les modèles domaine ne sont pas des entités Doctrine. Aucun attribut `#[ORM\...]` dans `Domain`.
- Les entités Doctrine sont des records de persistance dans `Infrastructure/Persistence/Entity/` (par exemple `EventRecord`), mappés avec des attributs. Ils ne portent que des données, aucune règle métier.
- Les repositories implémentent le port du Domain, vivent dans `Infrastructure/Persistence/`, et convertissent entre modèles domaine et records. Chaque repository a un test d'aller-retour (enregistrer, recharger, comparer).
- Les repositories sont le seul endroit qui utilise `EntityManagerInterface`. Exception : le provider Doctrine d'API Platform, en lecture seule (voir API Platform).
- Les identifiants sont des UUIDv7 générés par le domaine et encapsulés dans des Value Objects. Jamais d'auto-incrément.
- Les écritures en masse de l'ingestion peuvent passer par DBAL (`INSERT ... ON CONFLICT`).
- Tout changement de schéma passe par une migration générée, relue avant commit. Ne jamais modifier une migration déjà commitée.

## API Platform

- `#[ApiResource]` se place sur des DTO dans `Infrastructure/Api/`, jamais sur des modèles domaine ni sur des records.
- Lectures : le DTO est relié à un record Doctrine par `stateOptions` (`entityClass`). Le provider Doctrine d'API Platform gère la pagination et les filtres. Ces lectures ne passent pas par le domaine.
- Écritures : un state processor transforme le DTO en commande et appelle le command handler.
- Vérifier la syntaxe de `stateOptions` et du mapping record -> DTO dans la documentation de la version installée.

## Sécurité

- Chaque opération API Platform non publique a une règle `security`.
- Les données personnelles d'un utilisateur (favoris, profil) ne sont jamais accessibles à un autre utilisateur : filtrage par une extension de requête Doctrine en lecture, voter en écriture.
- Les fetchers appellent les sources avec un timeout explicite et valident le contenu reçu.
- Aucun secret en dur dans le code ou dans un fichier versionné.

## Back-office (EasyAdmin)

- EasyAdmin est un adaptateur d'Infrastructure du contexte `Source`.
- Les actions de création, de modification et d'activation appellent le command handler correspondant (surcharger `persistEntity` / `updateEntity`). Aucune règle métier dans un contrôleur CRUD.

## Ingestion

- Un port `SourceFetcher` ; un adaptateur par type de source dans `Infrastructure/Fetcher/`.
- L'ingestion est idempotente : la lancer deux fois sur les mêmes données ne crée aucun doublon (dédoublonnage sur identifiant de source + identifiant externe, garanti par une contrainte d'unicité).
- Une source en échec ne doit pas bloquer les autres. Logger l'échec avec l'identifiant de la source et continuer.
- Planifiée avec Symfony Scheduler, qui envoie un message Messenger par source active.

## Conventions du domaine

- Value Objects pour les identifiants, l'email, les dates porteuses de sens (`EventPeriod`), les URL. Pas pour un string ou un int sans règle. Les Value Objects sont `final readonly` et se valident dans leur constructeur.
- Les événements domaine sont nommés au passé (`SourceDisabled`, `EventIngested`) et ne portent que des données immuables.
- Les erreurs métier lèvent une exception nommée de `Domain/Exception/`, jamais une `\Exception` générique.
- Utiliser une horloge injectée (`Psr\Clock\ClockInterface`), jamais `new \DateTimeImmutable()` dans Domain ou Application.

## Tests

```
tests/{Context}/
├── Unit/           # Domain + Application. PHPUnit pur, sans kernel ni base de données
├── Integration/    # Repositories Doctrine (aller-retour), fetchers (KernelTestCase)
└── Functional/     # Endpoints API et admin (WebTestCase / ApiTestCase)
```

- Utiliser des fakes en mémoire (`InMemorySourceRepository`) pour les ports, pas des mocks. Les fakes vivent dans `tests/{Context}/Fake/`.
- Nommage des tests : `test{Action}{Scenario}`, par exemple `testThrowsWhenSourceIsDisabled`.
- Les tests de fetchers utilisent des fichiers de fixtures enregistrés, jamais un vrai appel réseau.
- Pour corriger un bug, écrire d'abord le test qui échoue.

## Style de code

- `declare(strict_types=1);` dans chaque fichier PHP.
- Classes `final` par défaut. `readonly` pour les Value Objects, commandes, requêtes et DTO.
- Code (classes, méthodes, variables) et commentaires en anglais.
- Utiliser les fonctionnalités de PHP 8.5 et Symfony 8.1. Vérifier la version réellement installée avant d'utiliser une API récente.

## Façon de travailler

- Pour une tâche qui touche plus de quelques fichiers, proposer un plan avant d'écrire du code.
- Ne jamais modifier `.env`, `compose*.yaml`, `Dockerfile` ou une migration déjà commitée sans demander.
- Les règles de ce fichier priment sur les conseils génériques des skills et plugins installés.
