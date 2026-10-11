FROM php:8.2-apache

# Instalar dependencias del sistema necesarias para las extensiones
RUN apt-get update && apt-get install -y \
    libldap2-dev \
    libxml2-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensión LDAP
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap

# Instalar extensiones pdo_mysql y soap
RUN docker-php-ext-install pdo pdo_mysql soap

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar el contenido del servicio de usuarios
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer el puerto 80
EXPOSE 80
