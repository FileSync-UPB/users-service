<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Service;

use SoapFault;
use InvalidArgumentException;
use Throwable;
use UpbCientifica\UserService\Exception\AuthenticationException;
use UpbCientifica\UserService\Exception\AuthorizationException;
use UpbCientifica\UserService\Exception\ConflictException;
use UpbCientifica\UserService\Exception\InternalServiceException;
use UpbCientifica\UserService\Exception\NotFoundException;
use UpbCientifica\UserService\Exception\ServiceException;
use UpbCientifica\UserService\Exception\TokenExpiredException;
use UpbCientifica\UserService\Exception\TokenInvalidException;
use UpbCientifica\UserService\Exception\ValidationException;

/**
 * Maps domain exceptions to SOAP faults.
 */
final class SoapFaultMapper
{
    /**
     * Converts any exception into a SOAP fault.
     */
    public static function toSoapFault(Throwable $throwable): SoapFault
    {
        if ($throwable instanceof ServiceException) {
            $code = match (true) {
                $throwable instanceof AuthenticationException => 'AUTHENTICATION_FAILED',
                $throwable instanceof AuthorizationException => 'ACCESS_DENIED',
                $throwable instanceof ConflictException => 'CONFLICT',
                $throwable instanceof NotFoundException => 'NOT_FOUND',
                $throwable instanceof TokenExpiredException => 'TOKEN_EXPIRED',
                $throwable instanceof TokenInvalidException => 'TOKEN_INVALID',
                $throwable instanceof ValidationException => 'VALIDATION_ERROR',
                default => $throwable->errorCode(),
            };

            return new SoapFault('SOAP-ENV:Client', $code . ': ' . $throwable->getMessage());
        }

        if ($throwable instanceof InvalidArgumentException) {
            return new SoapFault('SOAP-ENV:Client', 'VALIDATION_ERROR: ' . $throwable->getMessage());
        }

        return new SoapFault('SOAP-ENV:Server', 'INTERNAL_ERROR: ' . $throwable->getMessage());
    }
}
