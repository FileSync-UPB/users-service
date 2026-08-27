<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Infrastructure\Database;

use DateTimeImmutable;
use PDO;
use UpbCientifica\UserService\Domain\AuditEntry;
use UpbCientifica\UserService\Domain\TokenRecord;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Repository\ApplicationRepositoryInterface;
use UpbCientifica\UserService\Security\RoleCatalog;

/**
 * PDO-backed MySQL repository.
 */
final class PdoApplicationRepository implements ApplicationRepositoryInterface
{
    /**
     * Creates the repository.
     */
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function userProfileExists(string $username): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM user_profiles WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);

        return (bool) $statement->fetchColumn();
    }

    public function isUserActive(string $username): bool
    {
        $statement = $this->pdo->prepare('SELECT active FROM user_profiles WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $value = $statement->fetchColumn();

        return $value === false ? false : ((int) $value === 1);
    }

    public function createUserProfile(UserIdentity $user, string $roleCode): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_profiles (username, role_code, given_name, sn, mail, uid, home_directory, active, created_at, updated_at)
             VALUES (:username, :role_code, :given_name, :sn, :mail, :uid, :home_directory, :active, NOW(), NOW())'
        );
        $statement->execute([
            'username' => $user->username,
            'role_code' => $roleCode,
            'given_name' => $user->givenName,
            'sn' => $user->sn,
            'mail' => $user->mail,
            'uid' => $user->uid,
            'home_directory' => $user->homeDirectory,
            'active' => $user->active ? 1 : 0,
        ]);
    }

    public function updateUserProfile(UserIdentity $user, string $roleCode, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_profiles
             SET role_code = :role_code,
                 given_name = :given_name,
                 sn = :sn,
                 mail = :mail,
                 uid = :uid,
                 home_directory = :home_directory,
                 active = :active,
                 updated_at = NOW()
             WHERE username = :username'
        );
        $statement->execute([
            'username' => $user->username,
            'role_code' => $roleCode,
            'given_name' => $user->givenName,
            'sn' => $user->sn,
            'mail' => $user->mail,
            'uid' => $user->uid,
            'home_directory' => $user->homeDirectory,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function deleteUserProfile(string $username): void
    {
        $statement = $this->pdo->prepare('DELETE FROM user_profiles WHERE username = :username');
        $statement->execute(['username' => $username]);
    }

    public function getRoleForUser(string $username): ?string
    {
        $statement = $this->pdo->prepare('SELECT role_code FROM user_profiles WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function setRoleForUser(string $username, string $roleCode): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_profiles SET role_code = :role_code, updated_at = NOW() WHERE username = :username'
        );
        $statement->execute([
            'username' => $username,
            'role_code' => $roleCode,
        ]);
    }

    public function getPermissionsForRole(string $roleCode): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.permission_code
             FROM role_permissions rp
             INNER JOIN permissions p ON p.permission_code = rp.permission_code
             WHERE rp.role_code = :role_code
             ORDER BY p.permission_code ASC'
        );
        $statement->execute(['role_code' => $roleCode]);

        return array_map(static fn (array $row): string => (string) $row['permission_code'], $statement->fetchAll());
    }

    public function listRoles(): array
    {
        $statement = $this->pdo->query('SELECT role_code FROM roles ORDER BY role_code ASC');

        return array_map(static fn (array $row): string => (string) $row['role_code'], $statement->fetchAll());
    }

    public function listPermissions(): array
    {
        $statement = $this->pdo->query('SELECT permission_code FROM permissions ORDER BY permission_code ASC');

        return array_map(static fn (array $row): string => (string) $row['permission_code'], $statement->fetchAll());
    }

    public function setQuota(string $username, int $quotaMb): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_quotas (username, quota_mb, used_mb, created_at, updated_at)
             VALUES (:username, :quota_mb, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE quota_mb = VALUES(quota_mb), updated_at = NOW()'
        );
        $statement->execute([
            'username' => $username,
            'quota_mb' => $quotaMb,
        ]);
    }

    public function getQuota(string $username): ?int
    {
        $statement = $this->pdo->prepare('SELECT quota_mb FROM user_quotas WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function updateQuota(string $username, int $quotaMb): void
    {
        $this->setQuota($username, $quotaMb);
    }

    public function getUsedStorageMb(string $username): int
    {
        $statement = $this->pdo->prepare('SELECT used_mb FROM user_quotas WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    public function enableService(string $username, string $serviceCode): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_services (username, service_code, enabled, created_at, updated_at)
             VALUES (:username, :service_code, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE enabled = 1, updated_at = NOW()'
        );
        $statement->execute([
            'username' => $username,
            'service_code' => $serviceCode,
        ]);
    }

    public function disableService(string $username, string $serviceCode): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_services (username, service_code, enabled, created_at, updated_at)
             VALUES (:username, :service_code, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE enabled = 0, updated_at = NOW()'
        );
        $statement->execute([
            'username' => $username,
            'service_code' => $serviceCode,
        ]);
    }

    public function listUserServices(string $username): array
    {
        $statement = $this->pdo->prepare(
            'SELECT service_code
             FROM user_services
             WHERE username = :username AND enabled = 1
             ORDER BY service_code ASC'
        );
        $statement->execute(['username' => $username]);

        return array_map(static fn (array $row): string => (string) $row['service_code'], $statement->fetchAll());
    }

    public function storeToken(TokenRecord $tokenRecord): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_tokens (username, token_hash, role_code, permissions_json, expires_at, active, created_at, updated_at)
             VALUES (:username, :token_hash, :role_code, :permissions_json, :expires_at, :active, NOW(), NOW())'
        );
        $statement->execute([
            'username' => $tokenRecord->username,
            'token_hash' => $tokenRecord->tokenHash,
            'role_code' => $tokenRecord->roleCode,
            'permissions_json' => json_encode(array_values($tokenRecord->permissions), JSON_THROW_ON_ERROR),
            'expires_at' => $tokenRecord->expiresAt->format('Y-m-d H:i:s'),
            'active' => $tokenRecord->active ? 1 : 0,
        ]);
    }

    public function findValidTokenByHash(string $tokenHash, DateTimeImmutable $now): ?TokenRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT username, token_hash, role_code, permissions_json, expires_at, active
             FROM auth_tokens
             WHERE token_hash = :token_hash AND active = 1 AND revoked_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        if ($expiresAt < $now) {
            return null;
        }

        /** @var array<int, string> $permissions */
        $permissions = json_decode((string) $row['permissions_json'], true, 512, JSON_THROW_ON_ERROR);

        return new TokenRecord(
            (string) $row['username'],
            (string) $row['token_hash'],
            (string) $row['role_code'],
            $permissions,
            $expiresAt,
            (bool) $row['active']
        );
    }

    public function revokeTokensForUser(string $username): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_tokens SET active = 0, revoked_at = NOW(), updated_at = NOW() WHERE username = :username'
        );
        $statement->execute(['username' => $username]);
    }

    public function recordAudit(AuditEntry $entry): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (username, operation, client_ip, result, occurred_at, created_at, updated_at)
             VALUES (:username, :operation, :client_ip, :result, :occurred_at, NOW(), NOW())'
        );
        $statement->execute([
            'username' => $entry->username,
            'operation' => $entry->operation,
            'client_ip' => $entry->ipAddress,
            'result' => $entry->result,
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function listUsers(): array
    {
        $statement = $this->pdo->query(
            'SELECT username, given_name, sn, mail, uid, home_directory, active
             FROM user_profiles
             ORDER BY username ASC'
        );
        $rows = $statement->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $users[] = new UserIdentity(
                (string) $row['username'],
                (string) $row['given_name'],
                (string) $row['sn'],
                (string) $row['mail'],
                [],
                (string) $row['uid'],
                (string) $row['home_directory'],
                (bool) $row['active']
            );
        }

        return $users;
    }
}
