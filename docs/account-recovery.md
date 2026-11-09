# Account recovery

Companyware supports two one-time recovery channels. An authenticated user can create a recovery key in **Profile**; it is displayed once, stored only as a SHA-256 hash, expires after 24 hours and is invalidated after use or replacement. A verified recovery email can receive the same short-lived one-time key in a link.

Existing `admin_user.email` values are deliberately not trusted after the migration. A user must save the address and complete the 30-minute verification link from the profile. The normalized address is unique only after successful verification. Recovery requests always return the same neutral response, whether an account exists, is disabled, has an unverified address, is rate-limited or delivery fails. The username is revealed only after the recovery key has been consumed and the password changed atomically. The existing administrator password-reset route remains the company fallback.

## Mail transport

Set secrets only in the production environment, never in Git or application logs:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@smtp.example.invalid:587?encryption=tls
COMPANYWARE_MAIL_FROM=no-reply@example.invalid
```

The committed default is `null://null`, so development and automated checks never send real mail. For a transport-neutral test use Symfony's in-memory/test transport in a test environment. Production operators must provide a valid `MAILER_DSN`, a sender authorized by their mail provider, TLS, and monitored bounce/abuse handling before enabling email enrollment.

## Security and operation

- Five recovery attempts per client fingerprint and UTC hour are accepted.
- Recovery and verification tokens contain 256 random bits and only hashes are stored.
- Token consumption and password replacement use one database transaction with row locking.
- Disabled accounts are rejected, and audits contain outcome metadata but never addresses, passwords or tokens.
- Passwords use the central `PasswordPolicy` and PHP's current `PASSWORD_DEFAULT` hash.

Deploy with `composer install --no-dev --optimize-autoloader`, clear the production cache and run `php bin/console app:migrations:migrate --env=prod` before using the feature.
