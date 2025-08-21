# Blue Cleaning Services - Docker Configuration
FROM php:8.2-apache

# Definir argumentos de build
ARG APP_ENV=production
ARG STRIPE_PUBLISHABLE_KEY
ARG STRIPE_SECRET_KEY
ARG SENTRY_DSN

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    git \
    unzip \
    nano \
    cron \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    intl \
    mbstring \
    xml \
    curl \
    opcache

# Habilitar mod_rewrite
RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod headers
RUN a2enmod expires

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY . .

# Instalar dependências PHP
RUN composer install --no-dev --optimize-autoloader

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod -R 777 /var/www/html/logs
RUN chmod -R 777 /var/www/html/cache
RUN chmod -R 777 /var/www/html/assets/uploads

# Configurar Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/ssl-default.conf /etc/apache2/sites-available/ssl-default.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Configurar SSL (certificados devem ser fornecidos via volume)
RUN a2ensite ssl-default

# Configurar Supervisor para processos background
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configurar variáveis de ambiente
ENV APP_ENV=${APP_ENV}
ENV STRIPE_PUBLISHABLE_KEY=${STRIPE_PUBLISHABLE_KEY}
ENV STRIPE_SECRET_KEY=${STRIPE_SECRET_KEY}
ENV SENTRY_DSN=${SENTRY_DSN}

# Script de inicialização
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/api/health.php || exit 1

# Expor portas
EXPOSE 80 443

# Comando de inicialização
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
