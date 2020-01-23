FROM cyberpearuk/php-build-docker

ADD . /php-autosemver
ENV PATH="/php-autosemver/bin:${PATH}"

WORKDIR /php-autosemver
RUN composer install --no-dev

WORKDIR /app
CMD tag