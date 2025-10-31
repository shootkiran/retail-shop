FROM php:8.3-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_OPTIONS="--max-old-space-size=4096"

RUN apt-get update && apt-get install -y \
        nginx \
        curl \
        unzip \
        git \
        nano \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libssl-dev \
        libonig-dev \
        nodejs \
        npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pcntl opcache pdo pdo_mysql pdo_sqlite intl zip gd exif ftp bcmath mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    { \
        echo "opcache.enable=1"; \
        echo "opcache.jit=tracing"; \
        echo "opcache.jit_buffer_size=256M"; \
        echo "memory_limit=512M"; \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
    } > /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN set -eux; \
    mkdir -p storage bootstrap/cache database; \
    chown -R www-data:www-data storage bootstrap/cache database; \
    chmod -R 775 storage bootstrap/cache; \
    chmod 775 database

RUN cp .env.example .env

RUN composer upgrade --prefer-dist --optimize-autoloader --no-interaction

RUN npm install && npm run build

RUN php artisan key:generate --force

RUN touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite \
    && chmod 664 database/database.sqlite

RUN php artisan migrate --seed

RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/start-container.sh /usr/local/bin/start-container.sh

RUN chmod +x /usr/local/bin/start-container.sh

EXPOSE 80

CMD ["start-container.sh"]
