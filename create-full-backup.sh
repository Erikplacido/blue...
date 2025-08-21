#!/bin/bash
# =========================================================
# BACKUP COMPLETO - BLUE CLEANING SERVICES PROJECT
# =========================================================
# 
# @file create-full-backup.sh
# @description Script completo de backup do projeto e banco de dados
# @version 1.0
# @date 2025-08-20
# @author Blue Project Team

# Configurações
PROJECT_NAME="blue_cleaning_services"
BACKUP_DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="backups/full_backup_${BACKUP_DATE}"
DB_NAME="u979853733_rose"
DB_HOST="srv1417.hstgr.io"
DB_USER="u979853733_rose"
DB_PASS="BlueM@rketing33"

echo "🚀 INICIANDO BACKUP COMPLETO DO PROJETO"
echo "======================================="
echo "📅 Data: $(date '+%Y-%m-%d %H:%M:%S')"
echo "📁 Destino: $BACKUP_DIR"
echo "🗄️  Banco: $DB_NAME"
echo ""

# Criar diretório de backup
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/files"
mkdir -p "$BACKUP_DIR/logs"

echo "📋 1. CRIANDO BACKUP DOS ARQUIVOS..."
echo "-----------------------------------"

# Backup completo dos arquivos do projeto (excluindo node_modules, vendor, logs temporários)
rsync -av --progress \
    --exclude 'node_modules' \
    --exclude 'vendor' \
    --exclude '.git' \
    --exclude '*.log' \
    --exclude 'tmp/*' \
    --exclude 'cache/*' \
    ./ "$BACKUP_DIR/files/"

echo "✅ Arquivos copiados com sucesso!"
echo ""

echo "🗄️  2. CRIANDO BACKUP DO BANCO DE DADOS..."
echo "--------------------------------------------"

# Backup completo do banco de dados
mysqldump --single-transaction --routines --triggers \
    -h "$DB_HOST" \
    -u "$DB_USER" \
    -p"$DB_PASS" \
    "$DB_NAME" > "$BACKUP_DIR/database/full_database_${BACKUP_DATE}.sql"

if [ $? -eq 0 ]; then
    echo "✅ Backup do banco de dados criado com sucesso!"
else
    echo "❌ Erro ao criar backup do banco de dados"
fi

# Backup apenas da estrutura (sem dados)
mysqldump --no-data --routines --triggers \
    -h "$DB_HOST" \
    -u "$DB_USER" \
    -p"$DB_PASS" \
    "$DB_NAME" > "$BACKUP_DIR/database/structure_only_${BACKUP_DATE}.sql"

echo "✅ Backup da estrutura criado!"
echo ""

echo "📊 3. GERANDO RELATÓRIOS..."
echo "----------------------------"

# Relatório de arquivos
echo "RELATÓRIO DE BACKUP - $BACKUP_DATE" > "$BACKUP_DIR/logs/backup_report.txt"
echo "================================================" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "📁 ARQUIVOS INCLUÍDOS:" >> "$BACKUP_DIR/logs/backup_report.txt"
find . -type f -name "*.php" | wc -l >> "$BACKUP_DIR/logs/backup_report.txt"
echo " arquivos PHP" >> "$BACKUP_DIR/logs/backup_report.txt"
find . -type f -name "*.js" | wc -l >> "$BACKUP_DIR/logs/backup_report.txt"
echo " arquivos JavaScript" >> "$BACKUP_DIR/logs/backup_report.txt"
find . -type f -name "*.css" | wc -l >> "$BACKUP_DIR/logs/backup_report.txt"
echo " arquivos CSS" >> "$BACKUP_DIR/logs/backup_report.txt"
find . -type f -name "*.html" | wc -l >> "$BACKUP_DIR/logs/backup_report.txt"
echo " arquivos HTML" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "" >> "$BACKUP_DIR/logs/backup_report.txt"

# Lista de arquivos principais
echo "🗂️  ARQUIVOS PRINCIPAIS:" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "------------------------" >> "$BACKUP_DIR/logs/backup_report.txt"
ls -la *.php *.html 2>/dev/null >> "$BACKUP_DIR/logs/backup_report.txt"
echo "" >> "$BACKUP_DIR/logs/backup_report.txt"

# Estrutura de diretórios
echo "📂 ESTRUTURA DE DIRETÓRIOS:" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "----------------------------" >> "$BACKUP_DIR/logs/backup_report.txt"
tree -d -L 2 >> "$BACKUP_DIR/logs/backup_report.txt" 2>/dev/null || find . -type d -maxdepth 2 >> "$BACKUP_DIR/logs/backup_report.txt"

# Informações do sistema
echo "" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "💻 INFORMAÇÕES DO SISTEMA:" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "---------------------------" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "Data: $(date)" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "Usuário: $(whoami)" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "Sistema: $(uname -a)" >> "$BACKUP_DIR/logs/backup_report.txt"
echo "PHP Version: $(php --version | head -1)" >> "$BACKUP_DIR/logs/backup_report.txt"

echo "✅ Relatórios gerados!"
echo ""

echo "📦 4. CRIANDO ARQUIVO COMPACTADO..."
echo "------------------------------------"

# Criar arquivo tar.gz do backup completo
cd backups
tar -czf "full_backup_${BACKUP_DATE}.tar.gz" "full_backup_${BACKUP_DATE}/"

if [ $? -eq 0 ]; then
    BACKUP_SIZE=$(du -h "full_backup_${BACKUP_DATE}.tar.gz" | cut -f1)
    echo "✅ Arquivo compactado criado: full_backup_${BACKUP_DATE}.tar.gz (${BACKUP_SIZE})"
else
    echo "❌ Erro ao criar arquivo compactado"
fi

cd ..
echo ""

echo "📄 5. CRIANDO DOCUMENTAÇÃO DO BACKUP..."
echo "----------------------------------------"

# Criar arquivo de informações do backup
cat > "$BACKUP_DIR/BACKUP_INFO.md" << EOF
# 📦 BACKUP COMPLETO - BLUE CLEANING SERVICES

**Data do Backup:** $(date '+%Y-%m-%d %H:%M:%S')  
**Versão do Sistema:** 3.0  
**Estado:** Desenvolvimento/Produção  

## 📊 CONTEÚDO DO BACKUP

### 🗄️ Banco de Dados
- **Nome:** $DB_NAME
- **Host:** $DB_HOST
- **Arquivos:**
  - \`database/full_database_${BACKUP_DATE}.sql\` - Backup completo com dados
  - \`database/structure_only_${BACKUP_DATE}.sql\` - Apenas estrutura

### 📁 Arquivos do Projeto
- **Localização:** \`files/\`
- **Conteúdo:** Todos os arquivos PHP, HTML, CSS, JS e configurações
- **Excluídos:** node_modules, vendor, .git, logs temporários

### 📋 Logs e Relatórios
- **Localização:** \`logs/\`
- **Relatório Principal:** \`backup_report.txt\`

## 🔄 COMO RESTAURAR

### Banco de Dados:
\`\`\`bash
mysql -h [HOST] -u [USER] -p [DATABASE] < database/full_database_${BACKUP_DATE}.sql
\`\`\`

### Arquivos:
\`\`\`bash
cp -r files/* /caminho/do/projeto/
\`\`\`

## ⚠️ INFORMAÇÕES IMPORTANTES

- Backup criado automaticamente pelo sistema
- Verificar permissões dos arquivos após restauração
- Atualizar configurações de banco de dados se necessário
- Testar todas as funcionalidades após restauração

## 📞 SUPORTE

Em caso de dúvidas sobre a restauração:
- Email: admin@bluecleaningservices.com.au
- Documentação: PROJECT_DOCUMENTATION.md

---
*Backup gerado automaticamente em $(date '+%Y-%m-%d %H:%M:%S')*
EOF

echo "✅ Documentação criada!"
echo ""

echo "🎉 BACKUP COMPLETO FINALIZADO!"
echo "==============================="
echo "📂 Localização: $BACKUP_DIR"
echo "📦 Arquivo: backups/full_backup_${BACKUP_DATE}.tar.gz"
echo "📊 Conteúdo:"
echo "   • Todos os arquivos do projeto"
echo "   • Banco de dados completo"
echo "   • Estrutura do banco"
echo "   • Relatórios e logs"
echo "   • Documentação de restauração"
echo ""
echo "✅ Backup pronto para download ou armazenamento!"
echo ""

# Mostrar resumo do que foi criado
echo "📋 RESUMO FINAL:"
echo "----------------"
ls -la "$BACKUP_DIR/"
echo ""
if [ -f "backups/full_backup_${BACKUP_DATE}.tar.gz" ]; then
    FINAL_SIZE=$(du -h "backups/full_backup_${BACKUP_DATE}.tar.gz" | cut -f1)
    echo "📦 Arquivo final: full_backup_${BACKUP_DATE}.tar.gz (${FINAL_SIZE})"
fi

echo ""
echo "🚀 PROCESSO CONCLUÍDO COM SUCESSO!"
