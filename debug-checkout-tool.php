<?php
/**
 * 🛠️ FERRAMENTA DE DEBUG PARA CHECKOUT - VERSÃO FINAL
 * 
 * Esta ferramenta pode ser usada para capturar valores reais durante o checkout
 * e identificar onde exatamente $265.00 vira A$85.00
 */

// Configuração de debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 FERRAMENTA DE DEBUG - CHECKOUT STRIPE A$85.00 vs $265.00\n";
echo str_repeat("=", 70) . "\n";

// Função para testar conexão com banco
function testDatabaseConnectionDebug() {
    echo "🔍 1. TESTANDO CONEXÃO COM BANCO DE DADOS:\n";
    
    try {
        require_once __DIR__ . '/config/australian-database.php';
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        $stmt = $connection->prepare("SELECT id, name, base_price FROM services WHERE id = 2");
        $stmt->execute();
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            echo "   ✅ Conexão OK - Serviço ID=2:\n";
            echo "      Nome: " . $service['name'] . "\n";
            echo "      Preço: $" . $service['base_price'] . "\n";
            
            if ($service['base_price'] == 265.00) {
                echo "   ✅ PREÇO CORRETO no banco!\n";
                return true;
            } else {
                echo "   ❌ PREÇO INCORRETO no banco: $" . $service['base_price'] . "\n";
                return false;
            }
        } else {
            echo "   ❌ Serviço ID=2 não encontrado\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erro de conexão: " . $e->getMessage() . "\n";
        return false;
    }
}

// Função para testar PricingEngine
function testPricingEngine() {
    echo "\n🧮 2. TESTANDO PRICING ENGINE:\n";
    
    try {
        require_once __DIR__ . '/core/PricingEngine.php';
        
        $result = PricingEngine::calculate('2', [], 'one-time', 0, '', '');
        
        echo "   Resultado do PricingEngine:\n";
        echo "      Base Price: $" . $result['base_price'] . "\n";
        echo "      Final Amount: $" . $result['final_amount'] . "\n";
        echo "      Stripe Cents: " . $result['stripe_amount_cents'] . "\n";
        
        if ($result['final_amount'] == 265.00) {
            echo "   ✅ PRICING ENGINE CORRETO!\n";
            return true;
        } elseif ($result['final_amount'] == 85.00) {
            echo "   ❌ PRICING ENGINE RETORNA $85.00 - PROBLEMA AQUI!\n";
            return false;
        } else {
            echo "   ⚠️ PRICING ENGINE valor inesperado: $" . $result['final_amount'] . "\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erro no PricingEngine: " . $e->getMessage() . "\n";
        return false;
    }
}

// Função para criar arquivo de interceptação
function createInterceptor() {
    echo "\n📡 3. CRIANDO INTERCEPTADOR DE REQUISIÇÕES:\n";
    
    $interceptorCode = '<?php
/**
 * INTERCEPTADOR TEMPORÁRIO - SUBSTITUI stripe-checkout-unified-final.php
 */
 
// Log de debug
$logFile = __DIR__ . "/../debug-checkout.log";
$timestamp = date("Y-m-d H:i:s");

// Capturar dados da requisição
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

// Log detalhado
$logEntry = [
    "timestamp" => $timestamp,
    "method" => $_SERVER["REQUEST_METHOD"],
    "raw_input" => $rawInput,
    "json_data" => $jsonData,
    "total_field" => $jsonData["total"] ?? "NOT_PROVIDED",
    "service_id" => $jsonData["service_id"] ?? "NOT_PROVIDED"
];

file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Continuar com processamento normal
require_once __DIR__ . "/stripe-checkout-unified-final-original.php";
?>';

    // Backup da API original
    $originalFile = __DIR__ . '/api/stripe-checkout-unified-final.php';
    $backupFile = __DIR__ . '/api/stripe-checkout-unified-final-original.php';
    $interceptorFile = __DIR__ . '/api/stripe-checkout-unified-final-debug.php';
    
    if (file_exists($originalFile)) {
        // Fazer backup
        copy($originalFile, $backupFile);
        
        // Criar interceptador
        file_put_contents($interceptorFile, $interceptorCode);
        
        echo "   ✅ Interceptador criado em: api/stripe-checkout-unified-final-debug.php\n";
        echo "   ✅ Backup criado em: api/stripe-checkout-unified-final-original.php\n";
        echo "   💡 Para ativar: renomear debug.php para o arquivo original\n";
        
        return true;
    } else {
        echo "   ❌ Arquivo original não encontrado\n";
        return false;
    }
}

// Função para instruções de teste
function showTestInstructions() {
    echo "\n📋 4. INSTRUÇÕES PARA TESTE REAL:\n";
    echo "   1. Abrir browser em modo anônimo\n";
    echo "   2. Acessar booking3.php\n";
    echo "   3. Preencher dados do formulário\n";
    echo "   4. Antes de clicar 'Checkout', abrir DevTools (F12)\n";
    echo "   5. Ir para aba 'Network' (Rede)\n";
    echo "   6. Clicar 'Checkout' e observar requisição\n";
    echo "   7. Verificar:\n";
    echo "      - Payload enviado tem 'total': 265.00?\n";
    echo "      - Resposta da API tem 'final_amount': 265.00?\n";
    echo "      - Stripe recebe 26500 cents?\n";
    
    echo "\n🔍 5. LOGS PARA VERIFICAR:\n";
    echo "   - debug-checkout.log (se interceptador ativado)\n";
    echo "   - Logs do servidor web (error.log, access.log)\n";
    echo "   - Console do browser (erros JavaScript)\n";
}

// Executar testes
$dbOk = testDatabaseConnectionDebug();
$pricingOk = testPricingEngine();
$interceptorOk = createInterceptor();
showTestInstructions();

// Resumo final
echo "\n" . str_repeat("=", 70) . "\n";
echo "🎯 RESUMO DOS TESTES:\n";
echo "   Banco de dados: " . ($dbOk ? "✅ OK" : "❌ PROBLEMA") . "\n";
echo "   PricingEngine: " . ($pricingOk ? "✅ OK" : "❌ PROBLEMA") . "\n";
echo "   Interceptador: " . ($interceptorOk ? "✅ CRIADO" : "❌ ERRO") . "\n";

if ($dbOk && $pricingOk) {
    echo "\n💡 DIAGNÓSTICO:\n";
    echo "   Componentes internos estão funcionando corretamente.\n";
    echo "   Problema provavelmente está na comunicação browser → API\n";
    echo "   ou cache/dados antigos no frontend.\n";
    echo "\n🎯 AÇÃO RECOMENDADA:\n";
    echo "   Testar checkout em modo anônimo e verificar Network tab.\n";
} else {
    echo "\n❌ PROBLEMA IDENTIFICADO:\n";
    if (!$dbOk) echo "   - Banco de dados tem valor incorreto\n";
    if (!$pricingOk) echo "   - PricingEngine calculando errado\n";
    echo "\n🎯 AÇÃO RECOMENDADA:\n";
    echo "   Corrigir os componentes com problema antes de testar frontend.\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
?>
