<?php
/**
 * Script de Organização do Projeto
 * Remove arquivos temporários e move outros para pastas adequadas
 */

echo "🧹 ORGANIZANDO PROJETO BLUE CLEANING SERVICES\n";
echo "============================================\n\n";

// Criar diretórios necessários
$directories = [
    'archive/debug',
    'archive/deploy', 
    'archive/temp-files',
    'scripts/setup',
    'scripts/maintenance'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Criado diretório: $dir\n";
    }
}

// Arquivos para REMOVER (seguros de deletar)
$filesToDelete = [
    'analyze-database-structure.php',
    'comprehensive-price-analysis.php', 
    'database_analysis.php',
    'detailed_analysis.php',
    'diagnostic-500-error.php',
    'stripe-price-diagnostic.php',
    'price-diagnostic-console.js',
    'price-fix-verification.php',
    'simple-price-check.php',
    'deploy-price-fix.sh',
    'deploy-report-20250811-110712.txt',
    'emergency-price-fix.js',
    'price-flow-tracer.js',
    'verify-price-fix.sh',
    'verify-price-fix-final.js',
    'verify-gst-config.sh',
    'verify_system_status.sh',
    'upload-fixes.sh',
    'lista-upload-ftp.sh',
    'css-diagnostic.html',
    'live-price-monitor.html',
    'pricing-diagnostic.html',
    'sistema-48h-cronograma.html',
    'setup-production-php.php',
    'configure-duration-minutes.sql',
    'configure-minimum-quantities.php',
    'database-test-and-setup.sql',
    'config-result.txt',
    'update_preferences_real.sql'
];

// Arquivos para MOVER para scripts/
$filesToMoveToScripts = [
    'create-backup.php' => 'scripts/maintenance/',
    'create-test-professional.php' => 'scripts/setup/',
    'implement-database-connection.php' => 'scripts/setup/',
    'implement-dynamic-professional-system.php' => 'scripts/setup/',
    'populate-inclusions.php' => 'scripts/setup/',
    'setup_booking_referral_integration.php' => 'scripts/setup/',
    'setup_dynamic_tables.php' => 'scripts/setup/',
    'setup-coupon-system.php' => 'scripts/setup/',
    'fix-bookings-table.php' => 'scripts/maintenance/',
    'update-service-extras.php' => 'scripts/maintenance/',
    'update_preferences_db.php' => 'scripts/maintenance/'
];

// Arquivos para MOVER para assets/js/
$filesToMoveToJS = [
    'smart-time-picker.js' => 'assets/js/'
];

echo "\n🗑️  REMOVENDO ARQUIVOS TEMPORÁRIOS:\n";
echo "-----------------------------------\n";

$deletedCount = 0;
foreach ($filesToDelete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "❌ Removido: $file\n";
            $deletedCount++;
        } else {
            echo "⚠️  Erro ao remover: $file\n";
        }
    }
}

echo "\n📁 MOVENDO ARQUIVOS PARA SCRIPTS/:\n";
echo "----------------------------------\n";

$movedCount = 0;
foreach ($filesToMoveToScripts as $file => $destination) {
    if (file_exists($file)) {
        if (rename($file, $destination . $file)) {
            echo "📋 Movido: $file → $destination\n";
            $movedCount++;
        } else {
            echo "⚠️  Erro ao mover: $file\n";
        }
    }
}

echo "\n📁 MOVENDO ARQUIVOS PARA ASSETS/JS/:\n";
echo "------------------------------------\n";

foreach ($filesToMoveToJS as $file => $destination) {
    if (file_exists($file)) {
        if (rename($file, $destination . $file)) {
            echo "📋 Movido: $file → $destination\n";
            $movedCount++;
        } else {
            echo "⚠️  Erro ao mover: $file\n";
        }
    }
}

echo "\n📊 RESUMO DA ORGANIZAÇÃO:\n";
echo "=========================\n";
echo "🗑️  Arquivos removidos: $deletedCount\n";
echo "📁 Arquivos movidos: $movedCount\n";
echo "✅ Projeto organizado com sucesso!\n\n";

echo "📋 ARQUIVOS PRINCIPAIS MANTIDOS NA RAIZ:\n";
echo "========================================\n";

$coreFiles = [
    'config.php' => '⚙️ Configuração principal',
    'index.html' => '🏠 Página inicial', 
    'home.html' => '🏠 Homepage',
    'navigation.php' => '🧭 Navegação',
    'booking3.php' => '📅 Sistema de reservas principal',
    'booking-confirmation.php' => '✅ Confirmação de reserva',
    'support.php' => '🆘 Suporte',
    'tracking.php' => '📊 Rastreamento',
    'referral_processor.php' => '🤝 Processador de indicações'
];

foreach ($coreFiles as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description: $file\n";
    }
}

echo "\n🎯 PRÓXIMOS PASSOS RECOMENDADOS:\n";
echo "===============================\n";
echo "1. Revisar arquivos em /scripts/ se ainda são necessários\n";
echo "2. Documentar os scripts mantidos\n";
echo "3. Criar .gitignore para arquivos temporários futuros\n";
echo "4. Configurar pipeline de CI/CD para evitar acúmulo\n\n";

?>
