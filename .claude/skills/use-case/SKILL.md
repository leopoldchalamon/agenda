---
name: use-case
description: "Crée un use case complet selon l'architecture hexagonale du projet (commande ou requête, handler, port, fake, test unitaire), en TDD. À utiliser pour toute nouvelle action métier, par exemple « créer le use case pour désactiver une source »."
---

# Créer un use case

## Entrées

Avant de commencer, il faut connaître :
- le **contexte** (`Source`, `Ingestion`, `Event`, `Account`) ;
- le **nom** au format verbe + nom (`DisableSource`, `AddFavorite`, `GetEvent`) ;
- le **type** : commande (écriture) ou requête (lecture). Une lecture exposée par l'API ne demande pas de requête : elle passe par le provider Doctrine d'API Platform sur les records (voir `CLAUDE.md`, section API Platform). Le signaler à l'utilisateur au lieu de créer une requête ;
- les **règles métier** : préconditions, erreurs possibles, événement domaine émis.

Si l'une de ces informations manque ou est ambiguë, la demander avant d'écrire du code. Proposer un nom si l'utilisateur ne l'a pas donné.

## Étape 1 : s'aligner sur le code existant

1. Relire les sections Architecture, Conventions du domaine et Tests de `CLAUDE.md`.
2. Si le `CLAUDE.md` liste des exemples de référence, les lire et les imiter.
3. Lister ce qui existe déjà dans `src/{Context}/` : modèle, Value Objects, ports, exceptions. Réutiliser plutôt que recréer.

## Étape 2 : présenter le plan

Lister les fichiers à créer ou modifier, puis attendre la validation de l'utilisateur. Le plan type :

```
src/{Context}/Domain/Model/…                    # seulement si le modèle évolue (nouvelle méthode métier)
src/{Context}/Domain/Port/{X}Repository.php     # seulement si le port n'existe pas ou s'il manque une méthode
src/{Context}/Domain/Event/{X}{Fait}.php        # si le use case émet un événement
src/{Context}/Domain/Exception/{Erreur}.php     # une par erreur métier
src/{Context}/Application/Command/{Nom}.php     # ou Query/
src/{Context}/Application/Command/{Nom}Handler.php
tests/{Context}/Fake/InMemory{X}Repository.php  # créer ou compléter
tests/{Context}/Unit/Application/{Nom}HandlerTest.php
```

## Étape 3 : écrire les tests d'abord

1. Créer ou compléter le fake en mémoire du port. Il implémente l'interface du domaine, sans aucune logique métier.
2. Écrire le test du handler : le cas nominal, chaque erreur métier, et l'événement émis s'il y en a un.
3. Lancer les tests et vérifier qu'ils **échouent pour la bonne raison** (classe absente ou assertion fausse, pas une erreur de syntaxe) :
   ```
   docker compose exec -T php vendor/bin/phpunit tests/{Context}/Unit
   ```

## Étape 4 : implémenter

- La commande ou la requête est une classe `final readonly` qui ne contient que des données, typées avec des Value Objects quand ils existent.
- Le handler est `final`, a une seule méthode publique `__invoke()`, et ne dépend que d'interfaces du domaine, plus `Psr\Clock\ClockInterface` s'il a besoin de la date.
- Les règles métier vivent dans le modèle du domaine, pas dans le handler. Le handler orchestre : charger, appeler la méthode métier, sauvegarder, renvoyer.
- Une requête ne modifie jamais l'état.
- Aucun import de `Symfony\`, `Doctrine\` ou d'un autre contexte dans `Domain` et `Application`.
- `declare(strict_types=1);` dans chaque fichier.

Relancer les tests jusqu'à ce qu'ils passent.

## Étape 5 : infrastructure, seulement si nécessaire

- Si le port est nouveau ou a une nouvelle méthode : implémenter le repository Doctrine dans `Infrastructure/Persistence/`, créer ou compléter le record dans `Infrastructure/Persistence/Entity/` (attributs `#[ORM\...]`, données uniquement), la conversion domaine <-> record, et le test d'intégration d'aller-retour (enregistrer, recharger, comparer). Si le schéma change, générer la migration et proposer `/migration`.
- Ne pas créer d'adaptateur (ressource API Platform, contrôleur EasyAdmin, message Messenger) sans demande explicite. Le proposer en fin de tâche.

## Étape 6 : vérifier

```
docker compose exec -T php vendor/bin/phpunit
docker compose exec -T php vendor/bin/phpstan analyse
docker compose exec -T php vendor/bin/php-cs-fixer fix
docker compose exec -T php vendor/bin/deptrac
```

Tout doit passer. Si un outil n'est pas encore installé, le dire dans le récapitulatif au lieu de considérer la vérification comme réussie. Ne jamais contourner une erreur PHPStan avec `@phpstan-ignore` ni baisser son niveau.

## Résultat attendu

Un récapitulatif court : fichiers créés, règles métier couvertes par les tests, et l'adaptateur à créer ensuite si c'est pertinent (par exemple : « ajouter l'action dans EasyAdmin »).
