<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Security;

/**
 * Central catalogue of roles and permissions.
 */
final class RoleCatalog
{
    public const ROLE_ADMINISTRADOR = 'ADMINISTRADOR';
    public const ROLE_INVESTIGADOR = 'INVESTIGADOR';
    public const ROLE_EMPLEADO = 'EMPLEADO';
    public const ROLE_INVITADO = 'INVITADO';

    public const PERMISSION_USER_CREATE = 'USER_CREATE';
    public const PERMISSION_USER_DELETE = 'USER_DELETE';
    public const PERMISSION_QUOTA_MANAGE = 'QUOTA_MANAGE';
    public const PERMISSION_SERVICE_MANAGE = 'SERVICE_MANAGE';
    public const PERMISSION_ROLE_MANAGE = 'ROLE_MANAGE';
    public const PERMISSION_USER_READ = 'USER_READ';
    public const PERMISSION_USER_LIST = 'USER_LIST';
    public const PERMISSION_HPC_USE = 'HPC_USE';
    public const PERMISSION_FILE_SHARE = 'FILE_SHARE';
    public const PERMISSION_CONTENT_MANAGE = 'CONTENT_MANAGE';
    public const PERMISSION_FILE_MANAGE = 'FILE_MANAGE';
    public const PERMISSION_FILE_SYNC = 'FILE_SYNC';
    public const PERMISSION_LIMITED_READ = 'LIMITED_READ';

    /**
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_ADMINISTRADOR => [
                self::PERMISSION_USER_CREATE,
                self::PERMISSION_USER_DELETE,
                self::PERMISSION_QUOTA_MANAGE,
                self::PERMISSION_SERVICE_MANAGE,
                self::PERMISSION_ROLE_MANAGE,
                self::PERMISSION_USER_READ,
                self::PERMISSION_USER_LIST,
            ],
            self::ROLE_INVESTIGADOR => [
                self::PERMISSION_HPC_USE,
                self::PERMISSION_FILE_SHARE,
                self::PERMISSION_CONTENT_MANAGE,
            ],
            self::ROLE_EMPLEADO => [
                self::PERMISSION_FILE_MANAGE,
                self::PERMISSION_FILE_SYNC,
            ],
            self::ROLE_INVITADO => [
                self::PERMISSION_LIMITED_READ,
            ],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function ldapGroupToRoleMap(): array
    {
        return [
            'Administradores' => self::ROLE_ADMINISTRADOR,
            'Investigadores' => self::ROLE_INVESTIGADOR,
            'Empleados' => self::ROLE_EMPLEADO,
            'Invitados' => self::ROLE_INVITADO,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        $permissions = [];
        foreach (self::rolePermissions() as $rolePermissions) {
            foreach ($rolePermissions as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }
}
