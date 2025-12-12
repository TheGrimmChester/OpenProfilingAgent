#!/bin/bash
set -e

echo "Starting Symfony application setup..."

# Configure core dumps for debugging
echo "Configuring core dumps..."
echo "/var/log/core-dumps/core.%e.%p.%t" > /proc/sys/kernel/core_pattern 2>/dev/null || true
ulimit -c unlimited || true
mkdir -p /var/log/core-dumps
chmod 777 /var/log/core-dumps || true
chown -R www-data:www-data /var/log/core-dumps 2>/dev/null || true
echo "rlimit_core = unlimited" >> /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true

# Wait for MySQL
if command -v mysqladmin &> /dev/null; then
    echo "Waiting for MySQL..."
    until mysqladmin ping -h mysql-symfony -u symfony_user -psymfony_password --silent 2>/dev/null || [ $? -ne 0 ]; do
        sleep 1
    done
    echo "MySQL is ready!"
fi

# Wait for Redis
if command -v redis-cli &> /dev/null; then
    echo "Waiting for Redis..."
    until redis-cli -h redis-symfony ping 2>/dev/null || [ $? -ne 0 ]; do
        sleep 1
    done
    echo "Redis is ready!"
fi

# Install Composer dependencies
if [ ! -d "vendor" ] && [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."
    php -n /usr/local/bin/composer install --no-interaction --optimize-autoloader || true
fi

# Configure OPA - Full Featured Mode
if [ "${OPA_ENABLED:-0}" = "1" ]; then
    echo "Enabling OPA extension (Full Featured Mode)..."
    sed -i 's/^;extension=opa\.so$/extension=opa.so/' /usr/local/etc/php/conf.d/opa.ini || true
    sed -i 's/opa.enabled=0/opa.enabled=1/' /usr/local/etc/php/conf.d/opa.ini || true
    
    # Core settings
    if [ -n "${OPA_SOCKET_PATH:-}" ]; then
        sed -i "s|opa.socket_path=.*|opa.socket_path=${OPA_SOCKET_PATH}|" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    if [ -n "${OPA_SAMPLING_RATE:-}" ]; then
        sed -i "s/opa.sampling_rate=.*/opa.sampling_rate=${OPA_SAMPLING_RATE}/" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    if [ -n "${OPA_EXPAND_SPANS:-}" ]; then
        sed -i "s/opa.expand_spans=.*/opa.expand_spans=${OPA_EXPAND_SPANS}/" /usr/local/etc/php/conf.d/opa.ini || true
    fi
    
    # Helper function to set or add INI setting
    update_ini_setting() {
        local key=$1
        local value=$2
        if grep -q "^${key}=" /usr/local/etc/php/conf.d/opa.ini 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" /usr/local/etc/php/conf.d/opa.ini || true
        else
            echo "${key}=${value}" >> /usr/local/etc/php/conf.d/opa.ini || true
        fi
    }
    
    # Debug and logging
    if [ -n "${OPA_DEBUG_LOG:-}" ]; then
        update_ini_setting "opa.debug_log" "${OPA_DEBUG_LOG}"
    fi
    if [ -n "${OPA_TRACK_ERRORS:-}" ]; then
        update_ini_setting "opa.track_errors" "${OPA_TRACK_ERRORS}"
    fi
    if [ -n "${OPA_TRACK_LOGS:-}" ]; then
        update_ini_setting "opa.track_logs" "${OPA_TRACK_LOGS}"
    fi
    if [ -n "${OPA_LOG_LEVELS:-}" ]; then
        update_ini_setting "opa.log_levels" "${OPA_LOG_LEVELS}"
    fi
    
    # Performance and capture settings
    if [ -n "${OPA_FULL_CAPTURE_THRESHOLD_MS:-}" ]; then
        update_ini_setting "opa.full_capture_threshold_ms" "${OPA_FULL_CAPTURE_THRESHOLD_MS}"
    fi
    if [ -n "${OPA_STACK_DEPTH:-}" ]; then
        update_ini_setting "opa.stack_depth" "${OPA_STACK_DEPTH}"
    fi
    if [ -n "${OPA_BUFFER_SIZE:-}" ]; then
        update_ini_setting "opa.buffer_size" "${OPA_BUFFER_SIZE}"
    fi
    if [ -n "${OPA_COLLECT_INTERNAL_FUNCTIONS:-}" ]; then
        update_ini_setting "opa.collect_internal_functions" "${OPA_COLLECT_INTERNAL_FUNCTIONS}"
    fi
    
    # Metadata and identification
    if [ -n "${OPA_ORGANIZATION_ID:-}" ]; then
        update_ini_setting "opa.organization_id" "${OPA_ORGANIZATION_ID}"
    fi
    if [ -n "${OPA_PROJECT_ID:-}" ]; then
        update_ini_setting "opa.project_id" "${OPA_PROJECT_ID}"
    fi
    if [ -n "${OPA_SERVICE:-}" ]; then
        update_ini_setting "opa.service" "${OPA_SERVICE}"
    fi
    if [ -n "${OPA_FRAMEWORK:-}" ]; then
        update_ini_setting "opa.framework" "${OPA_FRAMEWORK}"
    fi
    if [ -n "${OPA_FRAMEWORK_VERSION:-}" ]; then
        update_ini_setting "opa.framework_version" "${OPA_FRAMEWORK_VERSION}"
    fi
    if [ -n "${OPA_LANGUAGE_VERSION:-}" ]; then
        update_ini_setting "opa.language_version" "${OPA_LANGUAGE_VERSION}"
    fi
    
    echo "OPA extension enabled (Full Featured Mode)"
    echo "  - expand_spans: ${OPA_EXPAND_SPANS:-1}"
    echo "  - sampling_rate: ${OPA_SAMPLING_RATE:-1.0}"
    echo "  - track_errors: ${OPA_TRACK_ERRORS:-1}"
    echo "  - track_logs: ${OPA_TRACK_LOGS:-1}"
    echo "  - collect_internal_functions: ${OPA_COLLECT_INTERNAL_FUNCTIONS:-1}"
    echo "  - framework: ${OPA_FRAMEWORK:-symfony}"
else
    echo "OPA extension disabled"
    sed -i 's/^extension=opa\.so$/;extension=opa.so/' /usr/local/etc/php/conf.d/opa.ini || true
    sed -i 's/opa.enabled=1/opa.enabled=0/' /usr/local/etc/php/conf.d/opa.ini || true
fi

# Clear cache
if [ -f "bin/console" ]; then
    echo "Clearing Symfony cache..."
    php bin/console cache:clear --no-interaction || true
fi

echo "Symfony application is ready!"
exec "$@"

