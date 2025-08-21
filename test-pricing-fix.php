<?php
/**
 * TEST SCRIPT - VERIFY PRICING DISCREPANCY FIX
 * 
 * This script simulates the frontend sending $265.00 to the Stripe API
 * and verifies that Stripe receives exactly $265.00 (26500 cents)
 */

// Simulate frontend data with $265.00 total
$frontendData = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '+61400000000',
    'address' => '123 Test St',
    'suburb' => 'Sydney',
    'postcode' => '2000',
    'date' => '2025-08-21',
    'time' => '10:00',
    'service_id' => '2',
    'recurrence' => 'one-time',
    'extras' => [],
    'discount_amount' => 0,
    'total' => 265.00  // THIS IS THE KEY - FRONTEND CALCULATED TOTAL
];

echo "🧪 TESTING PRICING DISCREPANCY FIX\n";
echo "=" . str_repeat("=", 50) . "\n";
echo "Frontend calculated total: $" . $frontendData['total'] . "\n";
echo "Testing if Stripe receives exactly this amount...\n\n";

// Test the API endpoint
$url = 'http://localhost/booking_ok/api/stripe-checkout-unified-final.php';
$postData = json_encode($frontendData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ],
        'content' => $postData
    ]
]);

echo "📡 Sending test request to API...\n";
$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ ERROR: Could not reach API endpoint\n";
    echo "   Make sure your local server is running\n";
    exit(1);
}

$result = json_decode($response, true);

if (!$result) {
    echo "❌ ERROR: Invalid JSON response from API\n";
    echo "Raw response: " . substr($response, 0, 500) . "\n";
    exit(1);
}

echo "✅ API Response received\n";
echo "📊 PRICING BREAKDOWN:\n";

if (isset($result['pricing'])) {
    $pricing = $result['pricing'];
    
    echo "   Frontend Total: $265.00\n";
    echo "   API Final Amount: $" . ($pricing['final_amount'] ?? 'N/A') . "\n";
    echo "   Stripe Amount (cents): " . ($pricing['stripe_amount_cents'] ?? 'N/A') . "\n";
    echo "   Expected: 26500 cents\n";
    
    $expectedCents = 26500;
    $actualCents = $pricing['stripe_amount_cents'] ?? 0;
    
    if ($actualCents == $expectedCents) {
        echo "✅ SUCCESS: Stripe will receive exactly $265.00!\n";
        echo "✅ Price discrepancy has been FIXED!\n";
    } else {
        echo "❌ FAILURE: Still price discrepancy\n";
        echo "   Expected: $expectedCents cents ($265.00)\n";
        echo "   Got: $actualCents cents ($" . ($actualCents/100) . ")\n";
    }
} else {
    echo "❌ ERROR: No pricing data in response\n";
    echo "Full response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
?>
