<?php
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
?>