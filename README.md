# Companyware Web

Companyware is one platform with two independently developed applications:

- **Companyware Desktop** is the native Java desktop application.
- **Companyware Web** is the Symfony-based web application in this repository.

They have separate runtimes, plugin formats, builds, tests and releases. The
boundary is the versioned Companyware HTTP API. Where a workflow needs both
applications, neither application imports the other application's code or
database.

## Release status

This repository currently contains a development snapshot of Companyware Web.
There is no public supported release yet. Do not infer a stable version from the
default branch and do not deploy an archive that lacks its matching checksum and
release notes.

Companyware Web targets PHP 8.3, Symfony 6.4 LTS, MySQL/MariaDB and current
supported browser versions. It is licensed under the [MIT License](LICENSE).

## What is included

- a domain-neutral process-platform Core;
- versioned public PHP, HTTP and Administration SDK contracts;
- Composer-compatible plugins with dependencies, migrations and lifecycle;
- roles, permissions and scoped data access;
- signed extension metadata and explicit update/recovery flows;
- Twig-based public frontends and a plugin-extensible theme contract;
- Documents as the reference plugin.

Commerce, office workflows and other business domains belong in independently
released plugins, not in Core.

## Local development

Prerequisites are PHP 8.3, Composer 2, Node.js and the PHP extensions declared in
`composer.json`.

```bash
composer install
composer test
composer release-check
```

Create every administrator explicitly. No account credentials are bundled with
Companyware:

```bash
php bin/console app:admin:create
```

Database and mail credentials belong in an untracked `.env.local`. Never commit
credentials, recovery keys, database exports, production caches or publisher
private keys.

## Documentation

- [Public API and compatibility policy](docs/public-api.md)
- [Plugin development](docs/plugin-development.md)
- [Administration SDK](docs/administration-sdk.md)
- [HTTP API](docs/http-api.md)
- [Themes](docs/themes.md)
- [Framework release process](docs/releasing-framework.md)
- [Upgrade and recovery](docs/upgrade-and-recovery.md)
- [Security policy](SECURITY.md)
- [Support and compatibility](SUPPORT.md)
- [Contributing](CONTRIBUTING.md)
- [GitHub publication checklist](docs/github-publication-checklist.md)

## Release integrity

Maintainers build an immutable source archive from a clean commit:

```bash
php tools/build-release.php 0.1.0-preview.1 --verify-reproducible
```

The command creates a ZIP, SHA-256 checksum and JSON evidence manifest in
`dist/releases/`. A tag or GitHub release is a separate, explicit publication
step and is never created by the build command.

