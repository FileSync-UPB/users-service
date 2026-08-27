<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Config;

/**
 * Application configuration loaded from environment variables.
 */
final class AppConfig
{
    /**
     * Reads a string value from the environment.
     */
    public function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Returns the LDAP host.
     */
    public function ldapHost(): string
    {
        return $this->env('LDAP_HOST', '127.0.0.1') ?? '127.0.0.1';
    }

    /**
     * Returns the LDAP port.
     */
    public function ldapPort(): int
    {
        return (int) ($this->env('LDAP_PORT', '389') ?? '389');
    }

    /**
     * Returns the LDAP base DN.
     */
    public function ldapBaseDn(): string
    {
        return $this->env('LDAP_BASE_DN', 'dc=upbcientifica,dc=local') ?? 'dc=upbcientifica,dc=local';
    }

    /**
     * Returns the LDAP bind DN.
     */
    public function ldapBindDn(): string
    {
        return $this->env('LDAP_BIND_DN', 'cn=admin,dc=upbcientifica,dc=local') ?? 'cn=admin,dc=upbcientifica,dc=local';
    }

    /**
     * Returns the LDAP bind password.
     */
    public function ldapBindPassword(): string
    {
        return $this->env('LDAP_BIND_PASSWORD', '') ?? '';
    }

    /**
     * Returns the MySQL host.
     */
    public function mysqlHost(): string
    {
        return $this->env('MYSQL_HOST', '127.0.0.1') ?? '127.0.0.1';
    }

    /**
     * Returns the MySQL port.
     */
    public function mysqlPort(): int
    {
        return (int) ($this->env('MYSQL_PORT', '3306') ?? '3306');
    }

    /**
     * Returns the MySQL database name.
     */
    public function mysqlDatabase(): string
    {
        return $this->env('MYSQL_DATABASE', 'upbcientifica_user_service') ?? 'upbcientifica_user_service';
    }

    /**
     * Returns the MySQL application username.
     */
    public function mysqlUser(): string
    {
        return $this->env('MYSQL_USER', 'upb_user_service') ?? 'upb_user_service';
    }

    /**
     * Returns the MySQL application password.
     */
    public function mysqlPassword(): string
    {
        return $this->env('MYSQL_PASSWORD', '') ?? '';
    }

    /**
     * Returns the token TTL in seconds.
     */
    public function tokenTtlSeconds(): int
    {
        return max(1, (int) ($this->env('TOKEN_TTL', '3600') ?? '3600'));
    }

    /**
     * Returns the application environment.
     */
    public function appEnv(): string
    {
        return $this->env('APP_ENV', 'production') ?? 'production';
    }

    /**
     * Returns the SOAP namespace.
     */
    public function soapNamespace(): string
    {
        return 'urn:upbcientifica:user-service';
    }

    /**
     * Returns the SOAP endpoint URL.
     */
    public function soapEndpoint(): string
    {
        return $this->env('SOAP_ENDPOINT', 'https://HOST/users/soap') ?? 'https://HOST/users/soap';
    }
}
