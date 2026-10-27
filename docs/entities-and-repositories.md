# Entities and repositories

Companyware entities use one public model in `Companyware\Core\Framework\Data`.

- Entity identifiers are opaque strings at contract boundaries. Existing numeric database keys are converted to strings by the repository.
- Entity timestamps are immutable UTC values. `createdAt` never changes; every write advances `updatedAt`.
- `version` starts at 1 and is checked by write repositories. A stale write throws `OptimisticLockException`.
- Removable domain data is soft-deleted by default through `deletedAt`. Queries exclude deleted rows unless `EntityQuery::includeDeleted` is true.
- `EntityMetadata` declares `core` or the owning plugin. Only that owner writes the entity and its schema.

Domain and application services depend on owner-defined repository interfaces. Concrete persistence adapters remain replaceable services. `EntityQuery` deliberately exposes only filters and sorts interpreted by the owning repository; callers never submit SQL, table names or ORM expressions.

Doctrine ORM/DBAL is the preferred adapter for new packages. The current minimal Core PDO connection remains a supported transition adapter behind `ConnectionProviderInterface`; it is not a domain-service API. This keeps the public contracts independent of a particular Doctrine major version while the existing schema is migrated incrementally.

Cross-plugin entity relations must be listed in `plugin.json` and backed by a required plugin dependency:

```json
{
  "require": {"contacts": "^1.0"},
  "entities": {"references": ["contacts"]}
}
```

Package validation rejects undeclared, duplicate and self references before installation. Integrations should still target a public contract of the providing plugin rather than its concrete repository or table.

The plugin test kit exposes `SchemaTestHelper` for required-column checks and `RepositoryTestHelper` for basic persistence round trips. Plugins may wrap them in PHPUnit, Pest or another runner without coupling Companyware's public API to a test framework.
