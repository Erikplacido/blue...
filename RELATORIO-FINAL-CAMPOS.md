RELATÓRIO FINAL: ANÁLISE COMPLETA DOS CAMPOS DE BOOKING
========================================================

🔍 INVESTIGAÇÃO COMPLETA REALIZADA:
===================================

1. FRONTEND (booking3.php):
   ✅ Coleta dados corretamente:
   - bookingData.date = executionDate 
   - bookingData.time = timeWindow
   - bookingData.address = address

2. API CHAMADA (stripe-checkout-unified-final.php):
   ✅ Recebe dados corretamente:
   - 'date' => $input['date'] (linha 95)
   - 'time' => $input['time'] (linha 96)  
   - 'address' => $input['address'] (linha 92)

3. SALVAMENTO NO BANCO (StripeManager.php - saveBookingRecord):
   ✅ USA CAMPOS CORRETOS DO BANCO:
   - ':scheduled_date' => $bookingData['date'] (linha 634)
   - ':scheduled_time' => $bookingData['time'] (linha 635)
   - ':street_address' => $bookingData['address'] (linha 632)

POR QUE ALGUNS CAMPOS FUNCIONAM E OUTROS NÃO:
============================================

✅ scheduled_time FUNCIONA:
   Frontend → API → StripeManager → scheduled_time ✓

✅ street_address FUNCIONA: 
   Frontend → API → StripeManager → street_address ✓

❌ scheduled_date NÃO FUNCIONA:
   MOTIVO: Provavelmente $bookingData['date'] está vazio/null

❌ referral_code NÃO FUNCIONA:
   MOTIVO: NÃO está sendo inserido no método saveBookingRecord

DIAGNÓSTICO ESPECÍFICO DO scheduled_date:
========================================

O campo scheduled_date está sendo mapeado corretamente, mas:
- O valor $bookingData['date'] pode estar vazio
- Pode haver problema na validação de data no frontend
- O campo execution_date pode não estar sendo preenchido

DIAGNÓSTICO ESPECÍFICO DO referral_code:
=======================================

O StripeManager.saveBookingRecord NÃO inclui referral_code na query INSERT:
- SQL não tem coluna referral_code (linha 610-620)  
- execute() não tem parâmetro :referral_code (linha 625-645)
- ESTE É O MOTIVO PRINCIPAL da falha no salvamento dos códigos

SOLUÇÕES NECESSÁRIAS:
====================

1. ADICIONAR referral_code no StripeManager.php:
   - Incluir 'referral_code' na query SQL INSERT
   - Adicionar ':referral_code' => $bookingData['unified_code'] ?? '' no execute()

2. VERIFICAR por que scheduled_date chega vazio:
   - Debug do valor $bookingData['date'] no StripeManager
   - Verificar se execution_date tem valor no frontend

3. CONFIRMAR campos funcionais:
   - scheduled_time e street_address estão OK
   - Manter mapeamento atual que está funcionando
