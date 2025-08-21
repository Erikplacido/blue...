<?php
/**
 * Teste da API Dynamic Professional
 */

// Simular chamada à API
$_GET['action'] = 'profile';
$_GET['professional_id'] = '1';

echo "🔍 Testando API Dynamic Professional...\n\n";

// Incluir e testar a API
include 'api/professional/dynamic-management.php';

?>
