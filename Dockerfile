FROM composer:2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93 AS composer

ARG TESTING=false
ENV TESTING=$TESTING

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install --ignore-platform-reqs --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist \
    `if [ "$TESTING" != "true" ]; then echo "--no-dev"; fi`

FROM appwrite/base:2.2.11@sha256:d4a5d23eef42690bae5d6d00aa0555d22042f1dbfff1bdcc835fd6d144aa147a AS final

RUN apk upgrade --no-cache

LABEL maintainer="team@appwrite.io"

WORKDIR /usr/src/code

COPY --from=composer /usr/local/src/vendor /usr/src/code/vendor

# Add Source Code
COPY ./app /usr/src/code/app
COPY ./src /usr/src/code/src

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "exit(str_contains(file_get_contents('http://localhost:' . (getenv('GEO_PORT') ?: '80') . '/v1/health'), 'ok') ? 0 : 1);"

CMD ["php", "app/http.php"]
