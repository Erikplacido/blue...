#!/bin/bash
set -e

# Blue Cleaning Services - Docker Entrypoint Script

echo "Starting Blue Cleaning Services..."

# Aguardar banco de dados
echo "Waiting for database connection..."
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --silent; do
    echo "Waiting for database..."
    sleep 2
done
echo "Database is ready!"

# Aguardar Redis
echo "Waiting for Redis..."
while ! redis-cli -h "$REDIS_HOST" ping > /dev/null 2>&1; do
    echo "Waiting for Redis..."
    sleep 2
done
echo "Redis is ready!"

# Criar diretórios necessários
echo "Creating required directories..."
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/cache
mkdir -p /var/www/html/assets/uploads
mkdir -p /var/www/html/assets/minified

# Configurar permissões
echo "Setting up permissions..."
chown -R www-data:www-data /var/www/html/logs
chown -R www-data:www-data /var/www/html/cache
chown -R www-data:www-data /var/www/html/assets/uploads
chmod -R 755 /var/www/html

# Executar migrações de banco (se existirem)
if [ -f "/var/www/html/database/migrations.sql" ]; then
    echo "Running database migrations..."
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/database/migrations.sql
fi

# Otimizar assets se em produção
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing assets for production..."
    
    # Minificar CSS e JS
    if [ -f "/var/www/html/scripts/optimize-assets.php" ]; then
        php /var/www/html/scripts/optimize-assets.php
    fi
    
    # Configurar OPcache
    echo "opcache.enable=1" >> /usr/local/etc/php/php.ini
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/php.ini
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/php.ini
    echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/php.ini
fi

# Configurar cron jobs
echo "Setting up cron jobs..."
cat > /etc/cron.d/blue-cleaning << EOF
# Blue Cleaning Services Cron Jobs

# Limpeza de cache expirado (a cada hora)
0 * * * * www-data /usr/local/bin/php /var/www/html/scripts/cleanup-cache.php

# Limpeza de logs antigos (diário às 2h)
0 2 * * * www-data /usr/local/bin/php /var/www/html/scripts/cleanup-logs.php

# Processamento de notificações pendentes (a cada 5 minutos)
*/5 * * * * www-data /usr/local/bin/php /var/www/html/scripts/process-notifications.php

# Health check interno (a cada 10 minutos)
*/10 * * * * www-data /usr/local/bin/php /var/www/html/scripts/health-check.php

# Backup incremental (diário às 3h)
0 3 * * * www-data /usr/local/bin/php /var/www/html/scripts/backup.php

EOF

# Iniciar cron
service cron start

# Configurar timezone
echo "Setting timezone..."
echo "Australia/Melbourne" > /etc/timezone
dpkg-reconfigure -f noninteractive tzdata

# Log de inicialização
echo "$(date): Blue Cleaning Services container started successfully" >> /var/www/html/logs/container.log

# Executar comando passado como parâmetro
echo "Starting services..."
exec "$@"
