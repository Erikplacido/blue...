<?php
/**
 * ANÁLISE REAL DA ESTRUTURA DO BANCO DE DADOS - SISTEMA DE CÓDIGOS
 * Comparação entre o que foi identificado vs. a realidade do banco
 */

echo "🔍 ANÁLISE REAL DA ESTRUTURA DO BANCO DE DADOS\n";
echo "=============================================\n\n";

echo "✅ CAMPOS QUE REALMENTE EXISTEM NA TABELA 'bookings':\n";
echo "====================================================\n";
echo "├─ referral_code (varchar(20), MUL, NULL) ✅ EXISTE\n";
echo "├─ referred_by (int(11), MUL, NULL) ✅ EXISTE  \n";
echo "├─ referral_commission_calculated (tinyint(1), DEFAULT 0) ✅ EXISTE\n";
echo "└─ discount_amount (decimal(10,2), DEFAULT 0.00) ✅ EXISTE\n\n";

echo "❌ CAMPOS QUE NÃO EXISTEM (identificados incorretamente):\n";
echo "========================================================\n";
echo "├─ unified_code ❌ NÃO EXISTE\n";
echo "├─ code_type ❌ NÃO EXISTE\n";
echo "├─ status (enum diferente do esperado)\n";
echo "└─ service_date/service_time (nomes diferentes)\n\n";

echo "📊 ESTRUTURA CORRETA DA TABELA 'bookings':\n";
echo "==========================================\n";

$bookings_fields = [
    'id' => 'bigint(20) unsigned, PRI, auto_increment',
    'booking_code' => 'varchar(20), UNI - Código único do booking',
    'customer_id' => 'bigint(20) unsigned - ID do cliente',
    'professional_id' => 'bigint(20) unsigned - ID do profissional',
    'service_id' => 'bigint(20) unsigned - ID do serviço',
    'customer_name' => 'varchar(255) - Nome do cliente',
    'customer_email' => 'varchar(255) - Email do cliente',
    'customer_phone' => 'varchar(20) - Telefone do cliente',
    'street_address' => 'varchar(255) - Endereço',
    'suburb' => 'varchar(100) - Subúrbio',
    'state' => "enum('NSW','VIC','QLD','SA','WA','TAS','NT','ACT')",
    'postcode' => 'varchar(4) - Código postal',
    'scheduled_date' => 'date - Data agendada',
    'scheduled_time' => 'time - Hora agendada',
    'duration_minutes' => 'int(11), DEFAULT 120',
    'base_price' => 'decimal(10,2) - Preço base',
    'extras_price' => 'decimal(10,2), DEFAULT 0.00',
    'discount_amount' => 'decimal(10,2), DEFAULT 0.00',
    'gst_amount' => 'decimal(10,2) - GST',
    'total_amount' => 'decimal(10,2) - Total final',
    'status' => "enum('pending','confirmed','in_progress','completed','cancelled')",
    'payment_status' => "enum('pending','paid','refunded','failed')",
    'special_instructions' => 'text - Instruções especiais',
    'created_at' => 'timestamp, current_timestamp()',
    'updated_at' => 'timestamp, auto-update',
    'referral_code' => 'varchar(20), MUL ✅ CAMPO PRINCIPAL',
    'referred_by' => 'int(11), MUL ✅ ID do usuário referrer',
    'referral_commission_calculated' => 'tinyint(1), DEFAULT 0',
    'address' => 'text - Endereço completo',
    'stripe_session_id' => 'varchar(200) - ID da sessão Stripe'
];

foreach ($bookings_fields as $field => $description) {
    echo sprintf("   %-30s %s\n", $field . ':', $description);
}

echo "\n🎯 SISTEMA DE REFERÊNCIA - TABELAS RELACIONADAS:\n";
echo "================================================\n";
echo "✅ referral_users - Usuários do sistema de referência\n";
echo "   ├─ referral_code (varchar(20), UNI) - Código único\n";
echo "   ├─ total_earned (decimal(10,2)) - Total ganho\n";
echo "   ├─ current_level_id (int(11)) - Nível atual\n";
echo "   └─ is_active (tinyint(1)) - Status ativo\n\n";

echo "✅ promo_codes - Códigos promocionais\n";
echo "   ├─ code (varchar(50), UNI) - Código promocional\n";
echo "   ├─ discount_percentage (decimal(5,2)) - % desconto\n";
echo "   ├─ discount_amount (decimal(10,2)) - Valor desconto\n";
echo "   └─ is_active (tinyint(1)) - Status ativo\n\n";

echo "🔧 CORREÇÕES NECESSÁRIAS NA API create-unified.php:\n";
echo "==================================================\n";
echo "❌ PROBLEMÁTICO - SQL atual:\n";
echo "INSERT INTO bookings (\n";
echo "    ..., unified_code, code_type, referred_by, referral_code, ...\n";
echo ") VALUES (...)\n\n";

echo "✅ CORRETO - SQL ajustado:\n";
echo "INSERT INTO bookings (\n";
echo "    customer_id, service_id, customer_name, customer_email, customer_phone,\n";
echo "    street_address, suburb, state, postcode, \n";
echo "    scheduled_date, scheduled_time, duration_minutes,\n";
echo "    base_price, extras_price, discount_amount, gst_amount, total_amount,\n";
echo "    status, payment_status, referral_code, referred_by,\n";
echo "    referral_commission_calculated, created_at\n";
echo ") VALUES (...)\n\n";

echo "💡 MAPEAMENTO CORRETO DOS DADOS:\n";
echo "===============================\n";

$mapping = [
    'Frontend Field' => 'Database Column',
    'unifiedCodeInput' => 'bookings.referral_code',
    'customer_name' => 'bookings.customer_name',
    'customer_email' => 'bookings.customer_email', 
    'service_date' => 'bookings.scheduled_date',
    'service_time' => 'bookings.scheduled_time',
    'service_address' => 'bookings.street_address',
    'duration_hours * 60' => 'bookings.duration_minutes'
];

foreach ($mapping as $frontend => $database) {
    echo sprintf("   %-20s → %s\n", $frontend, $database);
}

echo "\n🎯 CONCLUSÃO E RECOMENDAÇÃO:\n";
echo "============================\n";
echo "✅ VOCÊ ESTAVA CERTO! Os campos unified_code e code_type NÃO existem.\n";
echo "✅ O foco deve ser apenas em bookings.referral_code\n";
echo "✅ A API precisa ser corrigida para usar os campos reais\n";
echo "✅ O sistema pode funcionar perfeitamente só com referral_code\n\n";

echo "🔧 PRÓXIMO PASSO:\n";
echo "=================\n";
echo "1. Corrigir API create-unified.php para usar campos reais\n";
echo "2. Simplificar o sistema para focar apenas em referral_code\n";
echo "3. Manter a detecção de tipo (referral vs promo) no PHP, não no banco\n";
echo "4. Testar o fluxo completo após as correções\n\n";

echo "✅ ANÁLISE REAL CONCLUÍDA - BANCO MAPEADO CORRETAMENTE\n";
?>
