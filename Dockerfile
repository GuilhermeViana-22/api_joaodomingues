# =============================================================================
# Dockerfile da API Laravel: João Domingues
# Baseado no da API do Arquiteto Online, sem Octane/RoadRunner/Redis:
# esta API usa PHP 8.3, MySQL e fila em base de dados.
# =============================================================================
FROM php:8.3-cli-alpine

LABEL maintainer="API - Joao Domingues"
LABEL description="API Laravel - Joao Domingues Imobiliario"

# A imagem base já traz tudo o que o composer.lock exige (mbstring, xml,
# dom, curl, openssl, sodium, ...). Só faltam pdo_mysql (base de dados) e
# pcntl (timeouts do queue:work). bash corre o start.sh, curl o healthcheck
# e unzip extrai os pacotes do Composer.
RUN apk add --no-cache bash curl unzip \
    && docker-php-ext-install -j$(nproc) pdo_mysql pcntl

RUN printf "memory_limit=256M\nupload_max_filesize=10M\npost_max_size=12M\nexpose_php=Off\n" > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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
    storage/oauth \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Pastas que precisam de sobreviver aos redeploys. No Dokploy, em
# Advanced > Volumes/Mounts, crie um Volume Mount para cada uma:
#   joao-storage-public -> /var/www/html/storage/app/public  (fotos dos imóveis)
#   joao-passport       -> /var/www/html/storage/oauth       (chaves do Passport)
# Sem o mount, o Docker cria um volume anónimo novo a cada deploy e os
# ficheiros perdem-se.
VOLUME ["/var/www/html/storage/app/public", "/var/www/html/storage/oauth"]

EXPOSE 8048

CMD ["/usr/local/bin/start.sh"]
