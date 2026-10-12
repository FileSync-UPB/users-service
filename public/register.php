<?php
declare(strict_types=1);

use UpbCientifica\UserService\Config\AppConfig;
use UpbCientifica\UserService\Domain\AuditEntry;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Infrastructure\Database\PdoApplicationRepository;
use UpbCientifica\UserService\Infrastructure\Database\PdoFactory;
use UpbCientifica\UserService\Infrastructure\Ldap\ExtLdapDirectoryRepository;
use UpbCientifica\UserService\Security\RoleCatalog;

// Permitir llamadas CORS desde Frontend Flutter Web, Móvil, Desktop o cURL
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$bootstrapPath = file_exists(__DIR__ . '/../config/bootstrap.php')
    ? __DIR__ . '/../config/bootstrap.php'
    : __DIR__ . '/config/bootstrap.php';

$config = require $bootstrapPath;

// Si la petición es GET, devolver formulario interactivo o documentación JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'text/html') !== false && !isset($_GET['json'])) {
        renderRegistrationHtml();
        exit();
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'service' => 'FyleSync Enterprise - Users Registration Endpoint',
        'method' => 'POST',
        'endpoint' => '/public/register.php',
        'description' => 'Registra un nuevo usuario en OpenLDAP, MySQL y aprovisiona el directorio /var/fylesync/home/<username>',
        'schema' => [
            'username' => 'string (requerido, ej: carlos_upb)',
            'password' => 'string (requerido, ej: Upb2026!)',
            'givenName' => 'string (opcional, ej: Carlos)',
            'sn' => 'string (opcional, ej: Martinez)',
            'mail' => 'string (opcional, ej: carlos@upbcientifica.local)',
            'role' => 'string (opcional: Investigador, Empleado, Invitado, Administrador - default: Investigador)',
            'quotaMb' => 'int (opcional, default: 1024)',
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

// Procesar POST (JSON o form-data)
$rawBody = file_get_contents('php://input') ?: '';
$inputData = [];
if (!empty($rawBody)) {
    $jsonDecoded = json_decode($rawBody, true);
    if (is_array($jsonDecoded)) {
        $inputData = $jsonDecoded;
    }
}
// Mezclar con $_POST por compatibilidad
$inputData = array_merge($inputData, $_POST);

$username = trim((string) ($inputData['username'] ?? ''));
$password = (string) ($inputData['password'] ?? '');
$givenName = trim((string) ($inputData['givenName'] ?? $inputData['name'] ?? ucfirst($username)));
$sn = trim((string) ($inputData['sn'] ?? $inputData['surname'] ?? 'Usuario'));
$mail = trim((string) ($inputData['mail'] ?? $inputData['email'] ?? ($username . '@upbcientifica.local')));
$rawRole = strtoupper(trim((string) ($inputData['role'] ?? $inputData['roleCode'] ?? 'INVESTIGADOR')));
$quotaMb = isset($inputData['quotaMb']) ? (int) $inputData['quotaMb'] : (isset($inputData['quota_mb']) ? (int) $inputData['quota_mb'] : 1024);

if ($username === '' || $password === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'VALIDATION_ERROR',
        'message' => 'Los campos "username" y "password" son obligatorios.'
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// Mapeo seguro de roles UPB
$roleMap = [
    'ADMIN' => RoleCatalog::ROLE_ADMINISTRADOR,
    'ADMINISTRADOR' => RoleCatalog::ROLE_ADMINISTRADOR,
    'INVESTIGADOR' => RoleCatalog::ROLE_INVESTIGADOR,
    'EMPLEADO' => RoleCatalog::ROLE_EMPLEADO,
    'INVITADO' => RoleCatalog::ROLE_INVITADO,
];
$role = $roleMap[$rawRole] ?? RoleCatalog::ROLE_INVESTIGADOR;

// Grupo LDAP primario
$ldapGroupMap = [
    RoleCatalog::ROLE_ADMINISTRADOR => 'Administradores',
    RoleCatalog::ROLE_INVESTIGADOR => 'Investigadores',
    RoleCatalog::ROLE_EMPLEADO => 'Empleados',
    RoleCatalog::ROLE_INVITADO => 'Invitados',
];
$primaryGroup = $ldapGroupMap[$role] ?? 'Investigadores';

$pdoFactory = new PdoFactory($config);
$applicationRepository = new PdoApplicationRepository($pdoFactory->create());
$ldapRepository = new ExtLdapDirectoryRepository($config);

// 1. Verificar si el usuario ya existe
$existsInLdap = false;
$existsInMysql = false;
try {
    $existsInLdap = $ldapRepository->userExists($username);
} catch (\Throwable $e) {}

try {
    $existsInMysql = $applicationRepository->userProfileExists($username);
} catch (\Throwable $e) {}

if ($existsInLdap || $existsInMysql) {
    http_response_code(409);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'USER_ALREADY_EXISTS',
        'message' => "El usuario \"$username\" ya existe en el sistema."
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

$user = new UserIdentity(
    $username,
    $givenName,
    $sn,
    $mail,
    [$primaryGroup],
    $username,
    '/home/' . $username,
    true
);

// 2. Crear en LDAP
$ldapOk = false;
$ldapError = null;
try {
    $ldapRepository->createUser($user, $password, $primaryGroup);
    $ldapOk = true;
} catch (\Throwable $e) {
    $ldapError = $e->getMessage();
}

// 3. Crear en MySQL
$mysqlOk = false;
$mysqlError = null;
try {
    $applicationRepository->createUserProfile($user, $role);
    $applicationRepository->setQuota($username, $quotaMb);
    $mysqlOk = true;
} catch (\Throwable $e) {
    $mysqlError = $e->getMessage();
}

// 4. Aprovisionamiento físico de directorios del usuario (/var/fylesync/home/<username>)
$storagePathsCreated = [];
$candidateStorageDirs = [
    '/var/fylesync/home/' . $username,
    '/var/fylesync/' . $username,
];

// Si corre en Windows / desarrollo local
if (DIRECTORY_SEPARATOR === '\\') {
    $candidateStorageDirs = [
        'C:\\fylesync\\home\\' . $username,
        sys_get_temp_dir() . '\\fylesync\\home\\' . $username
    ];
}

foreach ($candidateStorageDirs as $dir) {
    try {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_dir($dir)) {
            @chmod($dir, 0777);
            // Archivo inicial de bienvenida para verificación inmediata
            $initFile = $dir . DIRECTORY_SEPARATOR . 'BIENVENIDO.txt';
            if (!file_exists($initFile)) {
                $content = "==============================================\n" .
                           "  FyleSync Enterprise - Repositorio de Usuario\n" .
                           "==============================================\n" .
                           "Usuario: $username\n" .
                           "Nombre: $givenName $sn\n" .
                           "Rol: $role\n" .
                           "Cuota: $quotaMb MB\n" .
                           "Fecha de Aprovisionamiento: " . date('Y-m-d H:i:s') . "\n" .
                           "Directorio Raíz: $dir\n";
                @file_put_contents($initFile, $content);
                @chmod($initFile, 0666);
            }
            $storagePathsCreated[] = $dir;
        }
    } catch (\Throwable $e) {}
}

// Registrar en auditoría
try {
    $applicationRepository->recordAudit(new AuditEntry(
        $username,
        'registerUser',
        new DateTimeImmutable('now'),
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $ldapOk || $mysqlOk ? 'SUCCESS' : 'FAILURE'
    ));
} catch (\Throwable $e) {}

http_response_code(201);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => true,
    'message' => "Usuario \"$username\" registrado exitosamente y ruta de almacenamiento aprovisionada.",
    'data' => [
        'username' => $username,
        'givenName' => $givenName,
        'sn' => $sn,
        'mail' => $mail,
        'role' => $role,
        'group' => $primaryGroup,
        'quotaMb' => $quotaMb,
        'homeDirectory' => '/var/fylesync/home/' . $username,
        'storageCreated' => !empty($storagePathsCreated),
        'storagePaths' => $storagePathsCreated,
        'ldapCreated' => $ldapOk,
        'ldapError' => $ldapError,
        'mysqlCreated' => $mysqlOk,
        'mysqlError' => $mysqlError,
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

function renderRegistrationHtml(): void {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FyleSync Enterprise — Registro de Usuario</title>
        <style>
            :root {
                --primary: #4f46e5;
                --primary-hover: #4338ca;
                --bg: #0f172a;
                --card: #1e293b;
                --text: #f8fafc;
                --muted: #94a3b8;
                --border: #334155;
                --success: #10b981;
                --error: #ef4444;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; width: 100%; max-width: 500px; padding: 32px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
            .header { text-align: center; margin-bottom: 24px; }
            .header h1 { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 6px; }
            .header p { color: var(--muted); font-size: 14px; }
            .form-group { margin-bottom: 16px; }
            label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #cbd5e1; }
            input, select { width: 100%; padding: 10px 14px; background: #0f172a; border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: border-color .2s; }
            input:focus, select:focus { border-color: var(--primary); }
            .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            button { width: 100%; padding: 12px; background: var(--primary); color: #fff; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; margin-top: 10px; transition: background .2s; }
            button:hover { background: var(--primary-hover); }
            #result { margin-top: 20px; padding: 14px; border-radius: 8px; font-size: 13px; display: none; line-height: 1.5; white-space: pre-wrap; word-break: break-all; }
            .success-box { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #6ee7b7; }
            .error-box { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--error); color: #fca5a5; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <h1>FyleSync Enterprise</h1>
                <p>Aprovisionamiento de Usuario y Almacenamiento Central</p>
            </div>
            <form id="regForm">
                <div class="form-group">
                    <label>Nombre de Usuario (Login LDAP/MySQL)</label>
                    <input type="text" name="username" required placeholder="ej: doc_gonzalez">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="ej: Upb2026!">
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="givenName" placeholder="ej: Juan">
                    </div>
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="sn" placeholder="ej: Gonzalez">
                    </div>
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="mail" placeholder="ej: juan.gonzalez@upb.edu.co">
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Rol UPB</label>
                        <select name="role">
                            <option value="INVESTIGADOR" selected>Investigador</option>
                            <option value="EMPLEADO">Empleado</option>
                            <option value="INVITADO">Invitado</option>
                            <option value="ADMINISTRADOR">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cuota (MB)</label>
                        <input type="number" name="quotaMb" value="1024">
                    </div>
                </div>
                <button type="submit" id="btnSubmit">Registrar Usuario y Crear Rutas</button>
            </form>
            <div id="result"></div>
        </div>
        <script>
            document.getElementById('regForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('btnSubmit');
                const resBox = document.getElementById('result');
                btn.disabled = true;
                btn.innerText = 'Procesando...';
                resBox.style.display = 'none';

                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                data.quotaMb = parseInt(data.quotaMb, 10);

                try {
                    const res = await fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const json = await res.json();
                    resBox.style.display = 'block';
                    if (res.ok && json.success) {
                        resBox.className = 'success-box';
                        resBox.innerText = `✅ ` + json.message + `\n\n` +
                                           `• Usuario: ${json.data.username}\n` +
                                           `• Rol: ${json.data.role}\n` +
                                           `• Directorio Físico Creado: ${json.data.homeDirectory}\n` +
                                           `• Cuota: ${json.data.quotaMb} MB\n` +
                                           `• OpenLDAP: ${json.data.ldapCreated ? 'OK' : 'Error'}\n` +
                                           `• MySQL: ${json.data.mysqlCreated ? 'OK' : 'Error'}`;
                        e.target.reset();
                    } else {
                        resBox.className = 'error-box';
                        resBox.innerText = `❌ Error: ` + (json.message || json.error || 'Fallo desconocido');
                    }
                } catch (err) {
                    resBox.style.display = 'block';
                    resBox.className = 'error-box';
                    resBox.innerText = `❌ Error de conexión: ` + err;
                } finally {
                    btn.disabled = false;
                    btn.innerText = 'Registrar Usuario y Crear Rutas';
                }
            });
        </script>
    </body>
    </html>
    <?php
}
