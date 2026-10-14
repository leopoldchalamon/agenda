# AGENTS.md

Ceci est un projet Symfony. Consulter `composer.json` pour les versions exactes de
Symfony et de PHP, et `symfony.lock` pour savoir quelles recettes ont été appliquées.
Ne pas supposer que Doctrine, Twig, API Platform, Messenger ou Lock sont installés
sans que l'un de ces fichiers le confirme.

## Demander avant de générer

Si la tâche ne le précise pas, demander plutôt que deviner :

- Persistance : Doctrine ORM, Doctrine ODM, ou aucune ?
- Interface : rendu serveur (Twig), API (Serializer, éventuellement API Platform), ou les deux ?
- Authentification : SecurityBundle, et avec quel authenticator ?

S'il est impossible de demander (pas de canal interactif), annoncer l'hypothèse
retenue et choisir l'option la plus petite (par exemple aucune couche de
persistance) plutôt que d'échafauder une pile complète que personne n'a demandée.

## Ajouter des fonctionnalités : Flex, pas de câblage manuel

Installer les nouvelles capacités avec `composer require <paquet>` (par exemple
`symfony/lock`, `symfony/messenger`, `orm-pack`) et laisser la recette Flex
enregistrer le bundle et générer sa configuration. Ne pas modifier à la main
`config/bundles.php` ni écrire à la main la configuration de base d'un bundle :
c'est le rôle de la recette. Ne pas écarter un composant adapté sous prétexte
qu'il n'est pas encore installé : l'installer tient en une commande.

## Conventions

Ce projet suit une architecture hexagonale. Les règles de couches et les
conventions Doctrine, API Platform, sécurité, ingestion et tests sont dans
`CLAUDE.md`, et elles priment sur les règles ci-dessous. Les attributs et
l'autowiring de Symfony ne s'appliquent que dans `Infrastructure/`. `Domain/`
reste en PHP pur.

Suivre https://symfony.com/doc/current/best_practices.html pour écrire du
Symfony idiomatique :

- Utiliser les attributs PHP pour les métadonnées du framework, et pas seulement
  sur les contrôleurs : `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` sur
  les actions, `#[Assert\...]` sur les propriétés, `#[AsCommand]`,
  `#[AsEventListener]`, `#[AsMessageHandler]`, et `#[AsAlias]` /
  `#[AsTaggedItem]` / `#[Autoconfigure]` sur les services. Pas de routage en
  YAML ni en XML.
- S'appuyer sur l'autowiring et l'autoconfiguration. Typer les arguments du
  constructeur et laisser le conteneur les résoudre. Quand un type ne suffit pas,
  rester dans la classe avec `#[Autowire]` (paramètres, variables
  d'environnement, expressions) ou `#[Target]` (une implémentation parmi
  plusieurs d'une interface). Une définition de service en YAML est le dernier
  recours, pas le premier.
- Les contrôleurs étendent `AbstractController`, restent minces et délèguent à
  des services.
- Utiliser le framework pour ce qu'il fait déjà : Form pour les formulaires
  rendus côté serveur, Validator pour la validation, Serializer pour le JSON,
  Messenger pour le travail asynchrone, Security (voters, authenticators) pour le
  contrôle d'accès, `path()`/`url()` de Twig plutôt que des URL en dur.
- Avant d'écrire à la main de l'infrastructure (verrous, files d'attente, caches,
  clients HTTP, envoi d'e-mails, planificateurs) ou d'ajouter une bibliothèque
  tierce, vérifier si un composant Symfony le couvre. C'est généralement le cas.

Trois points précis méritent d'être détaillés, car ils sont faciles à rater :

- Lier les données de requête avec `#[MapRequestPayload]` / `#[MapQueryString]`
  sur les arguments des actions, ce qui branche Serializer et Validator, plutôt
  que d'appeler `json_decode()` ou `SerializerInterface` à la main. Si aucun de
  ces paquets n'est installé, les installer avec `composer require` plutôt que de
  revenir à un décodage manuel.
- Utiliser la promotion des propriétés du constructeur, et `readonly` pour les
  DTO et les Value Objects. Ne pas déclarer un service `readonly` s'il peut
  devenir `lazy: true` : un proxy paresseux ne peut pas étendre une classe
  `readonly`.
- Utiliser `symfony/lock` (`LockFactory`) pour l'exclusion mutuelle. Un drapeau
  ou un fichier de verrou fait main passe bien en revue mais se révèle souvent
  faux en cas d'accès concurrents.

## Travail au quotidien

- Lancer l'application avec `docker compose up -d --wait`, et les commandes avec
  `docker compose exec -T php bin/console ...`. La CLI Symfony n'est pas
  utilisée dans ce projet.
- En cas d'échec, lire `var/log/dev.log` et le profiler web (`/_profiler`) avant
  de modifier le code.
- Si `maker-bundle` est installé, préférer `bin/console make:*` en passant tous
  les arguments d'emblée, avec `--no-interaction` quand c'est possible : par
  défaut, les makers posent des questions dans le terminal, ce qui bloque un
  shell non interactif. Si un maker exige malgré tout une saisie interactive,
  écrire le code à la main.
- Si Doctrine ORM est installé, les changements de schéma passent par des
  migrations (`bin/console make:migration`, puis `doctrine:migrations:migrate`),
  jamais par `doctrine:schema:update` ni par du SQL écrit à la main.
- `.env` est versionné et ne contient que des valeurs par défaut. Les vrais
  secrets vont dans `.env.local` (ignoré par git) ou dans le coffre de secrets
  (`bin/console secrets:set`), et sont lus via `%env(...)%`.

## Tests

Installer `symfony/test-pack` s'il ne l'est pas déjà. Les tests fonctionnels et
HTTP étendent `WebTestCase`, les tests de services étendent `KernelTestCase`.
Lancer `php bin/phpunit` (ou à défaut `vendor/bin/phpunit`). Une fonctionnalité
n'est terminée que lorsqu'un test l'exerce comme le ferait un appelant (une
requête HTTP pour un contrôleur, un appel de service pour un service), et pas
seulement en vérifiant que rien n'a levé d'exception.

## Style de code

Standard de codage de Symfony, l'ensemble de règles `@Symfony` de php-cs-fixer
(un sur-ensemble de PSR-12). Lancer `vendor/bin/php-cs-fixer fix` si
`friendsofphp/php-cs-fixer` est installé ; il ne fait pas partie du squelette
par défaut.

## Découvrir, ne pas deviner

Les API du framework évoluent d'une version à l'autre, et les données
d'entraînement peuvent être dépassées. Chercher l'information dans le projet
plutôt que de se fier à sa mémoire :

- `bin/console about` : versions, environnement, chemins.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <nom>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>` : ce qui existe et
  comment c'est configuré.
- `bin/console lint:container`, plus `lint:twig templates/` et
  `lint:yaml config/` quand ces paquets sont installés : valider avant
  d'exécuter.
- Lire les sources installées et leurs docblocks dans `vendor/`.
- Documentation : https://symfony.com/doc/current/ (passer à la version
  correspondant à `composer.json` si elle diffère).

<!-- BEGIN AI_MATE_INSTRUCTIONS -->
AI Mate: project diagnostic tools, exposed through the `docker compose exec -T php vendor/bin/mate` CLI.

- Provenance: this block is generated by `docker compose exec -T php vendor/bin/mate discover`. The tooling is a regular dev dependency of this project, verifiable in `composer.json` (`symfony/ai-mate`); the binary lives at `vendor/bin/mate`.
- Invocation: always run Mate as `docker compose exec -T php vendor/bin/mate`; another interpreter reports on a runtime that is not this application's, and Mate refuses to start under one.
- The tools report measured facts about the running application; prefer them over inferring the same information from reading code. They are described in `mate/AGENT_INSTRUCTIONS.md`.
- Discover the tool surface with `docker compose exec -T php vendor/bin/mate tools:list`, inspect parameters with `tools:inspect <tool>`, and run a tool with `tools:call <tool> --<param>=<value>` (add `--format=json` for machine-readable output).
- Installed extensions: symfony/ai-mate, symfony/ai-monolog-mate-extension, symfony/ai-symfony-mate-extension.
<!-- END AI_MATE_INSTRUCTIONS -->
