#!/bin/bash

# ====================================
# SCRIPT DE MIGRAÇÃO - DINAMIZAÇÃO DE DADOS
# Data: 8 de agosto de 2025
# Objetivo: Migrar sistema de dados hardcoded para dinâmico
# ====================================

echo "🚀 Iniciando migração para sistema dinâmico..."

# Configurações do banco
DB_HOST=${DB_HOST:-"localhost"}
DB_USER=${DB_USER:-"root"}
DB_PASS=${DB_PASS:-""}
DB_NAME=${DB_NAME:-"blue_cleaning_services"}

# Função para executar SQL com verificação de erro
execute_sql() {
    local sql_file=$1
    local description=$2
    
    echo "📄 Executando: $description"
    
    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$sql_file"; then
        echo "✅ Concluído: $description"
    else
        echo "❌ Erro ao executar: $description"
        exit 1
    fi
}

# Função para verificar se arquivo existe
check_file() {
    if [ ! -f "$1" ]; then
        echo "❌ Arquivo não encontrado: $1"
        exit 1
    fi
}

# Verificar arquivos necessários
echo "🔍 Verificando arquivos de migração..."
check_file "database/migrate_dynamic_data.sql"
check_file "database/booking_relations.sql"

# Backup do banco atual
echo "💾 Criando backup do banco atual..."
mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "backup_pre_dynamic_migration_$(date +%Y%m%d_%H%M%S).sql"

if [ $? -eq 0 ]; then
    echo "✅ Backup criado com sucesso"
else
    echo "❌ Erro ao criar backup"
    exit 1
fi

# Executar migrações
echo ""
echo "🔄 Executando migrações do banco de dados..."

execute_sql "database/migrate_dynamic_data.sql" "Criação de tabelas e dados dinâmicos"
execute_sql "database/booking_relations.sql" "Tabelas de relacionamento"

# Verificar se as APIs estão no lugar
echo ""
echo "🔍 Verificando APIs..."

if [ -f "api/system-config-dynamic.php" ]; then
    echo "✅ API de configuração dinâmica encontrada"
else
    echo "❌ API de configuração dinâmica não encontrada"
    exit 1
fi

if [ -f "api/booking/create-dynamic.php" ]; then
    echo "✅ API de booking dinâmico encontrada"
else
    echo "❌ API de booking dinâmico não encontrada"
    exit 1
fi

if [ -f "booking2_dynamic.php" ]; then
    echo "✅ Página de booking dinâmica encontrada"
else
    echo "❌ Página de booking dinâmica não encontrada"
    exit 1
fi

# Teste básico de conectividade
echo ""
echo "🧪 Testando conectividade com banco..."

TEST_QUERY="SELECT COUNT(*) as total FROM system_settings WHERE is_active = TRUE"
RESULT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$TEST_QUERY" -s -N)

if [ "$RESULT" -gt 0 ]; then
    echo "✅ Configurações carregadas: $RESULT entradas ativas"
else
    echo "❌ Problemas com configurações do sistema"
    exit 1
fi

# Verificar inclusões
INCLUSIONS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM service_inclusions WHERE is_active = TRUE" -s -N)
echo "✅ Inclusões carregadas: $INCLUSIONS"

# Verificar extras
EXTRAS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM service_extras WHERE is_active = TRUE" -s -N)
echo "✅ Extras carregados: $EXTRAS"

# Verificar preferências
PREFERENCES=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM cleaning_preferences WHERE is_active = TRUE" -s -N)
echo "✅ Preferências carregadas: $PREFERENCES"

echo ""
echo "🎉 MIGRAÇÃO CONCLUÍDA COM SUCESSO!"
echo ""
echo "📋 PRÓXIMOS PASSOS:"
echo "1. Testar API: curl http://localhost:8000/api/system-config-dynamic"
echo "2. Renomear booking2.php para booking2_old.php"
echo "3. Renomear booking2_dynamic.php para booking2.php"
echo "4. Testar formulário de booking completo"
echo "5. Configurar cronjob para limpeza de dados antigos"
echo ""
echo "⚠️  IMPORTANTE:"
echo "- Backup salvo como: backup_pre_dynamic_migration_$(date +%Y%m%d_%H%M%S).sql"
echo "- Em caso de problemas, restaurar com: mysql -u$DB_USER -p$DB_PASS $DB_NAME < backup_file.sql"
echo "- Testar tudo antes de ir para produção!"
echo ""
echo "✅ Sistema dinâmico pronto para uso!"
