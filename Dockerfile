# Stage 1: Build Frontend Assets
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY . .
RUN npm ci && npm run build

# Stage 2: PHP Runtime
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    autoconf \
    build-base \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    supervisor \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    opcache \
    pcntl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Copy built frontend assets from Stage 1 (Overwriting any host-generated ones)
COPY --from=frontend-builder /app/public/build ./public/build

# Fix Git ownership issue and install dependencies
RUN git config --global --add safe.directory /var/www/html \
    && composer install --no-interaction --optimize-autoloader --no-dev

# Set permissions
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache,testing} \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
