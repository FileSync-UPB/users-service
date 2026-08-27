<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

use DOMDocument;
use DOMXPath;
use UpbCientifica\UserService\Exception\ValidationException;

/**
 * Parses SOAP 1.1 envelopes.
 */
final class SoapEnvelopeParser
{
    /**
     * Parses a SOAP envelope into an invocation model.
     */
    public function parse(string $rawXml, string $clientIp): SoapInvocation
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($rawXml, LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new ValidationException('INVALID_SOAP', 'The SOAP request is not valid XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('auth', 'urn:upbcientifica:user-service');

        $operationNode = $xpath->query('/soap:Envelope/soap:Body/*')->item(0);
        if (!$operationNode instanceof \DOMElement) {
            throw new ValidationException('INVALID_SOAP', 'The SOAP body is empty.');
        }

        $operation = $operationNode->localName;
        $arguments = [];
        foreach ($operationNode->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                continue;
            }

            $arguments[$childNode->localName] = $this->elementValue($childNode);
        }

        $tokenNode = $xpath->query('/soap:Envelope/soap:Header/*[local-name()="auth"]/*[local-name()="token"]')->item(0);

        $token = $tokenNode instanceof \DOMElement ? trim($tokenNode->textContent) : null;
        if ($token === '') {
            $token = null;
        }

        return new SoapInvocation($operation, $arguments, $token, $clientIp);
    }

    /**
     */
    private function elementValue(\DOMElement $element): mixed
    {
        $childElements = [];
        foreach ($element->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                $childElements[] = $childNode;
            }
        }

        if ($childElements === []) {
            return trim($element->textContent);
        }

        $grouped = [];
        foreach ($childElements as $childElement) {
            $value = $this->elementValue($childElement);
            $name = $childElement->localName;
            if (!array_key_exists($name, $grouped)) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $value;
        }

        foreach ($grouped as $name => $values) {
            if (count($values) === 1) {
                $grouped[$name] = $values[0];
            }
        }

        return $grouped;
    }
}
