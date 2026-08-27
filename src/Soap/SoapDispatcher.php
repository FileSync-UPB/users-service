<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

use ReflectionClass;
use UpbCientifica\UserService\Exception\AuthorizationException;
use UpbCientifica\UserService\Exception\ValidationException;
use UpbCientifica\UserService\Service\RequestContext;
use UpbCientifica\UserService\Service\SoapFaultMapper;
use UpbCientifica\UserService\Service\UserManagementService;

/**
 * Dispatches parsed SOAP invocations to the application service.
 */
final class SoapDispatcher
{
    public function __construct(
        private readonly UserManagementService $service,
        private readonly SoapEnvelopeParser $parser,
        private readonly SoapResponseBuilder $builder,
        private readonly string $namespace
    ) {
    }

    /**
     * Handles raw SOAP XML and returns a SOAP response document.
     */
    public function handle(string $rawXml, string $clientIp): string
    {
        try {
            $invocation = $this->parser->parse($rawXml, $clientIp);
            $definition = OperationRegistry::definition($invocation->operation);

            if (($definition['authRequired'] ?? false) === true && ($invocation->token === null || $invocation->token === '')) {
                throw new AuthorizationException('ACCESS_DENIED', 'The SOAP auth header is required.');
            }

            $request = $this->instantiateRequest($definition['requestClass'], $invocation->arguments);
            $context = new RequestContext($invocation->token, $clientIp, $invocation->operation);
            $response = $this->service->{$invocation->operation}($request, $context);

            return $this->builder->build($invocation->operation, $response, $this->namespace);
        } catch (\Throwable $throwable) {
            return $this->faultXml(SoapFaultMapper::toSoapFault($throwable));
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function instantiateRequest(string $className, array $arguments): object
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return $reflection->newInstance();
        }

        $ordered = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $ordered[] = $arguments[$name] ?? $parameter->getDefaultValue();
        }

        return $reflection->newInstanceArgs($ordered);
    }

    private function faultXml(\SoapFault $fault): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $envelope = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $document->appendChild($envelope);
        $body = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Body');
        $envelope->appendChild($body);
        $faultNode = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Fault');
        $body->appendChild($faultNode);
        $faultNode->appendChild($document->createElement('faultcode', (string) $fault->faultcode));
        $faultNode->appendChild($document->createElement('faultstring', (string) $fault->faultstring));

        return $document->saveXML() ?: '';
    }
}
