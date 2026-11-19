## Outcome

Describe the user-visible result and what is explicitly out of scope.

## Web boundary

- [ ] This change belongs to Companyware Web.
- [ ] It does not claim to release Companyware Desktop.
- [ ] Public API, plugin compatibility and migrations are documented where affected.

## Verification

- [ ] `composer release-check`
- [ ] Reproducible release archive when release-relevant
- [ ] Production/browser checks are listed separately and not inferred from local tests

Actual evidence and unverified checks:

## Security, data and recovery

- [ ] No credentials, personal data, production exports or private keys are included.
- [ ] Deployment, migration, backup and rollback effects are documented.
