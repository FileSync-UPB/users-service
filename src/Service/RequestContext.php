<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Service;

/**
 * Runtime request context extracted from the SOAP envelope and transport.
 */
final class RequestContext
{
    /**
     * Creates the request context.
     */
    public function __construct(
        public ?string $token,
        public string $clientIp,
        public string $operation
    ) {
    }
}
