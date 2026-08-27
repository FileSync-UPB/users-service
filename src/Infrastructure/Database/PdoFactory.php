<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Infrastructure\Database;

use PDO;
use PDOException;
use UpbCientifica\UserService\Config\AppConfig;

/**
 * Creates configured PDO connections.
 */
final class PdoFactory
{
    /**
     * Creates the factory.
     */
    public function __construct(
        private readonly AppConfig $config
    ) {
    }

    /**
     * Creates a PDO connection for MySQL.
     */
    public function create(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->mysqlHost(),
            $this->config->mysqlPort(),
            $this->config->mysqlDatabase()
        );

        return new PDO(
            $dsn,
            $this->config->mysqlUser(),
            $this->config->mysqlPassword(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
