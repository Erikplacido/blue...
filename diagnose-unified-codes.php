<?php
/**
 * DIAGNÓSTICO COMPLETO DO SISTEMA DE CÓDIGOS PROMOCIONAIS E REFERÊNCIA
 * Análise de porque os códigos não são salvos na tabela bookings
 */

echo "🔍 DIAGNÓSTICO COMPLETO - SISTEMA DE CÓDIGOS PROMOCIONAIS E REFERÊNCIA\n";
echo "================================================================\n\n";

echo "📋 PROBLEMAS IDENTIFICADOS:\n";
echo "========================================\n\n";

echo "1. INCONSISTÊNCIA DE SERVICE_ID:\n";
echo "   ❌ booking3.php define SERVICE_ID_HOUSE_CLEANING = 1\n";
echo "   ❌ JavaScript força service_id = '2' na linha 3785\n";
echo "   ❌ Isso carrega o serviço ERRADO ($85.00 em vez de $265.00)\n\n";

echo "2. CAMPO UNIFICADO NÃO É COLETADO NO CHECKOUT:\n";
echo "   ❌ Campo hiddenUnifiedCode existe no HTML\n";
echo "   ❌ Sistema JavaScript atualiza o campo quando código é aplicado\n";
echo "   ❌ MAS o checkout JavaScript NÃO coleta esse valor para enviar à API\n\n";

echo "3. ESTRUTURA DA TABELA BOOKINGS:\n";
echo "   ✅ API create-unified.php usa campo 'unified_code' (correto)\n";
echo "   ❌ Usuário espera que seja salvo em 'referral_code'\n";
echo "   ❌ Pode haver incompatibilidade entre schema esperado vs implementado\n\n";

echo "📊 FLUXO ATUAL IDENTIFICADO:\n";
echo "========================================\n";
echo "1. Usuário insere código em: unifiedCodeInput\n";
echo "2. JavaScript detecta tipo e atualiza: hiddenUnifiedCode\n";
echo "3. No checkout, JavaScript coleta dados mas IGNORA hiddenUnifiedCode\n";
echo "4. API recebe dados sem unified_code\n";
echo "5. Banco não salva referral_code porque não foi enviado\n\n";

echo "🔧 SOLUÇÕES NECESSÁRIAS:\n";
echo "========================================\n";
echo "A. CORRIGIR SERVICE_ID:\n";
echo "   → Remover linha 3785: bookingData.service_id = '2'\n";
echo "   → Usar SERVICE_ID_HOUSE_CLEANING (1) consistentemente\n\n";

echo "B. ADICIONAR COLETA DE UNIFIED_CODE NO CHECKOUT:\n";
echo "   → No JavaScript do checkout, adicionar:\n";
echo "   → const unifiedCode = document.getElementById('hiddenUnifiedCode')?.value\n";
echo "   → bookingData.unified_code = unifiedCode\n\n";

echo "C. VERIFICAR ESTRUTURA DA TABELA:\n";
echo "   → Confirmar se tabela 'bookings' tem coluna 'unified_code'\n";
echo "   → OU mapear para 'referral_code' na API\n\n";

echo "📝 TESTE RECOMENDADO:\n";
echo "========================================\n";
echo "1. Inserir código no campo 'Promo & Referral Codes'\n";
echo "2. Abrir DevTools e verificar se hiddenUnifiedCode é atualizado\n";
echo "3. No checkout, verificar se unified_code é enviado na requisição\n";
echo "4. Confirmar se o valor chega na API create-unified.php\n";
echo "5. Verificar se é salvo na tabela bookings\n\n";

echo "✅ DIAGNÓSTICO COMPLETO FINALIZADO\n";
?>
