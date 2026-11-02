# Companyware public API 1.0

Entity and repository conventions are documented in [entities-and-repositories.md](entities-and-repositories.md).

Companyware plugins may depend only on symbols below the `Companyware\\Core\\` namespace that are explicitly marked `@api`. The current contract version is exposed by `Companyware\\Core\\Framework\\Api\\PublicApi::VERSION` and by `extra.companyware.public-api` in the root `composer.json`.

## Stable contracts

- `Companyware\Core\Framework\Data\EntityInterface`
- `Companyware\Core\Framework\Data\EntityMetadata`
- `Companyware\Core\Framework\Data\EntityQuery`
- `Companyware\Core\Framework\Data\RepositoryInterface`
- `Companyware\Core\Framework\Data\WritableRepositoryInterface`
- `Companyware\Core\Framework\Data\ConnectionProviderInterface`
- `Companyware\Core\Framework\Data\OptimisticLockException`
- `Companyware\Core\Test\Data\SchemaTestHelper`
- `Companyware\Core\Test\Data\RepositoryTestHelper`
- `Companyware\Core\Test\Plugin\PackageStructureTestHelper`
- `Companyware\Core\Framework\Application\ActorContextInterface`
- `Companyware\Core\Framework\Application\PageRequest`
- `Companyware\Core\Framework\Application\PageResult`
- `Companyware\Core\Framework\Security\AuthorizationInterface`
- `Companyware\Core\Framework\Security\RequiresPrivilege`
- `Companyware\Core\Framework\Security\PrivilegeAwareInterface`

- `Companyware\\Core\\Framework\\Plugin\\Contract\\PluginLifecycleInterface`
- `Companyware\\Core\\Framework\\Plugin\\Plugin`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\NavigationProviderInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\SlotProviderInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\PluginMigrationInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\IrreversibleMigrationInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\MigrationStateProviderInterface`
- `Companyware\\Core\\Framework\\Plugin\\Contract\\Migration\\MigrationState`
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
| Runtime manifest | `name`, `label`, `description`, `version`, `navigation`, `migrations`, `assets`, `uninstall.data`, optional `locales`, `compatibility`, `require`, `optional`, `conflict`, `provide`, `replace`, optional `settings` | stable in API 1.x |
| Translation catalogs | plugin-owned `translations/messages.<locale>.yaml` catalogs using stable message keys | stable in API 1.x |
| Twig slots | `document.stylesheets`, `document.javascripts`, `login.form.before`, `login.form.after`, `admin.navigation.after`, `admin.content.before`, `admin.content.after`, `admin.profile.form.before`, `admin.profile.form.after`, `admin.settings.fields`, `admin.settings.sections`, `dashboard.content.before`, `dashboard.continue`, `dashboard.quick_actions`, `dashboard.widgets`, `dashboard.activity`, `dashboard.content.after` | stable in API 1.x |
| Twig blocks | `head`, `title`, `stylesheets`, `body_class`, `body`, `javascripts`, `login_visual`, `login_form`, `sidebar`, `topbar`, `content`, `dashboard_header`, `dashboard_continue`, `dashboard_quick_actions`, `dashboard_widgets`, `dashboard_activity` | stable in API 1.x |
| Asset handles | `core-admin`, `core-plugin-manager`, `core-admin-js` plus plugin-owned globally unique handles | stable in API 1.x |
| Browser hook API | `window.AdminFramework.on()`, `emit()` and the `ready`, `dashboard:ready` and `dashboard:storage-error` events | stable in API 1.x |
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

## Versioned migrations

Core and plugin migrations share the stable `PluginMigrationInterface`. Versions are immutable within one owner and run in lexical order. Companyware derives a SHA-256 checksum from the class, version and source file; changing a successfully applied migration is rejected. Failed migrations may be repaired and retried under the same version because their failure remains explicit. A database-level named lock serializes each owner's migration batch, while the primary key gives a second exactly-once boundary. Transactions are used when the database operation supports them; MySQL/MariaDB DDL can commit implicitly, so migrations must remain idempotent and recoverable on retry.

Migration state is persisted as `running`, `completed` or `failed`, with direction, checksum, timestamps and a bounded error message. Failed/running states are visible through `MigrationStateProviderInterface`, `app:migrations:status`, the authenticated `/admin/api/migrations` endpoint and Plugin Manager. `app:migrations:migrate` applies pending Core and installed-plugin migrations and is the deployment command.

Every newly packaged plugin explicitly declares `uninstall.data` as `remove` or `retain`. `remove` executes `down()` in reverse order and deletes migration/configuration state only after success. `retain` removes code and activation state while preserving plugin-owned data, settings and completed migration records for a later reinstall. A migration that cannot safely implement `down()` also implements `IrreversibleMigrationInterface` with an operator-facing reason; Companyware then blocks destructive rollback. Such plugins must use the `retain` policy for normal uninstall. Packages created before this field was mandatory retain the historical `remove` interpretation when read, but the package validator requires the explicit field for new installation. Installation recovery always attempts reverse migration regardless of the uninstall policy and preserves the package for manual recovery if an irreversible migration prevents cleanup.

## Dashboard extension

Core owns the authenticated dashboard route and always renders a usable welcome area, a quiet system-status strip, quick access and process-oriented sections. Active plugins add server-rendered continuation cards through `dashboard.continue`, contextual actions through `dashboard.quick_actions`, overview cards through `dashboard.widgets` and recent events through `dashboard.activity`. `dashboard.content.before` and `dashboard.content.after` frame the Core content. Slot contributions are additive and disappear automatically while their owning plugin is inactive. Providers receive the current administrator and installed-plugin collection as slot context, allowing context-aware output without importing internal Core services.

Structural customization uses the stable `dashboard_header`, `dashboard_continue`, `dashboard_quick_actions`, `dashboard_widgets` and `dashboard_activity` Twig blocks. A plugin may override one block through Twig inheritance or deliberately replace the dashboard template through its documented override path. Core remains the fallback when that plugin is disabled. Documents is the reference provider for every process-oriented slot.

Every contributed dashboard element that participates in personalization uses a globally unique `data-dashboard-widget` identifier and belongs to a `data-dashboard-zone`. Core persists drag-and-drop order and hidden widget identifiers server-side per administrator. Hiding takes effect immediately and survives browsers and sessions. While customization is active, **Add widget** lists the currently hidden contributions and restores only the selected widget; reset restores all plugin-defined defaults. The `dashboard:ready` browser hook exposes the dashboard element and resolved state. Role-based defaults may be added compatibly once the authorization contracts are public.

## Supported platform

- PHP 8.3
- Symfony 6.4 LTS
- MySQL and MariaDB using InnoDB and `utf8mb4`
- the current and previous stable major versions of Chrome, Edge, Firefox and Safari

Browser support is rolling: production defects are accepted only when reproducible on a currently supported browser version. Internet Explorer and browsers without vendor security support are excluded.
