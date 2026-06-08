# Docker Hub images — configure Liara registry mirror on the host (daemon.json):
#   "registry-mirrors": ["https://docker-mirror.liara.ir"]
# Outside Iran, set APT_MIRROR=deb.debian.org for apt during build.
ARG PHP_IMAGE=php:8.2-apache-bookworm
FROM ${PHP_IMAGE}

ARG APT_MIRROR=mirror.iranserver.com
RUN sed -i "s|deb.debian.org|${APT_MIRROR}|g" /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli gd zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

# Copy ionCube loader manually
COPY ioncube/ioncube_loader_lin_8.2.so /usr/local/lib/php/extensions/

RUN echo "zend_extension=/usr/local/lib/php/extensions/ioncube_loader_lin_8.2.so" > /usr/local/etc/php/conf.d/00-ioncube.ini

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
