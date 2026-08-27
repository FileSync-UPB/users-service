<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Repository;

use UpbCientifica\UserService\Domain\AuditEntry;
use UpbCientifica\UserService\Domain\QuotaInfo;
use UpbCientifica\UserService\Domain\RoleGrant;
use UpbCientifica\UserService\Domain\ServiceRecord;
use UpbCientifica\UserService\Domain\TokenRecord;
use UpbCientifica\UserService\Domain\UserIdentity;

/**
 * LDAP directory access contract.
 */
interface LdapDirectoryRepositoryInterface
{
    public function bindAsUser(string $username, string $password): bool;

    public function findUserByUsername(string $username): ?UserIdentity;

    public function createUser(UserIdentity $user, string $password, string $primaryGroup): void;

    public function updateUser(UserIdentity $user): void;

    public function deleteUser(string $username): void;

    public function userExists(string $username): bool;

    /**
     * @return array<int, UserIdentity>
     */
    public function listUsers(): array;
}

/**
 * MySQL application data contract.
 */
interface ApplicationRepositoryInterface
{
    public function userProfileExists(string $username): bool;

    public function isUserActive(string $username): bool;

    public function createUserProfile(UserIdentity $user, string $roleCode): void;

    public function updateUserProfile(UserIdentity $user, string $roleCode, bool $active): void;

    public function deleteUserProfile(string $username): void;

    public function getRoleForUser(string $username): ?string;

    public function setRoleForUser(string $username, string $roleCode): void;

    /**
     * @return array<int, string>
     */
    public function getPermissionsForRole(string $roleCode): array;

    /**
     * @return array<int, string>
     */
    public function listRoles(): array;

    /**
     * @return array<int, string>
     */
    public function listPermissions(): array;

    public function setQuota(string $username, int $quotaMb): void;

    public function getQuota(string $username): ?int;

    public function updateQuota(string $username, int $quotaMb): void;

    public function getUsedStorageMb(string $username): int;

    public function enableService(string $username, string $serviceCode): void;

    public function disableService(string $username, string $serviceCode): void;

    /**
     * @return array<int, string>
     */
    public function listUserServices(string $username): array;

    public function storeToken(TokenRecord $tokenRecord): void;

    public function findValidTokenByHash(string $tokenHash, \DateTimeImmutable $now): ?TokenRecord;

    public function revokeTokensForUser(string $username): void;

    public function recordAudit(AuditEntry $entry): void;

    /**
     * @return array<int, UserIdentity>
     */
    public function listUsers(): array;
}
