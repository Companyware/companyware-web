# Releasing the Companyware Web framework

The framework and its public plugin contracts are released independently from business plugins. A release candidate is acceptable only from an immutable commit with a clean working tree and the checks below passing.

## Local release gate

Use PHP 8.3, Composer 2 and a current Node.js installation, then run:

```bash
composer install
composer release-check
```

The gate validates Composer metadata, the pinned dependency lock, MIT licensing, the declared public API version, required developer and operator documentation, generated plugin license headers, the PHP reference tests and the Administration SDK tests. It does not connect to production or mutate a database.

Every newly generated PHP, JavaScript, TypeScript, Vue and CSS source starts with the short MIT/SPDX notice and references the full generated `LICENSE`. JSON remains comment-free and carries its license through Composer metadata and the containing package license.

## Release evidence

Record the following before tagging:

- exact Git commit and semantic version;
- clean `composer release-check` output under PHP 8.3;
- archive SHA-256 checksum;
- public API and compatibility version;
- release notes, security changes and known limitations;
- installation, upgrade, migration and recovery commands;
- matching website, download and documentation status.

Do not publish credentials, environment files, caches, database exports or local plugin trust stores. Sign published artifacts only with the designated release identity. A later Store must consume the same package identity, compatibility, checksum and publisher metadata rather than introduce a second package format.

## Production verification after deployment

Local checks are not production verification. After the release commit is pushed, the operator pulls it on the server and runs the project-specific PHP 8.3 binary:

```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console app:migrations:migrate --env=prod
php bin/console app:migrations:status --env=prod
php bin/console app:plugins:validate --env=prod
```

Verify login, protected administration routes, the public OpenAPI document, one restricted authorization denial, plugin activation/deactivation and recovery behavior. Record production as verified only after the operator reports the observed result.
