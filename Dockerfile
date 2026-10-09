# =============================================================================
# Dockerfile Laravel API — João Domingues
# Baseado no da API do Arquiteto Online, sem Octane/RoadRunner/Redis:
# esta API usa PHP 8.3, MySQL e fila em base de dados.
# =============================================================================
FROM php:8.3-cli-alpine

LABEL maintainer="API - Joao Domingues"
LABEL description="API Laravel - Joao Domingues Imobiliario"

RUN apk add --no-cache \
    bash \
    curl \
    git \
    mysql-client \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    intl

RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory-limit.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction

RUN mkdir -p storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8048

CMD ["/usr/local/bin/start.sh"]
