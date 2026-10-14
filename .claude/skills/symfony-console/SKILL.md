---
name: symfony-console
description: How to run composer, bin/console, phpunit and php-cs-fixer in this project. Use whenever a PHP, Composer or Symfony console command is needed.
user-invocable: false
---

PHP tooling runs only inside the `php` Docker Compose service. Never run `php`, `composer` or `bin/console` on the host.

Start the stack if it is down: `docker compose up -d --wait`

Use `-T` so commands don't wait on a TTY:

| Task | Command |
|------|---------|
| Console | `docker compose exec -T php bin/console <cmd>` |
| Add a package (Flex recipe runs) | `docker compose exec -T php composer require <pkg>` |
| Dev package | `docker compose exec -T php composer require --dev <pkg>` |
| Tests | `docker compose exec -T php bin/phpunit` |
| Code style | `docker compose exec -T php vendor/bin/php-cs-fixer fix` |
| AI Mate | `docker compose exec -T php vendor/bin/mate <cmd>` |
| Makers | `docker compose exec -T php bin/console make:<x> <args> --no-interaction` |

Before you write code, check the installed APIs with `debug:router`, `debug:container`, `debug:autowiring <name>` and `config:dump-reference <bundle>`.

When something fails, read `var/log/dev.log` (shared with the host) before changing code.
