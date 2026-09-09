# syntax=docker/dockerfile:1

# =============================================================================
# Tahap 1 — Dependensi PHP
# =============================================================================
FROM composer:2 AS vendor

WORKDIR /app

# Lapisan terpisah agar cache composer tidak batal setiap kali kode berubah.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative


# =============================================================================
# Tahap 2 — Aset frontend
# =============================================================================
# Vendor disalin lebih dulu karena app.css memuat @source yang menunjuk ke
# berkas Blade milik Laravel; tanpa vendor, pemindaian kelas Tailwind meleset.
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=vendor /app /app

RUN npm run build


# =============================================================================
# Tahap 3 — Runtime
# =============================================================================
FROM php:8.3-fpm-alpine AS runtime

# nginx melayani permintaan, php-fpm mengeksekusi PHP, gettext menyediakan
# envsubst untuk menyisipkan $PORT dari Railway ke konfigurasi nginx.
RUN apk add --no-cache \
        nginx \
        gettext \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=assets /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/99-sibados.ini
COPY docker/nginx.template.conf /etc/nginx/nginx.template.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Direktori yang ditulis saat runtime harus dimiliki pengguna php-fpm.
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -rf node_modules public/hot

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
