FROM php:8.5-cli-alpine@sha256:93684051146ec037620855feb77f278090bde45ddc030801cd3f2a7685bc4deb AS runtime
WORKDIR /app
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/bin/composer
RUN --mount=type=cache,target=/var/cache/apk apk update \
    && apk add libpq \
    && apk add --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del .build-deps
RUN addgroup -S library && adduser -S library -G library

FROM runtime AS dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-interaction --prefer-dist --no-scripts

FROM dependencies AS application
COPY docker/php.ini /usr/local/etc/php/conf.d/library.ini
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/views storage/logs \
    && composer dump-autoload --no-interaction \
    && chown -R library:library /app
USER library
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
