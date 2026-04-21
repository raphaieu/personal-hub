# syntax=docker/dockerfile:1
FROM php:8.4-fpm

# SO + libs
RUN apt-get update && apt-get install -y \
    git unzip tzdata curl \
    libzip-dev libpq-dev libicu-dev libonig-dev libxml2-dev \
 && docker-php-ext-install pdo pdo_pgsql pgsql intl zip bcmath pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

# Node 24
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
 && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# Timezone BR
ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupadd -g 1003 deploy || true && \
    useradd -u 1003 -g 1003 -m -s /bin/bash deploy || true

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