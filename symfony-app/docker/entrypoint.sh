#!/bin/bash
set -e

echo "Starting Symfony application setup..."

# Wait for MySQL to be ready (if mysqladmin is available)
if command -v mysqladmin &> /dev/null; then
    echo "Waiting for MySQL..."
    until mysqladmin ping -h mysql-symfony -u symfony_user -psymfony_password --silent 2>/dev/null || [ $? -ne 0 ]; do
        echo "MySQL is unavailable - sleeping"
        sleep 1
    done
    echo "MySQL is ready!"
fi

# Wait for Redis to be ready (if redis-cli is available)
if command -v redis-cli &> /dev/null; then
    echo "Waiting for Redis..."
    until redis-cli -h redis-symfony ping 2>/dev/null || [ $? -ne 0 ]; do
        echo "Redis is unavailable - sleeping"
        sleep 1
    done
    echo "Redis is ready!"
fi

# Install Composer dependencies if needed (BEFORE enabling OPA)
if [ ! -d "vendor" ] && [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."
    # Use php -n to skip all ini files (including OPA extension) for Composer
    # This prevents any extension from interfering with Composer's cURL operations
    php -n /usr/local/bin/composer install --no-interaction --optimize-autoloader || true
fi

# Configure OPA based on environment variables (AFTER Composer installation)
if [ "${OPA_ENABLED:-0}" = "1" ]; then
    echo "Enabling OPA extension..."
    # Update PHP ini to enable OPA
    sed -i 's/opa.enabled=0/opa.enabled=1/' /usr/local/etc/php/conf.d/opa.ini || true
    if [ -n "${OPA_SOCKET_PATH:-}" ]; then
        sed -i "s|opa.socket_path=.*|opa.socket_path=${OPA_SOCKET_PATH}|" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    if [ -n "${OPA_SAMPLING_RATE:-}" ]; then
        sed -i "s/opa.sampling_rate=.*/opa.sampling_rate=${OPA_SAMPLING_RATE}/" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    if [ -n "${OPA_DEBUG_LOG:-}" ]; then
        sed -i "s/opa.debug_log=.*/opa.debug_log=${OPA_DEBUG_LOG}/" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    echo "OPA extension enabled"
else
    echo "OPA extension disabled (set OPA_ENABLED=1 to enable)"
fi

# Clear cache (if Symfony console is available)
if [ -f "bin/console" ]; then
    echo "Clearing Symfony cache..."
    php bin/console cache:clear --no-interaction || true
fi

echo "Symfony application is ready!"

# Execute the main command
exec "$@"

