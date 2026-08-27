<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Infrastructure\Ldap;

use UpbCientifica\UserService\Config\AppConfig;
use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Exception\InternalServiceException;
use UpbCientifica\UserService\Repository\LdapDirectoryRepositoryInterface;

/**
 * LDAP repository using ext-ldap.
 */
final class ExtLdapDirectoryRepository implements LdapDirectoryRepositoryInterface
{
    /**
     * Creates the repository.
     */
    public function __construct(
        private readonly AppConfig $config
    ) {
    }

    public function bindAsUser(string $username, string $password): bool
    {
        $user = $this->findUserByUsername($username);
        if ($user === null) {
            return false;
        }

        $connection = $this->connect();
        $dn = $this->dnForUser($username);
        $bound = @ldap_bind($connection, $dn, $password);
        ldap_unbind($connection);

        return (bool) $bound;
    }

    public function findUserByUsername(string $username): ?UserIdentity
    {
        $connection = $this->connect();
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        $search = @ldap_search(
            $connection,
            $this->config->ldapBaseDn(),
            '(uid=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')',
            ['uid', 'givenName', 'sn', 'mail', 'homeDirectory', 'memberOf', 'shadowExpire']
        );
        if ($search === false) {
            ldap_unbind($connection);
            return null;
        }

        $entries = ldap_get_entries($connection, $search);
        if (($entries['count'] ?? 0) < 1) {
            ldap_unbind($connection);
            return null;
        }

        $entry = $entries[0];
        $groups = $this->groupsForUser($connection, $username, $entry);
        ldap_unbind($connection);

        $homeDirectory = isset($entry['homedirectory'][0]) ? (string) $entry['homedirectory'][0] : '/home/' . $username;
        $active = !isset($entry['shadowexpire'][0]) || (int) $entry['shadowexpire'][0] === 0;

        return new UserIdentity(
            (string) ($entry['uid'][0] ?? $username),
            (string) ($entry['givenname'][0] ?? ''),
            (string) ($entry['sn'][0] ?? ''),
            (string) ($entry['mail'][0] ?? ''),
            $groups,
            (string) ($entry['uid'][0] ?? $username),
            $homeDirectory,
            $active
        );
    }

    public function createUser(UserIdentity $user, string $password, string $primaryGroup): void
    {
        $connection = $this->connect();
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);

        $dn = $this->dnForUser($user->username);
        $entry = [
            'objectClass' => ['top', 'person', 'organizationalPerson', 'inetOrgPerson', 'posixAccount', 'shadowAccount'],
            'cn' => $user->username,
            'sn' => $user->sn,
            'givenName' => $user->givenName,
            'uid' => $user->username,
            'uidNumber' => (string) $this->uidNumberForUsername($user->username),
            'gidNumber' => (string) $this->gidNumberForGroup($primaryGroup),
            'homeDirectory' => $user->homeDirectory,
            'loginShell' => '/bin/bash',
            'mail' => $user->mail,
            'userPassword' => $password,
        ];

        if (!@ldap_add($connection, $dn, $entry)) {
            ldap_unbind($connection);
            throw new InternalServiceException('LDAP_CREATE_FAILED', 'Unable to create the LDAP user.');
        }

        if (!$this->assignGroupMembership($connection, $user->username, $primaryGroup)) {
            @ldap_delete($connection, $dn);
            ldap_unbind($connection);
            throw new InternalServiceException('LDAP_GROUP_ASSIGN_FAILED', 'Unable to assign the LDAP group.');
        }
        ldap_unbind($connection);
    }

    public function updateUser(UserIdentity $user): void
    {
        $connection = $this->connect();
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = $this->dnForUser($user->username);
        $changes = [
            'givenName' => $user->givenName,
            'sn' => $user->sn,
            'mail' => $user->mail,
            'homeDirectory' => $user->homeDirectory,
        ];
        if (!@ldap_modify($connection, $dn, $changes)) {
            ldap_unbind($connection);
            throw new InternalServiceException('LDAP_UPDATE_FAILED', 'Unable to update the LDAP user.');
        }
        ldap_unbind($connection);
    }

    public function deleteUser(string $username): void
    {
        $connection = $this->connect();
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = $this->dnForUser($username);
        if (!@ldap_delete($connection, $dn)) {
            ldap_unbind($connection);
            throw new InternalServiceException('LDAP_DELETE_FAILED', 'Unable to delete the LDAP user.');
        }
        ldap_unbind($connection);
    }

    public function userExists(string $username): bool
    {
        return $this->findUserByUsername($username) !== null;
    }

    public function listUsers(): array
    {
        $connection = $this->connect();
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        $search = @ldap_search($connection, $this->config->ldapBaseDn(), '(uid=*)', ['uid', 'givenName', 'sn', 'mail', 'homeDirectory', 'memberOf']);
        if ($search === false) {
            ldap_unbind($connection);
            return [];
        }
        $entries = ldap_get_entries($connection, $search);
        $users = [];
        $count = (int) ($entries['count'] ?? 0);
        for ($index = 0; $index < $count; $index++) {
            $entry = $entries[$index];
            $groups = $this->groupsForUser($connection, (string) ($entry['uid'][0] ?? ''), $entry);
            $username = (string) ($entry['uid'][0] ?? '');
            $users[] = new UserIdentity(
                $username,
                (string) ($entry['givenname'][0] ?? ''),
                (string) ($entry['sn'][0] ?? ''),
                (string) ($entry['mail'][0] ?? ''),
                $groups,
                $username,
                (string) ($entry['homedirectory'][0] ?? '/home/' . $username),
                true
            );
        }
        ldap_unbind($connection);

        return $users;
    }

    private function connect(): mixed
    {
        if (!function_exists('ldap_connect')) {
            throw new InternalServiceException('LDAP_EXTENSION_MISSING', 'The LDAP extension is required.');
        }

        $connection = ldap_connect($this->config->ldapHost(), $this->config->ldapPort());
        if ($connection === false) {
            throw new InternalServiceException('LDAP_CONNECT_FAILED', 'Unable to connect to LDAP.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        if (!@ldap_bind($connection, $this->config->ldapBindDn(), $this->config->ldapBindPassword())) {
            throw new InternalServiceException('LDAP_BIND_FAILED', 'Unable to bind to LDAP.');
        }

        return $connection;
    }

    private function dnForUser(string $username): string
    {
        return 'uid=' . $username . ',ou=People,' . $this->config->ldapBaseDn();
    }

    private function groupNameFromDn(string $dn): string
    {
        if (preg_match('/cn=([^,]+)/i', $dn, $matches) === 1) {
            return $matches[1];
        }

        return $dn;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<int, string>
     */
    private function groupsForUser(mixed $connection, string $username, array $entry): array
    {
        $groups = [];
        if (isset($entry['memberof']) && is_array($entry['memberof'])) {
            foreach ($entry['memberof'] as $value) {
                if (is_string($value)) {
                    $groups[] = $this->groupNameFromDn($value);
                }
            }
        }

        if ($groups !== []) {
            return array_values(array_unique($groups));
        }

        $search = @ldap_search(
            $connection,
            'ou=Groups,' . $this->config->ldapBaseDn(),
            '(memberUid=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')',
            ['cn']
        );
        if ($search === false) {
            return [];
        }

        $entries = ldap_get_entries($connection, $search);
        $count = (int) ($entries['count'] ?? 0);
        for ($index = 0; $index < $count; $index++) {
            $groupEntry = $entries[$index];
            if (isset($groupEntry['cn'][0])) {
                $groups[] = (string) $groupEntry['cn'][0];
            }
        }

        return array_values(array_unique($groups));
    }

    private function assignGroupMembership(mixed $connection, string $username, string $groupName): bool
    {
        $groupDn = 'cn=' . $groupName . ',ou=Groups,' . $this->config->ldapBaseDn();
        $entry = ['memberUid' => $username];
        return (bool) @ldap_mod_add($connection, $groupDn, $entry);
    }

    private function uidNumberForUsername(string $username): int
    {
        return 10000 + (int) (abs(crc32($username)) % 50000);
    }

    private function gidNumberForGroup(string $groupName): int
    {
        return match ($groupName) {
            'Administradores' => 10000,
            'Investigadores' => 10001,
            'Empleados' => 10002,
            default => 10003,
        };
    }
}
