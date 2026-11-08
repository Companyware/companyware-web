# Porting Shopware plugins

Companyware aims for a low-effort, explicit port from Shopware plugins, not binary compatibility with the Shopware runtime. Shopware's plugin shape is valuable: Symfony services, lifecycle hooks, migrations, administration modules and storefront assets all map naturally to Companyware concepts. Shopware's commerce domain and DAL remain Shopware-specific and must be adapted rather than silently emulated.

The administration SDK provides a versioned compatibility facade for the stable registration patterns `Shopware.Module.register`, `Shopware.Component.register/extend/override`, `Shopware.Service.register` and `Shopware.Application.addServiceProvider`. Generated plugins use the familiar `src/Resources/app/administration/src` layout. This reduces structural changes and lets administration code be moved in small steps.

Run the portability analyzer before changing a plugin:

```bash
php bin/console app:plugins:analyze-shopware /path/to/ShopwarePlugin --json
```

The stable JSON report separates directly mapped administration patterns from manual mappings such as DAL, checkout, content, SystemConfig, storefront and administration repository-factory use. It reports every finding with its source file and the intended Companyware replacement.

## Binding rules

1. Preserve familiar Shopware directory structure and stable administration registrations where the compatibility report marks them direct.
2. Never pretend that Shopware commerce services or DAL exist. Move those dependencies to explicit Companyware domain plugins, entity/repository contracts and HTTP application APIs.
3. PHP code may import only public `Companyware\Core` contracts, Symfony APIs and its own declared dependencies. Package validation rejects remaining `Shopware\` imports with a dedicated diagnostic.
4. Client-side permission checks affect presentation only. Every operation remains authorized in a server-side application service.
5. Public plugin frontends may own `/` and guarded plugin routes, but may not shadow `/admin`, authentication, API, plugin assets, SDK assets or protected recovery infrastructure.
6. Compatibility-facade behavior is semantic-versioned, covered by contract tests and deprecated before removal. No silent behavior change is allowed within a public major version.
7. New Companyware-native capabilities use `window.CompanywareAdmin`; the `window.Shopware` facade exists for migration and may be replaced incrementally, not all at once.
8. A port is complete only when package validation, SDK contract tests and the plugin's application/API authorization tests pass without Shopware runtime dependencies.

The official Shopware documentation remains the source for the plugin being ported. Companyware intentionally maps stable administration registration and diagnoses Shopware-specific runtime dependencies for explicit adaptation.
