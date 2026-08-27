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
