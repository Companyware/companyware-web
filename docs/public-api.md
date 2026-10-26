# Companyware public API 1.0

Companyware plugins may depend only on symbols below the `Companyware\\Core\\` namespace that are explicitly marked `@api`. The current contract version is exposed by `Companyware\\Core\\Framework\\Api\\PublicApi::VERSION` and by `extra.companyware.public-api` in the root `composer.json`.

## Stable contracts

- `Companyware\\Core\\Framework\\Plugin\\Contract\\PluginLifecycleInterface`
- `Companyware\\Core\\Framework\\Plugin\\Plugin`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\NavigationProviderInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\SlotProviderInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\PluginMigrationInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\PluginConfigurationInterface`
- `Companyware\\Core\\Framework\\File\\FileNameNormalizerInterface` (decoration supported)
- `Companyware\\Core\\Framework\\Plugin\\Event\\BeforePluginLifecycleEvent`
- `Companyware\\Core\\Framework\\Plugin\\Event\\AfterPluginLifecycleEvent`
- `Companyware\\Core\\Framework\\Plugin\\Event\\PluginConfigurationChangedEvent`
- `Companyware\\Core\\Framework\\Plugin\\Event\\PluginLifecycleOperation`
- documented manifest fields, Twig blocks and slots, asset handles and the `window.AdminFramework` hooks listed in `plugins/README.md`

## Extension-point inventory

| Surface | Identifiers | Status |
| --- | --- | --- |
| PHP contracts | the interfaces, events, enum and `Plugin` base class listed above | stable in API 1.x |
| Composer package | package type `companyware-plugin` and `extra.companyware-plugin.manifest` | stable in API 1.x |
| Runtime manifest | `name`, `label`, `description`, `version`, `navigation`, `migrations`, `assets`, optional `locales`, `compatibility`, `require`, `optional`, `conflict`, `provide`, `replace`, optional `settings` | stable in API 1.x |
| Translation catalogs | plugin-owned `translations/messages.<locale>.yaml` catalogs using stable message keys | stable in API 1.x |
| Twig slots | `document.stylesheets`, `document.javascripts`, `login.form.before`, `login.form.after`, `admin.navigation.after`, `admin.content.before`, `admin.content.after`, `admin.profile.form.before`, `admin.profile.form.after`, `admin.settings.fields`, `admin.settings.sections` | stable in API 1.x |
| Twig blocks | `head`, `title`, `stylesheets`, `body_class`, `body`, `javascripts`, `login_visual`, `login_form`, `sidebar`, `topbar`, `content` | stable in API 1.x |
| Asset handles | `core-admin`, `core-plugin-manager`, `core-admin-js` plus plugin-owned globally unique handles | stable in API 1.x |
| Browser hook API | `window.AdminFramework.on()`, `emit()` and the `ready` event | stable in API 1.x |
| Theme registry and semantic theme package contract | planned for the platform foundation | experimental; not yet public API |
| Core service decoration | `FileNameNormalizerInterface`; decorate the public interface service ID | stable in API 1.x |
| Core events | lifecycle before/after events and configuration-changed event listed above | stable in API 1.x |

No other experimental surface is currently published.

The `App\\` namespace is internal application implementation. Plugins must not import it, decorate it or treat its service identifiers as stable. Only public interfaces explicitly documented as decoration-supported may be decorated. Container compilation rejects decoration of unpublished or protected services. Authentication, authorization, plugin activation checks, package validation, the plugin manager and fallback rendering are protected recovery paths and are not replaceable.

Lifecycle events use their FQCN as the Symfony event name. Before-events run synchronously in priority order before mutation; a listener exception aborts the operation. After-events and configuration-changed events run synchronously after successful persistence. Their listener failures are logged and cannot roll back or report failure for an already completed mutation. Event payloads are immutable; plugins coordinate behavior through services rather than mutating events.

## Compatibility and deprecation

- A Companyware minor release may add optional capabilities through new interfaces, add manifest fields with safe defaults and add events or extension points.
- Existing API 1.x method signatures, documented manifest semantics and extension-point identifiers remain compatible throughout the 1.x line.
- Public contracts are deprecated for at least one minor release before removal when a compatible migration path exists.
- Removing or incompatibly changing a public contract requires the next major public API version.
- Experimental APIs must be explicitly labelled and carry no compatibility promise until promoted.
- Anything not marked `@api`, including every `App\\` symbol, is internal and may change without a deprecation period.

## Ownership and isolation

Core owns platform bootstrapping, security and recovery. A plugin owns its domain code, data, templates and assets and communicates with Core through the contracts above. Cross-plugin integration requires an explicit dependency and a public contract owned by the providing plugin. A plugin must not read another plugin's private classes, files, tables or container services.

## Package and lifecycle baseline

Each plugin is a Composer package of type `companyware-plugin`. Its Composer metadata points to `plugin.json` through `extra.companyware-plugin.manifest`. The Composer version, when present, must equal the runtime-manifest version. Path repositories are supported for development; ordinary Composer repositories can distribute the same package unchanged.

The platform version is exposed by `Companyware\Core\Framework\Api\PlatformVersion::VERSION`. Plugin manifests declare compatible Companyware and PHP versions, required PHP extensions, required or optional plugin packages, conflicts and virtual packages. Companyware resolves the complete prospective graph before any package, migration, lifecycle or activation-state mutation.

Lifecycle services should extend `Companyware\\Core\\Framework\\Plugin\\Plugin`. Its no-op lifecycle methods allow a plugin to override only required hooks. Activation state is persisted only after migrations and the activation hook succeed. Failed ZIP installation rolls back applied plugin migrations and removes the staged package.

## Supported platform

- PHP 8.3
- Symfony 6.4 LTS
- MySQL and MariaDB using InnoDB and `utf8mb4`
- the current and previous stable major versions of Chrome, Edge, Firefox and Safari

Browser support is rolling: production defects are accepted only when reproducible on a currently supported browser version. Internet Explorer and browsers without vendor security support are excluded.
