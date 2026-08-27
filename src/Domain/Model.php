<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Domain;

/**
 * LDAP-backed identity information.
 */
final class UserIdentity
{
    /**
     * Creates the identity model.
     *
     * @param array<int, string> $groups
     */
    public function __construct(
        public string $username,
        public string $givenName,
        public string $sn,
        public string $mail,
        public array $groups,
        public string $uid,
        public string $homeDirectory,
        public bool $active
    ) {
    }
}

/**
 * Role assignment and permissions.
 */
final class RoleGrant
{
    /**
     * Creates the grant model.
     *
     * @param array<int, string> $permissions
     */
    public function __construct(
        public string $roleCode,
        public array $permissions
    ) {
    }
}

/**
 * Token persistence record.
 */
final class TokenRecord
{
    /**
     * Creates the token record.
     *
     * @param array<int, string> $permissions
     */
    public function __construct(
        public string $username,
        public string $tokenHash,
        public string $roleCode,
        public array $permissions,
        public \DateTimeImmutable $expiresAt,
        public bool $active
    ) {
    }
}

/**
 * Quota information in megabytes.
 */
final class QuotaInfo
{
    /**
     * Creates the quota model.
     */
    public function __construct(
        public string $username,
        public int $quotaMb,
        public int $usedMb,
        public int $availableMb
    ) {
    }
}

/**
 * Enabled service state.
 */
final class ServiceRecord
{
    /**
     * Creates the service model.
     */
    public function __construct(
        public string $username,
        public string $serviceCode,
        public bool $enabled
    ) {
    }
}

/**
 * Audit entry record.
 */
final class AuditEntry
{
    /**
     * Creates the audit entry.
     */
    public function __construct(
        public string $username,
        public string $operation,
        public \DateTimeImmutable $occurredAt,
        public string $ipAddress,
        public string $result
    ) {
    }
}
