FROM php:8.2-fpm-alpine

# =========================================================
# System dependencies
# =========================================================

RUN apk add --no-cache \
    nginx \
    supervisor \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    git \
    oniguruma-dev \
    icu-dev \
    icu-libs \
    nodejs \
    npm

# =========================================================
# PHP extensions
# =========================================================

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        pcntl \
        bcmath \
        intl

# =========================================================
# Composer
# =========================================================

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# =========================================================
# Composer dependencies
# =========================================================

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

# =========================================================
# Application
# =========================================================

COPY . .

# =========================================================
# Frontend / Vite
# =========================================================

RUN npm ci

RUN npm run build

# =========================================================
# Nginx + Supervisor
# =========================================================

COPY ./docker/nginx.conf /etc/nginx/nginx.conf

COPY ./docker/supervisord.conf \
    /etc/supervisor/conf.d/supervisord.conf

# =========================================================
# Permissions
# =========================================================

RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# =========================================================
# Port
# =========================================================

EXPOSE 80

# =========================================================
# Start application
# =========================================================

CMD php artisan migrate --force && \
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
