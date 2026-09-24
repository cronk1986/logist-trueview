FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

# Usuario con el mismo UID/GID que el host para no generar ficheros de root en el bind mount
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN (getent group "${HOST_GID}" || addgroup -g "${HOST_GID}" app) \
    && adduser -D -u "${HOST_UID}" -G "$(getent group "${HOST_GID}" | cut -d: -f1)" app

USER app
WORKDIR /var/www/html

CMD ["php-fpm"]
