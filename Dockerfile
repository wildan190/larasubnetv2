FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    libpq-dev \
    nano \
    procps

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy source dengan kepemilikan www-data sehingga tidak perlu chown terpisah
COPY --chown=www-data:www-data . .

# Install PHP deps
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions minimal (jika masih perlu)
RUN chmod -R 755 /var/www

EXPOSE 9000

CMD ["php-fpm"]
