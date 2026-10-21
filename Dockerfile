# ---- Fase 1: compilar el frontend de React ----
FROM node:22-alpine AS frontend-build
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# ---- Fase 2: la aplicación PHP ----
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
WORKDIR /var/www/html

# Cambiar esta fecha obliga a reinstalar PHP (sin caché) en el siguiente despliegue.
ARG PHP_ACTUALIZADO=2026-10-21
RUN echo "PHP actualizado: ${PHP_ACTUALIZADO}" \
    && echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-reintentos \
    && apt-get update && apt-get install -y \
    gnupg curl ca-certificates zip unzip git nginx supervisor pngquant \
    && mkdir -p /etc/apt/keyrings \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.setup-php.com/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        php8.5-fpm \
        php8.5-cli \
        php8.5-mysql \
        php8.5-gd \
        php8.5-curl \
        php8.5-mbstring \
        php8.5-xml \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-intl \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Ajustes de seguridad de PHP (docker/php-seguridad.ini).
COPY docker/php-seguridad.ini /etc/php/8.5/fpm/conf.d/99-seguridad.ini
COPY docker/php-seguridad.ini /etc/php/8.5/cli/conf.d/99-seguridad.ini
# Se niega a construir con un PHP sin el parche de CVE-2026-109618/109619:
# así Coolify nunca sustituye el contenedor bueno por uno vulnerable.
RUN php -r 'if (version_compare(PHP_VERSION, "8.5.12", "<")) { fwrite(STDERR, "PHP ".PHP_VERSION." sin parchear\n"); exit(1); }'

RUN userdel -r ubuntu 2>/dev/null || true \
    && groupadd --force -g 1000 sail \
    && useradd -ms /bin/bash --no-user-group -g 1000 -u 1000 sail

COPY . /var/www/html
COPY --from=frontend-build /app/frontend/dist /var/www/html/public

RUN mkdir -p /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/logs

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R sail:sail /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start-production.sh /usr/local/bin/start-production
RUN chmod +x /usr/local/bin/start-production

EXPOSE 80
ENTRYPOINT ["start-production"]