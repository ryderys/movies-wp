# Defaults use Arvan (Docker) + IranServer (apt) for Iran. Override outside Iran:
#   docker compose build --build-arg PHP_IMAGE=php:8.2-apache-bookworm --build-arg APT_MIRROR=deb.debian.org
ARG PHP_IMAGE=docker.arvancloud.ir/library/php:8.2-apache-bookworm
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
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli gd zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Port 8080 for production (Caddy terminates HTTPS on 80/443 and proxies here).
# Port 80 remains available for local dev (docker-compose.yml).
COPY deploy/apache/listen-8080.conf /etc/apache2/conf-available/listen-8080.conf
RUN a2enconf listen-8080

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

COPY . /var/www/html

# WordPress core is gitignored; download it when building from a clone-only checkout.
ARG WORDPRESS_VERSION=6.7.2
RUN if [ ! -f /var/www/html/wp-settings.php ]; then \
        curl -fsSL "https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz" \
            | tar -xz --strip-components=1 -C /var/www/html; \
    fi

RUN chown -R www-data:www-data /var/www/html

# Copy ionCube loader manually
COPY ioncube/ioncube_loader_lin_8.2.so /usr/local/lib/php/extensions/

RUN echo "zend_extension=/usr/local/lib/php/extensions/ioncube_loader_lin_8.2.so" > /usr/local/etc/php/conf.d/00-ioncube.ini

EXPOSE 80 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
