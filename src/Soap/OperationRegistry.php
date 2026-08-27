<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

use UpbCientifica\UserService\DTO\AssignRoleRequest;
use UpbCientifica\UserService\DTO\AssignRoleResponse;
use UpbCientifica\UserService\DTO\AuthenticateUserRequest;
use UpbCientifica\UserService\DTO\AuthenticateUserResponse;
use UpbCientifica\UserService\DTO\CreateUserRequest;
use UpbCientifica\UserService\DTO\CreateUserResponse;
use UpbCientifica\UserService\DTO\DeleteUserRequest;
use UpbCientifica\UserService\DTO\DeleteUserResponse;
use UpbCientifica\UserService\DTO\DisableServiceRequest;
use UpbCientifica\UserService\DTO\EnableServiceRequest;
use UpbCientifica\UserService\DTO\GetHomeDirectoryRequest;
use UpbCientifica\UserService\DTO\GetHomeDirectoryResponse;
use UpbCientifica\UserService\DTO\GetPermissionsRequest;
use UpbCientifica\UserService\DTO\GetPermissionsResponse;
use UpbCientifica\UserService\DTO\GetQuotaRequest;
use UpbCientifica\UserService\DTO\GetRolesRequest;
use UpbCientifica\UserService\DTO\GetRolesResponse;
use UpbCientifica\UserService\DTO\GetUserRequest;
use UpbCientifica\UserService\DTO\GetUserResponse;
use UpbCientifica\UserService\DTO\GetUserStorageInfoRequest;
use UpbCientifica\UserService\DTO\GetUserStorageInfoResponse;
use UpbCientifica\UserService\DTO\ListUserServicesRequest;
use UpbCientifica\UserService\DTO\ListUserServicesResponse;
use UpbCientifica\UserService\DTO\ListUsersRequest;
use UpbCientifica\UserService\DTO\ListUsersResponse;
use UpbCientifica\UserService\DTO\QuotaResponse;
use UpbCientifica\UserService\DTO\RemoveRoleRequest;
use UpbCientifica\UserService\DTO\RemoveRoleResponse;
use UpbCientifica\UserService\DTO\ServiceResponse;
use UpbCientifica\UserService\DTO\SetQuotaRequest;
use UpbCientifica\UserService\DTO\UpdateQuotaRequest;
use UpbCientifica\UserService\DTO\UpdateUserRequest;
use UpbCientifica\UserService\DTO\UpdateUserResponse;
use UpbCientifica\UserService\DTO\ValidateTokenRequest;
use UpbCientifica\UserService\DTO\ValidateTokenResponse;

/**
 * SOAP operation metadata used by the endpoint and WSDL generator.
 */
final class OperationRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'authenticateUser' => [
                'requestClass' => AuthenticateUserRequest::class,
                'responseClass' => AuthenticateUserResponse::class,
                'authRequired' => false,
                'permissions' => [],
                'requestFields' => ['username', 'password'],
                'responseFields' => ['token', 'roleCode', 'permissions', 'expiresAt'],
            ],
            'validateToken' => [
                'requestClass' => ValidateTokenRequest::class,
                'responseClass' => ValidateTokenResponse::class,
                'authRequired' => false,
                'permissions' => [],
                'requestFields' => [],
                'responseFields' => ['valid', 'username', 'roleCode', 'permissions', 'expiresAt'],
            ],
            'createUser' => [
                'requestClass' => CreateUserRequest::class,
                'responseClass' => CreateUserResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_CREATE'],
                'requestFields' => ['username', 'password', 'givenName', 'sn', 'mail', 'roleCode', 'quotaMb'],
                'responseFields' => ['username', 'roleCode', 'permissions', 'created'],
            ],
            'updateUser' => [
                'requestClass' => UpdateUserRequest::class,
                'responseClass' => UpdateUserResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_CREATE'],
                'requestFields' => ['username', 'givenName', 'sn', 'mail', 'active'],
                'responseFields' => ['username', 'updated'],
            ],
            'deleteUser' => [
                'requestClass' => DeleteUserRequest::class,
                'responseClass' => DeleteUserResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_DELETE'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'deleted'],
            ],
            'getUser' => [
                'requestClass' => GetUserRequest::class,
                'responseClass' => GetUserResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_READ'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'givenName', 'sn', 'mail', 'groups', 'uid', 'homeDirectory', 'active', 'roleCode'],
            ],
            'listUsers' => [
                'requestClass' => ListUsersRequest::class,
                'responseClass' => ListUsersResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_LIST'],
                'requestFields' => [],
                'responseFields' => ['users'],
            ],
            'assignRole' => [
                'requestClass' => AssignRoleRequest::class,
                'responseClass' => AssignRoleResponse::class,
                'authRequired' => true,
                'permissions' => ['ROLE_MANAGE'],
                'requestFields' => ['username', 'roleCode'],
                'responseFields' => ['username', 'roleCode', 'assigned'],
            ],
            'removeRole' => [
                'requestClass' => RemoveRoleRequest::class,
                'responseClass' => RemoveRoleResponse::class,
                'authRequired' => true,
                'permissions' => ['ROLE_MANAGE'],
                'requestFields' => ['username', 'roleCode'],
                'responseFields' => ['username', 'roleCode', 'removed'],
            ],
            'getRoles' => [
                'requestClass' => GetRolesRequest::class,
                'responseClass' => GetRolesResponse::class,
                'authRequired' => true,
                'permissions' => ['ROLE_MANAGE'],
                'requestFields' => [],
                'responseFields' => ['roles'],
            ],
            'getPermissions' => [
                'requestClass' => GetPermissionsRequest::class,
                'responseClass' => GetPermissionsResponse::class,
                'authRequired' => true,
                'permissions' => ['ROLE_MANAGE'],
                'requestFields' => ['roleCode'],
                'responseFields' => ['permissions'],
            ],
            'setQuota' => [
                'requestClass' => SetQuotaRequest::class,
                'responseClass' => QuotaResponse::class,
                'authRequired' => true,
                'permissions' => ['QUOTA_MANAGE'],
                'requestFields' => ['username', 'quotaMb'],
                'responseFields' => ['username', 'quotaMb'],
            ],
            'getQuota' => [
                'requestClass' => GetQuotaRequest::class,
                'responseClass' => QuotaResponse::class,
                'authRequired' => true,
                'permissions' => ['QUOTA_MANAGE', 'USER_READ'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'quotaMb'],
            ],
            'updateQuota' => [
                'requestClass' => UpdateQuotaRequest::class,
                'responseClass' => QuotaResponse::class,
                'authRequired' => true,
                'permissions' => ['QUOTA_MANAGE'],
                'requestFields' => ['username', 'quotaMb'],
                'responseFields' => ['username', 'quotaMb'],
            ],
            'enableService' => [
                'requestClass' => EnableServiceRequest::class,
                'responseClass' => ServiceResponse::class,
                'authRequired' => true,
                'permissions' => ['SERVICE_MANAGE'],
                'requestFields' => ['username', 'serviceCode'],
                'responseFields' => ['username', 'serviceCode', 'enabled'],
            ],
            'disableService' => [
                'requestClass' => DisableServiceRequest::class,
                'responseClass' => ServiceResponse::class,
                'authRequired' => true,
                'permissions' => ['SERVICE_MANAGE'],
                'requestFields' => ['username', 'serviceCode'],
                'responseFields' => ['username', 'serviceCode', 'enabled'],
            ],
            'listUserServices' => [
                'requestClass' => ListUserServicesRequest::class,
                'responseClass' => ListUserServicesResponse::class,
                'authRequired' => true,
                'permissions' => ['SERVICE_MANAGE', 'USER_READ'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'services'],
            ],
            'getHomeDirectory' => [
                'requestClass' => GetHomeDirectoryRequest::class,
                'responseClass' => GetHomeDirectoryResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_READ'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'homeDirectory'],
            ],
            'getUserStorageInfo' => [
                'requestClass' => GetUserStorageInfoRequest::class,
                'responseClass' => GetUserStorageInfoResponse::class,
                'authRequired' => true,
                'permissions' => ['USER_READ', 'QUOTA_MANAGE'],
                'requestFields' => ['username'],
                'responseFields' => ['username', 'quotaMb', 'usedMb', 'availableMb'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $operation): array
    {
        $definitions = self::definitions();
        if (!isset($definitions[$operation])) {
            throw new \InvalidArgumentException('Unknown SOAP operation: ' . $operation);
        }

        return $definitions[$operation];
    }
}
