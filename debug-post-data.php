<?php
/**
 * DEBUG: Mostrar exatamente o que está sendo enviado no POST
 */

echo "🔍 DEBUG: DADOS RECEBIDOS NO POST\n";
echo "==================================\n\n";

echo "📋 _POST contents:\n";
echo "------------------\n";
foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        echo "$key: " . json_encode($value) . "\n";
    } else {
        echo "$key: '" . $value . "'\n";
    }
}

echo "\n📋 _GET contents:\n";
echo "----------------\n";
foreach ($_GET as $key => $value) {
    echo "$key: '" . $value . "'\n";
}

if (empty($_POST)) {
    echo "\n❌ Nenhum dado POST recebido!\n";
}

// Verificar especificamente o campo referral_code
if (isset($_POST['referral_code'])) {
    echo "\n✅ referral_code found in POST: '" . $_POST['referral_code'] . "'\n";
} else {
    echo "\n❌ referral_code NOT found in POST\n";
}

if (isset($_POST['unifiedCodeInput'])) {
    echo "✅ unifiedCodeInput found in POST: '" . $_POST['unifiedCodeInput'] . "'\n";
} else {
    echo "❌ unifiedCodeInput NOT found in POST\n";
}
?>
