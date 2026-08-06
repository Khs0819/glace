FROM php:8.2-fpm-alpine

# تثبيت متطلبات النظام وامتدادات PHP المطلوبة لـ Laravel و Filament دفعة واحدة
RUN apk add --no-cache \
    nginx \
    supervisor \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    git \
    oniguruma-dev \
    icu-dev \
    icu-libs

# تثبيت وتفعيل إضافات PHP بالكامل
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd pcntl bcmath intl

# تثبيت أحدث إصدار من Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# تثبيت حزم الملحقات الخاصة بـ Laravel
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# نسخ ملفات إعدادات الخادم والتشغيل
COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ضبط صلاحيات المجلدات لتفادي مشاكل الرفع والقراءة
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["sh", "-c", "php artisan make:filament-user && exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf"]


