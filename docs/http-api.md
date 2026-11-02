# Companyware HTTP API v1

The public contract is served as OpenAPI 3.1 at `/api/v1/openapi.json`. Active plugins contribute their resource operations through `Companyware\Core\Framework\Api\ApiOperationProviderInterface`; Core merges them with protected platform operations. A plugin must keep its controller behavior and contributed OpenAPI operations synchronized.

## Authentication and authorization

Personal access tokens are created for interactive users with `app:api:token:create` and managed with `app:api:token:manage`. Machine integrations use OAuth2 client credentials. Create, list and revoke clients with:

```bash
php bin/console app:api:oauth-client:manage USERNAME --create="Integration name" --json
php bin/console app:api:oauth-client:manage USERNAME --json
php bin/console app:api:oauth-client:manage USERNAME --revoke=ID --json
```

The client secret is returned only once. Exchange it with HTTP Basic authentication at `POST /api/v1/oauth/token` using `grant_type=client_credentials`. OAuth access tokens and personal tokens enter the same actor context and therefore share authorization, auditing, revocation semantics and the per-token limit of 120 requests per minute. Companyware 0.1 deliberately supports personal tokens and OAuth2 client credentials; interactive delegated authorization-code/PKCE is deferred until a first-party client requires a consent surface.

## Requests

Collection pagination uses bounded `limit` and non-negative `offset`. Resources may document explicit filters and sorting fields; unknown values return a problem response rather than reaching persistence. Errors below `/api/v1` use RFC 7807-style JSON with `type`, `title`, `status` and a safe `detail`.

Mutating operations accept an optional `Idempotency-Key` header. Keys are scoped to the authenticated actor, retained for 24 hours and may contain letters, digits, `.`, `_`, `:`, and `-` up to 150 characters. Repeating the same request returns the stored status/body with `Idempotency-Replayed: true`; reusing a key for a different request returns `409 Conflict`.

## Plugin example

Implement `ApiOperationProviderInterface`, return OpenAPI path items relative to `/api/v1`, and register the class as a normal autoconfigured plugin service. The Core tag and registry are public; internal `App\Api` services are not. Controllers must call authorization-enforcing application services shared with UI and CLI adapters rather than duplicating domain behavior.
