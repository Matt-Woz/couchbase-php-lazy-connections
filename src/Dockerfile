FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    build-essential cmake g++ git ruby curl autoconf pkg-config make

ENV CB_PHP_PREFIX=/usr/local
ENV CMAKE_BUILD_PARALLEL_LEVEL=4

RUN which phpize && phpize --version

COPY couchbase-php-client /usr/src/couchbase-php-client
WORKDIR /usr/src/couchbase-php-client

 RUN ./bin/build.rb
 RUN cp ./modules/couchbase.so $(php -r "echo ini_get('extension_dir');")/

RUN docker-src-ext-enable couchbase

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
COPY couchbase-php-client ./couchbase-php-client
RUN composer install --no-interaction --prefer-dist

COPY . .
COPY php-fpm.conf /usr/local/etc/php-fpm.conf



