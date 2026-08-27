# UPB Científica User Service

Servicio SOAP 1.1 document/literal para gestión de usuarios con LDAP como fuente de identidad y MySQL como fuente de autorización, cuotas, servicios, tokens y auditoría.

## Arquitectura

```
SOAP Layer
    ↓
Application Services
    ↓
Domain / Business Logic
    ↓
Repositories
    ↓
Infrastructure
    ├── LDAP
    └── MySQL
```

No se usa Laravel, Symfony ni ningún framework.

## Estructura

- `public/SoapServer.php`: endpoint SOAP.
- `src/`: capas de aplicación, dominio, seguridad e infraestructura.
- `config/`: configuración por entorno.
- `sql/schema.sql`: esquema MySQL.
- `ldap/base.ldif`: base LDAP importable con `ldapadd`.
- `wsdl/`: contrato SOAP.
- `scripts/`: ejemplos y utilidades.
- `tests/`: pruebas automáticas sin dependencias externas.

## Requisitos

- PHP 8.4
- Extensiones `pdo`, `soap`, `ldap` y `openssl`
- Apache2
- OpenLDAP
- MySQL 8

## Configuración

1. Copia `config/.env.example` a un archivo de entorno real.
2. Define:
   - `LDAP_HOST`
   - `LDAP_PORT`
   - `LDAP_BASE_DN`
   - `LDAP_BIND_DN`
   - `LDAP_BIND_PASSWORD`
   - `MYSQL_HOST`
   - `MYSQL_PORT`
   - `MYSQL_DATABASE`
   - `MYSQL_USER`
   - `MYSQL_PASSWORD`
   - `TOKEN_TTL`
   - `APP_ENV`
3. Ajusta `SOAP_ENDPOINT` si el host final cambia.

## Instalación

Ejecuta `install.sh` en una máquina Linux con privilegios administrativos. El script:

1. instala dependencias;
2. configura OpenLDAP;
3. crea la estructura LDAP;
4. crea usuarios y grupos de prueba;
5. crea la base de datos;
6. crea el usuario MySQL de aplicación;
7. despliega la aplicación;
8. configura Apache;
9. configura permisos;
10. ejecuta pruebas básicas;
11. muestra el endpoint SOAP y el estado final.

Las credenciales pueden suministrarse por variables de entorno.

## Ejecución

Con Apache configurado, el endpoint SOAP queda publicado en:

`https://HOST/users/soap`

## Pruebas

```
php tests/RunTests.php
```

Las pruebas cubren:

- autenticación correcta e incorrecta;
- token válido y expirado;
- usuario inexistente y duplicado;
- autorización y rechazo;
- creación, consulta y eliminación de usuarios;
- roles y permisos;
- cuotas;
- servicios;
- auditoría;
- almacenamiento.

## SOAP

- Namespace: `urn:upbcientifica:user-service`
- Estilo: `document/literal`
- Versión: `SOAP 1.1`
- Autenticación protegida: header `<auth><token>...</token></auth>`

## Notas de seguridad

- No se incluyen credenciales de producción.
- No se registran contraseñas ni tokens en logs.
- Los tokens se guardan únicamente como hash en MySQL.
- Los errores públicos se exponen como SOAP Fault con códigos de aplicación.
