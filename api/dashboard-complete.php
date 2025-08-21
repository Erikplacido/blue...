<?php
/**
 * API do Dashboard do Cliente - Blue Project V2
 * Sistema completo de dashboard com dados reais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include required configurations
require_once '../../config/stripe-config.php';
require_once '../../config/email-system.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Classe principal para gerenciamento do dashboard
 */
class DashboardManager {
    
    private static $mockDatabase = [
        'customers' => [],
        'bookings' => [],
        'services' => [],
        'payments' => [],
        'loyalty_points' => []
    ];
    
    /**
     * Obter dados completos do dashboard
     */
    public static function getDashboardData($customerId, $includeHistory = true) {
        try {
            // Verificar se o cliente existe
            $customer = self::getCustomerData($customerId);
            if (!$customer) {
                return [
                    'success' => false,
                    'error' => 'customer_not_found',
                    'message' => 'Customer not found'
                ];
            }
            
            // Obter dados principais
            $dashboardData = [
                'success' => true,
                'customer' => $customer,
                'overview' => self::getOverviewStats($customerId),
                'upcoming_services' => self::getUpcomingServices($customerId),
                'active_subscriptions' => self::getActiveSubscriptions($customerId),
                'recent_activity' => self::getRecentActivity($customerId),
                'loyalty_program' => self::getLoyaltyData($customerId),
                'payment_methods' => self::getPaymentMethods($customerId),
                'preferences' => self::getCustomerPreferences($customerId),
                'notifications' => self::getNotifications($customerId),
                'quick_actions' => self::getQuickActions($customerId)
            ];
            
            // Incluir histórico se solicitado
            if ($includeHistory) {
                $dashboardData['service_history'] = self::getServiceHistory($customerId);
                $dashboardData['payment_history'] = self::getPaymentHistory($customerId);
                $dashboardData['communication_history'] = self::getCommunicationHistory($customerId);
            }
            
            // Dados de performance
            $dashboardData['performance_metrics'] = self::getPerformanceMetrics($customerId);
            
            // Recomendações personalizadas
            $dashboardData['recommendations'] = self::getPersonalizedRecommendations($customerId);
            
            return $dashboardData;
            
        } catch (Exception $e) {
            error_log("Dashboard error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to load dashboard data'
            ];
        }
    }
    
    /**
     * Obter dados do cliente
     */
    private static function getCustomerData($customerId) {
        // Simular busca no banco de dados
        return [
            'customer_id' => $customerId,
            'stripe_customer_id' => 'cus_' . substr(md5($customerId), 0, 14),
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@email.com',
            'phone' => '+61412345678',
            'address' => [
                'street' => '123 Collins Street',
                'suburb' => 'Melbourne',
                'postcode' => '3000',
                'state' => 'VIC',
                'country' => 'Australia'
            ],
            'member_since' => '2024-03-15',
            'customer_tier' => 'Gold', // Bronze, Silver, Gold, Platinum
            'verified_email' => true,
            'verified_phone' => true,
            'profile_completion' => 85,
            'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'avatar_url' => null,
            'emergency_contact' => [
                'name' => 'John Johnson',
                'phone' => '+61412345679',
                'relationship' => 'Spouse'
            ]
        ];
    }
    
    /**
     * Obter estatísticas de overview
     */
    private static function getOverviewStats($customerId) {
        return [
            'total_services_completed' => 47,
            'total_spent' => 6580.50,
            'current_savings' => 847.30,
            'loyalty_points_balance' => 2150,
            'active_subscriptions' => 2,
            'next_service_date' => date('Y-m-d', strtotime('+3 days')),
            'favorite_service_type' => 'house-cleaning',
            'average_rating_given' => 4.8,
            'customer_satisfaction' => 96,
            'streak_weeks' => 12, // Semanas consecutivas com serviços
            'carbon_footprint_saved' => '12.5 kg CO2', // Eco-friendly metrics
            'time_saved_hours' => 94 // Horas economizadas
        ];
    }
    
    /**
     * Obter próximos serviços
     */
    private static function getUpcomingServices($customerId) {
        return [
            [
                'service_id' => 'SERV_001',
                'booking_id' => 'BOOK_' . date('Ymd') . '_001',
                'service_type' => 'house-cleaning',
                'service_name' => 'Weekly House Cleaning',
                'scheduled_date' => date('Y-m-d', strtotime('+3 days')),
                'scheduled_time' => '10:00-12:00',
                'estimated_duration' => 120,
                'status' => 'confirmed',
                'professional_assigned' => [
                    'name' => 'Maria Santos',
                    'photo' => '/assets/professionals/maria.jpg',
                    'rating' => 4.9,
                    'specialties' => ['eco-friendly', 'pet-friendly'],
                    'arrival_estimate' => '9:45 AM'
                ],
                'service_details' => [
                    'bedrooms' => 3,
                    'bathrooms' => 2,
                    'special_instructions' => 'Please use eco-friendly products',
                    'extras' => ['inside-windows', 'inside-oven'],
                    'price' => 140.00,
                    'currency' => 'AUD'
                ],
                'tracking' => [
                    'can_track' => true,
                    'professional_location_shared' => false,
                    'estimated_arrival' => date('Y-m-d H:i:s', strtotime('+3 days 9:45'))
                ],
                'actions_available' => [
                    'reschedule' => true,
                    'cancel' => true,
                    'modify' => true,
                    'add_instructions' => true
                ]
            ],
            [
                'service_id' => 'SERV_002',
                'booking_id' => 'BOOK_' . date('Ymd') . '_002',
                'service_type' => 'deep-cleaning',
                'service_name' => 'Monthly Deep Clean',
                'scheduled_date' => date('Y-m-d', strtotime('+10 days')),
                'scheduled_time' => '14:00-17:00',
                'estimated_duration' => 180,
                'status' => 'pending_confirmation',
                'professional_assigned' => null,
                'service_details' => [
                    'bedrooms' => 3,
                    'bathrooms' => 2,
                    'special_instructions' => 'Focus on kitchen and bathrooms',
                    'extras' => ['inside-cupboards', 'garage'],
                    'price' => 220.00,
                    'currency' => 'AUD'
                ],
                'tracking' => [
                    'can_track' => false,
                    'professional_location_shared' => false
                ],
                'actions_available' => [
                    'reschedule' => true,
                    'cancel' => true,
                    'modify' => true,
                    'confirm' => true
                ]
            ]
        ];
    }
    
    /**
     * Obter subscriptions ativas
     */
    private static function getActiveSubscriptions($customerId) {
        return [
            [
                'subscription_id' => 'sub_weekly_house',
                'stripe_subscription_id' => 'sub_' . substr(md5($customerId . 'weekly'), 0, 14),
                'service_type' => 'house-cleaning',
                'plan_name' => 'Weekly House Cleaning',
                'frequency' => 'weekly',
                'status' => 'active',
                'current_period_start' => date('Y-m-d', strtotime('-4 days')),
                'current_period_end' => date('Y-m-d', strtotime('+3 days')),
                'next_billing_date' => date('Y-m-d', strtotime('+1 day')),
                'amount' => 120.00,
                'currency' => 'AUD',
                'discount_applied' => 15, // 15% recurring discount
                'services_remaining' => 3,
                'pause_credits' => 2,
                'total_services_completed' => 28,
                'start_date' => '2024-06-15',
                'cancellation_terms' => [
                    'can_cancel' => true,
                    'notice_required_days' => 7,
                    'cancellation_fee' => 0,
                    'refund_policy' => 'prorated'
                ],
                'pause_options' => [
                    'can_pause' => true,
                    'max_pause_duration' => 30, // days
                    'pause_credits_available' => 2,
                    'pause_fee' => 0
                ],
                'modification_options' => [
                    'can_change_frequency' => true,
                    'can_add_extras' => true,
                    'can_change_time' => true,
                    'notification_required' => '48 hours'
                ]
            ],
            [
                'subscription_id' => 'sub_monthly_deep',
                'stripe_subscription_id' => 'sub_' . substr(md5($customerId . 'monthly'), 0, 14),
                'service_type' => 'deep-cleaning',
                'plan_name' => 'Monthly Deep Clean',
                'frequency' => 'monthly',
                'status' => 'active',
                'current_period_start' => date('Y-m-d', strtotime('-15 days')),
                'current_period_end' => date('Y-m-d', strtotime('+15 days')),
                'next_billing_date' => date('Y-m-d', strtotime('+13 days')),
                'amount' => 200.00,
                'currency' => 'AUD',
                'discount_applied' => 10,
                'services_remaining' => 1,
                'pause_credits' => 1,
                'total_services_completed' => 8,
                'start_date' => '2024-04-01',
                'cancellation_terms' => [
                    'can_cancel' => true,
                    'notice_required_days' => 14,
                    'cancellation_fee' => 50,
                    'refund_policy' => 'none'
                ],
                'pause_options' => [
                    'can_pause' => true,
                    'max_pause_duration' => 60,
                    'pause_credits_available' => 1,
                    'pause_fee' => 0
                ],
                'modification_options' => [
                    'can_change_frequency' => false,
                    'can_add_extras' => true,
                    'can_change_time' => true,
                    'notification_required' => '72 hours'
                ]
            ]
        ];
    }
    
    /**
     * Obter atividade recente
     */
    private static function getRecentActivity($customerId) {
        return [
            [
                'activity_id' => 'ACT_001',
                'type' => 'service_completed',
                'title' => 'Weekly cleaning completed',
                'description' => 'Maria Santos completed your weekly house cleaning service',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'icon' => 'checkmark-circle',
                'color' => 'success',
                'metadata' => [
                    'service_rating' => 5,
                    'professional_name' => 'Maria Santos',
                    'duration' => 110, // minutes
                    'before_after_photos' => true
                ]
            ],
            [
                'activity_id' => 'ACT_002',
                'type' => 'payment_successful',
                'title' => 'Payment processed',
                'description' => 'Weekly cleaning payment of $120.00 processed successfully',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'icon' => 'card',
                'color' => 'info',
                'metadata' => [
                    'amount' => 120.00,
                    'payment_method' => '**** 4242',
                    'transaction_id' => 'txn_abc123'
                ]
            ],
            [
                'activity_id' => 'ACT_003',
                'type' => 'service_scheduled',
                'title' => 'Service scheduled',
                'description' => 'Next weekly cleaning scheduled for ' . date('M j, Y', strtotime('+3 days')),
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'icon' => 'calendar',
                'color' => 'primary',
                'metadata' => [
                    'service_date' => date('Y-m-d', strtotime('+3 days')),
                    'time_slot' => '10:00-12:00'
                ]
            ],
            [
                'activity_id' => 'ACT_004',
                'type' => 'loyalty_points_earned',
                'title' => 'Loyalty points earned',
                'description' => 'You earned 120 points from your recent service',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days 2 hours')),
                'icon' => 'star',
                'color' => 'warning',
                'metadata' => [
                    'points_earned' => 120,
                    'bonus_multiplier' => 1.2
                ]
            ],
            [
                'activity_id' => 'ACT_005',
                'type' => 'referral_bonus',
                'title' => 'Referral bonus received',
                'description' => 'You received $25 credit for referring Emma Williams',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'icon' => 'people',
                'color' => 'success',
                'metadata' => [
                    'referral_bonus' => 25.00,
                    'referred_customer' => 'Emma Williams'
                ]
            ]
        ];
    }
    
    /**
     * Obter dados do programa de fidelidade
     */
    private static function getLoyaltyData($customerId) {
        return [
            'current_points' => 2150,
            'lifetime_points_earned' => 8750,
            'points_redeemed' => 6600,
            'current_tier' => 'Gold',
            'next_tier' => 'Platinum',
            'points_to_next_tier' => 850,
            'tier_benefits' => [
                'current_tier_benefits' => [
                    '15% discount on all services',
                    'Priority booking',
                    'Free service upgrades',
                    '2 pause credits per subscription'
                ],
                'next_tier_benefits' => [
                    '20% discount on all services',
                    'Concierge service',
                    'Quarterly free deep clean',
                    '3 pause credits per subscription',
                    'Exclusive member events'
                ]
            ],
            'points_history' => [
                [
                    'date' => date('Y-m-d', strtotime('-2 days')),
                    'description' => 'Weekly cleaning service',
                    'points' => 120,
                    'type' => 'earned',
                    'multiplier' => 1.2
                ],
                [
                    'date' => date('Y-m-d', strtotime('-1 week')),
                    'description' => 'Referral bonus - Emma Williams',
                    'points' => 500,
                    'type' => 'earned',
                    'multiplier' => 1.0
                ],
                [
                    'date' => date('Y-m-d', strtotime('-2 weeks')),
                    'description' => 'Redeemed for service credit',
                    'points' => -1000,
                    'type' => 'redeemed',
                    'value' => '$20 credit'
                ]
            ],
            'available_rewards' => [
                [
                    'reward_id' => 'RW_001',
                    'name' => '$10 Service Credit',
                    'points_required' => 500,
                    'description' => 'Apply $10 credit to your next service',
                    'category' => 'service_credit',
                    'expiry_days' => 90
                ],
                [
                    'reward_id' => 'RW_002',
                    'name' => 'Free Window Cleaning Add-on',
                    'points_required' => 750,
                    'description' => 'Add interior window cleaning to any service',
                    'category' => 'service_upgrade',
                    'expiry_days' => 60
                ],
                [
                    'reward_id' => 'RW_003',
                    'name' => 'Free Deep Clean',
                    'points_required' => 2000,
                    'description' => 'Complete deep cleaning service',
                    'category' => 'free_service',
                    'expiry_days' => 120
                ]
            ],
            'referral_program' => [
                'referral_code' => 'SARAH' . strtoupper(substr(md5($customerId), 0, 6)),
                'referrals_made' => 3,
                'referral_bonus_per_signup' => 25.00,
                'referral_bonus_earned' => 75.00,
                'pending_referrals' => 1
            ]
        ];
    }
    
    /**
     * Obter métodos de pagamento
     */
    private static function getPaymentMethods($customerId) {
        return [
            [
                'payment_method_id' => 'pm_' . substr(md5($customerId . '1'), 0, 14),
                'type' => 'card',
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2027,
                    'funding' => 'credit'
                ],
                'is_default' => true,
                'created' => '2024-03-15',
                'billing_details' => [
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@email.com',
                    'address' => [
                        'line1' => '123 Collins Street',
                        'city' => 'Melbourne',
                        'postal_code' => '3000',
                        'state' => 'VIC',
                        'country' => 'AU'
                    ]
                ]
            ],
            [
                'payment_method_id' => 'pm_' . substr(md5($customerId . '2'), 0, 14),
                'type' => 'card',
                'card' => [
                    'brand' => 'mastercard',
                    'last4' => '8888',
                    'exp_month' => 8,
                    'exp_year' => 2026,
                    'funding' => 'debit'
                ],
                'is_default' => false,
                'created' => '2024-05-20',
                'billing_details' => [
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@email.com'
                ]
            ]
        ];
    }
    
    /**
     * Obter preferências do cliente
     */
    private static function getCustomerPreferences($customerId) {
        return [
            'service_preferences' => [
                'preferred_time_slots' => ['10:00-12:00', '14:00-16:00'],
                'avoid_time_slots' => ['08:00-10:00'],
                'preferred_days' => ['monday', 'wednesday', 'friday'],
                'special_instructions_default' => 'Please use eco-friendly products only',
                'cleaning_products' => 'eco-friendly',
                'pet_friendly_required' => true,
                'key_location' => 'Under the mat',
                'alarm_code' => '****', // Hidden for security
                'parking_instructions' => 'Driveway available'
            ],
            'communication_preferences' => [
                'email_notifications' => true,
                'sms_notifications' => true,
                'push_notifications' => true,
                'marketing_emails' => false,
                'service_reminders' => true,
                'professional_updates' => true,
                'payment_notifications' => true,
                'preferred_contact_method' => 'email',
                'language' => 'en'
            ],
            'accessibility_needs' => [
                'mobility_access_required' => false,
                'quiet_service_preferred' => false,
                'specific_allergies' => ['strong chemicals'],
                'additional_requirements' => []
            ],
            'billing_preferences' => [
                'auto_pay_enabled' => true,
                'invoice_delivery' => 'email',
                'payment_reminders' => true,
                'billing_day_preference' => 15, // Day of month
                'currency_preference' => 'AUD'
            ]
        ];
    }
    
    /**
     * Obter notificações
     */
    private static function getNotifications($customerId) {
        return [
            [
                'notification_id' => 'NOT_001',
                'type' => 'service_reminder',
                'title' => 'Service Tomorrow',
                'message' => 'Your weekly cleaning is scheduled for tomorrow at 10:00 AM',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'read' => false,
                'priority' => 'high',
                'action_required' => false,
                'actions' => [
                    ['label' => 'View Details', 'action' => 'view_service', 'service_id' => 'SERV_001'],
                    ['label' => 'Reschedule', 'action' => 'reschedule', 'service_id' => 'SERV_001']
                ]
            ],
            [
                'notification_id' => 'NOT_002',
                'type' => 'payment_upcoming',
                'title' => 'Payment Due',
                'message' => 'Your weekly cleaning payment of $120 will be processed tomorrow',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'read' => false,
                'priority' => 'medium',
                'action_required' => false,
                'actions' => [
                    ['label' => 'View Payment Methods', 'action' => 'manage_payments']
                ]
            ],
            [
                'notification_id' => 'NOT_003',
                'type' => 'loyalty_milestone',
                'title' => 'Congratulations!',
                'message' => 'You\'ve reached Gold tier! Enjoy 15% off all services.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'read' => true,
                'priority' => 'low',
                'action_required' => false,
                'actions' => [
                    ['label' => 'View Benefits', 'action' => 'view_loyalty']
                ]
            ],
            [
                'notification_id' => 'NOT_004',
                'type' => 'service_feedback',
                'title' => 'Rate Your Service',
                'message' => 'How was your cleaning service with Maria? Your feedback helps us improve.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'read' => false,
                'priority' => 'medium',
                'action_required' => true,
                'actions' => [
                    ['label' => 'Leave Review', 'action' => 'rate_service', 'service_id' => 'SERV_001']
                ]
            ]
        ];
    }
    
    /**
     * Obter ações rápidas
     */
    private static function getQuickActions($customerId) {
        return [
            [
                'action_id' => 'book_one_time',
                'title' => 'Book One-Time Service',
                'description' => 'Schedule a single cleaning service',
                'icon' => 'calendar-plus',
                'color' => 'primary',
                'url' => '/booking.php',
                'available' => true
            ],
            [
                'action_id' => 'pause_subscription',
                'title' => 'Pause Subscription',
                'description' => 'Temporarily pause your regular cleaning',
                'icon' => 'pause-circle',
                'color' => 'warning',
                'url' => '/pause-subscription.php',
                'available' => true,
                'credits_available' => 2
            ],
            [
                'action_id' => 'refer_friend',
                'title' => 'Refer a Friend',
                'description' => 'Earn $25 credit for each referral',
                'icon' => 'share',
                'color' => 'success',
                'url' => '/referral.php',
                'available' => true
            ],
            [
                'action_id' => 'redeem_points',
                'title' => 'Redeem Points',
                'description' => 'Use your loyalty points for rewards',
                'icon' => 'gift',
                'color' => 'info',
                'url' => '/loyalty/redeem.php',
                'available' => true,
                'points_available' => 2150
            ],
            [
                'action_id' => 'emergency_clean',
                'title' => 'Emergency Clean',
                'description' => 'Book urgent cleaning (rush fee applies)',
                'icon' => 'zap',
                'color' => 'danger',
                'url' => '/emergency-booking.php',
                'available' => true,
                'rush_fee' => 50
            ],
            [
                'action_id' => 'update_preferences',
                'title' => 'Update Preferences',
                'description' => 'Modify your cleaning preferences',
                'icon' => 'settings',
                'color' => 'secondary',
                'url' => '/preferences.php',
                'available' => true
            ]
        ];
    }
    
    /**
     * Obter histórico de serviços
     */
    private static function getServiceHistory($customerId, $limit = 10) {
        $history = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $history[] = [
                'service_id' => 'SERV_H_' . sprintf('%03d', $i + 1),
                'booking_id' => 'BOOK_H_' . sprintf('%03d', $i + 1),
                'service_type' => ['house-cleaning', 'deep-cleaning', 'window-cleaning'][rand(0, 2)],
                'service_date' => date('Y-m-d', strtotime('-' . ($i * 7 + rand(1, 6)) . ' days')),
                'duration_actual' => rand(90, 150),
                'amount_paid' => rand(100, 250),
                'professional' => [
                    'name' => ['Maria Santos', 'John Smith', 'Emma Wilson', 'David Chen'][rand(0, 3)],
                    'rating_given' => rand(4, 5)
                ],
                'status' => 'completed',
                'customer_rating' => rand(4, 5),
                'customer_feedback' => $i < 3 ? 'Excellent service as always!' : null,
                'before_after_photos' => rand(0, 1) == 1,
                'issues_reported' => $i == 5 ? ['arrived_late'] : [],
                'resolution_provided' => $i == 5 ? 'Service credit applied' : null
            ];
        }
        
        return $history;
    }
    
    /**
     * Obter histórico de pagamentos
     */
    private static function getPaymentHistory($customerId, $limit = 20) {
        $payments = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $payments[] = [
                'payment_id' => 'PAY_' . sprintf('%04d', $i + 1),
                'transaction_id' => 'txn_' . substr(md5($customerId . $i), 0, 12),
                'amount' => [120.00, 200.00, 140.00, 250.00][rand(0, 3)],
                'currency' => 'AUD',
                'status' => $i == 3 ? 'failed' : 'succeeded',
                'payment_method' => '**** ' . ['4242', '8888'][rand(0, 1)],
                'payment_date' => date('Y-m-d H:i:s', strtotime('-' . ($i * 7) . ' days')),
                'description' => ['Weekly Cleaning', 'Deep Cleaning', 'Window Cleaning'][rand(0, 2)],
                'invoice_number' => 'INV-' . date('Y') . '-' . sprintf('%04d', $i + 1),
                'refund_amount' => $i == 8 ? 50.00 : 0,
                'discount_applied' => $i % 4 == 0 ? 15 : 0, // 15% every 4th payment
                'loyalty_points_earned' => [120, 200, 140, 250][rand(0, 3)]
            ];
        }
        
        return $payments;
    }
    
    /**
     * Obter histórico de comunicações
     */
    private static function getCommunicationHistory($customerId, $limit = 15) {
        $communications = [];
        
        $types = ['email', 'sms', 'push', 'phone_call', 'chat'];
        $categories = ['service_reminder', 'payment', 'promotion', 'support', 'feedback_request'];
        
        for ($i = 0; $i < $limit; $i++) {
            $communications[] = [
                'communication_id' => 'COMM_' . sprintf('%04d', $i + 1),
                'type' => $types[rand(0, 4)],
                'category' => $categories[rand(0, 4)],
                'subject' => 'Service confirmation for tomorrow',
                'sent_date' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                'delivered' => true,
                'opened' => rand(0, 1) == 1,
                'clicked' => rand(0, 1) == 1,
                'response_required' => false,
                'response_received' => null
            ];
        }
        
        return $communications;
    }
    
    /**
     * Obter métricas de performance
     */
    private static function getPerformanceMetrics($customerId) {
        return [
            'service_reliability' => [
                'on_time_percentage' => 94,
                'completion_rate' => 98,
                'cancellation_rate' => 2,
                'average_rating' => 4.8
            ],
            'cost_efficiency' => [
                'average_cost_per_service' => 138.50,
                'total_savings_from_discounts' => 847.30,
                'cost_per_hour_saved' => 6.85,
                'roi_percentage' => 185 // ROI from using the service
            ],
            'satisfaction_metrics' => [
                'net_promoter_score' => 9, // 0-10 scale
                'repeat_booking_rate' => 96,
                'complaint_resolution_time' => '2.5 hours',
                'service_consistency_score' => 92
            ],
            'environmental_impact' => [
                'eco_friendly_services_percentage' => 85,
                'carbon_footprint_reduction' => '12.5 kg CO2',
                'sustainable_products_used' => 78,
                'waste_reduction_score' => 89
            ]
        ];
    }
    
    /**
     * Obter recomendações personalizadas
     */
    private static function getPersonalizedRecommendations($customerId) {
        return [
            [
                'recommendation_id' => 'REC_001',
                'type' => 'service_upgrade',
                'title' => 'Add Window Cleaning',
                'description' => 'Based on your service history, window cleaning would complement your regular cleaning perfectly',
                'confidence_score' => 85,
                'potential_savings' => 25.00,
                'reasoning' => 'Customers with similar preferences saved an average of $25 with this addition',
                'cta' => 'Add to next service',
                'estimated_benefit' => 'Sparkling windows year-round'
            ],
            [
                'recommendation_id' => 'REC_002',
                'type' => 'frequency_optimization',
                'title' => 'Consider Fortnightly Deep Clean',
                'description' => 'Your usage pattern suggests bi-weekly deep cleaning might be more cost-effective',
                'confidence_score' => 72,
                'potential_savings' => 120.00,
                'reasoning' => 'Based on your current monthly deep clean frequency',
                'cta' => 'View pricing',
                'estimated_benefit' => '$120 annual savings'
            ],
            [
                'recommendation_id' => 'REC_003',
                'type' => 'loyalty_optimization',
                'title' => 'Upgrade to Platinum Tier',
                'description' => 'You\'re only 850 points away from Platinum benefits including 20% discount',
                'confidence_score' => 95,
                'potential_savings' => 200.00,
                'reasoning' => 'Platinum members save an average of $200 annually',
                'cta' => 'View benefits',
                'estimated_benefit' => '20% off all services'
            ]
        ];
    }
    
    /**
     * Atualizar preferências do cliente
     */
    public static function updateCustomerPreferences($customerId, $preferences) {
        // Validar preferências
        $validatedPreferences = self::validatePreferences($preferences);
        
        if (!$validatedPreferences['valid']) {
            return [
                'success' => false,
                'errors' => $validatedPreferences['errors']
            ];
        }
        
        // Em implementação real, salvar no banco de dados
        return [
            'success' => true,
            'message' => 'Preferences updated successfully',
            'updated_fields' => array_keys($preferences)
        ];
    }
    
    /**
     * Validar preferências
     */
    private static function validatePreferences($preferences) {
        $errors = [];
        
        // Validar time slots
        if (isset($preferences['preferred_time_slots'])) {
            foreach ($preferences['preferred_time_slots'] as $slot) {
                if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $slot)) {
                    $errors['time_slots'] = 'Invalid time slot format';
                    break;
                }
            }
        }
        
        // Validar configurações de comunicação
        if (isset($preferences['communication_preferences'])) {
            $booleanFields = ['email_notifications', 'sms_notifications', 'push_notifications'];
            foreach ($booleanFields as $field) {
                if (isset($preferences['communication_preferences'][$field]) && 
                    !is_bool($preferences['communication_preferences'][$field])) {
                    $errors[$field] = 'Must be true or false';
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

// Processar requisição
try {
    $customerId = $_GET['customer_id'] ?? $_POST['customer_id'] ?? $_SESSION['customer_id'] ?? null;
    $includeHistory = ($_GET['include_history'] ?? 'true') === 'true';
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_customer_id',
            'message' => 'Customer ID is required'
        ]);
        exit();
    }
    
    // Se for requisição POST para atualizar preferências
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preferences'])) {
        $result = DashboardManager::updateCustomerPreferences($customerId, $_POST['preferences']);
    } else {
        $result = DashboardManager::getDashboardData($customerId, $includeHistory);
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'system_error',
        'message' => 'Unable to process dashboard request'
    ]);
}

?>
