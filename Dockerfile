FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions mysqli pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --optimize-autoloader --no-scripts --no-interaction

ENV SERVER_NAME=":8080"

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
