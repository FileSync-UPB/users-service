<?php
declare(strict_types=1);

use UpbCientifica\UserService\Config\AppConfig;

require_once __DIR__ . '/../src/Repository/Contracts.php';
require_once __DIR__ . '/../src/DTO/Requests.php';
require_once __DIR__ . '/../src/DTO/Responses.php';
require_once __DIR__ . '/../src/Exception/ServiceExceptions.php';
require_once __DIR__ . '/../src/Domain/Model.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'UpbCientifica\\UserService\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

return new AppConfig();
