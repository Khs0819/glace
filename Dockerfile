FROM php:8.2-fpm-alpine

# تثبيت متطلبات النظام وامتدادات PHP المطلوبة لـ Laravel و Filament
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

# تثبيت وتفعيل إضافات PHP
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

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# نسخ ملفات Composer أولاً للاستفادة من Docker cache
COPY composer.json composer.lock ./

# تثبيت حزم Laravel
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

# نسخ ملفات المشروع
COPY . .

# تثبيت حزم Node.js وبناء ملفات Vite
RUN npm ci

RUN npm run build

# نسخ ملفات إعدادات الخادم والتشغيل
COPY ./docker/nginx.conf /etc/nginx/nginx.conf

COPY ./docker/supervisord.conf \
    /etc/supervisor/conf.d/supervisord.conf

# ضبط الصلاحيات
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

EXPOSE 80

# تشغيل migrations ثم التطبيق
CMD php artisan migrate --force && \
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
