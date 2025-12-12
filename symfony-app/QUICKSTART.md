# Quick Start Guide

## Prerequisites

1. Docker and Docker Compose installed
2. The `opa_network` Docker network exists:
```bash
docker network create opa_network
```

## Quick Start

### 1. Start Core Services

```bash
# From the myapm root directory
docker-compose up -d clickhouse agent
```

Wait for services to be healthy (check with `docker-compose ps`).

### 2. Start Symfony Application

```bash
# Start all Symfony services
docker-compose up -d symfony-php symfony-nginx mysql-symfony redis-symfony
```

### 3. Install Dependencies

```bash
# Install Composer dependencies
docker exec -it symfony-php bash -c "cd /var/www/symfony && composer install"
```

### 4. Test the Application

```bash
# Health check
curl http://localhost:8080/health

# Test SQL profiling
curl http://localhost:8080/api/sql/pdo

# Test comprehensive features
curl http://localhost:8080/api/comprehensive
```

## Using Apache Instead of Nginx

```bash
# Start Apache
docker-compose --profile apache up -d symfony-apache

# Access at http://localhost:8081
```

## Running Tests

```bash
# Run PHPUnit tests
docker exec -it symfony-php bash -c "cd /var/www/symfony && php bin/phpunit"
```

## Viewing Logs

```bash
# PHP-FPM logs
docker logs -f symfony-php

# Nginx logs
docker logs -f symfony-nginx

# All services
docker-compose logs -f
```

## Stopping Services

```bash
# Stop Symfony services
docker-compose stop symfony-php symfony-nginx mysql-symfony redis-symfony

# Or stop everything
docker-compose down
```

## Available Endpoints

See [README.md](README.md) for a complete list of available endpoints.

## Troubleshooting

### Services Won't Start

1. Check if `opa_network` exists:
```bash
docker network ls | grep opa_network
```

2. Check service health:
```bash
docker-compose ps
```

3. Check logs:
```bash
docker-compose logs symfony-php
```

### Database Connection Issues

Test MySQL:
```bash
docker exec -it mysql-symfony mysql -u symfony_user -psymfony_password symfony_db -e "SELECT 1"
```

### OPA Extension Not Working

Check if extension is loaded:
```bash
docker exec -it symfony-php php -m | grep opa
```

Check OPA configuration:
```bash
docker exec -it symfony-php php -i | grep opa
```

## Next Steps

- Read [README.md](README.md) for detailed documentation
- Read [DOCKER.md](DOCKER.md) for Docker setup details
- Explore the test endpoints to see OPA features in action

