# Changelog

All notable changes to Companyware Web will be recorded here. The project follows
semantic versioning for published framework releases; the public API has its own
compatibility version documented in `docs/public-api.md`.

## Unreleased

### Added

- Symfony-native plugin lifecycle, dependency resolution and versioned migrations.
- Public API 1.0, Administration SDK 1.0 and versioned HTTP API.
- Roles, scoped permissions, signed extension metadata and theme contracts.
- Documents reference plugin and plugin developer tooling.
- Visible Companyware Web product naming in login, administration, page titles and
  public metadata while retaining Companyware as the shared product family.

### Security

- Hashed personal access, OAuth, recovery and publisher-trust credentials.
- Central authorization, security audit and explicit package verification gates.

### Known limitations

- No public supported framework version has been tagged or published.
- Production verification remains separate from local release evidence.
- Desktop and Web are not a shared runtime; integration uses versioned APIs only.

