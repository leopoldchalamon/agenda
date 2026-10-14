---
name: migration
description: "Vérifie les migrations Doctrine du projet (PostgreSQL) : perte de données, cohérence up/down, verrous et performances, déploiement sans interruption, cohérence avec les records Doctrine. À utiliser après doctrine:migrations:diff et avant de commiter une migration."
---

# Vérifier une migration Doctrine

## Périmètre

- Si l'utilisateur indique une migration : analyser celle-là.
- Sinon : les migrations de `migrations/` non commitées (`git status --porcelain migrations/`). S'il n'y en a aucune, la plus récente.

Ne jamais modifier une migration déjà commitée. Si une correction est nécessaire, proposer une nouvelle migration.

## Étape 1 : cohérence avec le mapping

```
docker compose exec -T php bin/console doctrine:schema:validate
```

- `schema:validate` doit être OK pour le mapping. Sa vérification de la base n'a de sens qu'une fois la migration appliquée (étape 3) : si elle signale encore une différence, il manque quelque chose dans la migration.
- Comparer la migration avec les records modifiés dans `Infrastructure/Persistence/Entity/` (`git diff` sur ce dossier) : chaque changement d'attribut `#[ORM\...]` doit avoir son SQL, et inversement.

## Étape 2 : analyser chaque instruction SQL

### Perte de données (bloquant)

- `DROP TABLE` ou `DROP COLUMN` sans reprise préalable des données.
- `TRUNCATE` ou `DELETE` sans `WHERE` dans une migration.
- Changement de type qui tronque ou convertit des données (longueur réduite, texte vers entier, perte de fuseau horaire).
- Suppression d'une contrainte `UNIQUE` ou d'une clé étrangère sans justification.

### Cohérence up/down (bloquant)

- `down()` absente ou vide.
- `down()` qui n'est pas l'inverse exact de `up()`.
- `down()` qui recrée une colonne supprimée sans pouvoir en restaurer les données : le signaler comme irréversible et le dire dans la description de la migration.
- `getDescription()` vide : proposer une description d'une ligne.

### PostgreSQL : verrous et performances (important)

- `CREATE INDEX` sur une table qui peut grossir (événements, favoris) sans `CONCURRENTLY`. Comme `CONCURRENTLY` ne peut pas tourner dans une transaction, la migration doit alors surcharger `isTransactional()` pour renvoyer `false`, et ne contenir que cette instruction.
- Ajout d'une colonne `NOT NULL` sans valeur par défaut sur une table non vide.
- Ajout d'une clé étrangère sur une grosse table sans `NOT VALID` suivi d'un `VALIDATE CONSTRAINT` séparé.
- Plusieurs `ALTER TABLE` sur la même table qui pourraient être regroupés.
- `UPDATE` de masse sans découpage.

### Déploiement sans interruption (important)

- Renommage de colonne ou de table : l'ancien code encore en service ne la trouvera plus.
- Suppression d'une colonne encore utilisée par le code ou le mapping.
- Changement de type de colonne sur une table en service.

Pour ces cas, recommander le découpage en étapes :
1. migration qui ajoute la nouvelle colonne ou table ;
2. déploiement du code qui écrit dans les deux ;
3. migration de reprise des données ;
4. déploiement du code qui n'utilise plus l'ancienne ;
5. migration qui supprime l'ancienne.

### Conventions du projet (important)

- Les identifiants sont des UUID (type `uuid` PostgreSQL), pas des entiers auto-incrémentés.
- Contrainte d'unicité sur identifiant de source + identifiant externe pour les événements ingérés (garantie de l'idempotence).
- Index sur les colonnes utilisées pour filtrer l'API (dates, source) et sur chaque clé étrangère.
- Noms de tables et de colonnes en `snake_case`, en anglais.

## Étape 3 : tester l'aller-retour

Uniquement sur la base de développement, et après accord de l'utilisateur :

```
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T php bin/console doctrine:migrations:migrate prev --no-interaction
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T php bin/console doctrine:schema:validate
```

## Format de sortie

1. Résumé : migrations analysées et ce qu'elles font, en une ligne chacune.
2. Problèmes classés par sévérité (**bloquant**, **important**, **suggestion**), avec l'instruction SQL concernée et la correction proposée.
3. Verdict : prête à commiter, ou corrections nécessaires.
