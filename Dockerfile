# -------- stage 1: deps (composer) --------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-ansi --no-interaction --no-scripts --no-progress

# -------- stage 2: app --------
FROM php:8.3-cli-alpine

# pacotes essenciais e extensões
RUN apk add --no-cache "$PHPIZE_DEPS" libstdc++ openssl-dev \
 && docker-php-ext-install pcntl opcache \
 && pecl install openswoole \
 && docker-php-ext-enable openswoole opcache

# OpenSwoole (ou troque por 'pecl install swoole' se preferir o Swoole)
#RUN pecl install openswoole \
# && docker-php-ext-enable openswoole opcache

# otimizações do OPcache (produção, baixo footprint)
COPY ./docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# cache de config/rotas p/ reduzir boot
RUN php artisan config:cache && php artisan route:cache

# porta do Octane
EXPOSE 8000

# comando padrão: 1 worker p/ economizar CPU; sem task workers p/ economizar RAM
# max-requests ajuda a evitar acumular pequenos leaks
ENTRYPOINT ["php", "artisan", "octane:start", \
  "--server=swoole", \
  "--host=0.0.0.0", \
  "--port=8000", \
  "--workers=1", \
  "--task-workers=0", \
  "--max-requests=1000" \
]
