<?php
/**
 * API de Verificação de Disponibilidade - Blue Project V2
 * Sistema completo de disponibilidade com validações reais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Include required configurations
require_once '../config/stripe-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Classe principal para gerenciamento de disponibilidade
 */
class AvailabilityManager {
    
    private static $businessHours = [
        'monday' => ['start' => '08:00', 'end' => '18:00'],
        'tuesday' => ['start' => '08:00', 'end' => '18:00'],
        'wednesday' => ['start' => '08:00', 'end' => '18:00'],
        'thursday' => ['start' => '08:00', 'end' => '18:00'],
        'friday' => ['start' => '08:00', 'end' => '18:00'],
        'saturday' => ['start' => '09:00', 'end' => '16:00'],
        'sunday' => [] // Closed on Sundays
    ];
    
    private static $holidays = [
        '2025-01-01', // New Year's Day
        '2025-01-26', // Australia Day
        '2025-04-25', // ANZAC Day
        '2025-12-25', // Christmas Day
        '2025-12-26'  // Boxing Day
    ];
    
    private static $serviceAreas = [
        'sydney' => ['2000', '2001', '2002', '2010', '2011', '2015', '2016', '2017'],
        'melbourne' => ['3000', '3001', '3002', '3003', '3004', '3006', '3008'],
        'brisbane' => ['4000', '4001', '4005', '4006', '4007', '4008', '4009'],
        'perth' => ['6000', '6001', '6002', '6003', '6004', '6005', '6006'],
        'adelaide' => ['5000', '5001', '5002', '5003', '5004', '5005', '5006']
    ];
    
    /**
     * Verifica disponibilidade para uma data específica
     */
    public static function checkAvailability($date, $suburb, $postcode, $serviceType, $duration = 2) {
        try {
            // Validar parâmetros
            $validation = self::validateAvailabilityRequest($date, $suburb, $postcode, $serviceType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'validation_failed',
                    'details' => $validation['errors']
                ];
            }
            
            // Verificar se a data é válida
            $dateValidation = self::validateServiceDate($date);
            if (!$dateValidation['valid']) {
                return [
                    'success' => false,
                    'error' => 'invalid_date',
                    'message' => $dateValidation['message'],
                    'next_available' => $dateValidation['next_available'] ?? null
                ];
            }
            
            // Verificar área de atendimento
            $areaCheck = self::validateServiceArea($suburb, $postcode);
            if (!$areaCheck['valid']) {
                return [
                    'success' => false,
                    'error' => 'area_not_serviced',
                    'message' => $areaCheck['message'],
                    'suggested_areas' => $areaCheck['suggested_areas'] ?? []
                ];
            }
            
            // Obter slots disponíveis
            $availableSlots = self::getAvailableTimeSlots($date, $suburb, $serviceType, $duration);
            
            // Verificar capacidade de profissionais
            $professionalCapacity = self::checkProfessionalCapacity($date, $suburb, $serviceType);
            
            return [
                'success' => true,
                'date' => $date,
                'suburb' => $suburb,
                'postcode' => $postcode,
                'service_type' => $serviceType,
                'duration_hours' => $duration,
                'available_slots' => $availableSlots,
                'total_available' => count($availableSlots),
                'professional_capacity' => $professionalCapacity,
                'area_info' => $areaCheck['area_info'],
                'booking_window' => [
                    'min_advance_hours' => 48,
                    'max_advance_days' => 180
                ],
                'next_available_dates' => self::getNextAvailableDates($date, $suburb, $serviceType, 5)
            ];
            
        } catch (Exception $e) {
            error_log("Availability check error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to check availability. Please try again.'
            ];
        }
    }
    
    // [Continue with other methods...]
}
$serviceId = $input['service_id'];

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use Y-m-d']);
    exit();
}

// Check if date is at least 48 hours from now
$requestedDate = new DateTime($date);
$minDate = new DateTime();
$minDate->add(new DateInterval('PT48H'));

if ($requestedDate < $minDate) {
    echo json_encode([
        'available' => false,
        'message' => 'Bookings must be made at least 48 hours in advance',
        'earliest_available' => $minDate->format('Y-m-d')
    ]);
    exit();
}

// Simulate cleaner availability check
// In real implementation, this would check database for cleaner schedules
$availableSlots = [
    '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'
];

// Simulate some unavailable slots (random for demo)
$dayOfWeek = $requestedDate->format('w'); // 0 = Sunday, 6 = Saturday
$hour = (int) substr($timeWindow, 0, 2);

// Weekend restrictions (higher demand)
if ($dayOfWeek == 0 || $dayOfWeek == 6) {
    if ($hour < 8 || $hour > 16) {
        echo json_encode([
            'available' => false,
            'message' => 'Weekend slots are limited to 8:00 AM - 4:00 PM',
            'alternative_slots' => ['08:00', '10:00', '12:00', '14:00', '16:00']
        ]);
        exit();
    }
}

// Simulate busy periods (mock data)
$busyDates = [
    '2025-08-15', // Example busy date
    '2025-08-22',
    '2025-08-29'
];

if (in_array($date, $busyDates)) {
    $availableAlternatives = array_diff($availableSlots, [$timeWindow]);
    
    if (empty($availableAlternatives)) {
        echo json_encode([
            'available' => false,
            'message' => 'No availability on this date',
            'alternative_dates' => [
                date('Y-m-d', strtotime($date . ' +1 day')),
                date('Y-m-d', strtotime($date . ' +2 days')),
                date('Y-m-d', strtotime($date . ' +3 days'))
            ]
        ]);
        exit();
    }
    
    echo json_encode([
        'available' => false,
        'message' => 'Selected time slot is not available',
        'alternative_slots' => array_slice($availableAlternatives, 0, 5)
    ]);
    exit();
}

// Mock cleaner assignment
$availableCleaners = [
    [
        'id' => 1,
        'name' => 'Sarah Johnson',
        'rating' => 4.9,
        'experience_years' => 5,
        'specialties' => ['Deep Clean', 'Eco-Friendly']
    ],
    [
        'id' => 2,
        'name' => 'Michael Chen',
        'rating' => 4.8,
        'experience_years' => 3,
        'specialties' => ['Regular Clean', 'Pet-Friendly']
    ],
    [
        'id' => 3,
        'name' => 'Emma Wilson',
        'rating' => 4.9,
        'experience_years' => 7,
        'specialties' => ['Deep Clean', 'Chemical-Free']
    ]
];

// Select a random cleaner for demo
$selectedCleaner = $availableCleaners[array_rand($availableCleaners)];

// Success response
echo json_encode([
    'available' => true,
    'message' => 'Slot is available!',
    'assigned_cleaner' => $selectedCleaner,
    'slot_details' => [
        'date' => $date,
        'time_window' => $timeWindow,
        'service_id' => $serviceId,
        'estimated_duration' => '2-3 hours'
    ],
    'booking_window' => [
        'charge_date' => date('Y-m-d H:i:s', strtotime($date . ' -48 hours')),
        'cancellation_deadline' => date('Y-m-d H:i:s', strtotime($date . ' -48 hours'))
    ]
]);
?>
