FROM php:8.2-fpm-alpine

# تثبيت متطلبات النظام
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

# تثبيت إضافات PHP
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

# تجهيز مجلدات Nginx المؤقتة
RUN mkdir -p \
    /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi \
    && chown -R www-data:www-data /var/lib/nginx \
    && chmod -R 755 /var/lib/nginx

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Composer cache
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

# نسخ المشروع
COPY . .

# Node / Vite
RUN npm ci
RUN npm run build

# إعدادات Nginx و Supervisor
COPY ./docker/nginx.conf /etc/nginx/nginx.conf

# PHP upload limits. Without this the image runs on PHP's built-in 2 MB limit and
# any larger dashboard upload (a QR code, a product photo) silently fails.
COPY ./docker/php-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Laravel permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# التأكد من صلاحيات Nginx
RUN chown -R www-data:www-data /var/lib/nginx \
    && chmod -R 755 /var/lib/nginx

EXPOSE 80

# Persistent volumes — uploaded media and logs survive container rebuilds.
VOLUME /var/www/html/storage/app/public
VOLUME /var/www/html/storage/logs

# Startup script: creates upload dirs, fixes permissions, then boots Laravel.
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
