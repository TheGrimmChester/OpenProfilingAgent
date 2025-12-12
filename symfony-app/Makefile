.PHONY: help install test up down logs shell

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install Composer dependencies
	docker exec -it symfony-php bash -c "cd /var/www/symfony && composer install"

test: ## Run PHPUnit tests
	docker exec -it symfony-php bash -c "cd /var/www/symfony && php bin/phpunit"

up: ## Start Symfony services
	docker-compose up -d symfony-php symfony-nginx mysql-symfony redis-symfony

down: ## Stop Symfony services
	docker-compose stop symfony-php symfony-nginx symfony-apache mysql-symfony redis-symfony

logs: ## Show logs
	docker-compose logs -f symfony-php symfony-nginx

shell: ## Open shell in PHP container
	docker exec -it symfony-php bash

