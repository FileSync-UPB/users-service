# SOAP Examples

## authenticateUser

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header/>
  <soapenv:Body>
    <urn:authenticateUser>
      <username>admin</username>
      <password>Admin123!</password>
    </urn:authenticateUser>
  </soapenv:Body>
</soapenv:Envelope>
```

## validateToken

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header>
    <urn:auth>
      <urn:token>...</urn:token>
    </urn:auth>
  </soapenv:Header>
  <soapenv:Body>
    <urn:validateToken/>
  </soapenv:Body>
</soapenv:Envelope>
```

## createUser

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header>
    <urn:auth><urn:token>...</urn:token></urn:auth>
  </soapenv:Header>
  <soapenv:Body>
    <urn:createUser>
      <username>nuevo1</username>
      <password>NewPass123!</password>
      <givenName>Nuevo</givenName>
      <sn>Usuario</sn>
      <mail>nuevo1@example.local</mail>
      <roleCode>INVITADO</roleCode>
      <quotaMb>250</quotaMb>
    </urn:createUser>
  </soapenv:Body>
</soapenv:Envelope>
```

## updateUser

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:updateUser>
      <username>nuevo1</username>
      <givenName>Nuevo</givenName>
      <sn>Usuario</sn>
      <mail>nuevo1@example.local</mail>
      <active>true</active>
    </urn:updateUser>
  </soapenv:Body>
</soapenv:Envelope>
```

## deleteUser

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:deleteUser><username>nuevo1</username></urn:deleteUser>
  </soapenv:Body>
</soapenv:Envelope>
```

## getUser

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getUser><username>admin</username></urn:getUser>
  </soapenv:Body>
</soapenv:Envelope>
```

## listUsers

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:listUsers/>
  </soapenv:Body>
</soapenv:Envelope>
```

## assignRole

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:assignRole>
      <username>nuevo1</username>
      <roleCode>EMPLEADO</roleCode>
    </urn:assignRole>
  </soapenv:Body>
</soapenv:Envelope>
```

## removeRole

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:removeRole>
      <username>nuevo1</username>
      <roleCode>EMPLEADO</roleCode>
    </urn:removeRole>
  </soapenv:Body>
</soapenv:Envelope>
```

## getRoles

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getRoles/>
  </soapenv:Body>
</soapenv:Envelope>
```

## getPermissions

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getPermissions>
      <roleCode>ADMINISTRADOR</roleCode>
    </urn:getPermissions>
  </soapenv:Body>
</soapenv:Envelope>
```

## setQuota

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:setQuota>
      <username>admin</username>
      <quotaMb>5000</quotaMb>
    </urn:setQuota>
  </soapenv:Body>
</soapenv:Envelope>
```

## getQuota

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getQuota><username>admin</username></urn:getQuota>
  </soapenv:Body>
</soapenv:Envelope>
```

## updateQuota

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:updateQuota>
      <username>admin</username>
      <quotaMb>4800</quotaMb>
    </urn:updateQuota>
  </soapenv:Body>
</soapenv:Envelope>
```

## enableService

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:enableService>
      <username>admin</username>
      <serviceCode>HPC</serviceCode>
    </urn:enableService>
  </soapenv:Body>
</soapenv:Envelope>
```

## disableService

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:disableService>
      <username>admin</username>
      <serviceCode>HPC</serviceCode>
    </urn:disableService>
  </soapenv:Body>
</soapenv:Envelope>
```

## listUserServices

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:listUserServices><username>admin</username></urn:listUserServices>
  </soapenv:Body>
</soapenv:Envelope>
```

## getHomeDirectory

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getHomeDirectory><username>admin</username></urn:getHomeDirectory>
  </soapenv:Body>
</soapenv:Envelope>
```

## getUserStorageInfo

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:upbcientifica:user-service">
  <soapenv:Header><urn:auth><urn:token>...</urn:token></urn:auth></soapenv:Header>
  <soapenv:Body>
    <urn:getUserStorageInfo><username>admin</username></urn:getUserStorageInfo>
  </soapenv:Body>
</soapenv:Envelope>
```
