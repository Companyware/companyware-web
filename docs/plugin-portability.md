# Plugin portability

Companyware ports external plugins through explicit source transformation, never through foreign runtime emulation. The platform exports only native, versioned Companyware APIs and globals.

Start with a machine-readable report:

```bash
php bin/console app:plugins:analyze-portability /path/to/external-plugin --json
```

The analyzer is source-platform-neutral. It finds administration registration patterns that can be transformed to `window.CompanywareAdmin`, external PHP imports that need a public Companyware contract or declared independent Composer dependency, and foreign frontend runtime APIs that need SDK/API adapters. It does not copy source, rewrite files or claim runtime compatibility.

## Porting rules

1. Generated and ported runtime code uses only Companyware names, public contracts and declared independent dependencies.
2. External globals, namespaces, component names and service identifiers are transformed; Companyware does not publish aliases for them.
3. Domain behavior is reimplemented against Companyware application services, repositories and `/api/v1`, not hidden behind an emulation layer.
4. Client-side permission checks are presentational; server-side authorization remains mandatory.
5. Public frontends use Twig, semantic theme tokens and guarded plugin routes. They cannot shadow protected Core routes.
6. A port is complete only when the neutral analyzer reports no unresolved manual mappings and package/application tests pass without the source platform runtime.
7. Source-platform names may appear descriptively in a dedicated migration guide, never in Companyware runtime APIs, generated code, product names or compatibility claims.

Platform-specific migration guides may add mapping recipes and source references without changing these rules.
