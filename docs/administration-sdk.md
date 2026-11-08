# Administration SDK 1.0

Companyware's administration SDK is a public TypeScript contract independent from Core and individual plugins. The browser-ready build is served at `/admin-sdk/companyware-admin-sdk.js`; semantic design tokens are served beside it. Core loads both before its own administration assets, and the same APIs are available to every active plugin as `window.CompanywareAdmin`.

## Technology direction

Vue 3 with TypeScript is the official default for interactive administration pages. It gives plugin developers a familiar component model and fast Vite feedback without making Vue a requirement for backend-only plugins. The SDK itself remains framework-independent: backend-only packages, Twig pages and small progressive enhancements do not need a Node build. The plugin generator creates both a working backend/Twig slice and optional ready-to-build Vue sources below `resources/administration/`.

External plugins are ported through the neutral `app:plugins:analyze-portability` workflow. Companyware exposes no foreign-named runtime globals, namespace aliases or compatibility facade. Transformable registrations target native `window.CompanywareAdmin` modules, components and services; unresolved domain dependencies require explicit adapters. See `plugin-portability.md`.

## Stable SDK surfaces

- `routes`, `navigation` and `slots` register additive UI capabilities.
- `modules` registers reusable modules and components.
- `permissions` exposes client-side presentation checks; server-side authorization remains mandatory.
- `api` provides same-origin or bearer-authenticated JSON requests and normalized problem errors.
- `notifications`, `forms`, `tables` and `modals` provide common UI behavior.
- `hooks` is the event bridge used by Core and existing plugins. `window.AdminFramework` remains a compatibility facade in API 1.x.

Every registration has a stable ID, package owner and semantic version. Duplicate IDs fail. Replacement must be explicit, the original registration must opt in through `replaceable`, and the expected major version must match. Contributions use priority followed by registration order, making composition deterministic.

Plugins must use the `--cw-*` semantic tokens rather than private Core selectors or hard-coded Companyware colors. Core owns the protected fallback values. A future theme package can override public tokens without taking ownership of login, plugin recovery or security controls.

## Public plugin frontends

Plugins may provide a public website on the main domain independently from the administration at `/admin`. An active plugin can implement `FrontendProviderInterface` to render `/`; other public routes are declared in its normal route file and retain the `_plugin` guard. When no active frontend provider supports `/`, Core redirects it to `/admin`, whose existing authentication guard then sends anonymous users to `/login`.

Twig is the default public frontend template system. It provides server rendering, SEO-friendly output and a Symfony-native extension model. Public plugins contribute Twig templates, slots, assets and semantic theme tokens. Vue is used as an optional island for focused interactions such as cart state, variant selection or live search; a fully headless client may consume `/api/v1`, but is not required for a normal plugin frontend.

Frontend providers never shadow `/admin`, `/login`, `/logout`, `/api`, `/plugin-assets` or `/admin-sdk`. Public controllers choose their own authentication model and must use application services rather than bypassing authorization or persistence contracts.
