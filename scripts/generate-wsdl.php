<?php
declare(strict_types=1);

use UpbCientifica\UserService\Config\AppConfig;
use UpbCientifica\UserService\Soap\WsdlGenerator;

require __DIR__ . '/../config/bootstrap.php';

$config = new AppConfig();
$generator = new WsdlGenerator();
$wsdl = $generator->generate($config->soapEndpoint(), $config->soapNamespace());
file_put_contents(__DIR__ . '/../wsdl/user-service.wsdl', $wsdl);
echo "WSDL written successfully.\n";
