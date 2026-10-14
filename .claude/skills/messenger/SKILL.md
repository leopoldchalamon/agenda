---
name: messenger
description: "Vérifie la configuration Symfony Messenger et Scheduler du projet : routage, transports, retry, failure transport, handlers, et règles de l'ingestion (un message par source, idempotence, isolation des échecs). À utiliser après un changement dans messenger.yaml, un message, un handler ou un schedule."
---

# Vérifier Messenger et Scheduler

## Étape 1 : faire l'inventaire

1. Lire `config/packages/messenger.yaml` (et `scheduler.yaml` s'il existe).
2. Lister les messages, les handlers et les schedules :
   ```
   docker compose exec -T php bin/console debug:messenger
   docker compose exec -T php bin/console debug:scheduler
   ```
3. Chercher dans `src/` les classes marquées `#[AsMessageHandler]`, `#[AsMessage]`, `#[AsSchedule]`, `#[AsCronTask]` et `#[AsPeriodicTask]`.
4. Croiser les trois sources.

## Étape 2 : vérifier

### Routage (bloquant)

- Message sans handler, ou handler pour un message qui n'existe pas.
- Message routé vers un transport non défini.
- Message qui doit être asynchrone (appel à une source externe, traitement long) mais qui n'est routé vers aucun transport : il sera traité en synchrone sans avertissement.

### Retry et échecs (bloquant)

- Transport asynchrone sans `retry_strategy` explicite, ou avec des tentatives illimitées.
- Pas de `failure_transport` : les messages en échec seraient perdus.
- Erreur définitive (source introuvable, source désactivée, contenu invalide) qui n'est pas signalée avec `UnrecoverableMessageHandlingException` : elle serait retentée pour rien.
- Erreur temporaire (timeout, erreur HTTP 5xx) qui n'est pas laissée au mécanisme de retry.

### Architecture (bloquant)

- Les messages et leurs handlers vivent dans `Infrastructure/Messenger/`. Un handler Messenger ne contient aucune règle métier : il appelle un command handler d'Application.
- Un message ne transporte que des données sérialisables simples (identifiants en chaîne, pas d'entité ni de Value Object complexe), car il est sérialisé dans le transport.
- Le domaine et l'application n'importent jamais `Symfony\Component\Messenger`.

### Ingestion (important)

- Le schedule envoie **un message par source active**, et ne fait pas lui-même le travail de récupération.
- Le traitement d'une source est idempotent : rejoué après un retry ou en double, il ne crée aucun doublon.
- L'échec d'une source ne bloque pas les autres : un message par source, et aucun traitement en boucle de toutes les sources dans un seul handler.
- Les appels HTTP des fetchers ont un timeout explicite.
- Les logs d'échec contiennent l'identifiant de la source.

### Mode worker et consommateurs (important)

- Handler qui garde un état entre deux messages (propriété statique, cache en mémoire, données de la source précédente) : le consommateur tourne longtemps, comme le worker FrankenPHP.
- `flush()` Doctrine dans une boucle.
- Consommateur lancé sans limites (`--time-limit`, `--memory-limit` ou `--limit`) dans la configuration de déploiement.

### Tests (important)

- Le command handler appelé par le handler Messenger a ses tests unitaires.
- Le routage est vérifié par un test fonctionnel avec le transport `in-memory` en environnement de test.

## Format de sortie

1. Résumé : nombre de messages, handlers, transports et schedules.
2. Tableau de routage : message, transport, handler, stratégie de retry.
3. Problèmes classés par sévérité (**bloquant**, **important**, **suggestion**), avec la correction proposée et un extrait de configuration si utile.

Ne rien modifier sans que l'utilisateur le demande.
