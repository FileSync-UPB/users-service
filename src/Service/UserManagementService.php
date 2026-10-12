<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Service;

use DateTimeImmutable;
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
use UpbCientifica\UserService\Domain\AuditEntry;
use UpbCientifica\UserService\Domain\QuotaInfo;
use UpbCientifica\UserService\Domain\RoleGrant;
use UpbCientifica\UserService\Domain\TokenRecord;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Exception\AuthenticationException;
use UpbCientifica\UserService\Exception\AuthorizationException;
use UpbCientifica\UserService\Exception\ConflictException;
use UpbCientifica\UserService\Exception\NotFoundException;
use UpbCientifica\UserService\Exception\TokenExpiredException;
use UpbCientifica\UserService\Exception\TokenInvalidException;
use UpbCientifica\UserService\Exception\ValidationException;
use UpbCientifica\UserService\Repository\ApplicationRepositoryInterface;
use UpbCientifica\UserService\Repository\LdapDirectoryRepositoryInterface;
use UpbCientifica\UserService\Security\RoleCatalog;
use UpbCientifica\UserService\Security\TokenManager;

/**
 * Application service coordinating LDAP, RBAC and persistence.
 */
final class UserManagementService
{
    /**
     * Creates the service.
     */
    public function __construct(
        private readonly LdapDirectoryRepositoryInterface $ldapRepository,
        private readonly ApplicationRepositoryInterface $applicationRepository,
        private readonly TokenManager $tokenManager,
        private readonly int $tokenTtlSeconds
    ) {
    }

    /**
     * Authenticates a user by LDAP bind and issues a token.
     */
    public function authenticateUser(AuthenticateUserRequest $request, RequestContext $context): AuthenticateUserResponse
    {
        $this->assertUsernameAndPassword($request->username, $request->password);

        if (!$this->ldapRepository->bindAsUser($request->username, $request->password)) {
            throw new AuthenticationException('AUTH_INVALID_CREDENTIALS', 'LDAP bind failed.');
        }

        $user = $this->ldapRepository->findUserByUsername($request->username);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'The user does not exist in LDAP.');
        }

        if (!$user->active || !$this->applicationRepository->isUserActive($request->username)) {
            throw new AuthenticationException('USER_INACTIVE', 'The user is inactive.');
        }

        $role = $this->resolveRole($user);
        $permissions = $this->applicationRepository->getPermissionsForRole($role);
        $token = $this->tokenManager->generateToken();
        $tokenHash = $this->tokenManager->hashToken($token);
        $expiresAt = $this->tokenManager->expirationTime($this->tokenTtlSeconds);

        $this->applicationRepository->storeToken(new TokenRecord(
            $user->username,
            $tokenHash,
            $role,
            $permissions,
            $expiresAt,
            true
        ));

        $this->applicationRepository->recordAudit(new AuditEntry(
            $user->username,
            'authenticateUser',
            new DateTimeImmutable('now'),
            $context->clientIp,
            'SUCCESS'
        ));

        return new AuthenticateUserResponse($token, $role, $permissions, $expiresAt->format(DATE_ATOM));
    }

    /**
     * Validates a token using the MySQL token store.
     */
    public function validateToken(ValidateTokenRequest $request, RequestContext $context): ValidateTokenResponse
    {
        $token = $context->token;
        if ($token === null || $token === '') {
            throw new TokenInvalidException('TOKEN_MISSING', 'A SOAP auth token is required.');
        }

        $tokenHash = $this->tokenManager->hashToken($token);
        $record = $this->applicationRepository->findValidTokenByHash($tokenHash, new DateTimeImmutable('now'));
        if ($record === null) {
            throw new TokenInvalidException('TOKEN_INVALID', 'The token is not valid.');
        }

        $this->assertTokenNotExpired($record);

        return new ValidateTokenResponse(
            true,
            $record->username,
            $record->roleCode,
            $record->permissions,
            $record->expiresAt->format(DATE_ATOM)
        );
    }

    /**
     * Creates a new user and performs logical rollback if an intermediate step fails.
     */
    public function createUser(CreateUserRequest $request, RequestContext $context): CreateUserResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_CREATE]);
        $this->assertUsernameAndPassword($request->username, $request->password);

        if ($this->ldapRepository->userExists($request->username) || $this->applicationRepository->userProfileExists($request->username)) {
            throw new ConflictException('USER_ALREADY_EXISTS', 'The username already exists.');
        }

        $role = $request->roleCode ?? RoleCatalog::ROLE_INVITADO;
        $this->assertKnownRole($role);

        $user = new UserIdentity(
            $request->username,
            $request->givenName,
            $request->sn,
            $request->mail,
            $this->groupsForRole($role),
            $request->username,
            '/home/' . $request->username,
            true
        );

        try {
            $this->ldapRepository->createUser($user, $request->password, $this->primaryGroupForRole($role));
            $this->applicationRepository->createUserProfile($user, $role);
            if ($request->quotaMb !== null) {
                $this->applicationRepository->setQuota($request->username, $request->quotaMb);
            }
            $this->ensureStorageDirectory($request->username, $role, $request->quotaMb ?? 1024);
            $this->applicationRepository->recordAudit(new AuditEntry(
                $request->username,
                'createUser',
                new DateTimeImmutable('now'),
                $context->clientIp,
                'SUCCESS'
            ));
        } catch (\Throwable $throwable) {
            $this->safeRollbackUserCreation($request->username);
            throw $throwable;
        }

        return new CreateUserResponse($request->username, $role, $this->applicationRepository->getPermissionsForRole($role), true);
    }

    private function ensureStorageDirectory(string $username, string $role, int $quotaMb): void
    {
        $dir = DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\fylesync\\home\\' . $username
            : '/var/fylesync/home/' . $username;

        try {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (is_dir($dir)) {
                @chmod($dir, 0777);
                $welcome = $dir . DIRECTORY_SEPARATOR . 'BIENVENIDO.txt';
                if (!file_exists($welcome)) {
                    @file_put_contents($welcome, "FyleSync Enterprise - Repositorio de $username\nRol: $role\nCuota: $quotaMb MB\nFecha: " . date('Y-m-d H:i:s') . "\n");
                    @chmod($welcome, 0666);
                }
            }
        } catch (\Throwable $e) {
            // No interrumpir si el sistema de archivos está en red/remoto
        }
    }

    /**
     * Updates user data in LDAP and MySQL.
     */
    public function updateUser(UpdateUserRequest $request, RequestContext $context): UpdateUserResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_CREATE]);
        $user = $this->ldapRepository->findUserByUsername($request->username);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'The user was not found.');
        }

        $updated = new UserIdentity(
            $user->username,
            $request->givenName ?? $user->givenName,
            $request->sn ?? $user->sn,
            $request->mail ?? $user->mail,
            $user->groups,
            $user->uid,
            $user->homeDirectory,
            $request->active ?? $user->active
        );

        $this->ldapRepository->updateUser($updated);
        $this->applicationRepository->updateUserProfile($updated, $this->resolveRole($user), $updated->active);

        $this->applicationRepository->recordAudit(new AuditEntry(
            $request->username,
            'updateUser',
            new DateTimeImmutable('now'),
            $context->clientIp,
            'SUCCESS'
        ));

        return new UpdateUserResponse($request->username, true);
    }

    /**
     * Deletes a user from LDAP and MySQL.
     */
    public function deleteUser(DeleteUserRequest $request, RequestContext $context): DeleteUserResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_DELETE]);
        if (!$this->ldapRepository->userExists($request->username) && !$this->applicationRepository->userProfileExists($request->username)) {
            throw new NotFoundException('USER_NOT_FOUND', 'The user was not found.');
        }

        $this->ldapRepository->deleteUser($request->username);
        $this->applicationRepository->revokeTokensForUser($request->username);
        $this->applicationRepository->deleteUserProfile($request->username);

        $this->applicationRepository->recordAudit(new AuditEntry(
            $request->username,
            'deleteUser',
            new DateTimeImmutable('now'),
            $context->clientIp,
            'SUCCESS'
        ));

        return new DeleteUserResponse($request->username, true);
    }

    /**
     * Returns a user profile from LDAP.
     */
    public function getUser(GetUserRequest $request, RequestContext $context): GetUserResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_READ]);
        $user = $this->ldapRepository->findUserByUsername($request->username);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'The user was not found.');
        }

        return new GetUserResponse(
            $user->username,
            $user->givenName,
            $user->sn,
            $user->mail,
            $user->groups,
            $user->uid,
            $user->homeDirectory,
            $user->active,
            $this->applicationRepository->getRoleForUser($user->username)
        );
    }

    /**
     * Lists users.
     */
    public function listUsers(ListUsersRequest $request, RequestContext $context): ListUsersResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_LIST]);
        $users = [];
        foreach ($this->ldapRepository->listUsers() as $user) {
            $users[] = new GetUserResponse(
                $user->username,
                $user->givenName,
                $user->sn,
                $user->mail,
                $user->groups,
                $user->uid,
                $user->homeDirectory,
                $user->active,
                $this->applicationRepository->getRoleForUser($user->username)
            );
        }

        return new ListUsersResponse($users);
    }

    /**
     * Assigns a role to a user.
     */
    public function assignRole(AssignRoleRequest $request, RequestContext $context): AssignRoleResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_ROLE_MANAGE]);
        $this->assertKnownRole($request->roleCode);
        $this->applicationRepository->setRoleForUser($request->username, $request->roleCode);
        $this->applicationRepository->recordAudit(new AuditEntry(
            $request->username,
            'assignRole',
            new DateTimeImmutable('now'),
            $context->clientIp,
            'SUCCESS'
        ));

        return new AssignRoleResponse($request->username, $request->roleCode, true);
    }

    /**
     * Removes a role from a user.
     */
    public function removeRole(RemoveRoleRequest $request, RequestContext $context): RemoveRoleResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_ROLE_MANAGE]);
        $this->assertKnownRole($request->roleCode);
        $this->applicationRepository->setRoleForUser($request->username, RoleCatalog::ROLE_INVITADO);
        $this->applicationRepository->recordAudit(new AuditEntry(
            $request->username,
            'removeRole',
            new DateTimeImmutable('now'),
            $context->clientIp,
            'SUCCESS'
        ));

        return new RemoveRoleResponse($request->username, $request->roleCode, true);
    }

    /**
     * Returns all roles.
     */
    public function getRoles(GetRolesRequest $request, RequestContext $context): GetRolesResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_ROLE_MANAGE]);
        return new GetRolesResponse($this->applicationRepository->listRoles());
    }

    /**
     * Returns the permission set for a role or for all roles.
     */
    public function getPermissions(GetPermissionsRequest $request, RequestContext $context): GetPermissionsResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_ROLE_MANAGE]);
        if ($request->roleCode !== null) {
            $this->assertKnownRole($request->roleCode);
            return new GetPermissionsResponse($this->applicationRepository->getPermissionsForRole($request->roleCode));
        }

        return new GetPermissionsResponse($this->applicationRepository->listPermissions());
    }

    /**
     * Sets a user's quota.
     */
    public function setQuota(SetQuotaRequest $request, RequestContext $context): QuotaResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_QUOTA_MANAGE]);
        $this->applicationRepository->setQuota($request->username, $request->quotaMb);
        return new QuotaResponse($request->username, $request->quotaMb);
    }

    /**
     * Returns a user's quota.
     */
    public function getQuota(GetQuotaRequest $request, RequestContext $context): QuotaResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_QUOTA_MANAGE, RoleCatalog::PERMISSION_USER_READ]);
        $quota = $this->applicationRepository->getQuota($request->username);
        if ($quota === null) {
            throw new NotFoundException('QUOTA_NOT_FOUND', 'The quota was not found.');
        }

        return new QuotaResponse($request->username, $quota);
    }

    /**
     * Updates a user's quota.
     */
    public function updateQuota(UpdateQuotaRequest $request, RequestContext $context): QuotaResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_QUOTA_MANAGE]);
        $this->applicationRepository->updateQuota($request->username, $request->quotaMb);
        return new QuotaResponse($request->username, $request->quotaMb);
    }

    /**
     * Enables a service for a user.
     */
    public function enableService(EnableServiceRequest $request, RequestContext $context): ServiceResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_SERVICE_MANAGE]);
        $this->applicationRepository->enableService($request->username, $request->serviceCode);
        return new ServiceResponse($request->username, $request->serviceCode, true);
    }

    /**
     * Disables a service for a user.
     */
    public function disableService(DisableServiceRequest $request, RequestContext $context): ServiceResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_SERVICE_MANAGE]);
        $this->applicationRepository->disableService($request->username, $request->serviceCode);
        return new ServiceResponse($request->username, $request->serviceCode, false);
    }

    /**
     * Returns the enabled services for a user.
     */
    public function listUserServices(ListUserServicesRequest $request, RequestContext $context): ListUserServicesResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_SERVICE_MANAGE, RoleCatalog::PERMISSION_USER_READ]);
        return new ListUserServicesResponse($request->username, $this->applicationRepository->listUserServices($request->username));
    }

    /**
     * Returns the user's home directory.
     */
    public function getHomeDirectory(GetHomeDirectoryRequest $request, RequestContext $context): GetHomeDirectoryResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_READ]);
        $user = $this->ldapRepository->findUserByUsername($request->username);
        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', 'The user was not found.');
        }

        return new GetHomeDirectoryResponse($request->username, $user->homeDirectory);
    }

    /**
     * Returns quota and storage usage for a user.
     */
    public function getUserStorageInfo(GetUserStorageInfoRequest $request, RequestContext $context): GetUserStorageInfoResponse
    {
        $this->authorize($context, [RoleCatalog::PERMISSION_USER_READ, RoleCatalog::PERMISSION_QUOTA_MANAGE]);
        $quota = $this->applicationRepository->getQuota($request->username);
        if ($quota === null) {
            throw new NotFoundException('QUOTA_NOT_FOUND', 'The quota was not found.');
        }

        $used = $this->applicationRepository->getUsedStorageMb($request->username);
        $available = max($quota - $used, 0);

        return new GetUserStorageInfoResponse($request->username, $quota, $used, $available);
    }

    /**
     * Returns the application role for a user.
     */
    public function resolveRole(UserIdentity $identity): string
    {
        foreach ($identity->groups as $group) {
            $role = RoleCatalog::ldapGroupToRoleMap()[$group] ?? null;
            if ($role !== null) {
                return $role;
            }
        }

        return RoleCatalog::ROLE_INVITADO;
    }

    /**
     * @param array<int, string> $requiredPermissions
     */
    private function authorize(RequestContext $context, array $requiredPermissions): void
    {
        $token = $context->token;
        if ($token === null || $token === '') {
            throw new AuthorizationException('ACCESS_DENIED', 'The SOAP auth token is required.');
        }

        $tokenHash = $this->tokenManager->hashToken($token);
        $record = $this->applicationRepository->findValidTokenByHash($tokenHash, new DateTimeImmutable('now'));
        if ($record === null) {
            throw new TokenInvalidException('TOKEN_INVALID', 'The token is not valid.');
        }

        $this->assertTokenNotExpired($record);
        if (!$this->applicationRepository->isUserActive($record->username)) {
            throw new AuthorizationException('ACCESS_DENIED', 'The user is inactive.');
        }

        foreach ($requiredPermissions as $requiredPermission) {
            if (in_array($requiredPermission, $record->permissions, true)) {
                return;
            }
        }

        throw new AuthorizationException('ACCESS_DENIED', 'The user does not have the required permission.');
    }

    /**
     * Returns the LDAP group list associated with a role.
     *
     * @return array<int, string>
     */
    private function groupsForRole(string $roleCode): array
    {
        $group = array_search($roleCode, RoleCatalog::ldapGroupToRoleMap(), true);
        if ($group === false) {
            return ['Invitados'];
        }

        return [$group];
    }

    /**
     * Returns the primary LDAP group for a role.
     */
    private function primaryGroupForRole(string $roleCode): string
    {
        $group = array_search($roleCode, RoleCatalog::ldapGroupToRoleMap(), true);
        if ($group === false) {
            return 'Invitados';
        }

        return $group;
    }

    /**
     * Ensures a token has not expired.
     */
    private function assertTokenNotExpired(TokenRecord $record): void
    {
        if ($record->expiresAt < new DateTimeImmutable('now')) {
            throw new TokenExpiredException('TOKEN_EXPIRED', 'The token has expired.');
        }
    }

    private function assertKnownRole(string $roleCode): void
    {
        if (!array_key_exists($roleCode, RoleCatalog::rolePermissions())) {
            throw new ValidationException('ROLE_INVALID', 'The role code is not valid.');
        }
    }

    private function assertUsernameAndPassword(string $username, string $password): void
    {
        if ($username === '' || $password === '') {
            throw new ValidationException('VALIDATION_ERROR', 'Username and password are required.');
        }
    }

    private function safeRollbackUserCreation(string $username): void
    {
        try {
            if ($this->ldapRepository->userExists($username)) {
                $this->ldapRepository->deleteUser($username);
            }
        } catch (\Throwable $throwable) {
            // Best-effort rollback. The compensating action must not hide the original failure.
        }

        try {
            if ($this->applicationRepository->userProfileExists($username)) {
                $this->applicationRepository->deleteUserProfile($username);
            }
        } catch (\Throwable $throwable) {
            // Best-effort rollback.
        }
    }
}
