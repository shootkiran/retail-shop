# ===========================================
# Stage 0 - PHP builder with required extensions
# ===========================================
FROM php:8.3-cli AS php-builder

# System deps for PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*


WORKDIR /app


# Now copy the rest of the application
COPY . .

# Bring in Composer binary
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ===========================================
# Stage 1 - Production Runtime (PHP-FPM)
# ===========================================
FROM php:8.3-fpm

# System deps and PHP extensions (match builder to avoid surprises)
RUN apt-get update && apt-get install -y \
    supervisor \
    cron \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy app (including vendor from builder stage)
COPY --from=php-builder /app /var/www/html

# Supervisor configs (queue/reverb/etc.)
COPY supervisor/ /etc/supervisor/conf.d/

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Permissions for Laravel
RUN chown -R www-data:www-data \
      /var/www/html/storage \
      /var/www/html/bootstrap/cache

# Reverb default port
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
