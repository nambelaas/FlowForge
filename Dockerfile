FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader
RUN rm -rf node_modules package-lock.json && \
    npm install @rollup/rollup-linux-arm64-gnu --save-optional && \
    npm install && \
    npm run build

# Set permission sesuai standar keamanan Laravel 12
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000

CMD ["php-fpm"]