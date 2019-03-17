FROM docker.jbuncle.co.uk/jbuncle/php-docker

ADD . /php-autosemver
ENV PATH="/php-autosemver/bin:${PATH}"

CMD tag