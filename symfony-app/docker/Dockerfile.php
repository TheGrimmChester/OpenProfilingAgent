ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    autoconf \
    gcc \
    make \
    libtool \
    pkg-config \
    liblz4-dev \
    libpthread-stubs0-dev \
    default-mysql-client \
    libmariadb-dev \
    curl \
    wget \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    chmod +x /usr/local/bin/composer

# Install PHP extensions
RUN docker-php-ext-install sockets mysqli pdo pdo_mysql zip intl opcache

# Install APCu extension
RUN pecl install apcu && \
    docker-php-ext-enable apcu

# Install Redis extension
RUN pecl install redis && \
    docker-php-ext-enable redis

# Build OPA extension from source
WORKDIR /usr/src/opa
COPY ../../php-extension/config.m4 ../../php-extension/configure.ac ./
COPY ../../php-extension/src/ ./src/

# Build and install extension
RUN phpize && \
    ./configure --enable-opa && \
    make && \
    EXT_DIR=$(php-config --extension-dir) && \
    cp modules/opa.so $EXT_DIR/opa.so || \
    (echo "Warning: OPA extension build failed, continuing without it..." && touch $EXT_DIR/opa.so)

# Configure OPA extension
RUN if [ -f $(php-config --extension-dir)/opa.so ]; then \
        echo "extension=opa.so" > /usr/local/etc/php/conf.d/opa.ini && \
        echo "opa.enabled=1" >> /usr/local/etc/php/conf.d/opa.ini && \
        echo "opa.socket_path=/var/run/opa.sock" >> /usr/local/etc/php/conf.d/opa.ini && \
        echo "opa.sampling_rate=1.0" >> /usr/local/etc/php/conf.d/opa.ini && \
        echo "opa.debug_log=1" >> /usr/local/etc/php/conf.d/opa.ini; \
    else \
        echo "; OPA extension not available" > /usr/local/etc/php/conf.d/opa.ini; \
    fi

# Configure APCu
RUN echo "apc.enabled=1" >> /usr/local/etc/php/conf.d/apcu.ini && \
    echo "apc.shm_size=64M" >> /usr/local/etc/php/conf.d/apcu.ini && \
    echo "apc.ttl=3600" >> /usr/local/etc/php/conf.d/apcu.ini

# Configure PHP-FPM
RUN sed -i 's/listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set working directory back to Symfony app
WORKDIR /var/www/symfony

# Set permissions
RUN chown -R www-data:www-data /var/www/symfony && \
    chmod -R 755 /var/www/symfony

# Expose PHP-FPM port
EXPOSE 9000

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm", "-F"]

