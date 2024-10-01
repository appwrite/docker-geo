FROM composer:2.0 AS composer

ARG TESTING=false
ENV TESTING=$TESTING

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install --ignore-platform-reqs --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist \
    `if [ "$TESTING" != "true" ]; then echo "--no-dev"; fi`

# TODO fix utopia-php/docker-base and use appwrite/utopia-base
FROM appwrite/base:0.9.3 as final 

LABEL maintainer="team@appwrite.io"

WORKDIR /usr/src/code

COPY --from=composer /usr/local/src/vendor /usr/src/code/vendor

# Add Source Code
COPY ./app /usr/src/code/app
COPY ./src /usr/src/code/src

EXPOSE 80

CMD ["php", "app/http.php"]