# Core Dump Configuration for PHP-FPM

## Status: ✅ CONFIGURED

PHP-FPM is now configured to generate core dump files when segfaults occur.

## Configuration Summary

### 1. Core Pattern
- **Location**: `/proc/sys/kernel/core_pattern`
- **Value**: `/var/log/core-dumps/core.%e.%p.%t`
- **Format**: `core.<executable>.<pid>.<timestamp>`

### 2. Core Limit
- **System**: `unlimited` (via `ulimit -c unlimited`)
- **PHP-FPM Workers**: `unlimited` (via `rlimit_core = unlimited` in www.conf)
- **Docker**: `ulimits.core: -1` in docker-compose.yml

### 3. Directory
- **Container**: `/var/log/core-dumps/`
- **Host**: `./symfony-app/var/core-dumps/`
- **Permissions**: `777` (world-writable)
- **Owner**: `www-data:www-data`

### 4. PHP-FPM Configuration
- **File**: `/usr/local/etc/php-fpm.d/www.conf`
- **Setting**: `rlimit_core = unlimited` (set in `[www]` section)

### 5. Container Configuration
- **Privileged Mode**: `privileged: true` (required to set core_pattern)
- **Entrypoint**: `/usr/local/bin/entrypoint.sh` (configures core dumps on startup)

## How Core Dumps Are Generated

When a segfault occurs:
1. The kernel writes a core dump to `/var/log/core-dumps/core.php-fpm.<pid>.<timestamp>`
2. The file is automatically available on the host at `./symfony-app/var/core-dumps/`
3. The core dump contains the full memory state at the time of the crash

## Analyzing Core Dumps

### Find Core Dumps
```bash
# In container
docker exec symfony-php ls -lh /var/log/core-dumps/

# On host
ls -lh ./symfony-app/var/core-dumps/
```

### Analyze with GDB
```bash
# Get the latest core dump
CORE_FILE=$(docker exec symfony-php bash -c "ls -t /var/log/core-dumps/core.* | head -1")

# Analyze with GDB
docker exec symfony-php gdb -batch \
  -ex "file /usr/local/sbin/php-fpm" \
  -ex "core $CORE_FILE" \
  -ex "bt" \
  -ex "info registers" \
  -ex "x/20i \$pc" \
  -ex "quit"
```

### Get Backtrace
```bash
docker exec symfony-php gdb -batch \
  -ex "file /usr/local/sbin/php-fpm" \
  -ex "core $CORE_FILE" \
  -ex "bt full" \
  -ex "thread apply all bt" \
  -ex "quit"
```

## Troubleshooting

### Core Dumps Not Generated

1. **Check core pattern**:
   ```bash
   docker exec symfony-php cat /proc/sys/kernel/core_pattern
   ```

2. **Check core limit**:
   ```bash
   docker exec symfony-php bash -c "ps aux | grep 'php-fpm: pool www' | head -1 | awk '{print \$2}' | xargs -I {} cat /proc/{}/limits | grep core"
   ```

3. **Check directory permissions**:
   ```bash
   docker exec symfony-php ls -ld /var/log/core-dumps
   ```

4. **Check PHP-FPM config**:
   ```bash
   docker exec symfony-php grep 'rlimit_core' /usr/local/etc/php-fpm.d/www.conf
   ```

### Manual Test
```bash
# Trigger a test segfault
docker exec symfony-php bash -c "PHP_PID=\$(ps aux | grep 'php-fpm: pool www' | head -1 | awk '{print \$2}'); kill -SEGV \$PHP_PID"

# Check for core dump
docker exec symfony-php ls -lh /var/log/core-dumps/
```

## Notes

- Core dumps are large files (can be several GB)
- They contain sensitive memory data
- Clean up old core dumps regularly
- Core dumps are only generated on actual segfaults, not on normal exits

