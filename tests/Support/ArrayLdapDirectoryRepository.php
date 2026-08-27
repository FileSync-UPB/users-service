<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Tests\Support;

use UpbCientifica\UserService\Domain\UserIdentity;
use UpbCientifica\UserService\Repository\LdapDirectoryRepositoryInterface;

/**
 * In-memory LDAP repository for tests.
 */
final class ArrayLdapDirectoryRepository implements LdapDirectoryRepositoryInterface
{
    /**
     * @param array<string, array{password: string, user: UserIdentity, group: string}> $users
     */
    public function __construct(
        private array $users = []
    ) {
    }

    public function bindAsUser(string $username, string $password): bool
    {
        return isset($this->users[$username]) && $this->users[$username]['password'] === $password;
    }

    public function findUserByUsername(string $username): ?UserIdentity
    {
        return $this->users[$username]['user'] ?? null;
    }

    public function createUser(UserIdentity $user, string $password, string $primaryGroup): void
    {
        $this->users[$user->username] = [
            'password' => $password,
            'user' => $user,
            'group' => $primaryGroup,
        ];
    }

    public function updateUser(UserIdentity $user): void
    {
        if (!isset($this->users[$user->username])) {
            throw new \RuntimeException('User not found.');
        }

        $password = $this->users[$user->username]['password'];
        $group = $this->users[$user->username]['group'];
        $this->users[$user->username] = [
            'password' => $password,
            'user' => $user,
            'group' => $group,
        ];
    }

    public function deleteUser(string $username): void
    {
        unset($this->users[$username]);
    }

    public function userExists(string $username): bool
    {
        return isset($this->users[$username]);
    }

    public function listUsers(): array
    {
        return array_map(static fn (array $row): UserIdentity => $row['user'], array_values($this->users));
    }
}
