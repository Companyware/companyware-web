<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Mail\MailTransportInterface;
use DateTimeImmutable;
use PDO;
use Throwable;

final class AccountEmailVerificationService
{
    public function __construct(private readonly Database $database, private readonly Schema $schema, private readonly SecurityAuditLogger $audit, private readonly MailTransportInterface $mailer) {}

    public function send(int $adminId, string $email, string $verificationUrl): void
    {
        $this->schema->ensure();
        $normalized = AccountRecoveryService::normalizeEmail($email);
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('profile.email_invalid');
        $duplicate = $this->database->connection()->prepare('SELECT COUNT(*) FROM admin_user WHERE email_normalized = :email AND id <> :id');
        $duplicate->execute(['email' => $normalized, 'id' => $adminId]);
        if ((int) $duplicate->fetchColumn() > 0) throw new \InvalidArgumentException('profile.email_unavailable');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = new DateTimeImmutable('+30 minutes');
        $revoke = $this->database->connection()->prepare('UPDATE account_email_verification SET used_at = UTC_TIMESTAMP() WHERE admin_user_id = :id AND used_at IS NULL');
        $revoke->execute(['id' => $adminId]);
        $insert = $this->database->connection()->prepare('INSERT INTO account_email_verification (admin_user_id, email_normalized, token_hash, expires_at) VALUES (:id, :email, :hash, :expires)');
        $insert->execute(['id' => $adminId, 'email' => $normalized, 'hash' => hash('sha256', $token), 'expires' => $expires->format('Y-m-d H:i:s')]);
        $this->mailer->send($normalized, 'Verify your Companyware recovery email', "Confirm this recovery email within 30 minutes:\n\n".$verificationUrl.'?token='.rawurlencode($token)."\n\nIf you did not request this, ignore this message.");
        $this->audit->log('security.email_verification.sent', 'admin_user', (string) $adminId);
    }

    public function verify(string $token): bool
    {
        $this->schema->ensure();
        $database = $this->database->connection();
        $database->beginTransaction();
        try {
            $find = $database->prepare('SELECT id, admin_user_id, email_normalized FROM account_email_verification WHERE token_hash = :hash AND used_at IS NULL AND expires_at >= UTC_TIMESTAMP() FOR UPDATE');
            $find->execute(['hash' => hash('sha256', trim($token))]);
            $record = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) { $database->rollBack(); return false; }
            $update = $database->prepare('UPDATE admin_user SET email = :email_value, email_normalized = :email_normalized, email_verified_at = UTC_TIMESTAMP() WHERE id = :id_value AND active = 1 AND NOT EXISTS (SELECT 1 FROM admin_user other WHERE other.email_normalized = :email_unique AND other.id <> :id_unique)');
            $update->execute(['email_value' => $record['email_normalized'], 'email_normalized' => $record['email_normalized'], 'id_value' => $record['admin_user_id'], 'email_unique' => $record['email_normalized'], 'id_unique' => $record['admin_user_id']]);
            if ($update->rowCount() !== 1) { $database->rollBack(); return false; }
            $consume = $database->prepare('UPDATE account_email_verification SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL');
            $consume->execute(['id' => $record['id']]);
            if ($consume->rowCount() !== 1) throw new \RuntimeException('Verification state changed concurrently.');
            $database->commit();
            $this->audit->log('security.email_verification.completed', 'admin_user', (string) $record['admin_user_id']);
            return true;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }
}
