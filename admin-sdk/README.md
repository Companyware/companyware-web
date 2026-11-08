# Companyware administration SDK

`@companyware/admin-sdk` is the public, framework-independent browser contract for Companyware administration extensions. The checked-in distribution has no runtime dependency or build prerequisite and is loaded before Core and plugin administration scripts.

Plugins use `window.CompanywareAdmin` to register routes, navigation, extension slots and replaceable UI modules. Registrations carry an owner and semantic version. Duplicate identifiers are rejected; explicit replacement is allowed only for targets that opted into replacement and only when the expected major version matches.

The SDK also provides authenticated API access, permission checks, notifications, form serialization, table state and modal lifecycle. Semantic tokens live in `dist/companyware-admin-tokens.css` and are shared by Core and plugins.

```js
const admin = window.CompanywareAdmin;
admin.routes.register({
    id: 'acme.overview',
    owner: 'acme',
    version: '1.0.0',
    path: '/admin/acme',
    load: () => import('/plugin-assets/acme?path=overview.js')
});
admin.slots.add('dashboard.widgets', {
    id: 'acme.summary', owner: 'acme', version: '1.0.0', priority: 100,
    render: context => ({component: 'acme-summary', context})
});
```

Twig remains supported for login, email, print and suitable server-rendered pages. SDK registrations enhance or host independent administration pages without requiring Core to be rebuilt.
