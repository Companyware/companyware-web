# Build a first Companyware Web plugin

Companyware Web's generator creates a working backend plugin structure in one command:

```bash
php bin/console app:plugins:create acme-example --vendor=acme --no-interaction
php bin/console app:plugins:validate-package plugins/AcmeExample --json --no-interaction
composer dump-autoload
php bin/console cache:clear
```

The package contains Composer and runtime manifests, guarded routes, Symfony services, a lifecycle class, controller, domain example, migration, template, German translations, assets, a standalone smoke test and a CI workflow template. The generated controller protects its route with the declared read privilege.

## Development loop

1. Put application behavior below `src/` and depend only on documented `Companyware\\Core\\` APIs.
2. Declare every privilege, migration, asset and cross-plugin entity owner in `plugin.json`.
3. Run `php tests/smoke.php` in the plugin and `composer test` plus `app:plugins:validate-package` in the Companyware checkout.
4. Rebuild Composer autoload and clear the Symfony cache after adding classes, routes, services or translations.
5. Package either the plugin contents or one enclosing directory as ZIP.

The generated CI workflow expects a prepared Companyware checkout through the `COMPANYWARE_ROOT` repository variable. This keeps CI on the same validator used by local development, ZIP installation and future Store ingestion.

Generated source files contain the short MIT/SPDX notice and reference the package's full `LICENSE`. Keep the notice at the beginning of new source files. Formats that do not permit comments, including JSON, remain comment-free and use Composer license metadata plus the package `LICENSE`.

Framework maintainers run `composer release-check` before tagging a framework release. The complete release and production-verification gates are documented in [releasing-framework.md](releasing-framework.md).

## Operator commands

All read and mutation commands support unattended use; add `--json --no-interaction` for automation.

```bash
php bin/console app:plugins:list --json
php bin/console app:plugins:inspect acme-example --json
php bin/console app:plugins:install acme-example.zip --json --no-interaction
php bin/console app:plugins:activate acme-example --json --no-interaction
php bin/console app:plugins:deactivate acme-example --json --no-interaction
php bin/console app:plugins:update acme-example-0.2.0.zip --json --no-interaction
php bin/console app:plugins:uninstall acme-example --yes --json --no-interaction
```

Updates accept only a higher-version ZIP for an installed, inactive plugin. Companyware validates the complete prospective graph before swapping files. The previous package is retained outside plugin discovery until migrations and the optional update hook succeed. On failure the new package remains inactive and the reported recovery path is preserved for an operator. Online discovery, signatures, dependency-plan rollback and security advisories belong to CW-011.

After production package changes, run optimized Composer autoload generation, clear the production cache, apply migrations and validate the graph with the Plesk PHP 8.3 binary.
