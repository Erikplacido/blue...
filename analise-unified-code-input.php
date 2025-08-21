<?php
/**
 * ANÁLISE COMPLETA DO FLUXO DE DADOS - CAMPO UNIFIEDCODEINPUT
 * Rastreamento completo de como os dados fluem até o banco
 */

echo "🔍 ANÁLISE DO TRECHO HTML - UNIFIED CODES\n";
echo "===============================================\n\n";

echo "📋 ESTRUTURA HTML ANALISADA:\n";
echo "-----------------------------\n";
echo "Campo Principal: <input id='unifiedCodeInput' name='unifiedCodeInput'>\n";
echo "Botão de Ação: <button id='applyUnifiedCodeBtn'>Apply Code</button>\n";
echo "Status Display: <div id='unifiedCodeStatus'></div>\n\n";

echo "🔄 FLUXO COMPLETO DOS DADOS:\n";
echo "============================\n\n";

echo "1️⃣ CAPTURA INICIAL:\n";
echo "   HTML Input: unifiedCodeInput (id/name)\n";
echo "   Valor Inicial: \$_GET['referral_code'] ?? \$_GET['promo_code'] ?? ''\n";
echo "   Exemplo: booking3.php?referral_code=TESTCODE123\n\n";

echo "2️⃣ PROCESSAMENTO JAVASCRIPT:\n";
echo "   - Usuário digita código\n";
echo "   - Clica 'Apply Code'\n";
echo "   - JavaScript detecta tipo (referral/promo)\n";
echo "   - Atualiza campo oculto 'hiddenUnifiedCode'\n";
echo "   - Exibe status no 'unifiedCodeStatus'\n\n";

echo "3️⃣ SUBMISSÃO DO FORMULÁRIO:\n";
echo "   PHP Line 799: 'unified_code' => \$_POST['unified_code'] ?? \$_POST['unifiedCodeInput'] ?? ''\n";
echo "   Campo coletado: unifiedCodeInput\n";
echo "   Mapeado para: unified_code\n\n";

echo "4️⃣ PROCESSAMENTO NA API:\n";
echo "   API: api/booking/create-unified.php\n";
echo "   Recebe: booking_data['unified_code']\n";
echo "   Processa: \$applied_code = strtoupper(trim(\$booking_data['unified_code']))\n\n";

echo "5️⃣ INSERÇÃO NO BANCO DE DADOS:\n";
echo "   Tabela: bookings\n";
echo "   Colunas afetadas:\n";
echo "   ├─ unified_code = \$applied_code\n";
echo "   ├─ referral_code = \$applied_code (compatibilidade)\n";
echo "   ├─ code_type = 'referral' | 'promo' | 'none'\n";
echo "   └─ referred_by = \$referral_user_id (se referral)\n\n";

echo "📊 MAPEAMENTO FINAL:\n";
echo "====================\n";

$mapping = [
    'HTML Input' => 'unifiedCodeInput',
    'JavaScript Oculto' => 'hiddenUnifiedCode',
    'PHP Processing' => 'unified_code',
    'API Variable' => '$applied_code',
    'DB Column 1' => 'bookings.unified_code',
    'DB Column 2' => 'bookings.referral_code',
    'DB Column 3' => 'bookings.code_type',
    'DB Column 4' => 'bookings.referred_by'
];

foreach ($mapping as $stage => $field) {
    echo sprintf("   %-15s → %s\n", $stage, $field);
}

echo "\n🎯 RESPOSTA DIRETA À PERGUNTA:\n";
echo "==============================\n";
echo "Com base no trecho HTML analisado, os campos do banco de dados\n";
echo "que serão abastecidos pelos dados capturados são:\n\n";

echo "✅ CAMPOS PRINCIPAIS:\n";
echo "   bookings.unified_code     (valor do código inserido)\n";
echo "   bookings.referral_code    (mesmo valor, para compatibilidade)\n";
echo "   bookings.code_type        (tipo: 'referral', 'promo' ou 'none')\n";
echo "   bookings.referred_by      (ID do usuário referrer, se aplicável)\n\n";

echo "📝 EXEMPLO PRÁTICO:\n";
echo "===================\n";
echo "Usuário digita: 'FRIEND123'\n";
echo "Sistema detecta: Código de referral\n";
echo "Banco recebe:\n";
echo "├─ unified_code: 'FRIEND123'\n";
echo "├─ referral_code: 'FRIEND123'\n";
echo "├─ code_type: 'referral'\n";
echo "└─ referred_by: 42 (ID do usuário dono do código)\n\n";

echo "🔍 CONCLUSÃO:\n";
echo "==============\n";
echo "O campo 'unifiedCodeInput' alimenta MÚLTIPLAS colunas na tabela 'bookings',\n";
echo "sendo 'referral_code' a principal que o usuário mencionou.\n";
echo "O sistema é inteligente e detecta automaticamente o tipo de código.\n\n";

echo "✅ ANÁLISE COMPLETA FINALIZADA\n";
?>
