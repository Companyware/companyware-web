# Security policy

## Supported versions

Companyware Web has no publicly supported release yet. The default branch is a
development snapshot and does not receive a production-support promise. Supported
versions and their end dates will be listed here before the first public release.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability and do not include real
credentials, personal data, recovery keys or production exports in a report.

Use GitHub private vulnerability reporting for the public repository when it is
enabled. If that channel is unavailable, contact the maintainers privately through
the contact method published by the Companyware GitHub organization. Include:

- the affected commit or released version;
- prerequisites and minimal reproduction steps;
- expected and observed impact;
- whether credentials or personal data may be involved;
- a safe way to contact the reporter.

The maintainers will acknowledge receipt, reproduce and classify the issue, agree
on coordinated disclosure and publish fixed-version guidance when a supported
release exists. A report is not confirmation of a vulnerability or a promised
timeline.

## Security boundaries

Plugin packages are code and must be reviewed before installation. A successful
build, signature or compatibility check is not a security approval. Installation,
activation, migrations and updates remain separate explicit operations.

