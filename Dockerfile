FROM php:8.3-apache

# pdo_mysql es la extensión que permite a PDO hablar con MySQL;
# la imagen oficial de PHP no la trae activada.
RUN docker-php-ext-install pdo pdo_mysql

# unzip lo necesita Composer para descargar paquetes comprimidos.
RUN apt-get update && apt-get install -y unzip \
    && rm -rf /var/lib/apt/lists/*

# Copiamos el binario de Composer desde su imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache servirá solo la carpeta public/.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# mod_rewrite es el módulo de Apache que reescribe URLs; viene desactivado por defecto.
RUN a2enmod rewrite

# Por defecto Apache ignora los .htaccess ("AllowOverride None"); lo cambiamos para que se lean.
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf