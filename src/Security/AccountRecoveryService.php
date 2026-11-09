<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use DateTimeImmutable;
use PDO;
use Throwable;
use Companyware\Core\Framework\Mail\MailTransportInterface;

final class AccountRecoveryService
{
    private const KEY_TTL = '+24 hours';
    private const MAX_ATTEMPTS_PER_HOUR = 5;

    public function __construct(
        private readonly Database $database,
        private readonly Schema $schema,
        private readonly SecurityAuditLogger $audit,
        private readonly MailTransportInterface $mailer,
    ) {}

    /** @return array{key:string,expires_at:string} */
    public function issue(int $adminId): array
    {
        $this->schema->ensure();
        $database = $this->database->connection();
        $active = $database->prepare('SELECT active FROM admin_user WHERE id = :id');
        $active->execute(['id' => $adminId]);
        if ((int) $active->fetchColumn() !== 1) throw new \RuntimeException('Account recovery is unavailable.');

        $key = 'CW1-'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = new DateTimeImmutable(self::KEY_TTL);
        $database->beginTransaction();
        try {
            $revoke = $database->prepare('UPDATE account_recovery_key SET used_at = UTC_TIMESTAMP() WHERE admin_user_id = :id AND used_at IS NULL');
            $revoke->execute(['id' => $adminId]);
            $insert = $database->prepare('INSERT INTO account_recovery_key (admin_user_id, token_hash, expires_at) VALUES (:id, :hash, :expires)');
            $insert->execute(['id' => $adminId, 'hash' => hash('sha256', $key), 'expires' => $expiresAt->format('Y-m-d H:i:s')]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
        $this->audit->log('security.account_recovery.issued', 'admin_user', (string) $adminId, ['expires_at' => $expiresAt->format(DATE_ATOM)]);

        return ['key' => $key, 'expires_at' => $expiresAt->format(DATE_ATOM)];
    }

    public function requestByEmail(string $email, string $recoveryUrl, string $clientFingerprint): void
    {
        $this->schema->ensure();
        if (!$this->allowAttempt($clientFingerprint)) {
            $this->audit->log('security.account_recovery.requested', 'account_recovery', null, ['delivery' => 'not_sent']);
            return;
        }
        $normalized = self::normalizeEmail($email);
        $statement = $this->database->connection()->prepare('SELECT id, email_normalized FROM admin_user WHERE email_normalized = :email AND email_verified_at IS NOT NULL AND active = 1');
        $statement->execute(['email' => $normalized]);
        $admin = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($admin)) {
            $this->audit->log('security.account_recovery.requested', 'account_recovery', null, ['delivery' => 'not_sent']);
            return;
        }
        $issued = $this->issue((int) $admin['id']);
        try {
            $this->mailer->send((string) $admin['email_normalized'], 'Companyware account recovery', "Open this link within 24 hours to recover your account:\n\n".$recoveryUrl.'?key='.rawurlencode($issued['key'])."\n\nIf you did not request this, ignore this message.");
            $this->audit->log('security.account_recovery.requested', 'admin_user', (string) $admin['id'], ['delivery' => 'mail']);
        } catch (Throwable) {
            $this->audit->log('security.account_recovery.requested', 'admin_user', (string) $admin['id'], ['delivery' => 'failed']);
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @return array{username:string}|null */
    public function consume(string $key, string $password, string $clientFingerprint): ?array
    {
        $this->schema->ensure();
        if (!PasswordPolicy::accepts($password) || !$this->allowAttempt($clientFingerprint)) {
            $this->audit->log('security.account_recovery.rejected', 'account_recovery', null, ['reason' => 'invalid_or_limited']);
            return null;
        }

        $database = $this->database->connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare('SELECT k.id, k.admin_user_id, u.username, u.active
                FROM account_recovery_key k INNER JOIN admin_user u ON u.id = k.admin_user_id
                WHERE k.token_hash = :hash AND k.used_at IS NULL AND k.expires_at >= UTC_TIMESTAMP() FOR UPDATE');
            $statement->execute(['hash' => hash('sha256', trim($key))]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record) || (int) $record['active'] !== 1) {
                $database->rollBack();
                $this->audit->log('security.account_recovery.rejected', 'account_recovery', null, ['reason' => 'invalid_or_limited']);
                return null;
            }
            $update = $database->prepare('UPDATE admin_user SET password_hash = :password WHERE id = :id AND active = 1');
            $update->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $record['admin_user_id']]);
            $consume = $database->prepare('UPDATE account_recovery_key SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL');
            $consume->execute(['id' => $record['id']]);
            if ($update->rowCount() !== 1 || $consume->rowCount() !== 1) throw new \RuntimeException('Recovery state changed concurrently.');
            $database->commit();
            $this->audit->log('security.account_recovery.completed', 'admin_user', (string) $record['admin_user_id']);
            return ['username' => (string) $record['username']];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    private function allowAttempt(string $clientFingerprint): bool
    {
        $database = $this->database->connection();
        $bucket = hash('sha256', 'account-recovery|'.$clientFingerprint);
        $window = gmdate('Y-m-d H:00:00');
        $statement = $database->prepare('INSERT INTO account_recovery_rate_limit (bucket_hash, window_start, attempt_count)
            VALUES (:bucket, :window, 1) ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1');
        $statement->execute(['bucket' => $bucket, 'window' => $window]);
        $read = $database->prepare('SELECT attempt_count FROM account_recovery_rate_limit WHERE bucket_hash = :bucket AND window_start = :window');
        $read->execute(['bucket' => $bucket, 'window' => $window]);
        return (int) $read->fetchColumn() <= self::MAX_ATTEMPTS_PER_HOUR;
    }
}
