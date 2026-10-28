FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor curl libpng-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
