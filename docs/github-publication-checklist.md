# GitHub publication checklist — Companyware Web

This checklist prepares a release; it does not authorize a GitHub repository,
push, tag, production deployment or public release.

The historical `Companyware/companyware` repository belongs to an obsolete Java
Desktop snapshot. It must not be reused for Companyware Web. `companyware-public`
is outside this GitHub publication scope.

## Exact candidate

- Product: **Companyware Web**
- Candidate: `0.1.0-preview.1`
- Commit: fill from the final clean `git rev-parse HEAD`
- Proposed public repository: `Companyware/companyware-web`
- Companyware Desktop: explicitly excluded

## Repository-owned gates

- [ ] Working tree is clean and synchronized with `git pull --ff-only`.
- [ ] PHP 8.3 `composer install` and `composer release-check` pass.
- [ ] `php tools/build-release.php 0.1.0-preview.1 --verify-reproducible`
      produces matching repeated archives, checksum and JSON evidence.
- [ ] README, LICENSE, SECURITY, SUPPORT, CONTRIBUTING, CHANGELOG, public API,
      upgrade/recovery and version-bound release notes are current.
- [ ] The archive contains no environment files, secrets, caches, database
      exports, private publisher keys or production data.

## External gates

- [ ] Create and review the public repository and organization profile.
- [ ] Enable private vulnerability reporting and branch/tag protection.
- [ ] GitHub dependency/Dependabot findings contain no unresolved critical or
      high issue for the exact candidate.
- [ ] Production installation, migrations, cache build, plugin validation,
      restricted authorization denial and recovery are verified by the operator.
- [ ] Any later external download reference identifies the same version, commit
      and SHA-256; `companyware-public` is not a GitHub publication repository.
- [ ] Formal name/trademark/domain review is recorded before public launch.

## Approved publication sequence

1. Record the exact verified commit and evidence-manifest SHA-256.
2. Obtain explicit approval for public repository creation or update.
3. Push only the selected reviewed commit to the public `main` branch.
4. Create annotated tag `v0.1.0-preview.1` on that same commit.
5. Publish a GitHub **pre-release** with the verified ZIP, `.sha256` and JSON
   evidence files.
6. Record the public commit, tag, release URL and production verification scope
   in Symfony Knowledge.
