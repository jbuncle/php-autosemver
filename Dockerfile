FROM alpine:3.13 as base
RUN apk add --no-cache \
        php7-tokenizer \
        php7-json \
        php7 \
        curl \
        bash \
        git


FROM base as build
RUN apk add --no-cache composer
COPY ./ /php-autosemver
WORKDIR /php-autosemver
RUN composer install --no-dev --prefer-dist


FROM base
COPY --from=build /php-autosemver /php-autosemver
ENV PATH="/php-autosemver/bin:${PATH}"
WORKDIR /app