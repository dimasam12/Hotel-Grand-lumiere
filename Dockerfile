FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions mysqli pdo_mysql

COPY . /app

WORKDIR /app

RUN if [ -f composer.json ]; then composer install --optimize-autoloader --no-scripts --no-interaction; fi