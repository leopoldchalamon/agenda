---
name: review
description: "Revue de code du projet : architecture hexagonale, règles du CLAUDE.md, sécurité, mode worker, Doctrine, tests. Analyse le diff git (staged, sinon non staged) ou les fichiers indiqués. À utiliser avant un commit ou une pull request."
---

# Revue de code

## Périmètre

- Si l'utilisateur indique des fichiers ou un dossier : analyser uniquement ceux-là.
- Sinon : le diff staged (`git diff --cached`). S'il est vide : le diff non staged (`git diff`).
- S'il n'y a aucun changement, le dire et s'arrêter.

Lire les fichiers modifiés en entier, pas seulement les lignes du diff, pour juger le contexte.

## Étape 1 : lancer les outils

```
docker compose exec -T php vendor/bin/phpstan analyse
docker compose exec -T php vendor/bin/deptrac
docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec -T php vendor/bin/phpunit
```

Si un outil n'est pas installé, l'indiquer dans le résultat et continuer avec les autres. Reporter leurs erreurs telles quelles. Ne pas redire dans la revue ce que ces outils signalent déjà : se concentrer sur ce qu'ils ne voient pas.

## Étape 2 : relire selon les critères

Les règles de référence sont dans `CLAUDE.md`. Vérifier en particulier :

### Architecture (bloquant)

- Import de `Symfony\`, `Doctrine\` ou d'une autre bibliothèque d'infrastructure dans `Domain` ou `Application`.
- Import des classes `Domain` d'un autre contexte (hors `Shared/Domain`).
- Règle métier dans un handler, un contrôleur EasyAdmin, un state provider ou processor, un message handler ou un fetcher, au lieu du modèle du domaine.
- Adaptateur d'infrastructure qui écrit via un repository au lieu d'appeler un command handler.
- Handler avec plusieurs méthodes publiques ou sans `__invoke()`.
- Requête (Query) qui modifie l'état.
- Attribut `#[ORM\...]` hors de `Infrastructure/Persistence/Entity/`, ou `#[ApiResource]` hors de `Infrastructure/Api/` (en particulier sur un modèle du domaine ou un record).
- Record Doctrine qui contient une règle métier.
- Identifiant auto-incrémenté au lieu d'un UUIDv7 généré par le domaine.

### Mode worker FrankenPHP (bloquant)

- Propriété statique ou cache en mémoire qui garde des données d'une requête à l'autre.
- Service qui stocke dans une propriété l'utilisateur courant, la requête ou toute donnée liée à la requête, sans implémenter `ResetInterface`.

### Sécurité (bloquant)

- Requête DQL ou SQL construite par concaténation au lieu de paramètres liés.
- Opération API Platform sans règle `security` alors que la ressource n'est pas publique.
- Donnée personnelle d'un utilisateur (favoris, profil) accessible par un autre utilisateur.
- Secret, token ou mot de passe en dur dans le code ou dans un fichier versionné.
- Fetcher qui appelle une URL fournie par une source sans timeout, ou qui fait confiance au contenu reçu sans validation.

### Domaine (important)

- `new \DateTimeImmutable()` ou `time()` dans `Domain` ou `Application` au lieu de l'horloge injectée.
- `\Exception` générique au lieu d'une exception nommée de `Domain/Exception/`.
- Value Object mutable, ou qui ne se valide pas dans son constructeur.
- Événement domaine qui n'est pas au passé ou qui porte des objets mutables.

### Doctrine (important)

- `EntityManagerInterface` utilisé hors de `Infrastructure/Persistence/` (seule exception : le provider Doctrine d'API Platform, en lecture).
- Conversion domaine <-> record qui oublie un champ, ou repository sans test d'aller-retour.
- `flush()` dans une boucle.
- Requête N+1 probable (boucle sur une collection chargée à la demande).
- Changement d'un record sans migration correspondante, ou migration commitée modifiée.

### Ingestion (important)

- Ingestion non idempotente : pas de dédoublonnage sur identifiant de source + identifiant externe.
- Exception d'une source qui interrompt le traitement des autres.

### Tests (important)

- Use case sans test unitaire, ou erreur métier non testée.
- Mock d'un port au lieu d'un fake en mémoire.
- Test unitaire qui démarre le kernel ou touche la base.
- Test de fetcher qui fait un vrai appel réseau.

### Style (suggestion)

- `declare(strict_types=1);` manquant, classe non `final` sans raison, objet de données non `readonly`.
- Nommage qui s'écarte des conventions (use case verbe + nom, tests `test{Action}{Scenario}`).
- Code ou commentaire en français.

## Format de sortie

1. Résultat des outils : OK ou erreurs.
2. Remarques classées par sévérité : **bloquant**, **important**, **suggestion**. Pour chacune : fichier et ligne, problème, correction proposée (avec un court extrait de code si utile).
3. Verdict en une ligne : prêt à commiter, ou liste des bloquants à corriger.

Ne rien modifier sans que l'utilisateur le demande.
