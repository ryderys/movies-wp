ARG PHP_IMAGE=php:8.2-apache-bookworm
FROM ${PHP_IMAGE}

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
        curl; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install mysqli gd zip; \
    mkdir -p /usr/src/php/ext/redis; \
    curl -fsSL -o /tmp/redis.tar.gz \
        https://github.com/phpredis/phpredis/archive/refs/tags/6.2.0.tar.gz; \
    tar xzf /tmp/redis.tar.gz -C /usr/src/php/ext/redis --strip-components=1; \
    rm /tmp/redis.tar.gz; \
    docker-php-ext-install redis; \
    a2enmod rewrite headers; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Copy and fix entrypoint line endings (Windows compatibility)
COPY docker-entrypoint.sh /usr/local/bin/
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy application source code
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy ionCube loader straight into the official extensions directory discovered in your log
COPY ioncube/ioncube_loader_lin_8.2.so /usr/local/lib/php/extensions/no-debug-non-zts-20220829/
RUN echo "zend_extension=ioncube_loader_lin_8.2.so" > /usr/local/etc/php/conf.d/00-ioncube.ini

# Use absolute path for safety
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
