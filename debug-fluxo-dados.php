<?php
/**
 * DEBUG FLUXO DE DADOS - Rastreamento Completo
 * Identifica EXATAMENTE onde os dados se perdem
 */

// Ativar todos os logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/debug_fluxo.log');

echo "<h1>🔍 DEBUG FLUXO DE DADOS - Rastreamento Completo</h1>";
echo "<p>Vamos identificar EXATAMENTE onde os dados se perdem...</p>";

// =====================================
// TESTE 1: SIMULAR ENVIO FRONTEND
// =====================================
echo "<h2>📤 TESTE 1: Simulando envio do frontend</h2>";

$dados_frontend = [
    'referral_code' => 'TESTE123',
    'scheduled_date' => '2024-12-25',
    'scheduled_time' => '10:00:00',
    'street_address' => '123 Test Street, Sydney NSW 2000',
    'first_name' => 'João',
    'last_name' => 'Silva',
    'email' => 'joao@teste.com',
    'phone' => '0412345678'
];

echo "<pre>";
echo "📋 Dados que o frontend deveria enviar:\n";
print_r($dados_frontend);
echo "</pre>";

// =====================================
// TESTE 2: SIMULAR API PROCESSING
// =====================================
echo "<h2>🔄 TESTE 2: Simulando processamento da API</h2>";

// Simular o que acontece em stripe-checkout-unified-final.php
$input = $dados_frontend; // Simular $_POST

// Mapear campos como na API
$dadosParaStripe = [
    'referral_code' => $input['referral_code'] ?? null,
    'scheduled_date' => $input['scheduled_date'] ?? null,
    'scheduled_time' => $input['scheduled_time'] ?? null,
    'street_address' => $input['street_address'] ?? null,
    'first_name' => $input['first_name'] ?? null,
    'last_name' => $input['last_name'] ?? null,
    'email' => $input['email'] ?? null,
    'phone' => $input['phone'] ?? null
];

echo "<pre>";
echo "🔧 Dados após mapeamento da API:\n";
print_r($dadosParaStripe);
echo "</pre>";

// =====================================
// TESTE 3: SIMULAR STRIPEMANAGER
// =====================================
echo "<h2>💳 TESTE 3: Simulando StripeManager</h2>";

// Simular o que acontece no StripeManager.php
$bookingData = $dadosParaStripe; // Recebe da API

// Mapear para inserção no banco
$camposBanco = [
    ':referral_code' => $bookingData['referral_code'] ?? '',
    ':scheduled_date' => $bookingData['scheduled_date'] ?? null,
    ':scheduled_time' => $bookingData['scheduled_time'] ?? null,
    ':street_address' => $bookingData['street_address'] ?? null,
    ':first_name' => $bookingData['first_name'] ?? null,
    ':last_name' => $bookingData['last_name'] ?? null,
    ':email' => $bookingData['email'] ?? null,
    ':phone' => $bookingData['phone'] ?? null
];

echo "<pre>";
echo "🗄️ Parâmetros preparados para o banco:\n";
print_r($camposBanco);
echo "</pre>";

// =====================================
// TESTE 4: VERIFICAR QUERY SQL
// =====================================
echo "<h2>📝 TESTE 4: Query SQL que seria executada</h2>";

$sql = "INSERT INTO bookings (
    referral_code, scheduled_date, scheduled_time, 
    street_address, first_name, last_name, email, phone
) VALUES (
    :referral_code, :scheduled_date, :scheduled_time,
    :street_address, :first_name, :last_name, :email, :phone
)";

echo "<pre>";
echo "SQL Query:\n";
echo $sql . "\n\n";

echo "Com os parâmetros:\n";
foreach ($camposBanco as $param => $value) {
    echo "$param => " . ($value ?? 'NULL') . "\n";
}
echo "</pre>";

// =====================================
// TESTE 5: EXECUTAR NO BANCO REAL
// =====================================
echo "<h2>🗃️ TESTE 5: Executando no banco REAL</h2>";

try {
    require_once 'config.php';
    
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão com banco estabelecida<br>";
    
    // Executar INSERT de teste
    $stmt = $pdo->prepare($sql);
    
    echo "<h3>📊 Tentando inserir dados de teste...</h3>";
    
    $resultado = $stmt->execute($camposBanco);
    
    if ($resultado) {
        $bookingId = $pdo->lastInsertId();
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
        echo "✅ <strong>SUCESSO!</strong> Booking inserido com ID: $bookingId<br>";
        echo "</div>";
        
        // Verificar o que foi realmente salvo
        $verificacao = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $verificacao->execute([$bookingId]);
        $dadosSalvos = $verificacao->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>🔍 Dados que foram REALMENTE salvos no banco:</h3>";
        echo "<pre>";
        print_r($dadosSalvos);
        echo "</pre>";
        
        // Comparar campo por campo
        echo "<h3>📋 Comparação Campo por Campo:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Enviado</th><th>Salvo</th><th>Status</th></tr>";
        
        $comparacao = [
            'referral_code' => $dados_frontend['referral_code'],
            'scheduled_date' => $dados_frontend['scheduled_date'],
            'scheduled_time' => $dados_frontend['scheduled_time'],
            'street_address' => $dados_frontend['street_address'],
            'first_name' => $dados_frontend['first_name'],
            'last_name' => $dados_frontend['last_name'],
            'email' => $dados_frontend['email'],
            'phone' => $dados_frontend['phone']
        ];
        
        foreach ($comparacao as $campo => $valorEnviado) {
            $valorSalvo = $dadosSalvos[$campo] ?? 'NULL';
            $status = ($valorEnviado == $valorSalvo) ? '✅ OK' : '❌ PROBLEMA';
            
            if ($status == '❌ PROBLEMA') {
                $status .= ' - AQUI ESTÁ O PROBLEMA!';
                $cor = 'background: #f8d7da; color: #721c24;';
            } else {
                $cor = 'background: #d4edda; color: #155724;';
            }
            
            echo "<tr style='$cor'>";
            echo "<td><strong>$campo</strong></td>";
            echo "<td>$valorEnviado</td>";
            echo "<td>$valorSalvo</td>";
            echo "<td><strong>$status</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
        echo "❌ <strong>ERRO!</strong> Falha ao inserir dados<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>ERRO DE BANCO:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}

// =====================================
// TESTE 6: VERIFICAR ARQUIVOS REAIS
// =====================================
echo "<h2>📁 TESTE 6: Analisando arquivos reais do sistema</h2>";

$arquivos = [
    'stripe-checkout-unified-final.php' => 'api/stripe-checkout-unified-final.php',
    'StripeManager.php' => 'classes/StripeManager.php'
];

foreach ($arquivos as $nome => $caminho) {
    echo "<h3>📄 Analisando: $nome</h3>";
    
    if (file_exists($caminho)) {
        $conteudo = file_get_contents($caminho);
        
        // Procurar por padrões específicos
        $padroes = [
            'unified_code' => '/unified_code/',
            'referral_code' => '/referral_code/',
            'scheduled_date' => '/scheduled_date/',
            'INSERT INTO bookings' => '/INSERT INTO bookings/'
        ];
        
        echo "<ul>";
        foreach ($padroes as $descricao => $padrao) {
            $encontrou = preg_match($padrao, $conteudo);
            $status = $encontrou ? '✅ ENCONTRADO' : '❌ NÃO ENCONTRADO';
            echo "<li><strong>$descricao:</strong> $status</li>";
            
            if ($encontrou && $descricao == 'INSERT INTO bookings') {
                // Extrair a query SQL real
                preg_match('/INSERT INTO bookings.*?VALUES.*?;/s', $conteudo, $matches);
                if (!empty($matches[0])) {
                    echo "<pre style='background: #f8f9fa; padding: 10px; margin: 10px 0;'>";
                    echo "SQL encontrada:\n" . htmlspecialchars($matches[0]);
                    echo "</pre>";
                }
            }
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Arquivo não encontrado: $caminho</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 CONCLUSÃO</h2>";
echo "<p>Este debug vai mostrar EXATAMENTE onde os dados estão sendo perdidos!</p>";
echo "<p>Execute este arquivo e vamos identificar o problema.</p>";
?>
