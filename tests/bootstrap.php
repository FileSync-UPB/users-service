<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $testPrefix = 'UpbCientifica\\UserService\\Tests\\';
    if (str_starts_with($class, $testPrefix)) {
        $relative = substr($class, strlen($testPrefix));
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }

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
