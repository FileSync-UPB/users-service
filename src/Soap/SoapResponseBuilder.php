<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

use DOMDocument;
use DOMElement;

/**
 * Builds SOAP 1.1 document/literal responses.
 */
final class SoapResponseBuilder
{
    /**
     * Builds a SOAP XML response.
     */
    public function build(string $operation, mixed $response, string $namespace): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $envelope = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $document->appendChild($envelope);

        $body = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Body');
        $envelope->appendChild($body);

        $wrapper = $document->createElementNS($namespace, $operation . 'Response');
        $body->appendChild($wrapper);

        $this->appendValue($document, $wrapper, $response, null, $namespace);

        return $document->saveXML() ?: '';
    }

    /**
     * @param scalar|array<int, mixed>|array<string, mixed>|object|null $value
     */
    private function appendValue(DOMDocument $document, DOMElement $parent, mixed $value, ?string $name = null, ?string $namespace = null): void
    {
        if (is_object($value)) {
            foreach (get_object_vars($value) as $property => $propertyValue) {
                $this->appendValue($document, $parent, $propertyValue, $property, $namespace);
            }

            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_int($key)) {
                    $itemName = $name ?? 'item';
                    if (is_object($item)) {
                        $child = $namespace !== null ? $document->createElementNS($namespace, $itemName) : $document->createElement($itemName);
                        $parent->appendChild($child);
                        $this->appendValue($document, $child, $item, null, $namespace);
                    } else {
                        $child = $namespace !== null
                            ? $document->createElementNS($namespace, $itemName, $this->scalarToString($item))
                            : $document->createElement($itemName, $this->scalarToString($item));
                        $parent->appendChild($child);
                    }

                    continue;
                }

                if ($key === 'users' && is_array($item)) {
                    $usersContainer = $namespace !== null ? $document->createElementNS($namespace, 'users') : $document->createElement('users');
                    $parent->appendChild($usersContainer);
                    foreach ($item as $userItem) {
                        $userNode = $namespace !== null ? $document->createElementNS($namespace, 'user') : $document->createElement('user');
                        $usersContainer->appendChild($userNode);
                        $this->appendValue($document, $userNode, $userItem, null, $namespace);
                    }
                    continue;
                }

                if ($key === 'permissions' || $key === 'roles' || $key === 'services' || $key === 'groups') {
                    $container = $namespace !== null ? $document->createElementNS($namespace, $key) : $document->createElement($key);
                    $parent->appendChild($container);
                    foreach ((array) $item as $entry) {
                        $child = $namespace !== null
                            ? $document->createElementNS($namespace, 'item', $this->scalarToString($entry))
                            : $document->createElement('item', $this->scalarToString($entry));
                        $container->appendChild($child);
                    }
                    continue;
                }

                $child = $namespace !== null ? $document->createElementNS($namespace, $key) : $document->createElement($key);
                $parent->appendChild($child);
                $this->appendValue($document, $child, $item, null, $namespace);
            }

            return;
        }

        $parent->appendChild($document->createTextNode($this->scalarToString($value)));
    }

    private function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
