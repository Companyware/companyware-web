# Releasing the Companyware Web framework

The framework and its public plugin contracts are released independently from business plugins. A release candidate is acceptable only from an immutable commit with a clean working tree and the checks below passing.

## Local release gate

Use PHP 8.3, Composer 2 and a current Node.js installation, then run:

```bash
composer install
composer release-check
php tools/build-release.php 0.1.0-preview.1 --verify-reproducible
```

The gate validates Composer metadata, audits the pinned dependency lock for known security advisories, checks MIT licensing, the declared public API version, required developer and operator documentation, generated plugin license headers, the PHP reference tests and the Administration SDK tests. The archive command refuses a dirty tree, archives the exact HEAD commit twice, compares both SHA-256 values and writes the ZIP, checksum and machine-readable evidence manifest below `dist/releases/`. It does not create a tag or release, connect to production or mutate a database.

Before building, add the reviewed version-bound release notes at
`docs/release-notes/<version>.md`. The first heading must be
`# Companyware Web <version>`. The builder refuses an archive without this file
and records both its repository-relative path and SHA-256 checksum in the evidence
manifest. This binds the candidate artifact to reviewable notes without claiming
that the candidate was tagged or published.

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

The JSON evidence manifest records the application identity, semantic version,
exact commit, commit timestamp, public API version, version-bound release-note
path and checksum, artifact byte size and SHA-256. Compare the checksums with the
uploaded artifact and published notes after publication. A CI evidence archive is
short-lived test output and is not a public framework release.

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
