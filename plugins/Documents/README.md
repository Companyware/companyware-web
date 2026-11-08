# Documents reference plugin

Documents is Companyware's canonical, installable reference plugin. It deliberately uses only the published `Companyware\\Core` contracts plus ordinary Symfony components, so external plugin developers can reuse its patterns without depending on internal `App\\` implementation.

## Patterns demonstrated

- Composer package plus `plugin.json`, stable publisher identity and semantic compatibility metadata.
- `Plugin` lifecycle hooks, versioned migrations and explicit uninstall data policy.
- Plugin-owned entity and repository contracts with opaque IDs, timestamps, optimistic versions and soft deletion.
- Server-side privileges shared by Twig administration and `/api/v1` HTTP adapters.
- OpenAPI registration, OAuth2/PAT authentication through Core and idempotent writes.
- Symfony service decoration and lifecycle-event validation without Core modifications.
- Twig for the page and a build-free `window.CompanywareAdmin` enhancement for progressive interaction.
- Dashboard slots, translated navigation, configurable upload limits and isolated file storage.

## Verify the package

From the Companyware root:

```bash
php bin/console app:plugins:validate-package plugins/Documents --json --no-interaction
php plugins/Documents/tests/reference.php
```

The reference test checks package boundaries, public-contract usage, lifecycle cleanup, service decoration, event validation, API registration and Administration SDK registration. Database/file behavior remains covered through the shared application service and production lifecycle checks.

Copy the generated skeleton for a new plugin; use Documents when you need a complete real-world example. Do not import `App\\` classes from plugin code.
