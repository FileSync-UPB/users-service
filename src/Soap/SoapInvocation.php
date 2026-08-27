<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

/**
 * Parsed SOAP invocation envelope.
 */
final class SoapInvocation
{
    /**
     * Creates the invocation model.
     *
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $operation,
        public array $arguments,
        public ?string $token,
        public string $clientIp
    ) {
    }
}
