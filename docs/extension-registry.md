# Companyware extension registry protocol 1

The registry protocol is open and independent from any hosted Companyware service. `COMPANYWARE_EXTENSION_REGISTRY` points to an HTTPS JSON document with `format: companyware-extension-registry-1`, `packages` and `advisories`. Local files are accepted only when explicitly passed to CLI for development and tests.

Each package contains a stable `name` and a `versions` list. Every release contains `version`, the complete runtime `manifest`, an HTTPS `url`, byte `size`, lowercase SHA-256 `sha256`, `changelog`, and `signature` with `publisher`, `keyId` and base64 Ed25519 `value`. The signed payload is `companyware-package-v1`, package name, version and lowercase SHA-256 separated by newlines.

Publisher keys are pinned locally with `app:extensions:publisher trust`; registry metadata cannot make its own key trusted. Revocation is local and immediate. Downloads are size-bounded, checksum-verified and signature-verified before the existing package validator, complete dependency graph, migrations and update lifecycle run. The sodium PHP extension is required to install signed remote updates.

`app:extensions:updates --json` and `/admin/api/extensions` expose the same compatible update/advisory plan. `app:extensions:updates <plugin> --apply` operates only on an inactive plugin and leaves activation explicit. Registry releases incompatible with Companyware, PHP or the installed dependency graph are not offered. Security advisories contain `id`, `package`, `affected`, `severity`, `title` and optional `url`.

Trust state lives in `var/extension-trust.json` and belongs in protected operational backup, not source control. Registry operators may mirror the protocol; Companyware Core does not require one commercial store.
