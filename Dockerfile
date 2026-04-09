FROM php:8.1-cli

# Install system deps for Composer and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json ./

# Install deps
RUN composer install --no-interaction --no-progress --prefer-dist || true

# Copy the rest of the project
COPY . .

# Re-run install to pick up autoload for project files
RUN composer install --no-interaction --no-progress --prefer-dist
