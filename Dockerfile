ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

# Install system dependencies and PHP PostgreSQL extension
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

WORKDIR /app
