# Upgrade and recovery

Companyware Web upgrades are operator-controlled. Never replace a production tree
with an unverified working-directory snapshot. Use an immutable release archive or
exact Git commit whose checksum, release notes and compatibility information have
been reviewed.

## Before an upgrade

1. Read the release notes, compatibility policy and known limitations.
2. Back up the database, `.env.local`, uploaded/plugin-owned files and the current
   application release. Test that the backup can be restored.
3. Record active plugins, migration status and the current commit or artifact
   checksum.
4. Verify PHP 8.3, required extensions, database and browser support.
5. Keep an authenticated recovery account and its protected recovery path
   available outside the ordinary plugin workflow.

## Apply an upgrade

Use the installation's explicit PHP 8.3 binary for every command:

```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod --no-debug
php bin/console app:migrations:migrate --env=prod --no-interaction
php bin/console app:migrations:status --env=prod
php bin/console app:plugins:validate --env=prod
```

Run these commands only after the new code and matching dependency lock are in
place. Do not activate or update plugins merely to satisfy a failed dependency
graph; inspect the diagnostic and restore a compatible set deliberately.

## Verify

Verify login, protected administration, recovery, one restricted authorization
denial, the public OpenAPI document, plugin listing and the release-specific
workflow. Local release checks never replace this production observation.

## Recover

If code or dependency installation fails before migrations, restore the previous
release tree and lock file, reinstall its dependencies and clear its cache. If a
migration starts, inspect `app:migrations:status` before retrying: failed migrations
remain explicit and are designed for idempotent repair. Do not silently edit a
completed migration or its checksum.

Restore the database and plugin-owned files together when rollback requires a
backup. Preserve logs and the failed release evidence without copying secrets into
an issue. For an account lockout, use the documented Core recovery path rather
than bypassing authorization or changing password hashes manually.

