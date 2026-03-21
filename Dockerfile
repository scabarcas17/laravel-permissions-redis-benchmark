FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install zip pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

RUN cp .env.example .env \
    && php artisan key:generate

EXPOSE 8080

CMD ["sh", "-c", "php artisan migrate:fresh --seed --seeder=BenchmarkSeeder --force && php artisan serve --host=0.0.0.0 --port=8080"]
