<?php
declare(strict_types=1);

use UpbCientifica\UserService\Config\AppConfig;
use UpbCientifica\UserService\Infrastructure\Database\PdoApplicationRepository;
use UpbCientifica\UserService\Infrastructure\Database\PdoFactory;
use UpbCientifica\UserService\Infrastructure\Ldap\ExtLdapDirectoryRepository;
use UpbCientifica\UserService\Security\TokenManager;
use UpbCientifica\UserService\Service\UserManagementService;
use UpbCientifica\UserService\Soap\SoapDispatcher;
use UpbCientifica\UserService\Soap\SoapEnvelopeParser;
use UpbCientifica\UserService\Soap\SoapResponseBuilder;

$config = require __DIR__ . '/../config/bootstrap.php';

$rawXml = file_get_contents('php://input') ?: '';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, SOAPAction, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$pdoFactory = new PdoFactory($config);
$applicationRepository = new PdoApplicationRepository($pdoFactory->create());
$ldapRepository = new ExtLdapDirectoryRepository($config);
$service = new UserManagementService(
    $ldapRepository,
    $applicationRepository,
    new TokenManager(),
    $config->tokenTtlSeconds()
);

$dispatcher = new SoapDispatcher(
    $service,
    new SoapEnvelopeParser(),
    new SoapResponseBuilder(),
    $config->soapNamespace()
);

header('Content-Type: text/xml; charset=UTF-8');
echo $dispatcher->handle($rawXml, $clientIp);
