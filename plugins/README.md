# Plugin development

Plugins are self-contained Composer packages of type `companyware-plugin`. Development packages conventionally live below `plugins/` and can be linked through a Composer path repository; distribution packages may come from any Composer repository.

Plugins may import only public `Companyware\\Core\\` symbols marked `@api`. The `App\\` namespace is internal and is not a compatibility contract. See [`docs/public-api.md`](../docs/public-api.md) for the API version, compatibility promise and protected Core boundaries.

```text
plugins/AcmeExample/
├── composer.json
├── plugin.json
├── config/
│   ├── routes.yaml
│   └── services.yaml
├── src/
│   ├── Controller/ExampleController.php
│   ├── ExampleLifecycle.php
│   ├── ExampleNavigation.php
│   └── ExampleSlots.php
├── templates/
│   ├── admin/index.html.twig
│   └── overrides/
│       └── admin/profile/index.html.twig
└── public/
    ├── example.css
    └── example.js
```

## Composer package

```json
{
  "name": "acme/companyware-example",
  "type": "companyware-plugin",
  "version": "1.0.0",
  "require": { "php": "~8.3.0" },
  "autoload": { "psr-4": { "Plugins\\AcmeExample\\": "src/" } },
  "extra": {
    "companyware-plugin": {
      "manifest": "plugin.json",
      "official": false
    }
  }
}
```

The optional `official` flag identifies packages maintained by Companyware and enables the additional official-package checks. Composer and runtime-manifest versions must match. Published packages use the same metadata as local path packages. Composer-installed packages are discovered by package type, independent of their installation directory.

## Manifest

```json
{
  "name": "acme-example",
  "label": "Acme Example",
  "description": "Adds an administration capability.",
  "version": "1.0.0",
  "compatibility": {
    "companyware": "^0.1",
    "php": "~8.3.0",
    "extensions": {"zip": "*"}
  },
  "require": {"companyware.documents": "^1.0"},
  "optional": {"acme.search": "^2.0"},
  "conflict": {"legacy-documents": "<2.0"},
  "provide": {"acme.example": "1.0.0"},
  "replace": {"acme.example-legacy": "1.0.0"},
  "entities": {"references": ["documents"]},
  "privileges": [
    {"key": "acme-example.read", "label": "acme.privilege.read", "group": "example"},
    {"key": "acme-example.write", "label": "acme.privilege.write", "group": "example"}
  ],
  "uninstall": {"data": "remove"},
  "settings": [
    {
      "key": "page_size",
      "type": "number",
      "label": "acme.settings.page_size",
      "help": "acme.settings.page_size_help",
      "default": 25,
      "min": 5,
      "max": 100
    }
  ],
  "locales": [{"code": "es", "label": "Español"}],
  "migrations": ["Plugins\\AcmeExample\\Migration\\Version202608150001"],
  "assets": {
    "styles": [{"handle": "acme-css", "path": "example.css", "priority": 100}],
    "scripts": [{"handle": "acme-js", "path": "example.js", "priority": 100, "defer": true}]
  },
  "navigation": [
    {"section": "PLUGINS", "label": "Example", "route": "acme_example_index", "icon": "E", "position": 100, "privilege": "acme-example.read"}
  ]
}
```

All runtime versions are semantic versions. Constraints support exact versions, comparison operators, caret and tilde ranges, wildcards, AND expressions separated by spaces or commas, and `||` alternatives. `require` dependencies must be present during installation and active during activation. An installed optional integration is version-checked, while an absent one is accepted. `provide` and `replace` expose a virtual package at one concrete semantic version.

The canonical compatibility resolver validates the complete prospective graph before installation, activation, deactivation or uninstall changes files, migrations, lifecycle state or the database. It checks Companyware and PHP versions, extensions, dependencies, conflicts, replacements and cycles and returns the same structured diagnostics for administration and future CLI or Store consumers.

## Entities and repositories

Entities implement the public `EntityInterface`, declare their owner through `EntityMetadata` and expose opaque string IDs, immutable UTC timestamps, an optimistic-lock version and an optional soft-delete timestamp. Domain services depend on plugin-owned repository interfaces; concrete PDO or Doctrine adapters remain replaceable infrastructure. See [`docs/entities-and-repositories.md`](../docs/entities-and-repositories.md).

Every cross-plugin entity relation is listed in `entities.references`. Each referenced owner must also be a key in `require`; package and graph validation reject the relation otherwise. Consumers integrate through a public contract of the owning plugin, never through its table or concrete repository.

## Roles and privileges

Declare plugin-owned privileges in the runtime manifest and reference them from navigation with `"privilege": "acme-example.read"`. Enforce the same privilege in PHP through the public `AuthorizationInterface` or `RequiresPrivilege` attribute. Navigation and slot filtering improve usability but do not replace server-side checks. `PrivilegeAwareInterface` lets a slot provider name the privilege required for each extension point. See [`docs/authorization.md`](../docs/authorization.md).

The compact role editor groups registered privileges by owner and area. Core and plugins remain the exclusive privilege registry: administrators combine declared privileges into roles but cannot create arbitrary privilege keys. Each package supplies translations for the privilege label and for `security.group.<group>` in every supported catalog.

Validate the currently installed graph through the same resolver used by administration:

```bash
php bin/console app:plugins:validate --env=prod
```

Generate a complete development package and validate it with the same package validator intended for CI and future Store ingestion:

```bash
php bin/console app:plugins:create acme-example --vendor=acme --no-interaction --json
php bin/console app:plugins:validate-package plugins/AcmeExample --json
php bin/console app:plugins:list --json
php bin/console app:plugins:inspect acme-example --json
php bin/console app:plugins:install build/acme-example.zip --no-interaction --json
php bin/console app:plugins:activate acme-example --no-interaction --json
php bin/console app:plugins:deactivate acme-example --no-interaction --json
php bin/console app:plugins:uninstall acme-example --yes --no-interaction --json
```

The generator includes Composer/runtime metadata, services, guarded routes, a migration, domain and controller examples, translations, assets, a test placeholder and a CI workflow. It never overwrites an existing target. Package validation checks metadata, compatibility, entity references, privileges, declared asset files, YAML configuration and imports of the internal `App\\` namespace.

## Plugin settings

Plugins optionally declare a non-empty `settings` list in their runtime manifest. Supported field types are `text`, `number`, `checkbox` and `select`; every field has a stable plugin-owned key and translated label, and may define a translated help text and default value. Number fields support `min`, `max` and `step`; select fields declare `options` with `value` and translated `label`.

The Plugin Manager shows **Configure** only when the manifest contains at least one valid field. Core renders and validates the form and stores values under the plugin name and field key. Deactivation preserves settings; complete uninstall removes them. Plugins read values through the public `PluginConfigurationInterface`; they do not access Core configuration tables directly.

## Translations and language packs

Core always provides German (`de`) and English (`en`). Every plugin package must provide a non-empty `translations/messages.de.yaml`; an official Companyware plugin must additionally provide `translations/messages.en.yaml`. Further catalogs are optional. Use stable message keys in Twig with `|trans` and in PHP through `TranslatorInterface`.

Plugin catalogs are registered while Symfony compiles the service container. Installing or removing a package invalidates that container; Git-based deployments must clear the production cache after changing plugin catalogs or packages.

A language-pack plugin additionally declares one or more locales in its manifest through `locales` and must contain a catalog for every declared locale. Only active language-pack plugins add their locales to the system-language selector. `plugins/LanguageSpanish` is the reference package: activating it makes Spanish (`es`) selectable. Domain plugins remain responsible for supplying their own messages for each locale they support; missing messages fall back to German.

## Services

`config/services.yaml` is imported automatically. Plugin services can implement `NavigationProviderInterface` or `SlotProviderInterface`; autoconfiguration tags them. A lifecycle service extends the public plugin base class and is explicitly tagged with the manifest name:

Dashboard plugins should prefer the additive `dashboard.continue`, `dashboard.quick_actions`, `dashboard.widgets` and `dashboard.activity` slots. The framing slots are `dashboard.content.before` and `dashboard.content.after`. For intentional structural changes, extend the stable `dashboard_header`, `dashboard_continue`, `dashboard_quick_actions`, `dashboard_widgets` or `dashboard_activity` Twig blocks; full template replacement remains available through the plugin override directory. Core renders the fallback dashboard whenever the replacing plugin is inactive. Documents contains the reference provider. Add a globally unique `data-dashboard-widget` identifier to personalizable contributions and place them in a `data-dashboard-zone`; Core then supplies server-persisted drag-and-drop ordering and immediate hiding per administrator. Hidden contributions remain available through the **Add widget** picker, while reset restores all plugin-defined defaults.

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Plugins\AcmeExample\:
        resource: '../src/'

    Plugins\AcmeExample\ExamplePlugin:
        tags:
            - { name: 'app.plugin.lifecycle', plugin: 'acme-example' }
```

```php
use Companyware\Core\Framework\Plugin\Plugin;

final class ExamplePlugin extends Plugin
{
    public function activate(): void
    {
        // Optional hook; dependencies are injected through Symfony.
    }
}
```

Symfony service decoration is supported only for public interfaces explicitly marked `@api` and documented as decoration-supported. The first such service is `Companyware\Core\Framework\File\FileNameNormalizerInterface`. Decorate the interface service ID and inject `.inner`; Documents provides the reference implementation:

```yaml
Plugins\Documents\DocumentsFileNameNormalizer:
    decorates: Companyware\Core\Framework\File\FileNameNormalizerInterface
    arguments:
        $inner: '@Plugins\Documents\DocumentsFileNameNormalizer.inner'
```

Container compilation rejects decoration of unpublished or protected services, including authentication, activation guards, compatibility/package validation and plugin recovery. Symfony autowiring and autoconfiguration remain the canonical backend registration mechanism.

## Core events

Plugins subscribe through Symfony's `EventSubscriberInterface`. Stable class-named events are `BeforePluginLifecycleEvent`, `AfterPluginLifecycleEvent` and `PluginConfigurationChangedEvent`; lifecycle operations use the `PluginLifecycleOperation` enum. Standard Symfony listener priorities apply, with higher numbers running first.

Before-events run synchronously before mutation and may abort by throwing. After/configuration events run after successful persistence; listener failures are logged and do not turn a completed mutation into a reported failure. Payloads are immutable.

```php
public static function getSubscribedEvents(): array
{
    return [BeforePluginLifecycleEvent::class => ['validateConfiguration', 100]];
}
```

## Routes

`config/routes.yaml` is imported automatically:

```yaml
acme_example_controllers:
    resource: '../src/Controller/'
    type: attribute
    prefix: /admin/example
    defaults: { _plugin: 'acme-example' }
```

The `_plugin` default is required; it prevents routes of inactive plugins from executing.

Plugin controllers must be registered for argument injection. Add Symfony's `#[AsController]` attribute to every controller, or define the controller directory explicitly in `services.yaml`:

```yaml
Plugins\AcmeExample\Controller\:
    resource: '../src/Controller/'
    autowire: true
    autoconfigure: true
    tags: ['controller.service_arguments']
```

## Twig

Active plugins receive a namespace derived from their name:

```twig
{% include '@AcmeExample/admin/index.html.twig' %}
```

Files below `templates/overrides/` take priority over core templates with the same relative path. Prefer blocks and slots over copying complete core templates.

Available core slots include:

- `document.stylesheets`
- `document.javascripts`
- `login.form.before` / `login.form.after`
- `admin.navigation.after`
- `admin.content.before` / `admin.content.after`
- `admin.profile.form.before` / `admin.profile.form.after`
- `admin.settings.fields`
- `admin.settings.sections`
- `dashboard.content.before` / `dashboard.content.after`
- `dashboard.continue`
- `dashboard.quick_actions`
- `dashboard.widgets`
- `dashboard.activity`

Assets declared in the manifest are loaded only while the plugin is active. They are delivered through an extensionless authenticated controller URL so nginx cannot bypass Symfony's plugin guard. Files remain confined to the plugin's `public/` directory.

Every asset has a globally unique handle and numeric priority. Higher numbers load later. A plugin can replace Core or another plugin asset explicitly:

```json
{
  "assets": {
    "scripts": [
      {
        "handle": "acme-admin-js",
        "path": "admin-replacement.js",
        "priority": 100,
        "replaces": ["core-admin-js"],
        "defer": true
      }
    ]
  }
}
```

Core handles currently available for replacement are `core-admin`, `core-plugin-manager` and `core-admin-js`. Replacing `core-admin-js` transfers responsibility for menu and password-toggle behavior to the plugin. Prefer extending `window.AdminFramework` hooks unless a complete replacement is intentional.

## Override policy

Templates, services, navigation, routes, slots and assets are extensible. Authentication guards, plugin activation checks and the plugin manager remain protected recovery infrastructure. Plugins are loaded from disk during container compilation, so deploy a new plugin with `composer dump-autoload` and `cache:clear`.

## Migrations

Migration classes implement `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\PluginMigrationInterface` with a stable version plus `up()` and `down()` methods. Versions are recorded as `<plugin-name>:<version>` and must never be edited after successful application: Companyware checksums the class, version and source. A failed migration may be repaired and retried under its version. `up()` runs in lexical order during deployment, installation or activation. A database-level named lock serializes concurrent batches per owner; persistent `running`, `completed` and `failed` states make interrupted work visible and retryable. Transactions are used when supported, but migrations must remain idempotent because MySQL and MariaDB can implicitly commit DDL statements.

Every package declares one explicit uninstall policy:

- `"uninstall": {"data": "remove"}` calls `down()` in reverse order, then removes migration and plugin-configuration records;
- `"uninstall": {"data": "retain"}` removes the package while retaining its database data, settings and migration records for reinstall.

Installation recovery always attempts `down()` for migrations from the failed installation, independent of that policy. After deploying code that adds the migration commands, clear the production cache before invoking them. Use `app:migrations:migrate --env=prod` during deployment and inspect state with `app:migrations:status --env=prod` or `--json`.

If a migration cannot safely reverse its changes, additionally implement `IrreversibleMigrationInterface` and return a concise operator-facing reason. Destructive rollback is then blocked. A plugin containing such a migration should declare `retain`; if installation fails after that migration ran, Companyware keeps the inactive package and recorded failure for manual recovery rather than pretending that cleanup succeeded.

## ZIP packages

Package either the plugin contents directly or one enclosing plugin directory. The installer accepts ZIP files up to 20 MB and rejects traversal paths, absolute paths, symbolic links, excessive file counts and expanded payloads above 100 MB. It validates `composer.json`, `plugin.json`, matching semantic versions and required translation catalogs before the package becomes visible. Failed migrations are rolled back before package files are removed.

The installer invalidates the compiled Symfony container after install/uninstall so newly supplied or removed services and routes take effect on the next request. For Git-based plugin deployment, rebuild explicitly:

```bash
composer dump-autoload --optimize
php bin/console cache:clear --env=prod
```
