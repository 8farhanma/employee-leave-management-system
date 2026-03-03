FROM php:8.2-cli AS sodium-builder

# Recompile PHP dengan sodium static
RUN apt-get update && apt-get install -y \
    libsodium-dev build-essential autoconf \
    && rm -rf /var/lib/apt/lists/*

# Download source PHP yang sama dan recompile sodium sebagai static
RUN cd /usr/src/php \
    && ./configure \
    --with-sodium \
    --disable-all \
    && make ext/sodium/libsodium.la -j$(nproc) 2>/dev/null || true

FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_mysql mbstring zip exif pcntl sodium

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .
RUN composer install --optimize-autoloader
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]