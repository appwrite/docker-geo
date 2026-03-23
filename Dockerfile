FROM composer:2 AS composer-bin

FROM appwrite/base:1.1.1 AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

ARG TESTING=false
ENV TESTING=$TESTING

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist \
    `if [ "$TESTING" != "true" ]; then echo "--no-dev"; fi`

FROM appwrite/base:1.1.1 as final

LABEL maintainer="team@appwrite.io"

WORKDIR /usr/src/code

COPY --from=vendor /usr/local/src/vendor /usr/src/code/vendor

# Add Source Code
COPY ./app /usr/src/code/app
COPY ./src /usr/src/code/src

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "exit(str_contains(file_get_contents('http://localhost:80/v1/health'), 'ok') ? 0 : 1);"

CMD ["php", "app/http.php"]
