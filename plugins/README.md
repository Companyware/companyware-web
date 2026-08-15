# Plugin development

Plugins are self-contained Symfony modules below `plugins/`. Their PHP namespace starts with `Plugins\\`, followed by the directory name. The framework registers this isolated class loader before Symfony compiles its container.

```text
plugins/AcmeExample/
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

## Manifest

```json
{
  "name": "acme-example",
  "label": "Acme Example",
  "description": "Adds an administration capability.",
  "version": "1.0.0",
  "migrations": ["Plugins\\AcmeExample\\Migration\\Version202608150001"],
  "navigation": [
    {"section": "PLUGINS", "label": "Example", "route": "acme_example_index", "icon": "E", "position": 100}
  ]
}
```

## Services

`config/services.yaml` is imported automatically. Plugin services can implement `NavigationProviderInterface` or `SlotProviderInterface`; autoconfiguration tags them. A lifecycle service is explicitly tagged with the manifest name:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Plugins\AcmeExample\:
        resource: '../src/'

    Plugins\AcmeExample\ExampleLifecycle:
        tags:
            - { name: 'app.plugin.lifecycle', plugin: 'acme-example' }
```

Symfony service decoration is the supported way to replace core PHP behavior. Direct edits to core services are not required.

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

Render an asset of an active plugin with `{{ plugin_asset('acme-example', 'example.css') }}`. Assets are confined to that plugin's `public/` directory.

## Override policy

Templates, services, navigation, routes, slots and assets are extensible. Authentication guards, plugin activation checks and the plugin manager remain protected recovery infrastructure. Plugins are loaded from disk during container compilation, so deploy a new plugin with `composer dump-autoload` and `cache:clear`.

## Migrations

Migration classes implement `App\\Plugin\\Migration\\PluginMigrationInterface` with a stable version plus `up()` and `down()` methods. Versions are recorded as `<plugin-name>:<version>`. `up()` runs during installation/activation; complete uninstall calls `down()` in reverse order. Migrations must be idempotent because MySQL can implicitly commit DDL statements.

## ZIP packages

Package either the plugin contents directly or one enclosing plugin directory. The installer accepts ZIP files up to 20 MB and rejects traversal paths, absolute paths, symbolic links, excessive file counts and expanded payloads above 100 MB. `plugin.json` must contain a lowercase kebab-case name, label and semantic version.

The installer invalidates the compiled Symfony container after install/uninstall so newly supplied or removed services and routes take effect on the next request. For Git-based plugin deployment, rebuild explicitly:

```bash
composer dump-autoload --optimize
php bin/console cache:clear --env=prod
```
