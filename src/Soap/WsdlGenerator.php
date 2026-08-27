<?php
declare(strict_types=1);

namespace UpbCientifica\UserService\Soap;

/**
 * Generates the self-contained WSDL document.
 */
final class WsdlGenerator
{
    /**
     * Generates the WSDL XML content.
     */
    public function generate(string $endpoint, string $namespace): string
    {
        $definitions = OperationRegistry::definitions();
        $xsd = "http://www.w3.org/2001/XMLSchema";
        $soap = "http://schemas.xmlsoap.org/wsdl/soap/";
        $wsdl = "http://schemas.xmlsoap.org/wsdl/";

        $types = [];
        foreach ($definitions as $operation => $definition) {
            $types[] = $this->complexTypeFor($operation, $definition['requestFields'], 'Request', $xsd);
            $types[] = $this->complexTypeFor($operation, $definition['responseFields'], 'Response', $xsd);
        }

        $messages = [];
        $portType = [];
        $bindingOps = [];
        $serviceOps = [];
        foreach ($definitions as $operation => $definition) {
            $messages[] = <<<XML
                <message name="{$operation}Request">
                  <part name="parameters" element="tns:{$operation}"/>
                </message>
                <message name="{$operation}Response">
                  <part name="parameters" element="tns:{$operation}Response"/>
                </message>
                XML;
            $portType[] = <<<XML
                <operation name="{$operation}">
                  <input message="tns:{$operation}Request"/>
                  <output message="tns:{$operation}Response"/>
                </operation>
                XML;
            $bindingOps[] = <<<XML
                <operation name="{$operation}">
                  <soap:operation soapAction="{$namespace}#{$operation}" style="document"/>
                  <input><soap:body use="literal"/></input>
                  <output><soap:body use="literal"/></output>
                </operation>
                XML;
            $serviceOps[] = '';
        }

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <definitions xmlns="{$wsdl}"
                     xmlns:soap="{$soap}"
                     xmlns:tns="{$namespace}"
                     xmlns:xsd="{$xsd}"
                     targetNamespace="{$namespace}"
                     name="UserService">
          <types>
            <xsd:schema targetNamespace="{$namespace}" elementFormDefault="qualified">
              {$this->schemaElements($definitions)}
              {$this->schemaResponses($definitions)}
            </xsd:schema>
          </types>
          {$this->joinXml($messages)}
          <portType name="UserServicePortType">
            {$this->joinXml($portType)}
          </portType>
          <binding name="UserServiceBinding" type="tns:UserServicePortType">
            <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
            {$this->joinXml($bindingOps)}
          </binding>
          <service name="UserService">
            <port name="UserServicePort" binding="tns:UserServiceBinding">
              <soap:address location="{$endpoint}"/>
            </port>
          </service>
        </definitions>
        XML;
    }

    /**
     * @param array<int, string> $fields
     */
    private function complexTypeFor(string $operation, array $fields, string $suffix, string $xsdNamespace): string
    {
        $elements = [];
        foreach ($fields as $field) {
            $type = match ($field) {
                'permissions', 'roles', 'services', 'groups' => 'tns:ArrayOfString',
                'users' => 'tns:ArrayOfUser',
                'valid', 'created', 'updated', 'deleted', 'assigned', 'removed', 'enabled' => 'xsd:boolean',
                'quotaMb', 'usedMb', 'availableMb' => 'xsd:int',
                default => 'xsd:string',
            };
            $elements[] = '<xsd:element name="' . $field . '" type="' . $type . '" minOccurs="0"/>';
        }

        return '';
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function schemaElements(array $definitions): string
    {
        $elements = [];
        foreach ($definitions as $operation => $definition) {
            $elements[] = $this->schemaElement($operation, $definition['requestFields']);
            $elements[] = $this->schemaElement($operation . 'Response', $definition['responseFields']);
        }

        $elements[] = <<<XML
          <xsd:complexType name="ArrayOfString">
            <xsd:sequence>
              <xsd:element name="item" type="xsd:string" minOccurs="0" maxOccurs="unbounded"/>
            </xsd:sequence>
          </xsd:complexType>
          XML;
        $elements[] = <<<XML
          <xsd:complexType name="User">
            <xsd:sequence>
              <xsd:element name="username" type="xsd:string"/>
              <xsd:element name="givenName" type="xsd:string"/>
              <xsd:element name="sn" type="xsd:string"/>
              <xsd:element name="mail" type="xsd:string"/>
              <xsd:element name="groups" type="tns:ArrayOfString" minOccurs="0"/>
              <xsd:element name="uid" type="xsd:string"/>
              <xsd:element name="homeDirectory" type="xsd:string"/>
              <xsd:element name="active" type="xsd:boolean"/>
              <xsd:element name="roleCode" type="xsd:string" minOccurs="0"/>
            </xsd:sequence>
          </xsd:complexType>
          XML;
        $elements[] = <<<XML
          <xsd:complexType name="ArrayOfUser">
            <xsd:sequence>
              <xsd:element name="user" type="tns:User" minOccurs="0" maxOccurs="unbounded"/>
            </xsd:sequence>
          </xsd:complexType>
          XML;

        return implode("\n", $elements);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function schemaElement(string $name, array $fields): string
    {
        $children = [];
        foreach ($fields as $field) {
            $type = match ($field) {
                'permissions', 'roles', 'services', 'groups' => 'tns:ArrayOfString',
                'users' => 'tns:ArrayOfUser',
                'valid', 'created', 'updated', 'deleted', 'assigned', 'removed', 'enabled' => 'xsd:boolean',
                'quotaMb', 'usedMb', 'availableMb' => 'xsd:int',
                default => 'xsd:string',
            };
            $children[] = '<xsd:element name="' . $field . '" type="' . $type . '" minOccurs="0"/>';
        }

        return '<xsd:element name="' . $name . '"><xsd:complexType><xsd:sequence>' . implode('', $children) . '</xsd:sequence></xsd:complexType></xsd:element>';
    }

    /**
     * @param array<int, string> $chunks
     */
    private function joinXml(array $chunks): string
    {
        return implode("\n", array_filter(array_map('trim', $chunks), static fn (string $chunk): bool => $chunk !== ''));
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function schemaResponses(array $definitions): string
    {
        $responses = [];
        foreach ($definitions as $operation => $definition) {
            if ($operation === 'getUser') {
                continue;
            }
        }

        return '';
    }
}
