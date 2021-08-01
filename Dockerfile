FROM php:8.0

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
  libicu-dev libonig-dev

RUN docker-php-ext-install  -j$(nproc) iconv intl mbstring sockets && \
  pecl install xdebug && \
  docker-php-ext-enable xdebug
