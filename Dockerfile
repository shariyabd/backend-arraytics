# Address Book API — Laravel (PHP 8.3)
FROM php:8.3-cli

# System deps + PHP extensions required by Laravel + MySQL
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip intl bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

# Copy the application source
COPY . .
RUN composer dump-autoload --optimize

# Entrypoint waits for MySQL, prepares the app key, migrates + seeds, then serves
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chmod -R ug+rw storage bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
