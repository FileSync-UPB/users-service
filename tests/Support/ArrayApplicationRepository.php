<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Tests\Support;

use DateTimeImmutable;
use UpbCientifica\UserService\Domain\AuditEntry;
use UpbCientifica\UserService\Domain\TokenRecord;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Repository\ApplicationRepositoryInterface;
use UpbCientifica\UserService\Security\RoleCatalog;

/**
 * In-memory MySQL repository for tests.
 */
final class ArrayApplicationRepository implements ApplicationRepositoryInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $profiles = [];

    /**
     * @var array<string, int>
     */
    private array $quotas = [];

    /**
     * @var array<string, int>
     */
    private array $used = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $services = [];

    /**
     * @var array<string, TokenRecord>
     */
    private array $tokens = [];

    /**
     * @var array<int, AuditEntry>
     */
    public array $auditLog = [];

    public function __construct()
    {
    }

    public function userProfileExists(string $username): bool
    {
        return isset($this->profiles[$username]);
    }

    public function isUserActive(string $username): bool
    {
        return ($this->profiles[$username]['active'] ?? false) === true;
    }

    public function createUserProfile(UserIdentity $user, string $roleCode): void
    {
        $this->profiles[$user->username] = [
            'username' => $user->username,
            'role_code' => $roleCode,
            'given_name' => $user->givenName,
            'sn' => $user->sn,
            'mail' => $user->mail,
            'uid' => $user->uid,
            'home_directory' => $user->homeDirectory,
            'active' => $user->active,
        ];
    }

    public function updateUserProfile(UserIdentity $user, string $roleCode, bool $active): void
    {
        $this->profiles[$user->username] = [
            'username' => $user->username,
            'role_code' => $roleCode,
            'given_name' => $user->givenName,
            'sn' => $user->sn,
            'mail' => $user->mail,
            'uid' => $user->uid,
            'home_directory' => $user->homeDirectory,
            'active' => $active,
        ];
    }

    public function deleteUserProfile(string $username): void
    {
        unset($this->profiles[$username], $this->quotas[$username], $this->used[$username], $this->services[$username], $this->tokens[$username]);
    }

    public function getRoleForUser(string $username): ?string
    {
        return $this->profiles[$username]['role_code'] ?? null;
    }

    public function setRoleForUser(string $username, string $roleCode): void
    {
        if (!isset($this->profiles[$username])) {
            $this->profiles[$username] = [
                'username' => $username,
                'role_code' => $roleCode,
                'given_name' => '',
                'sn' => '',
                'mail' => '',
                'uid' => $username,
                'home_directory' => '/home/' . $username,
                'active' => true,
            ];
            return;
        }

        $this->profiles[$username]['role_code'] = $roleCode;
    }

    public function getPermissionsForRole(string $roleCode): array
    {
        return RoleCatalog::rolePermissions()[$roleCode] ?? [];
    }

    public function listRoles(): array
    {
        return array_keys(RoleCatalog::rolePermissions());
    }

    public function listPermissions(): array
    {
        return RoleCatalog::allPermissions();
    }

    public function setQuota(string $username, int $quotaMb): void
    {
        $this->quotas[$username] = $quotaMb;
    }

    public function getQuota(string $username): ?int
    {
        return $this->quotas[$username] ?? null;
    }

    public function updateQuota(string $username, int $quotaMb): void
    {
        $this->quotas[$username] = $quotaMb;
    }

    public function getUsedStorageMb(string $username): int
    {
        return $this->used[$username] ?? 0;
    }

    public function enableService(string $username, string $serviceCode): void
    {
        $this->services[$username][$serviceCode] = true;
    }

    public function disableService(string $username, string $serviceCode): void
    {
        $this->services[$username][$serviceCode] = false;
    }

    public function listUserServices(string $username): array
    {
        $services = [];
        foreach ($this->services[$username] ?? [] as $serviceCode => $enabled) {
            if ($enabled) {
                $services[] = $serviceCode;
            }
        }

        sort($services);
        return $services;
    }

    public function storeToken(TokenRecord $tokenRecord): void
    {
        $this->tokens[$tokenRecord->tokenHash] = $tokenRecord;
    }

    public function findValidTokenByHash(string $tokenHash, DateTimeImmutable $now): ?TokenRecord
    {
        $record = $this->tokens[$tokenHash] ?? null;
        if ($record === null) {
            return null;
        }

        if ($record->expiresAt < $now || !$record->active) {
            return null;
        }

        return $record;
    }

    public function revokeTokensForUser(string $username): void
    {
        foreach ($this->tokens as $hash => $record) {
            if ($record->username === $username) {
                $this->tokens[$hash]->active = false;
            }
        }
    }

    public function recordAudit(AuditEntry $entry): void
    {
        $this->auditLog[] = $entry;
    }

    public function listUsers(): array
    {
        $users = [];
        foreach ($this->profiles as $profile) {
            $users[] = new UserIdentity(
                (string) $profile['username'],
                (string) $profile['given_name'],
                (string) $profile['sn'],
                (string) $profile['mail'],
                [],
                (string) $profile['uid'],
                (string) $profile['home_directory'],
                (bool) $profile['active']
            );
        }

        return $users;
    }
}
