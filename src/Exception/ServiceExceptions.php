<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Exception;

use RuntimeException;

/**
 * Base application exception carrying a stable application error code.
 */
abstract class ServiceException extends RuntimeException
{
    /**
     * Creates the exception.
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the application error code.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

/**
 * Indicates invalid input or malformed request data.
 */
final class ValidationException extends ServiceException
{
}

/**
 * Indicates authentication failure.
 */
final class AuthenticationException extends ServiceException
{
}

/**
 * Indicates an access control violation.
 */
final class AuthorizationException extends ServiceException
{
}

/**
 * Indicates a missing resource.
 */
final class NotFoundException extends ServiceException
{
}

/**
 * Indicates a conflicting resource state.
 */
final class ConflictException extends ServiceException
{
}

/**
 * Indicates token expiration.
 */
final class TokenExpiredException extends ServiceException
{
}

/**
 * Indicates token validation failure.
 */
final class TokenInvalidException extends ServiceException
{
}

/**
 * Indicates an infrastructure or unexpected error.
 */
final class InternalServiceException extends ServiceException
{
}
