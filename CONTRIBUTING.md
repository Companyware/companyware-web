# Contributing to Companyware Web

Thank you for improving Companyware Web. This repository owns only the Symfony
web application. Companyware Desktop is developed, built and released in its own
Java repository; cross-application behavior belongs in a versioned API contract.

## Before opening a change

1. Use an issue or an agreed work package for non-trivial behavior.
2. Keep Core domain-neutral and prefer a plugin for business functionality.
3. Use only contracts documented in `docs/public-api.md`; `App\\` is internal.
4. Do not add credentials, production data, private keys or generated local state.
5. Preserve the protected authentication, authorization and recovery paths.

## Development checks

Use PHP 8.3, Composer 2 and a current Node.js version:

```bash
composer install
composer release-check
```

Add focused regression coverage for every behavioral fix. New or substantively
rewritten PHP, JavaScript, TypeScript, Vue and CSS source must carry the short
Companyware MIT/SPDX header used by the existing generators. JSON remains
comment-free and inherits the containing package license.

## Pull requests

Describe the user-visible outcome, public-contract impact, migrations, security
considerations, checks actually executed and checks still outstanding. Do not call
an unexecuted production or browser check successful. Breaking public API changes
require a new major public API version and an explicit migration path.

By contributing, you agree that your contribution is licensed under this
repository's [MIT License](LICENSE).

