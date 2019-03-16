FROM php:7.3

ADD . /php-autosemver
ENV PATH="/php-autosemver/bin:${PATH}"

CMD tag