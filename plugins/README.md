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
    {"section": "PLUGINS", "label": "Example", "route": "acme_example_index", "icon": "E", "position": 100}
  ]
}
```

All runtime versions are semantic versions. Constraints support exact versions, comparison operators, caret and tilde ranges, wildcards, AND expressions separated by spaces or commas, and `||` alternatives. `require` dependencies must be present during installation and active during activation. An installed optional integration is version-checked, while an absent one is accepted. `provide` and `replace` expose a virtual package at one concrete semantic version.

The canonical compatibility resolver validates the complete prospective graph before installation, activation, deactivation or uninstall changes files, migrations, lifecycle state or the database. It checks Companyware and PHP versions, extensions, dependencies, conflicts, replacements and cycles and returns the same structured diagnostics for administration and future CLI or Store consumers.

Validate the currently installed graph through the same resolver used by administration:

```bash
php bin/console app:plugins:validate --env=prod
```

## Plugin settings

Plugins optionally declare a non-empty `settings` list in their runtime manifest. Supported field types are `text`, `number`, `checkbox` and `select`; every field has a stable plugin-owned key and translated label, and may define a translated help text and default value. Number fields support `min`, `max` and `step`; select fields declare `options` with `value` and translated `label`.

The Plugin Manager shows **Configure** only when the manifest contains at least one valid field. Core renders and validates the form and stores values under the plugin name and field key. Deactivation preserves settings; complete uninstall removes them. Plugins read values through the public `PluginConfigurationInterface`; they do not access Core configuration tables directly.

## Translations and language packs

Core always provides German (`de`) and English (`en`). Every plugin package must provide a non-empty `translations/messages.de.yaml`; an official Companyware plugin must additionally provide `translations/messages.en.yaml`. Further catalogs are optional. Use stable message keys in Twig with `|trans` and in PHP through `TranslatorInterface`.

Plugin catalogs are registered while Symfony compiles the service container. Installing or removing a package invalidates that container; Git-based deployments must clear the production cache after changing plugin catalogs or packages.

A language-pack plugin additionally declares one or more locales in its manifest through `locales` and must contain a catalog for every declared locale. Only active language-pack plugins add their locales to the system-language selector. `plugins/LanguageSpanish` is the reference package: activating it makes Spanish (`es`) selectable. Domain plugins remain responsible for supplying their own messages for each locale they support; missing messages fall back to German.

## Services

`config/services.yaml` is imported automatically. Plugin services can implement `NavigationProviderInterface` or `SlotProviderInterface`; autoconfiguration tags them. A lifecycle service extends the public plugin base class and is explicitly tagged with the manifest name:

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

Symfony service decoration is supported only for public interfaces explicitly marked `@api`. No Core service identifier is public yet; CW-004 will publish the first decoration-ready Core services. Plugins must never decorate protected recovery services.

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

Migration classes implement `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\PluginMigrationInterface` with a stable version plus `up()` and `down()` methods. Versions are recorded as `<plugin-name>:<version>`. `up()` runs during installation/activation; complete uninstall calls `down()` in reverse order. Migrations must be idempotent because MySQL and MariaDB can implicitly commit DDL statements.

## ZIP packages

Package either the plugin contents directly or one enclosing plugin directory. The installer accepts ZIP files up to 20 MB and rejects traversal paths, absolute paths, symbolic links, excessive file counts and expanded payloads above 100 MB. It validates `composer.json`, `plugin.json`, matching semantic versions and required translation catalogs before the package becomes visible. Failed migrations are rolled back before package files are removed.

The installer invalidates the compiled Symfony container after install/uninstall so newly supplied or removed services and routes take effect on the next request. For Git-based plugin deployment, rebuild explicitly:

```bash
composer dump-autoload --optimize
php bin/console cache:clear --env=prod
```
