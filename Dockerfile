FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions mysqli pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /app

WORKDIR /app

RUN if [ -f composer.json ]; then composer install --optimize-autoloader --no-scripts --no-interaction; fi