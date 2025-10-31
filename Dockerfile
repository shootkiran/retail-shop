FROM unit:1.34.1-php8.3

RUN apt update && apt install -y \
    curl unzip git nano libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libssl-dev nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pcntl opcache pdo pdo_mysql intl zip gd exif ftp bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit_buffer_size=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache

RUN chown -R unit:unit /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY . .

RUN chown -R unit:unit storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 775 database

RUN cp .env.example .env

RUN composer install --prefer-dist --optimize-autoloader --no-interaction

RUN composer upgrade --prefer-dist --optimize-autoloader --no-interaction

RUN npm install && npm run build

RUN php artisan key:generate --force

RUN touch database/database.sqlite \
    && chown unit:unit database/database.sqlite \
    && chmod 664 database/database.sqlite

RUN php artisan migrate --seed

COPY unit.json /docker-entrypoint.d/unit.json

EXPOSE 80

CMD ["sh", "-c", "php artisan migrate --seed && unitd --no-daemon"]
