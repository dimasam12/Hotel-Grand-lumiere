FROM php:8.4-fpm

RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev

COPY . /app

EXPOSE 9000

CMD ["php-fpm"]