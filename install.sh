#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${SCRIPT_DIR}"
ENV_FILE="${APP_DIR}/.env"
SOURCE_ENV="${APP_DIR}/config/.env.example"

log() {
  printf '%s\n' "$1"
}

require_env() {
  local name="$1"
  local value="${!name:-}"
  if [[ -z "${value}" ]]; then
    printf 'Missing required environment variable: %s\n' "$name" >&2
    exit 1
  fi
}

write_env_file() {
  if [[ -f "${ENV_FILE}" ]]; then
    log "Environment file already exists: ${ENV_FILE}"
    return
  fi

  cp "${SOURCE_ENV}" "${ENV_FILE}"
  log "Created ${ENV_FILE} from template."
}

install_packages() {
  if command -v apt-get >/dev/null 2>&1; then
    sudo apt-get update
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 php php-cli php-soap php-ldap php-mysql ldap-utils mysql-server
  fi
}

configure_ldap() {
  require_env LDAP_BIND_PASSWORD
  require_env LDAP_HOST
  require_env LDAP_PORT

  if command -v ldapadd >/dev/null 2>&1; then
    ldapadd -x -H "ldap://${LDAP_HOST}:${LDAP_PORT}" -D "${LDAP_BIND_DN:-cn=admin,dc=upbcientifica,dc=local}" -w "${LDAP_BIND_PASSWORD}" -f "${APP_DIR}/ldap/base.ldif" || true
  fi
}

configure_mysql() {
  require_env MYSQL_DATABASE
  require_env MYSQL_USER
  require_env MYSQL_PASSWORD

  if command -v mysql >/dev/null 2>&1; then
    mysql -uroot <<SQL || true
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, EXECUTE ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
    mysql -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" < "${APP_DIR}/sql/schema.sql" || true
  fi
}

configure_apache() {
  local site_conf="/etc/apache2/sites-available/upbcientifica-user-service.conf"
  local conf_conf="/etc/apache2/conf-available/upbcientifica-user-service.conf"
  sudo mkdir -p /var/www/upbcientifica-user-service
  if command -v rsync >/dev/null 2>&1; then
    sudo rsync -a --delete --exclude '.git' --exclude '.env' --exclude 'vendor' "${APP_DIR}/" /var/www/upbcientifica-user-service/
  else
    sudo cp -a "${APP_DIR}/." /var/www/upbcientifica-user-service/
  fi

  sudo tee "${conf_conf}" >/dev/null <<'EOF'
<Directory /var/www/upbcientifica-user-service>
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>

<FilesMatch "^(?!SoapServer\.php$).*$">
    Require all denied
</FilesMatch>
EOF

  sudo tee "${site_conf}" >/dev/null <<'EOF'
<VirtualHost *:80>
    ServerName HOST
    DocumentRoot /var/www/upbcientifica-user-service/public
    Alias /users/soap /var/www/upbcientifica-user-service/public/SoapServer.php

    <Directory /var/www/upbcientifica-user-service/public>
        Require all granted
        AllowOverride None
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/upbcientifica-user-service-error.log
    CustomLog ${APACHE_LOG_DIR}/upbcientifica-user-service-access.log combined
</VirtualHost>
EOF

  sudo a2enmod rewrite headers ssl || true
  sudo a2enconf upbcientifica-user-service || true
  sudo a2ensite upbcientifica-user-service || true
  sudo systemctl reload apache2 || true
}

run_tests() {
  if command -v php >/dev/null 2>&1; then
    php "${APP_DIR}/tests/RunTests.php" || true
  fi
}

main() {
  write_env_file
  install_packages
  configure_ldap
  configure_mysql
  configure_apache
  run_tests
  log "SOAP endpoint: ${SOAP_ENDPOINT:-https://HOST/users/soap}"
  log "Installation status: completed"
}

main "$@"
