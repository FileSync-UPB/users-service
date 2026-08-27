<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Security;

use DateInterval;
use DateTimeImmutable;

/**
 * Generates and validates cryptographically secure tokens.
 */
final class TokenManager
{
    /**
     * Generates a token string.
     */
    public function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Hashes a token for storage.
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Calculates the expiration timestamp.
     */
    public function expirationTime(int $ttlSeconds, ?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $base = $now ?? new DateTimeImmutable('now');

        return $base->add(new DateInterval('PT' . max(1, $ttlSeconds) . 'S'));
    }
}
