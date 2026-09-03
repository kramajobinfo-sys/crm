FROM php:8.3-fpm-alpine
RUN apk add --no-cache git curl zip unzip libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
      libzip-dev oniguruma-dev icu-dev mysql-client autoconf gcc g++ make $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del autoconf gcc g++ make $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-krama.ini
WORKDIR /var/www/backend
EXPOSE 9000
CMD ["php-fpm"]
