# Local development image only – production runs on goneo shared hosting
# (Apache + PHP 8.4, no Docker). Keep this image close to that environment.
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip bcmath gd opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

# Only Laravel's public/ directory is exposed, exactly as on goneo.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
