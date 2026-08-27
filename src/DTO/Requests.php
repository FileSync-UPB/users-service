<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\DTO;

/**
 * Authentication request payload.
 */
final class AuthenticateUserRequest
{
    public function __construct(
        public string $username,
        public string $password
    ) {
    }
}

/**
 * Token validation request payload.
 */
final class ValidateTokenRequest
{
    public function __construct()
    {
    }
}

/**
 * User creation request payload.
 */
final class CreateUserRequest
{
    public function __construct(
        public string $username,
        public string $password,
        public string $givenName,
        public string $sn,
        public string $mail,
        public ?string $roleCode = null,
        public ?int $quotaMb = null
    ) {
    }
}

/**
 * User update request payload.
 */
final class UpdateUserRequest
{
    public function __construct(
        public string $username,
        public ?string $givenName = null,
        public ?string $sn = null,
        public ?string $mail = null,
        public ?bool $active = null
    ) {
    }
}

/**
 * User deletion request payload.
 */
final class DeleteUserRequest
{
    public function __construct(
        public string $username
    ) {
    }
}

/**
 * User lookup request payload.
 */
final class GetUserRequest
{
    public function __construct(
        public string $username
    ) {
    }
}

/**
 * User listing request payload.
 */
final class ListUsersRequest
{
    public function __construct()
    {
    }
}

/**
 * Assign role request payload.
 */
final class AssignRoleRequest
{
    public function __construct(
        public string $username,
        public string $roleCode
    ) {
    }
}

/**
 * Remove role request payload.
 */
final class RemoveRoleRequest
{
    public function __construct(
        public string $username,
        public string $roleCode
    ) {
    }
}

/**
 * Role listing request payload.
 */
final class GetRolesRequest
{
    public function __construct()
    {
    }
}

/**
 * Permission listing request payload.
 */
final class GetPermissionsRequest
{
    public function __construct(
        public ?string $roleCode = null
    ) {
    }
}

/**
 * Quota creation request payload.
 */
final class SetQuotaRequest
{
    public function __construct(
        public string $username,
        public int $quotaMb
    ) {
    }
}

/**
 * Quota read request payload.
 */
final class GetQuotaRequest
{
    public function __construct(
        public string $username
    ) {
    }
}

/**
 * Quota update request payload.
 */
final class UpdateQuotaRequest
{
    public function __construct(
        public string $username,
        public int $quotaMb
    ) {
    }
}

/**
 * Service enable request payload.
 */
final class EnableServiceRequest
{
    public function __construct(
        public string $username,
        public string $serviceCode
    ) {
    }
}

/**
 * Service disable request payload.
 */
final class DisableServiceRequest
{
    public function __construct(
        public string $username,
        public string $serviceCode
    ) {
    }
}

/**
 * Service listing request payload.
 */
final class ListUserServicesRequest
{
    public function __construct(
        public string $username
    ) {
    }
}

/**
 * Home directory request payload.
 */
final class GetHomeDirectoryRequest
{
    public function __construct(
        public string $username
    ) {
    }
}

/**
 * Storage info request payload.
 */
final class GetUserStorageInfoRequest
{
    public function __construct(
        public string $username
    ) {
    }
}
