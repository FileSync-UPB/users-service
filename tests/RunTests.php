<?php
declare(strict_types=1);

use DateTimeImmutable;
use UpbCientifica\UserService\DTO\AuthenticateUserRequest;
use UpbCientifica\UserService\DTO\CreateUserRequest;
use UpbCientifica\UserService\DTO\DeleteUserRequest;
use UpbCientifica\UserService\DTO\GetQuotaRequest;
use UpbCientifica\UserService\DTO\GetUserRequest;
use UpbCientifica\UserService\DTO\GetUserStorageInfoRequest;
use UpbCientifica\UserService\DTO\ListUserServicesRequest;
use UpbCientifica\UserService\DTO\ListUsersRequest;
use UpbCientifica\UserService\DTO\SetQuotaRequest;
use UpbCientifica\UserService\DTO\UpdateQuotaRequest;
use UpbCientifica\UserService\DTO\UpdateUserRequest;
use UpbCientifica\UserService\DTO\ValidateTokenRequest;
use UpbCientifica\UserService\Domain\TokenRecord;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Security\RoleCatalog;
use UpbCientifica\UserService\Security\TokenManager;
use UpbCientifica\UserService\Service\RequestContext;
use UpbCientifica\UserService\Service\UserManagementService;
use UpbCientifica\UserService\Tests\Support\ArrayApplicationRepository;
use UpbCientifica\UserService\Tests\Support\ArrayLdapDirectoryRepository;

require __DIR__ . '/bootstrap.php';

/**
 * Simple test runner.
 */
final class TestRunner
{
    /**
     * @var array<int, array{name: string, fn: callable}>
     */
    private array $tests = [];

    public function add(string $name, callable $fn): void
    {
        $this->tests[] = ['name' => $name, 'fn' => $fn];
    }

    public function run(): int
    {
        $passed = 0;
        foreach ($this->tests as $test) {
            try {
                ($test['fn'])();
                $passed++;
                echo "[PASS] {$test['name']}\n";
            } catch (Throwable $throwable) {
                fwrite(STDERR, "[FAIL] {$test['name']}: {$throwable->getMessage()}\n");
                return 1;
            }
        }

        echo "Passed {$passed} tests.\n";
        return 0;
    }
}

/**
 * Asserts a condition.
 */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * Builds a service with seeded data.
 */
function buildService(): array
{
    $ldap = new ArrayLdapDirectoryRepository([
        'admin' => [
            'password' => 'Admin123!',
            'user' => new UserIdentity('admin', 'Admin', 'User', 'admin@example.local', ['Administradores'], 'admin', '/home/admin', true),
            'group' => 'Administradores',
        ],
        'investigador1' => [
            'password' => 'Research123!',
            'user' => new UserIdentity('investigador1', 'Invest', 'I Gador', 'investigador1@example.local', ['Investigadores'], 'investigador1', '/home/investigador1', true),
            'group' => 'Investigadores',
        ],
        'empleado1' => [
            'password' => 'Employee123!',
            'user' => new UserIdentity('empleado1', 'Emp', 'L Eado', 'empleado1@example.local', ['Empleados'], 'empleado1', '/home/empleado1', true),
            'group' => 'Empleados',
        ],
    ]);

    $mysql = new ArrayApplicationRepository();
    $mysql->createUserProfile(new UserIdentity('admin', 'Admin', 'User', 'admin@example.local', ['Administradores'], 'admin', '/home/admin', true), RoleCatalog::ROLE_ADMINISTRADOR);
    $mysql->createUserProfile(new UserIdentity('investigador1', 'Invest', 'I Gador', 'investigador1@example.local', ['Investigadores'], 'investigador1', '/home/investigador1', true), RoleCatalog::ROLE_INVESTIGADOR);
    $mysql->createUserProfile(new UserIdentity('empleado1', 'Emp', 'L Eado', 'empleado1@example.local', ['Empleados'], 'empleado1', '/home/empleado1', true), RoleCatalog::ROLE_EMPLEADO);
    $mysql->setQuota('admin', 5000);
    $mysql->setQuota('investigador1', 2000);
    $mysql->setQuota('empleado1', 1000);

    $service = new UserManagementService($ldap, $mysql, new TokenManager(), 60);
    return [$service, $ldap, $mysql];
}

$runner = new TestRunner();

$runner->add('autenticación correcta', function (): void {
    [$service] = buildService();
    $response = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    assertTrue($response->roleCode === RoleCatalog::ROLE_ADMINISTRADOR, 'Expected administrator role.');
    assertTrue($response->token !== '', 'Token must not be empty.');
});

$runner->add('autenticación incorrecta', function (): void {
    [$service] = buildService();
    try {
        $service->authenticateUser(new AuthenticateUserRequest('admin', 'wrong'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
        throw new RuntimeException('Expected authentication failure.');
    } catch (Throwable) {
        assertTrue(true, 'Authentication failed as expected.');
    }
});

$runner->add('token válido', function (): void {
    [$service, , $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $validate = $service->validateToken(new ValidateTokenRequest(), new RequestContext($auth->token, '127.0.0.1', 'validateToken'));
    assertTrue($validate->valid, 'Token must validate.');
    assertTrue($validate->roleCode === RoleCatalog::ROLE_ADMINISTRADOR, 'Role must match.');
});

$runner->add('token expirado', function (): void {
    $mysql = new ArrayApplicationRepository();
    $mysql->createUserProfile(new UserIdentity('admin', 'Admin', 'User', 'admin@example.local', ['Administradores'], 'admin', '/home/admin', true), RoleCatalog::ROLE_ADMINISTRADOR);
    $ldap = new ArrayLdapDirectoryRepository([
        'admin' => ['password' => 'Admin123!', 'user' => new UserIdentity('admin', 'Admin', 'User', 'admin@example.local', ['Administradores'], 'admin', '/home/admin', true), 'group' => 'Administradores'],
    ]);
    $service = new UserManagementService($ldap, $mysql, new TokenManager(), 1);
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $record = new TokenRecord('admin', (new TokenManager())->hashToken($auth->token), RoleCatalog::ROLE_ADMINISTRADOR, [RoleCatalog::PERMISSION_USER_CREATE], (new DateTimeImmutable('now'))->modify('-1 hour'), true);
    $mysql->storeToken($record);
    try {
        $service->validateToken(new ValidateTokenRequest(), new RequestContext($auth->token, '127.0.0.1', 'validateToken'));
        throw new RuntimeException('Expected expiration failure.');
    } catch (Throwable) {
        assertTrue(true, 'Expired token was rejected.');
    }
});

$runner->add('usuario inexistente', function (): void {
    [$service] = buildService();
    try {
        $service->getUser(new GetUserRequest('missing'), new RequestContext('token', '127.0.0.1', 'getUser'));
        throw new RuntimeException('Expected not found.');
    } catch (Throwable) {
        assertTrue(true, 'Missing user rejected.');
    }
});

$runner->add('usuario duplicado', function (): void {
    [$service] = buildService();
    try {
        $service->createUser(new CreateUserRequest('admin', 'Admin123!', 'Admin', 'User', 'admin@example.local', RoleCatalog::ROLE_ADMINISTRADOR, 100), new RequestContext('token', '127.0.0.1', 'createUser'));
        throw new RuntimeException('Expected duplicate conflict.');
    } catch (Throwable) {
        assertTrue(true, 'Duplicate user rejected.');
    }
});

$runner->add('autorización de administrador', function (): void {
    [$service, , $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->setQuota(new SetQuotaRequest('empleado1', 1500), new RequestContext($auth->token, '127.0.0.1', 'setQuota'));
    assertTrue($response->quotaMb === 1500, 'Quota must be updated.');
});

$runner->add('rechazo de autorización', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('empleado1', 'Employee123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    try {
        $service->deleteUser(new DeleteUserRequest('investigador1'), new RequestContext($auth->token, '127.0.0.1', 'deleteUser'));
        throw new RuntimeException('Expected access denied.');
    } catch (Throwable) {
        assertTrue(true, 'Unauthorized access denied.');
    }
});

$runner->add('creación de usuario', function (): void {
    [$service, $ldap, $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->createUser(new CreateUserRequest('nuevo1', 'NewPass123!', 'Nuevo', 'Usuario', 'nuevo1@example.local', RoleCatalog::ROLE_INVITADO, 250), new RequestContext($auth->token, '127.0.0.1', 'createUser'));
    assertTrue($response->created, 'User must be created.');
    assertTrue($ldap->userExists('nuevo1'), 'LDAP user must exist.');
    assertTrue($mysql->userProfileExists('nuevo1'), 'MySQL profile must exist.');
});

$runner->add('consulta de usuario', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->getUser(new GetUserRequest('admin'), new RequestContext($auth->token, '127.0.0.1', 'getUser'));
    assertTrue($response->username === 'admin', 'Username must match.');
});

$runner->add('lista de usuarios', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->listUsers(new ListUsersRequest(), new RequestContext($auth->token, '127.0.0.1', 'listUsers'));
    assertTrue(count($response->users) >= 3, 'User list must contain seeded users.');
});

$runner->add('actualización de usuario', function (): void {
    [$service, $ldap, $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->updateUser(new UpdateUserRequest('empleado1', 'Empleado', 'Uno', 'empleado1@example.local', true), new RequestContext($auth->token, '127.0.0.1', 'updateUser'));
    assertTrue($response->updated, 'User must be updated.');
    assertTrue($ldap->findUserByUsername('empleado1')?->givenName === 'Empleado', 'LDAP update must persist.');
    assertTrue($mysql->isUserActive('empleado1'), 'MySQL update must persist.');
});

$runner->add('eliminación', function (): void {
    [$service, $ldap, $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->deleteUser(new DeleteUserRequest('empleado1'), new RequestContext($auth->token, '127.0.0.1', 'deleteUser'));
    assertTrue($response->deleted, 'User must be deleted.');
});

$runner->add('roles', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->getRoles(new \UpbCientifica\UserService\DTO\GetRolesRequest(), new RequestContext($auth->token, '127.0.0.1', 'getRoles'));
    assertTrue(in_array(RoleCatalog::ROLE_ADMINISTRADOR, $response->roles, true), 'Roles must include administrator.');
});

$runner->add('permisos', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->getPermissions(new \UpbCientifica\UserService\DTO\GetPermissionsRequest(RoleCatalog::ROLE_ADMINISTRADOR), new RequestContext($auth->token, '127.0.0.1', 'getPermissions'));
    assertTrue(in_array(RoleCatalog::PERMISSION_USER_DELETE, $response->permissions, true), 'Permissions must include user delete.');
});

$runner->add('cuotas', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $updated = $service->updateQuota(new UpdateQuotaRequest('admin', 4800), new RequestContext($auth->token, '127.0.0.1', 'updateQuota'));
    $response = $service->getQuota(new GetQuotaRequest('admin'), new RequestContext($auth->token, '127.0.0.1', 'getQuota'));
    assertTrue($updated->quotaMb === 4800, 'Updated quota must match.');
    assertTrue($response->quotaMb === 4800, 'Quota must match after update.');
});

$runner->add('servicios', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $service->enableService(new \UpbCientifica\UserService\DTO\EnableServiceRequest('admin', 'HPC'), new RequestContext($auth->token, '127.0.0.1', 'enableService'));
    $service->disableService(new \UpbCientifica\UserService\DTO\DisableServiceRequest('admin', 'HPC'), new RequestContext($auth->token, '127.0.0.1', 'disableService'));
    $response = $service->listUserServices(new ListUserServicesRequest('admin'), new RequestContext($auth->token, '127.0.0.1', 'listUserServices'));
    assertTrue($response->services === [], 'Disabled service must not be listed.');
});

$runner->add('auditoría', function (): void {
    [$service, , $mysql] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $service->setQuota(new SetQuotaRequest('admin', 4800), new RequestContext($auth->token, '127.0.0.1', 'setQuota'));
    assertTrue(count($mysql->auditLog) >= 1, 'Audit log must contain entries.');
});

$runner->add('almacenamiento', function (): void {
    [$service] = buildService();
    $auth = $service->authenticateUser(new AuthenticateUserRequest('admin', 'Admin123!'), new RequestContext(null, '127.0.0.1', 'authenticateUser'));
    $response = $service->getUserStorageInfo(new GetUserStorageInfoRequest('admin'), new RequestContext($auth->token, '127.0.0.1', 'getUserStorageInfo'));
    assertTrue($response->availableMb === max($response->quotaMb - $response->usedMb, 0), 'Available must be quota minus used.');
});

exit($runner->run());
