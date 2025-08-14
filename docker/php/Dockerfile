FROM php:8.3-fpm

# System packages und php add ons
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libxml2-dev libzip-dev libonig-dev \
        iputils-ping unzip git curl ca-certificates autoconf \
        pkg-config \
    && docker-php-ext-install -j$(nproc) intl mysqli pdo pdo_mysql pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# Xdebug optional (via build arg)
ARG ENABLE_XDEBUG=0
RUN if [ "$ENABLE_XDEBUG" = "1" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi

# OPcache (Prod/Stage)
# Activate OPcache only on request
ARG ENABLE_OPCACHE=1
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN if [ "$ENABLE_OPCACHE" != "1" ]; then rm -f /usr/local/etc/php/conf.d/opcache.ini; fi

# Xdebug base config (only takes effect when extension is loaded)
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

WORKDIR /var/www/app