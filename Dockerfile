# syntax=docker/dockerfile:1
FROM php:8.4-fpm

# SO + libs (adicionado libpq-dev para PostgreSQL, removido libpng/jpeg/gd desnecessários pra esse projeto)
RUN apt-get update && apt-get install -y \
    git unzip tzdata curl \
    libzip-dev libpq-dev libicu-dev libonig-dev libxml2-dev \
 && docker-php-ext-install pdo pdo_pgsql pgsql intl zip bcmath pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

# Timezone BR
ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cria usuário com mesmo UID/GID do deploy na VPS (1003:1003)
RUN groupadd -g 1003 deploy || true && \
    useradd -u 1003 -g 1003 -m -s /bin/bash deploy || true

# Cria diretórios com permissões corretas
RUN mkdir -p storage/app/public \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache && \
    chown -R deploy:deploy storage bootstrap && \
    chmod -R 775 storage bootstrap

USER deploy

CMD ["php-fpm"]
