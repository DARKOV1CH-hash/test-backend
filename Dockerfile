FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
