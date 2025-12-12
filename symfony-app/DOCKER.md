# Docker Setup for Symfony Test Application

This document describes the Docker setup for the Symfony test application.

## Architecture

The application consists of the following containers:

1. **symfony-php**: PHP-FPM container with OPA extension
2. **symfony-nginx**: Nginx web server (default)
3. **symfony-apache**: Apache web server (optional)
4. **mysql-symfony**: MySQL 8.0 database
5. **redis-symfony**: Redis cache server

All containers are connected to the `opa_network` Docker network and can communicate with the OPA agent.

## Building

### Prerequisites

1. Ensure the `opa_network` network exists:
```bash
docker network create opa_network
```

2. Start the core services (agent, ClickHouse):
```bash
docker-compose up -d clickhouse agent
```

### Build PHP Container

The PHP container uses the existing OPA test Dockerfile which includes:
- PHP 8.4 with FPM
- OPA extension (built from source)
- All required PHP extensions (PDO, MySQLi, Redis, APCu, cURL)
- Composer

The container is built automatically when you start the services.

### Build Web Servers

Nginx and Apache containers are built from their respective Dockerfiles:
- `docker/Dockerfile.nginx` - Nginx configuration
- `docker/Dockerfile.apache` - Apache configuration

## Starting Services

### Start All Services

```bash
# Start core services first
docker-compose up -d clickhouse agent

# Start Symfony app services
docker-compose up -d symfony-php symfony-nginx mysql-symfony redis-symfony
```

### Start with Apache

```bash
docker-compose --profile apache up -d symfony-apache
```

### Start Everything

```bash
docker-compose up -d
```

## Accessing the Application

- **Nginx**: http://localhost:8080
- **Apache**: http://localhost:8081
- **Health Check**: http://localhost:8080/health

## Container Details

### symfony-php

- **Image**: Built from `php-extension/docker/Dockerfile.test`
- **Working Directory**: `/var/www/symfony`
- **Volumes**: 
  - `./symfony-app:/var/www/symfony` (application code)
  - `opa_socket:/var/run` (OPA socket)
- **Ports**: 9000 (PHP-FPM)
- **Environment Variables**:
  - `DATABASE_URL`: MySQL connection string
  - `REDIS_URL`: Redis connection string
  - `OPA_ENABLED`: Enable OPA profiling
  - `OPA_SOCKET_PATH`: OPA socket path

### symfony-nginx

- **Image**: Built from `symfony-app/docker/Dockerfile.nginx`
- **Ports**: 80 (mapped to 8080)
- **Configuration**: `docker/nginx/default.conf`
- **Volumes**: Application code (read-only)

### symfony-apache

- **Image**: Built from `symfony-app/docker/Dockerfile.apache`
- **Ports**: 80 (mapped to 8081)
- **Configuration**: `docker/apache/httpd.conf` and `docker/apache/vhost.conf`
- **Volumes**: Application code (read-only)
- **Profile**: `apache` (use `--profile apache` to start)

### mysql-symfony

- **Image**: `mysql:8.0`
- **Ports**: 3306 (mapped to 3308)
- **Database**: `symfony_db`
- **User**: `symfony_user`
- **Password**: `symfony_password`
- **Root Password**: `symfony_root_password`

### redis-symfony

- **Image**: `redis:7-alpine`
- **Ports**: 6379 (mapped to 6380)
- **Volumes**: `redis_symfony_data:/data`

## Development Workflow

### Making Changes

1. Edit files in `symfony-app/`
2. Changes are automatically reflected (volumes are mounted)
3. For PHP changes, no restart needed
4. For configuration changes, restart the container:
```bash
docker-compose restart symfony-php
```

### Installing Dependencies

```bash
docker exec -it symfony-php bash -c "cd /var/www/symfony && composer install"
```

### Running Tests

```bash
docker exec -it symfony-php bash -c "cd /var/www/symfony && php bin/phpunit"
```

### Viewing Logs

```bash
# PHP-FPM logs
docker logs -f symfony-php

# Nginx logs
docker logs -f symfony-nginx

# All Symfony services
docker-compose logs -f symfony-php symfony-nginx
```

### Accessing Containers

```bash
# PHP container
docker exec -it symfony-php bash

# MySQL
docker exec -it mysql-symfony mysql -u symfony_user -psymfony_password symfony_db

# Redis
docker exec -it redis-symfony redis-cli
```

## Troubleshooting

### OPA Extension Not Loaded

Check if extension is available:
```bash
docker exec -it symfony-php php -m | grep opa
```

Check OPA configuration:
```bash
docker exec -it symfony-php php -i | grep opa
```

### Database Connection Issues

Test MySQL connection:
```bash
docker exec -it mysql-symfony mysql -u symfony_user -psymfony_password -e "SELECT 1"
```

Check if MySQL is ready:
```bash
docker exec -it mysql-symfony mysqladmin ping -h localhost -u root -psymfony_root_password
```

### Redis Connection Issues

Test Redis:
```bash
docker exec -it redis-symfony redis-cli ping
```

### Permission Issues

Fix permissions:
```bash
docker exec -it symfony-php chown -R www-data:www-data /var/www/symfony
docker exec -it symfony-php chmod -R 755 /var/www/symfony
```

### Container Won't Start

Check logs:
```bash
docker-compose logs symfony-php
```

Check if dependencies are ready:
```bash
docker-compose ps
```

## Stopping Services

```bash
# Stop Symfony services
docker-compose stop symfony-php symfony-nginx symfony-apache mysql-symfony redis-symfony

# Stop and remove containers
docker-compose down

# Stop and remove with volumes (WARNING: deletes data)
docker-compose down -v
```

## Network Configuration

All services use the `opa_network` Docker network. This allows:
- Communication between containers using service names
- Access to the OPA agent via socket or TCP
- Isolation from other Docker networks

## Volumes

- `mysql_symfony_data`: MySQL data persistence
- `redis_symfony_data`: Redis data persistence
- `opa_socket`: Shared OPA socket between PHP and agent

## Environment Variables

See `.env` file for default values. Override in `docker-compose.yml` or using environment files.

