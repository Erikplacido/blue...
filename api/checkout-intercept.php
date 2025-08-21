<?php
/**
 * INTERCEPTADOR DE REQUISIÇÕES - CAPTURAR DADOS REAIS DO CHECKOUT
 */

// Headers para permitir CORS e JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');

// Para requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log tudo que chegar
$timestamp = date('Y-m-d H:i:s');
$logFile = __DIR__ . '/checkout-intercept.log';

$logData = [
    'timestamp' => $timestamp,
    'method' => $_SERVER['REQUEST_METHOD'],
    'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'headers' => getallheaders(),
    'get_data' => $_GET,
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
];

// Salvar log
file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Processar dados JSON se for POST
$responseData = ['success' => true, 'message' => 'Intercepted successfully'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        
        if ($jsonData) {
            // Analisar dados críticos
            $analysis = [
                'contains_total' => isset($jsonData['total']),
                'total_value' => $jsonData['total'] ?? 'NOT_PROVIDED',
                'service_id' => $jsonData['service_id'] ?? 'NOT_PROVIDED',
                'frontend_calculated' => 'unknown'
            ];
            
            // Se tem total, analisar
            if (isset($jsonData['total'])) {
                $totalValue = floatval($jsonData['total']);
                
                if ($totalValue == 265.00) {
                    $analysis['status'] = 'CORRECT - Frontend sent $265.00';
                } elseif ($totalValue == 85.00) {
                    $analysis['status'] = 'PROBLEM - Frontend sent $85.00';
                } else {
                    $analysis['status'] = "UNEXPECTED - Frontend sent $" . $totalValue;
                }
            } else {
                $analysis['status'] = 'ERROR - No total field in request';
            }
            
            $responseData['analysis'] = $analysis;
            $responseData['received_data'] = $jsonData;
            
            // Log resumido no terminal
            echo "🔍 CHECKOUT INTERCEPTADO em $timestamp\n";
            echo "   Total enviado: " . ($jsonData['total'] ?? 'NENHUM') . "\n";
            echo "   Service ID: " . ($jsonData['service_id'] ?? 'NENHUM') . "\n";
            echo "   Status: " . $analysis['status'] . "\n";
            echo "   ✅ Dados salvos em checkout-intercept.log\n";
        }
    }
}

// Resposta simulada de sucesso (para não quebrar o frontend)
$responseData['checkout_url'] = 'https://checkout.stripe.com/test/intercepted_' . uniqid();
$responseData['session_id'] = 'cs_intercepted_' . uniqid();
$responseData['booking_code'] = 'INT-' . strtoupper(uniqid());

echo json_encode($responseData);
?>
