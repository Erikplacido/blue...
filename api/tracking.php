<?php
/**
 * Sistema de Tracking Avançado - Blue Project V2
 * Rastreamento completo de serviços e profissionais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include required configurations
require_once '../config/stripe-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Classe principal para sistema de tracking
 */
class TrackingSystem {
    
    // Estados do serviço
    private static $serviceStates = [
        'scheduled' => 'Agendado',
        'professional_assigned' => 'Profissional Designado',
        'professional_en_route' => 'A Caminho',
        'professional_arrived' => 'Chegou',
        'service_in_progress' => 'Em Andamento',
        'service_paused' => 'Pausado',
        'service_completed' => 'Concluído',
        'payment_processing' => 'Processando Pagamento',
        'completed' => 'Finalizado',
        'cancelled' => 'Cancelado'
    ];
    
    /**
     * Obter status de tracking completo
     */
    public static function getTrackingData($bookingId, $includeHistory = true) {
        try {
            // Verificar se o booking existe
            $booking = self::getBookingData($bookingId);
            if (!$booking) {
                return [
                    'success' => false,
                    'error' => 'booking_not_found',
                    'message' => 'Booking not found'
                ];
            }
            
            // Obter dados de tracking
            $trackingData = [
                'success' => true,
                'booking_id' => $bookingId,
                'current_status' => self::getCurrentStatus($bookingId),
                'professional_info' => self::getProfessionalInfo($bookingId),
                'location_tracking' => self::getLocationTracking($bookingId),
                'service_progress' => self::getServiceProgress($bookingId),
                'timeline' => self::getServiceTimeline($bookingId),
                'estimated_times' => self::getEstimatedTimes($bookingId),
                'real_time_updates' => self::getRealTimeUpdates($bookingId),
                'customer_actions' => self::getAvailableActions($bookingId),
                'communication_options' => self::getCommunicationOptions($bookingId),
                'quality_monitoring' => self::getQualityMonitoring($bookingId),
                'weather_conditions' => self::getWeatherConditions($booking['service_address']),
                'traffic_conditions' => self::getTrafficConditions($booking['service_address'])
            ];
            
            // Incluir histórico se solicitado
            if ($includeHistory) {
                $trackingData['historical_data'] = self::getHistoricalTracking($bookingId);
                $trackingData['performance_metrics'] = self::getPerformanceMetrics($bookingId);
            }
            
            return $trackingData;
            
        } catch (Exception $e) {
            error_log("Tracking error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to retrieve tracking information'
            ];
        }
    }
    
    /**
     * Obter dados do booking
     */
    private static function getBookingData($bookingId) {
        // Simular dados do booking
        return [
            'booking_id' => $bookingId,
            'customer_id' => 'CUST_001',
            'service_type' => 'house-cleaning',
            'scheduled_date' => date('Y-m-d'),
            'scheduled_time' => '10:00-12:00',
            'service_address' => '123 Collins Street, Melbourne VIC 3000',
            'professional_id' => 'PROF_001',
            'status' => 'service_in_progress'
        ];
    }
    
    /**
     * Obter status atual do serviço
     */
    private static function getCurrentStatus($bookingId) {
        // Simular status baseado na hora atual
        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');
        
        // Simular progressão do serviço durante o dia
        if ($currentHour < 9) {
            $status = 'scheduled';
        } elseif ($currentHour == 9 && $currentMinute < 45) {
            $status = 'professional_en_route';
        } elseif ($currentHour == 9 && $currentMinute >= 45) {
            $status = 'professional_arrived';
        } elseif ($currentHour >= 10 && $currentHour < 12) {
            $status = 'service_in_progress';
        } elseif ($currentHour == 12 && $currentMinute < 15) {
            $status = 'service_completed';
        } else {
            $status = 'completed';
        }
        
        return [
            'status_code' => $status,
            'status_name' => self::$serviceStates[$status],
            'status_description' => self::getStatusDescription($status),
            'progress_percentage' => self::calculateProgressPercentage($status),
            'estimated_completion' => self::getEstimatedCompletion($status),
            'last_updated' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'next_milestone' => self::getNextMilestone($status),
            'can_cancel' => self::canCancel($status),
            'can_reschedule' => self::canReschedule($status)
        ];
    }
    
    /**
     * Obter informações do profissional
     */
    private static function getProfessionalInfo($bookingId) {
        return [
            'professional_id' => 'PROF_001',
            'name' => 'Maria Santos',
            'photo' => '/assets/professionals/maria-santos.jpg',
            'rating' => 4.9,
            'total_services' => 342,
            'specialties' => ['eco-friendly', 'pet-friendly', 'deep-cleaning'],
            'languages' => ['English', 'Portuguese', 'Spanish'],
            'certifications' => ['Certified Professional Cleaner', 'Eco-Friendly Specialist'],
            'experience_years' => 5,
            'contact_info' => [
                'can_call' => true,
                'can_message' => true,
                'phone' => '+61412345678', // Masked for security
                'emergency_contact' => '+61412345000'
            ],
            'current_location' => [
                'sharing_enabled' => true,
                'last_update' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
                'distance_from_customer' => '0.8 km',
                'estimated_arrival' => '5 minutes'
            ],
            'equipment' => [
                'vacuum_type' => 'HEPA Filter Commercial',
                'cleaning_supplies' => 'Eco-friendly products',
                'special_equipment' => ['Steam cleaner', 'Microfiber cloths'],
                'insurance_verified' => true,
                'background_check' => 'Verified'
            ],
            'service_history_with_customer' => [
                'previous_services' => 8,
                'customer_rating_average' => 5.0,
                'last_service_date' => date('Y-m-d', strtotime('-1 week')),
                'preferred_by_customer' => true,
                'notes_from_previous_services' => 'Prefers eco-friendly products, has two cats'
            ]
        ];
    }
    
    /**
     * Obter tracking de localização
     */
    private static function getLocationTracking($bookingId) {
        // Simular localização baseada no status
        $currentStatus = self::getCurrentStatus($bookingId)['status_code'];
        
        if ($currentStatus === 'professional_en_route') {
            return [
                'tracking_enabled' => true,
                'professional_location' => [
                    'latitude' => -37.8136,
                    'longitude' => 144.9631,
                    'accuracy' => '10 meters',
                    'last_updated' => date('Y-m-d H:i:s', strtotime('-1 minute'))
                ],
                'customer_location' => [
                    'latitude' => -37.8173,
                    'longitude' => 144.9666
                ],
                'route_info' => [
                    'distance_remaining' => '0.8 km',
                    'estimated_time_remaining' => '5 minutes',
                    'traffic_conditions' => 'light',
                    'route_optimized' => true,
                    'alternative_routes' => 2
                ],
                'privacy_settings' => [
                    'customer_can_see_exact_location' => true,
                    'location_sharing_expires' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                    'location_history_retained' => false
                ]
            ];
        } elseif ($currentStatus === 'professional_arrived' || $currentStatus === 'service_in_progress') {
            return [
                'tracking_enabled' => true,
                'professional_location' => [
                    'status' => 'at_customer_location',
                    'arrived_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    'verified_arrival' => true
                ],
                'geofence_status' => [
                    'within_service_area' => true,
                    'arrival_verified' => true,
                    'checkout_required' => true
                ]
            ];
        } else {
            return [
                'tracking_enabled' => false,
                'reason' => 'Service not yet started or already completed'
            ];
        }
    }
    
    /**
     * Obter progresso do serviço
     */
    private static function getServiceProgress($bookingId) {
        $currentStatus = self::getCurrentStatus($bookingId)['status_code'];
        
        $progress = [
            'overall_completion' => self::calculateProgressPercentage($currentStatus),
            'current_phase' => self::getCurrentPhase($currentStatus),
            'phases_completed' => self::getCompletedPhases($currentStatus),
            'estimated_completion_time' => self::getEstimatedCompletion($currentStatus),
            'time_elapsed' => self::getTimeElapsed($bookingId),
            'time_remaining' => self::getTimeRemaining($currentStatus)
        ];
        
        // Detalhes específicos se o serviço estiver em andamento
        if ($currentStatus === 'service_in_progress') {
            $progress['detailed_progress'] = [
                'rooms_completed' => [
                    ['room' => 'Living Room', 'status' => 'completed', 'time_spent' => 25],
                    ['room' => 'Kitchen', 'status' => 'in_progress', 'time_spent' => 15],
                    ['room' => 'Master Bedroom', 'status' => 'pending', 'time_spent' => 0],
                    ['room' => 'Bathroom', 'status' => 'pending', 'time_spent' => 0]
                ],
                'current_activity' => 'Cleaning kitchen countertops and appliances',
                'next_activity' => 'Move to master bedroom',
                'quality_checkpoints' => [
                    ['checkpoint' => 'Dust removal', 'status' => 'passed'],
                    ['checkpoint' => 'Surface cleaning', 'status' => 'in_progress'],
                    ['checkpoint' => 'Floor cleaning', 'status' => 'pending']
                ],
                'photos_taken' => [
                    ['type' => 'before', 'count' => 4, 'timestamp' => date('Y-m-d H:i:s', strtotime('-45 minutes'))],
                    ['type' => 'progress', 'count' => 2, 'timestamp' => date('Y-m-d H:i:s', strtotime('-20 minutes'))]
                ]
            ];
        }
        
        return $progress;
    }
    
    /**
     * Obter timeline do serviço
     */
    private static function getServiceTimeline($bookingId) {
        return [
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'event' => 'service_scheduled',
                'title' => 'Service Confirmed',
                'description' => 'Your cleaning service has been confirmed and scheduled',
                'icon' => 'calendar-check',
                'color' => 'success',
                'automated' => true
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'event' => 'professional_assigned',
                'title' => 'Professional Assigned',
                'description' => 'Maria Santos has been assigned to your service',
                'icon' => 'user-check',
                'color' => 'info',
                'automated' => true,
                'metadata' => ['professional_id' => 'PROF_001', 'professional_name' => 'Maria Santos']
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'event' => 'reminder_sent',
                'title' => 'Service Reminder Sent',
                'description' => 'Reminder notification sent to customer and professional',
                'icon' => 'bell',
                'color' => 'warning',
                'automated' => true
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'event' => 'professional_en_route',
                'title' => 'Professional En Route',
                'description' => 'Maria is on her way to your location',
                'icon' => 'navigation',
                'color' => 'primary',
                'automated' => false,
                'estimated_arrival' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                'event' => 'professional_arrived',
                'title' => 'Professional Arrived',
                'description' => 'Maria has arrived at your location',
                'icon' => 'map-pin',
                'color' => 'success',
                'automated' => false,
                'location_verified' => true
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'event' => 'service_started',
                'title' => 'Service Started',
                'description' => 'Cleaning service has begun',
                'icon' => 'play',
                'color' => 'success',
                'automated' => false,
                'before_photos_taken' => 4
            ]
        ];
    }
    
    /**
     * Obter tempos estimados
     */
    private static function getEstimatedTimes($bookingId) {
        return [
            'scheduled_start' => '10:00',
            'estimated_start' => '10:05',
            'actual_start' => '10:03',
            'estimated_duration' => 120, // minutes
            'estimated_completion' => '12:03',
            'buffer_time' => 15, // minutes buffer
            'travel_time_to_location' => 12,
            'setup_time' => 5,
            'cleanup_time' => 8,
            'checkout_time' => 3,
            'total_window' => '10:00-12:15',
            'confidence_level' => 89, // percentage
            'factors_affecting_time' => [
                'traffic_conditions' => 'favorable',
                'weather_conditions' => 'clear',
                'property_size' => 'standard',
                'special_requirements' => 'minimal'
            ]
        ];
    }
    
    /**
     * Obter atualizações em tempo real
     */
    private static function getRealTimeUpdates($bookingId) {
        return [
            'websocket_endpoint' => 'wss://api.bluecleaning.com/tracking/' . $bookingId,
            'polling_interval' => 30, // seconds
            'last_heartbeat' => date('Y-m-d H:i:s', strtotime('-15 seconds')),
            'connection_status' => 'connected',
            'update_types' => [
                'location_updates' => true,
                'status_changes' => true,
                'professional_messages' => true,
                'progress_updates' => true,
                'photo_uploads' => true
            ],
            'recent_updates' => [
                [
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
                    'type' => 'progress_update',
                    'message' => 'Living room cleaning completed',
                    'data' => ['room' => 'living_room', 'completion' => 100]
                ],
                [
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                    'type' => 'photo_upload',
                    'message' => 'Progress photo uploaded',
                    'data' => ['photo_type' => 'progress', 'room' => 'living_room']
                ],
                [
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-8 minutes')),
                    'type' => 'location_update',
                    'message' => 'Professional location updated',
                    'data' => ['status' => 'at_customer_location']
                ]
            ]
        ];
    }
    
    /**
     * Obter ações disponíveis para o cliente
     */
    private static function getAvailableActions($bookingId) {
        $currentStatus = self::getCurrentStatus($bookingId)['status_code'];
        
        $actions = [
            'contact_professional' => [
                'available' => in_array($currentStatus, ['professional_en_route', 'professional_arrived', 'service_in_progress']),
                'methods' => ['call', 'message', 'chat'],
                'restrictions' => []
            ],
            'view_progress' => [
                'available' => $currentStatus === 'service_in_progress',
                'real_time' => true,
                'photo_access' => true
            ],
            'provide_feedback' => [
                'available' => in_array($currentStatus, ['service_completed', 'completed']),
                'rating_required' => true,
                'photo_upload' => true
            ],
            'request_modification' => [
                'available' => in_array($currentStatus, ['scheduled', 'professional_assigned']),
                'advance_notice_required' => '24 hours',
                'fee_may_apply' => false
            ],
            'cancel_service' => [
                'available' => !in_array($currentStatus, ['service_in_progress', 'service_completed', 'completed', 'cancelled']),
                'cancellation_fee' => self::getCancellationFee($currentStatus),
                'refund_policy' => self::getRefundPolicy($currentStatus)
            ],
            'reschedule_service' => [
                'available' => !in_array($currentStatus, ['service_in_progress', 'service_completed', 'completed']),
                'advance_notice_required' => '48 hours',
                'fee_applies' => false
            ],
            'report_issue' => [
                'available' => true,
                'emergency_contact' => '+61412345000',
                'escalation_available' => true
            ]
        ];
        
        return $actions;
    }
    
    /**
     * Obter opções de comunicação
     */
    private static function getCommunicationOptions($bookingId) {
        return [
            'chat' => [
                'available' => true,
                'endpoint' => '/api/chat/' . $bookingId,
                'supports_media' => true,
                'response_time_avg' => '2 minutes'
            ],
            'phone_call' => [
                'available' => true,
                'professional_direct' => '+61412345678',
                'customer_service' => '+61412345000',
                'hours' => '8:00 AM - 6:00 PM'
            ],
            'sms' => [
                'available' => true,
                'two_way' => true,
                'automated_updates' => true
            ],
            'email' => [
                'available' => true,
                'response_time_avg' => '1 hour',
                'attachments_supported' => true
            ],
            'emergency_contact' => [
                'available' => true,
                'number' => '+61412345000',
                'available_24_7' => false,
                'emergency_hours' => 'During service hours only'
            ]
        ];
    }
    
    /**
     * Obter monitoramento de qualidade
     */
    private static function getQualityMonitoring($bookingId) {
        return [
            'quality_checkpoints' => [
                [
                    'checkpoint' => 'Arrival punctuality',
                    'status' => 'passed',
                    'score' => 95,
                    'benchmark' => 90,
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
                ],
                [
                    'checkpoint' => 'Equipment condition',
                    'status' => 'passed',
                    'score' => 100,
                    'benchmark' => 85,
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-8 minutes'))
                ],
                [
                    'checkpoint' => 'Progress photos',
                    'status' => 'in_progress',
                    'score' => null,
                    'benchmark' => 80,
                    'expected_completion' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
                ]
            ],
            'automated_quality_checks' => [
                'photo_analysis' => 'enabled',
                'time_tracking' => 'enabled',
                'customer_feedback_integration' => 'enabled',
                'professional_self_assessment' => 'enabled'
            ],
            'quality_score_prediction' => [
                'predicted_rating' => 4.8,
                'confidence' => 87,
                'factors' => [
                    'professional_history' => 4.9,
                    'punctuality' => 5.0,
                    'current_progress' => 4.7
                ]
            ],
            'intervention_triggers' => [
                'late_arrival' => 'disabled',
                'extended_duration' => 'enabled',
                'customer_complaint' => 'enabled',
                'quality_threshold' => 4.0
            ]
        ];
    }
    
    /**
     * Obter condições climáticas
     */
    private static function getWeatherConditions($address) {
        return [
            'current_conditions' => [
                'temperature' => 22,
                'humidity' => 65,
                'conditions' => 'Partly Cloudy',
                'wind_speed' => 12,
                'visibility' => 'Excellent'
            ],
            'impact_on_service' => [
                'outdoor_work_affected' => false,
                'travel_conditions' => 'good',
                'equipment_considerations' => 'none',
                'estimated_delays' => 0
            ],
            'forecast' => [
                'next_hour' => 'Partly Cloudy',
                'service_window' => 'Favorable conditions',
                'precipitation_chance' => 15
            ]
        ];
    }
    
    /**
     * Obter condições de trânsito
     */
    private static function getTrafficConditions($address) {
        return [
            'current_conditions' => 'Light traffic',
            'travel_impact' => [
                'delay_minutes' => 2,
                'route_optimization' => 'applied',
                'alternative_routes' => 2,
                'congestion_level' => 'low'
            ],
            'real_time_updates' => true,
            'estimated_travel_time' => 12, // minutes
            'last_updated' => date('Y-m-d H:i:s', strtotime('-3 minutes'))
        ];
    }
    
    // Funções auxiliares para cálculos
    private static function getStatusDescription($status) {
        $descriptions = [
            'scheduled' => 'Your service is confirmed and scheduled',
            'professional_assigned' => 'A professional has been assigned to your service',
            'professional_en_route' => 'Your professional is on the way',
            'professional_arrived' => 'Your professional has arrived',
            'service_in_progress' => 'Your cleaning service is currently in progress',
            'service_completed' => 'Your cleaning service has been completed',
            'completed' => 'Service completed and finalized'
        ];
        
        return $descriptions[$status] ?? 'Status update';
    }
    
    private static function calculateProgressPercentage($status) {
        $percentages = [
            'scheduled' => 10,
            'professional_assigned' => 25,
            'professional_en_route' => 40,
            'professional_arrived' => 50,
            'service_in_progress' => 75,
            'service_completed' => 90,
            'completed' => 100
        ];
        
        return $percentages[$status] ?? 0;
    }
    
    private static function getEstimatedCompletion($status) {
        // Calcular baseado no status atual
        switch ($status) {
            case 'scheduled':
                return date('Y-m-d H:i:s', strtotime('+2 hours'));
            case 'professional_en_route':
                return date('Y-m-d H:i:s', strtotime('+1 hour 30 minutes'));
            case 'service_in_progress':
                return date('Y-m-d H:i:s', strtotime('+45 minutes'));
            default:
                return null;
        }
    }
    
    private static function getNextMilestone($status) {
        $milestones = [
            'scheduled' => 'Professional assignment',
            'professional_assigned' => 'Professional departure',
            'professional_en_route' => 'Professional arrival',
            'professional_arrived' => 'Service start',
            'service_in_progress' => 'Service completion',
            'service_completed' => 'Payment processing'
        ];
        
        return $milestones[$status] ?? null;
    }
    
    private static function canCancel($status) {
        return !in_array($status, ['service_in_progress', 'service_completed', 'completed', 'cancelled']);
    }
    
    private static function canReschedule($status) {
        return in_array($status, ['scheduled', 'professional_assigned']);
    }
    
    private static function getCancellationFee($status) {
        switch ($status) {
            case 'scheduled':
            case 'professional_assigned':
                return 0;
            case 'professional_en_route':
                return 25;
            case 'professional_arrived':
                return 50;
            default:
                return 100;
        }
    }
    
    private static function getRefundPolicy($status) {
        if (in_array($status, ['scheduled', 'professional_assigned'])) {
            return 'Full refund';
        } elseif ($status === 'professional_en_route') {
            return 'Partial refund (75%)';
        } else {
            return 'No refund';
        }
    }
    
    private static function getCurrentPhase($status) {
        $phases = [
            'scheduled' => 'Preparation',
            'professional_assigned' => 'Assignment',
            'professional_en_route' => 'Travel',
            'professional_arrived' => 'Arrival',
            'service_in_progress' => 'Service Execution',
            'service_completed' => 'Completion',
            'completed' => 'Finalization'
        ];
        
        return $phases[$status] ?? 'Unknown';
    }
    
    private static function getCompletedPhases($status) {
        $allPhases = ['Booking', 'Assignment', 'Travel', 'Arrival', 'Service Execution', 'Completion', 'Finalization'];
        $statusMap = [
            'scheduled' => 1,
            'professional_assigned' => 2,
            'professional_en_route' => 3,
            'professional_arrived' => 4,
            'service_in_progress' => 5,
            'service_completed' => 6,
            'completed' => 7
        ];
        
        $completedCount = $statusMap[$status] ?? 0;
        return array_slice($allPhases, 0, $completedCount);
    }
    
    private static function getTimeElapsed($bookingId) {
        // Simular tempo decorrido desde o início
        return 65; // minutes
    }
    
    private static function getTimeRemaining($status) {
        switch ($status) {
            case 'service_in_progress':
                return 55; // minutes
            case 'professional_en_route':
                return 85; // minutes
            default:
                return null;
        }
    }
    
    private static function getHistoricalTracking($bookingId) {
        return [
            'similar_services' => 15,
            'average_duration' => 118, // minutes
            'punctuality_rate' => 94, // percentage
            'customer_satisfaction' => 4.8
        ];
    }
    
    private static function getPerformanceMetrics($bookingId) {
        return [
            'efficiency_score' => 92,
            'quality_score' => 89,
            'customer_satisfaction_predicted' => 4.7,
            'on_time_performance' => 98,
            'issue_resolution_time' => '3.2 minutes'
        ];
    }
}

// Processar requisição
try {
    $bookingId = $_GET['booking_id'] ?? $_POST['booking_id'] ?? null;
    $includeHistory = ($_GET['include_history'] ?? 'false') === 'true';
    
    if (!$bookingId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_booking_id',
            'message' => 'Booking ID is required'
        ]);
        exit();
    }
    
    $result = TrackingSystem::getTrackingData($bookingId, $includeHistory);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Tracking API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'system_error',
        'message' => 'Unable to process tracking request'
    ]);
}

?>
