<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\DTO;

/**
 * Authentication response payload.
 */
final class AuthenticateUserResponse
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public string $token,
        public string $roleCode,
        public array $permissions,
        public string $expiresAt
    ) {
    }
}

/**
 * Token validation response payload.
 */
final class ValidateTokenResponse
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public bool $valid,
        public ?string $username,
        public ?string $roleCode,
        public array $permissions,
        public ?string $expiresAt
    ) {
    }
}

/**
 * User creation response payload.
 */
final class CreateUserResponse
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public string $username,
        public string $roleCode,
        public array $permissions,
        public bool $created
    ) {
    }
}

/**
 * User update response payload.
 */
final class UpdateUserResponse
{
    public function __construct(
        public string $username,
        public bool $updated
    ) {
    }
}

/**
 * User deletion response payload.
 */
final class DeleteUserResponse
{
    public function __construct(
        public string $username,
        public bool $deleted
    ) {
    }
}

/**
 * User lookup response payload.
 */
final class GetUserResponse
{
    /**
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
        public bool $active,
        public ?string $roleCode
    ) {
    }
}

/**
 * User list response payload.
 */
final class ListUsersResponse
{
    /**
     * @param array<int, GetUserResponse> $users
     */
    public function __construct(
        public array $users
    ) {
    }
}

/**
 * Role assignment response payload.
 */
final class AssignRoleResponse
{
    public function __construct(
        public string $username,
        public string $roleCode,
        public bool $assigned
    ) {
    }
}

/**
 * Role removal response payload.
 */
final class RemoveRoleResponse
{
    public function __construct(
        public string $username,
        public string $roleCode,
        public bool $removed
    ) {
    }
}

/**
 * Role listing response payload.
 */
final class GetRolesResponse
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        public array $roles
    ) {
    }
}

/**
 * Permission listing response payload.
 */
final class GetPermissionsResponse
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public array $permissions
    ) {
    }
}

/**
 * Quota create/read/update response payload.
 */
final class QuotaResponse
{
    public function __construct(
        public string $username,
        public int $quotaMb
    ) {
    }
}

/**
 * Service enable/disable response payload.
 */
final class ServiceResponse
{
    public function __construct(
        public string $username,
        public string $serviceCode,
        public bool $enabled
    ) {
    }
}

/**
 * User service list response payload.
 */
final class ListUserServicesResponse
{
    /**
     * @param array<int, string> $services
     */
    public function __construct(
        public string $username,
        public array $services
    ) {
    }
}

/**
 * Home directory response payload.
 */
final class GetHomeDirectoryResponse
{
    public function __construct(
        public string $username,
        public string $homeDirectory
    ) {
    }
}

/**
 * Storage info response payload.
 */
final class GetUserStorageInfoResponse
{
    public function __construct(
        public string $username,
        public int $quotaMb,
        public int $usedMb,
        public int $availableMb
    ) {
    }
}
