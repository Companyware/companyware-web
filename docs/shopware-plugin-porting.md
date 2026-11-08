# Porting Shopware plugins

This is a descriptive migration guide for independently porting a plugin originally written for Shopware. Shopware is a trademark of its respective owner; Companyware is not affiliated with, endorsed by or runtime-compatible with Shopware. Consult the source plugin's license before modifying or redistributing it.

Companyware does not provide Shopware globals, PHP namespaces, components or services. The neutral analyzer recognizes common external registration shapes and reports how to transform them into native `window.CompanywareAdmin` modules, components and services. Commerce domain and DAL dependencies require explicit Companyware implementations.

Run the portability analyzer before changing a plugin:

```bash
php bin/console app:plugins:analyze-portability /path/to/source-plugin --json
```

The stable JSON report separates transformable administration patterns from dependencies needing manual mapping. It reports every finding with its source file and the intended native Companyware target.

## Binding rules

1. Move administration source into the Companyware `resources/administration/` layout and transform registrations to `window.CompanywareAdmin`.
2. Never pretend that Shopware commerce services or DAL exist. Move those dependencies to explicit Companyware domain plugins, entity/repository contracts and HTTP application APIs.
3. Runtime PHP code may import only public `Companyware\Core` contracts, Symfony APIs, its own namespace and declared independent dependencies.
4. Client-side permission checks affect presentation only. Every operation remains authorized in a server-side application service.
5. Public plugin frontends may own `/` and guarded plugin routes, but may not shadow `/admin`, authentication, API, plugin assets, SDK assets or protected recovery infrastructure.
6. Companyware SDK behavior is semantic-versioned, covered by contract tests and deprecated before removal. No silent behavior change is allowed within a public major version.
7. All ported administration code uses `window.CompanywareAdmin`; no source-platform global remains at runtime.
8. A port is complete only when package validation, SDK contract tests and the plugin's application/API authorization tests pass without Shopware runtime dependencies.

The official Shopware documentation remains the source for the plugin being ported. Companyware intentionally maps stable administration registration and diagnoses Shopware-specific runtime dependencies for explicit adaptation.
