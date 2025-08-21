<?php
/**
 * Sistema de Fidelidade e Pontos - Blue Project V2
 * Sistema completo de loyalty program com pontos, rewards e gamificação
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Sistema de Fidelidade Completo
 */
class LoyaltySystem {
    
    // Tipos de ações que geram pontos
    private static $pointActions = [
        'service_booking' => ['points' => 100, 'description' => 'Points earned for each service booking'],
        'service_completion' => ['points' => 200, 'description' => 'Bonus points for completing a service'],
        'referral_signup' => ['points' => 500, 'description' => 'Points for each friend who signs up'],
        'review_submission' => ['points' => 50, 'description' => 'Points for leaving a review'],
        'social_share' => ['points' => 25, 'description' => 'Points for sharing on social media'],
        'eco_service_choice' => ['points' => 150, 'description' => 'Bonus for choosing eco-friendly services'],
        'first_service' => ['points' => 300, 'description' => 'Welcome bonus for first service'],
        'monthly_subscription' => ['points' => 400, 'description' => 'Monthly subscription loyalty bonus'],
        'streak_maintenance' => ['points' => 100, 'description' => 'Consecutive monthly services bonus'],
        'perfect_rating' => ['points' => 75, 'description' => 'Bonus for receiving 5-star ratings'],
        'app_download' => ['points' => 100, 'description' => 'One-time bonus for downloading our app'],
        'profile_completion' => ['points' => 50, 'description' => 'Complete your profile bonus'],
        'early_booking' => ['points' => 30, 'description' => 'Book services 7+ days in advance']
    ];
    
    // Níveis de membership
    private static $membershipTiers = [
        'bronze' => [
            'name' => 'Bronze Member',
            'points_required' => 0,
            'benefits' => [
                'Standard customer support',
                'Basic booking flexibility',
                '5% discount on eco-services'
            ],
            'point_multiplier' => 1.0,
            'color' => '#CD7F32'
        ],
        'silver' => [
            'name' => 'Silver Member',
            'points_required' => 2000,
            'benefits' => [
                'Priority customer support',
                'Free service rescheduling (up to 2 times)',
                '10% discount on eco-services',
                '5% discount on all services',
                'Early access to new services'
            ],
            'point_multiplier' => 1.25,
            'color' => '#C0C0C0'
        ],
        'gold' => [
            'name' => 'Gold Member',
            'points_required' => 5000,
            'benefits' => [
                'VIP customer support',
                'Unlimited free rescheduling',
                '15% discount on eco-services',
                '10% discount on all services',
                'Free deep cleaning upgrade (1x per month)',
                'Priority professional assignment',
                'Exclusive monthly promotions'
            ],
            'point_multiplier' => 1.5,
            'color' => '#FFD700'
        ],
        'platinum' => [
            'name' => 'Platinum Member',
            'points_required' => 10000,
            'benefits' => [
                'Dedicated account manager',
                'Same-day booking priority',
                '20% discount on eco-services',
                '15% discount on all services',
                'Free monthly deep cleaning upgrade',
                'Free laundry service (1x per month)',
                'Complimentary home organization consultation',
                'Exclusive VIP events and previews',
                'Personal cleaner preference guarantee'
            ],
            'point_multiplier' => 2.0,
            'color' => '#E5E4E2'
        ]
    ];
    
    /**
     * Obter perfil completo de fidelidade do cliente
     */
    public static function getLoyaltyProfile($customerId) {
        try {
            $profile = self::getCustomerLoyaltyData($customerId);
            $currentTier = self::getCurrentTier($profile['total_points']);
            $nextTier = self::getNextTier($currentTier);
            
            return [
                'success' => true,
                'customer_id' => $customerId,
                'profile' => $profile,
                'current_tier' => $currentTier,
                'next_tier' => $nextTier,
                'point_balance' => self::getPointBalance($customerId),
                'recent_activities' => self::getRecentActivities($customerId),
                'available_rewards' => self::getAvailableRewards($customerId),
                'achievement_status' => self::getAchievements($customerId),
                'referral_program' => self::getReferralProgram($customerId),
                'seasonal_bonuses' => self::getSeasonalBonuses($customerId),
                'gamification' => self::getGamificationData($customerId),
                'personalized_offers' => self::getPersonalizedOffers($customerId)
            ];
            
        } catch (Exception $e) {
            error_log("Loyalty profile error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to retrieve loyalty profile'
            ];
        }
    }
    
    /**
     * Adicionar pontos por ação específica
     */
    public static function addPoints($customerId, $action, $metadata = []) {
        try {
            if (!isset(self::$pointActions[$action])) {
                return [
                    'success' => false,
                    'error' => 'invalid_action',
                    'message' => 'Action not recognized'
                ];
            }
            
            $basePoints = self::$pointActions[$action]['points'];
            $currentTier = self::getCurrentTierByCustomer($customerId);
            $multiplier = self::$membershipTiers[$currentTier]['point_multiplier'];
            
            // Calcular pontos com multiplicador e bônus
            $finalPoints = round($basePoints * $multiplier);
            
            // Aplicar bônus especiais
            $bonusPoints = self::calculateBonusPoints($customerId, $action, $metadata);
            $totalPoints = $finalPoints + $bonusPoints;
            
            // Registrar transação
            $transaction = self::recordPointTransaction($customerId, $action, $totalPoints, $metadata);
            
            // Verificar mudança de tier
            $newTier = self::checkTierUpgrade($customerId);
            
            return [
                'success' => true,
                'points_added' => $totalPoints,
                'base_points' => $basePoints,
                'multiplier' => $multiplier,
                'bonus_points' => $bonusPoints,
                'new_balance' => self::getPointBalance($customerId)['available'],
                'tier_upgrade' => $newTier ? $newTier : null,
                'transaction_id' => $transaction['id'],
                'achievements_unlocked' => self::checkNewAchievements($customerId),
                'milestone_reached' => self::checkMilestones($customerId, $totalPoints)
            ];
            
        } catch (Exception $e) {
            error_log("Add points error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to add points'
            ];
        }
    }
    
    /**
     * Resgatar recompensa
     */
    public static function redeemReward($customerId, $rewardId, $metadata = []) {
        try {
            $reward = self::getRewardDetails($rewardId);
            if (!$reward) {
                return [
                    'success' => false,
                    'error' => 'reward_not_found',
                    'message' => 'Reward not found'
                ];
            }
            
            $balance = self::getPointBalance($customerId);
            if ($balance['available'] < $reward['points_cost']) {
                return [
                    'success' => false,
                    'error' => 'insufficient_points',
                    'message' => 'Insufficient points for this reward',
                    'required' => $reward['points_cost'],
                    'available' => $balance['available']
                ];
            }
            
            // Verificar elegibilidade
            $eligibility = self::checkRewardEligibility($customerId, $rewardId);
            if (!$eligibility['eligible']) {
                return [
                    'success' => false,
                    'error' => 'not_eligible',
                    'message' => $eligibility['reason']
                ];
            }
            
            // Processar resgate
            $redemption = self::processRedemption($customerId, $rewardId, $reward['points_cost'], $metadata);
            
            return [
                'success' => true,
                'redemption_id' => $redemption['id'],
                'reward' => $reward,
                'points_spent' => $reward['points_cost'],
                'new_balance' => self::getPointBalance($customerId)['available'],
                'voucher_code' => $redemption['voucher_code'] ?? null,
                'expiry_date' => $redemption['expiry_date'] ?? null,
                'instructions' => $redemption['instructions'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Redeem reward error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => 'Unable to process redemption'
            ];
        }
    }
    
    /**
     * Obter dados do cliente
     */
    private static function getCustomerLoyaltyData($customerId) {
        return [
            'customer_id' => $customerId,
            'member_since' => date('Y-m-d', strtotime('-8 months')),
            'total_points_earned' => 7850,
            'total_points_spent' => 2350,
            'total_points' => 5500,
            'lifetime_value' => 2340.50,
            'services_completed' => 23,
            'referrals_made' => 4,
            'reviews_submitted' => 18,
            'average_rating_given' => 4.8,
            'favorite_services' => ['house-cleaning', 'eco-cleaning'],
            'last_service_date' => date('Y-m-d', strtotime('-5 days')),
            'consecutive_months' => 6,
            'eco_service_percentage' => 75
        ];
    }
    
    /**
     * Determinar tier atual baseado nos pontos
     */
    private static function getCurrentTier($totalPoints) {
        $tier = 'bronze';
        foreach (array_reverse(self::$membershipTiers, true) as $tierKey => $tierData) {
            if ($totalPoints >= $tierData['points_required']) {
                $tier = $tierKey;
                break;
            }
        }
        return self::$membershipTiers[$tier];
    }
    
    /**
     * Obter próximo tier
     */
    private static function getNextTier($currentTier) {
        $tiers = array_keys(self::$membershipTiers);
        $currentIndex = array_search(array_search($currentTier['name'], array_column(self::$membershipTiers, 'name')), $tiers);
        
        if ($currentIndex < count($tiers) - 1) {
            $nextTierKey = $tiers[$currentIndex + 1];
            $nextTier = self::$membershipTiers[$nextTierKey];
            return [
                'tier' => $nextTier,
                'points_needed' => $nextTier['points_required'] - 5500, // current points
                'progress_percentage' => round((5500 / $nextTier['points_required']) * 100, 1)
            ];
        }
        
        return null; // Already at highest tier
    }
    
    /**
     * Obter saldo de pontos
     */
    private static function getPointBalance($customerId) {
        $profile = self::getCustomerLoyaltyData($customerId);
        
        return [
            'available' => $profile['total_points'],
            'earned_this_month' => 450,
            'earned_lifetime' => $profile['total_points_earned'],
            'spent_lifetime' => $profile['total_points_spent'],
            'pending' => 150, // Points from recent services not yet credited
            'expiring_soon' => [
                'points' => 200,
                'expiry_date' => date('Y-m-d', strtotime('+30 days')),
                'reason' => 'Points from services older than 2 years'
            ]
        ];
    }
    
    /**
     * Obter atividades recentes
     */
    private static function getRecentActivities($customerId) {
        return [
            [
                'date' => date('Y-m-d', strtotime('-2 days')),
                'action' => 'service_completion',
                'points' => 250,
                'description' => 'House cleaning service completed',
                'type' => 'earned',
                'service_id' => 'SRV_001'
            ],
            [
                'date' => date('Y-m-d', strtotime('-5 days')),
                'action' => 'review_submission',
                'points' => 63, // 50 base + 25% silver bonus
                'description' => '5-star review submitted',
                'type' => 'earned',
                'review_id' => 'REV_001'
            ],
            [
                'date' => date('Y-m-d', strtotime('-1 week')),
                'action' => 'eco_service_choice',
                'points' => 188, // 150 base + 25% silver bonus
                'description' => 'Chose eco-friendly cleaning option',
                'type' => 'earned',
                'service_id' => 'SRV_002'
            ],
            [
                'date' => date('Y-m-d', strtotime('-10 days')),
                'action' => 'reward_redemption',
                'points' => -500,
                'description' => 'Redeemed: Free deep cleaning upgrade',
                'type' => 'spent',
                'reward_id' => 'RWD_005'
            ],
            [
                'date' => date('Y-m-d', strtotime('-2 weeks')),
                'action' => 'referral_signup',
                'points' => 625, // 500 base + 25% silver bonus
                'description' => 'Friend Sarah joined Blue Cleaning',
                'type' => 'earned',
                'referral_id' => 'REF_003'
            ]
        ];
    }
    
    /**
     * Obter recompensas disponíveis
     */
    private static function getAvailableRewards($customerId) {
        $currentPoints = self::getPointBalance($customerId)['available'];
        
        $allRewards = [
            [
                'id' => 'RWD_001',
                'name' => '$10 Service Discount',
                'description' => '$10 off your next cleaning service',
                'points_cost' => 500,
                'category' => 'discount',
                'value' => 10.00,
                'expiry_days' => 90,
                'usage_limit' => 1,
                'restrictions' => 'Minimum $50 service value',
                'icon' => 'dollar-sign'
            ],
            [
                'id' => 'RWD_002',
                'name' => 'Free Window Cleaning Add-on',
                'description' => 'Complimentary interior window cleaning',
                'points_cost' => 750,
                'category' => 'service_upgrade',
                'value' => 25.00,
                'expiry_days' => 60,
                'usage_limit' => 1,
                'restrictions' => 'With regular house cleaning service',
                'icon' => 'home'
            ],
            [
                'id' => 'RWD_003',
                'name' => '$25 Service Credit',
                'description' => '$25 credit towards any service',
                'points_cost' => 1250,
                'category' => 'discount',
                'value' => 25.00,
                'expiry_days' => 120,
                'usage_limit' => 1,
                'restrictions' => 'Cannot be combined with other offers',
                'icon' => 'gift'
            ],
            [
                'id' => 'RWD_004',
                'name' => 'Priority Booking',
                'description' => '30 days of priority booking status',
                'points_cost' => 800,
                'category' => 'premium_service',
                'value' => 0,
                'expiry_days' => 30,
                'usage_limit' => 1,
                'restrictions' => 'Active for 30 days from activation',
                'icon' => 'clock'
            ],
            [
                'id' => 'RWD_005',
                'name' => 'Deep Cleaning Upgrade',
                'description' => 'Free upgrade to deep cleaning service',
                'points_cost' => 1500,
                'category' => 'service_upgrade',
                'value' => 50.00,
                'expiry_days' => 90,
                'usage_limit' => 1,
                'restrictions' => 'Gold tier and above only',
                'icon' => 'star'
            ],
            [
                'id' => 'RWD_006',
                'name' => 'Eco-Products Gift Set',
                'description' => 'Premium eco-friendly cleaning products',
                'points_cost' => 2000,
                'category' => 'physical_gift',
                'value' => 45.00,
                'expiry_days' => 180,
                'usage_limit' => 1,
                'restrictions' => 'Delivery within Melbourne metro area',
                'icon' => 'leaf'
            ]
        ];
        
        // Filtrar por elegibilidade e marcar affordability
        $availableRewards = [];
        foreach ($allRewards as $reward) {
            $eligibility = self::checkRewardEligibility($customerId, $reward['id']);
            if ($eligibility['eligible']) {
                $reward['can_afford'] = $currentPoints >= $reward['points_cost'];
                $reward['popularity'] = rand(1, 100); // Mock popularity score
                $availableRewards[] = $reward;
            }
        }
        
        // Ordenar por affordability e popularidade
        usort($availableRewards, function($a, $b) {
            if ($a['can_afford'] !== $b['can_afford']) {
                return $b['can_afford'] - $a['can_afford'];
            }
            return $b['popularity'] - $a['popularity'];
        });
        
        return $availableRewards;
    }
    
    /**
     * Obter conquistas do cliente
     */
    private static function getAchievements($customerId) {
        return [
            'unlocked' => [
                [
                    'id' => 'ACH_001',
                    'name' => 'First Service Champion',
                    'description' => 'Completed your first service',
                    'icon' => 'trophy',
                    'category' => 'milestone',
                    'unlocked_date' => date('Y-m-d', strtotime('-8 months')),
                    'rarity' => 'common'
                ],
                [
                    'id' => 'ACH_002',
                    'name' => 'Eco Warrior',
                    'description' => 'Chose eco-friendly options 10 times',
                    'icon' => 'leaf',
                    'category' => 'environmental',
                    'unlocked_date' => date('Y-m-d', strtotime('-3 months')),
                    'rarity' => 'uncommon'
                ],
                [
                    'id' => 'ACH_003',
                    'name' => 'Review Master',
                    'description' => 'Left 15+ detailed reviews',
                    'icon' => 'star',
                    'category' => 'community',
                    'unlocked_date' => date('Y-m-d', strtotime('-1 month')),
                    'rarity' => 'rare'
                ],
                [
                    'id' => 'ACH_004',
                    'name' => 'Streak Keeper',
                    'description' => '6 consecutive months of service',
                    'icon' => 'calendar',
                    'category' => 'consistency',
                    'unlocked_date' => date('Y-m-d', strtotime('-1 week')),
                    'rarity' => 'epic'
                ]
            ],
            'in_progress' => [
                [
                    'id' => 'ACH_005',
                    'name' => 'Silver Supporter',
                    'description' => 'Reach Silver tier status',
                    'icon' => 'medal',
                    'category' => 'milestone',
                    'progress' => 100,
                    'target' => 100,
                    'rarity' => 'rare'
                ],
                [
                    'id' => 'ACH_006',
                    'name' => 'Referral King/Queen',
                    'description' => 'Refer 10 friends to Blue Cleaning',
                    'icon' => 'users',
                    'category' => 'social',
                    'progress' => 4,
                    'target' => 10,
                    'rarity' => 'legendary'
                ],
                [
                    'id' => 'ACH_007',
                    'name' => 'Service Explorer',
                    'description' => 'Try 5 different service types',
                    'icon' => 'compass',
                    'category' => 'exploration',
                    'progress' => 2,
                    'target' => 5,
                    'rarity' => 'uncommon'
                ]
            ],
            'available' => [
                [
                    'id' => 'ACH_008',
                    'name' => 'Perfect Score',
                    'description' => 'Receive 10 consecutive 5-star ratings',
                    'icon' => 'award',
                    'category' => 'quality',
                    'progress' => 0,
                    'target' => 10,
                    'rarity' => 'legendary'
                ]
            ]
        ];
    }
    
    /**
     * Obter programa de referência
     */
    private static function getReferralProgram($customerId) {
        return [
            'referral_code' => 'BLUE-' . strtoupper(substr($customerId, -6)),
            'total_referrals' => 4,
            'successful_referrals' => 3,
            'pending_referrals' => 1,
            'total_earnings' => 1875, // 3 * 625 points
            'referral_reward' => [
                'referrer_points' => 500,
                'referee_discount' => 20, // percentage
                'referee_points' => 300
            ],
            'recent_referrals' => [
                [
                    'name' => 'Sarah M.',
                    'status' => 'completed_first_service',
                    'date_referred' => date('Y-m-d', strtotime('-2 weeks')),
                    'points_earned' => 625,
                    'services_completed' => 2
                ],
                [
                    'name' => 'John D.',
                    'status' => 'signed_up',
                    'date_referred' => date('Y-m-d', strtotime('-1 week')),
                    'points_earned' => 0,
                    'services_completed' => 0
                ],
                [
                    'name' => 'Lisa K.',
                    'status' => 'completed_first_service',
                    'date_referred' => date('Y-m-d', strtotime('-3 weeks')),
                    'points_earned' => 625,
                    'services_completed' => 1
                ]
            ],
            'sharing_options' => [
                'email' => true,
                'sms' => true,
                'whatsapp' => true,
                'facebook' => true,
                'twitter' => true,
                'linkedin' => true
            ],
            'monthly_bonus' => [
                'threshold' => 3, // referrals needed
                'bonus_points' => 1000,
                'current_month_referrals' => 1,
                'eligible' => false
            ]
        ];
    }
    
    /**
     * Obter bônus sazonais
     */
    private static function getSeasonalBonuses($customerId) {
        return [
            'active_campaigns' => [
                [
                    'id' => 'SEASON_001',
                    'name' => 'Summer Deep Clean Bonus',
                    'description' => 'Double points on deep cleaning services',
                    'multiplier' => 2.0,
                    'applicable_services' => ['deep-cleaning'],
                    'start_date' => date('Y-m-d', strtotime('first day of this month')),
                    'end_date' => date('Y-m-d', strtotime('last day of this month')),
                    'usage_count' => 1,
                    'usage_limit' => 2
                ]
            ],
            'upcoming_campaigns' => [
                [
                    'id' => 'SEASON_002',
                    'name' => 'Back to School Special',
                    'description' => '50% bonus points on all services',
                    'multiplier' => 1.5,
                    'applicable_services' => ['all'],
                    'start_date' => date('Y-m-d', strtotime('next month')),
                    'end_date' => date('Y-m-d', strtotime('next month +1 month')),
                    'notification_sent' => false
                ]
            ],
            'seasonal_rewards' => [
                [
                    'id' => 'SEASONAL_RWD_001',
                    'name' => 'Summer Refresh Package',
                    'description' => 'Complete home refresh service',
                    'points_cost' => 3000,
                    'available_until' => date('Y-m-d', strtotime('+2 months')),
                    'limited_quantity' => 50,
                    'claimed' => 23
                ]
            ]
        ];
    }
    
    /**
     * Obter dados de gamificação
     */
    private static function getGamificationData($customerId) {
        return [
            'level' => [
                'current' => 12,
                'experience_points' => 7850,
                'points_to_next_level' => 1150,
                'next_level' => 13
            ],
            'badges' => [
                'earned' => ['early_bird', 'eco_champion', 'review_master', 'streak_keeper'],
                'in_progress' => ['social_butterfly', 'service_explorer'],
                'total_available' => 25
            ],
            'streaks' => [
                'monthly_service' => [
                    'current' => 6,
                    'longest' => 8,
                    'bonus_multiplier' => 1.2
                ],
                'review_submission' => [
                    'current' => 12,
                    'longest' => 15,
                    'bonus_multiplier' => 1.1
                ]
            ],
            'leaderboards' => [
                'monthly_points' => [
                    'rank' => 23,
                    'total_participants' => 1247,
                    'percentile' => 98
                ],
                'eco_services' => [
                    'rank' => 8,
                    'total_participants' => 892,
                    'percentile' => 99
                ]
            ],
            'challenges' => [
                'active' => [
                    [
                        'id' => 'CHALLENGE_001',
                        'name' => 'Green Month Challenge',
                        'description' => 'Book 3 eco-friendly services this month',
                        'progress' => 2,
                        'target' => 3,
                        'reward_points' => 500,
                        'expires' => date('Y-m-d', strtotime('last day of this month'))
                    ]
                ],
                'completed_this_month' => 2,
                'total_completed' => 15
            ]
        ];
    }
    
    /**
     * Obter ofertas personalizadas
     */
    private static function getPersonalizedOffers($customerId) {
        return [
            'recommendations' => [
                [
                    'id' => 'OFFER_001',
                    'type' => 'point_bonus',
                    'title' => 'Triple Points Tuesday',
                    'description' => 'Book a service for next Tuesday and earn 3x points',
                    'offer_value' => 'Up to 600 bonus points',
                    'valid_until' => date('Y-m-d', strtotime('next Tuesday')),
                    'conditions' => 'Valid for services booked for next Tuesday only',
                    'personalization_reason' => 'Based on your Tuesday booking preference'
                ],
                [
                    'id' => 'OFFER_002',
                    'type' => 'service_discount',
                    'title' => 'Eco-Service Loyalty Discount',
                    'description' => '25% off eco-friendly cleaning services',
                    'offer_value' => 'Save up to $40',
                    'valid_until' => date('Y-m-d', strtotime('+2 weeks')),
                    'conditions' => 'Valid for eco-friendly services only',
                    'personalization_reason' => 'You love eco-friendly services'
                ],
                [
                    'id' => 'OFFER_003',
                    'type' => 'reward_discount',
                    'title' => 'VIP Reward Access',
                    'description' => 'Unlock premium rewards 500 points early',
                    'offer_value' => 'Early access to premium rewards',
                    'valid_until' => date('Y-m-d', strtotime('+1 week')),
                    'conditions' => 'Silver tier members only',
                    'personalization_reason' => 'Exclusive Silver tier benefit'
                ]
            ],
            'targeted_rewards' => [
                [
                    'reward_id' => 'RWD_007',
                    'name' => 'Your Favorite Service Discount',
                    'description' => '$15 off your preferred house cleaning service',
                    'points_cost' => 750,
                    'regular_cost' => 900,
                    'savings' => 150,
                    'expires' => date('Y-m-d', strtotime('+1 month'))
                ]
            ],
            'surprise_bonuses' => [
                [
                    'id' => 'SURPRISE_001',
                    'name' => 'Loyalty Appreciation',
                    'description' => 'Thank you for being a valued customer for 8 months!',
                    'bonus_points' => 200,
                    'claimed' => false,
                    'expires' => date('Y-m-d', strtotime('+1 week'))
                ]
            ]
        ];
    }
    
    // Funções auxiliares
    private static function getCurrentTierByCustomer($customerId) {
        $profile = self::getCustomerLoyaltyData($customerId);
        $currentTier = self::getCurrentTier($profile['total_points']);
        return array_search($currentTier['name'], array_column(self::$membershipTiers, 'name'));
    }
    
    private static function calculateBonusPoints($customerId, $action, $metadata) {
        $bonusPoints = 0;
        
        // Bônus por streaks
        if ($action === 'service_completion') {
            $profile = self::getCustomerLoyaltyData($customerId);
            if ($profile['consecutive_months'] >= 3) {
                $bonusPoints += 50; // Streak bonus
            }
        }
        
        // Bônus sazonal
        if (isset($metadata['seasonal_campaign'])) {
            $bonusPoints += 100;
        }
        
        // Bônus por rating perfeito
        if ($action === 'review_submission' && isset($metadata['rating']) && $metadata['rating'] == 5) {
            $bonusPoints += 25;
        }
        
        return $bonusPoints;
    }
    
    private static function recordPointTransaction($customerId, $action, $points, $metadata) {
        return [
            'id' => 'TXN_' . strtoupper(uniqid()),
            'customer_id' => $customerId,
            'action' => $action,
            'points' => $points,
            'timestamp' => date('Y-m-d H:i:s'),
            'metadata' => $metadata
        ];
    }
    
    private static function checkTierUpgrade($customerId) {
        // Simular verificação de upgrade
        return null; // No upgrade in this example
    }
    
    private static function checkNewAchievements($customerId) {
        return []; // No new achievements in this example
    }
    
    private static function checkMilestones($customerId, $pointsAdded) {
        return null; // No milestone reached in this example
    }
    
    private static function getRewardDetails($rewardId) {
        $rewards = self::getAvailableRewards('CUST_001'); // Mock customer for structure
        foreach ($rewards as $reward) {
            if ($reward['id'] === $rewardId) {
                return $reward;
            }
        }
        return null;
    }
    
    private static function checkRewardEligibility($customerId, $rewardId) {
        // Basic eligibility check
        if ($rewardId === 'RWD_005') { // Deep cleaning upgrade
            $currentTier = self::getCurrentTierByCustomer($customerId);
            if (!in_array($currentTier, ['gold', 'platinum'])) {
                return [
                    'eligible' => false,
                    'reason' => 'Gold tier or above required'
                ];
            }
        }
        
        return ['eligible' => true];
    }
    
    private static function processRedemption($customerId, $rewardId, $pointsCost, $metadata) {
        return [
            'id' => 'RED_' . strtoupper(uniqid()),
            'voucher_code' => 'BLUE' . rand(100000, 999999),
            'expiry_date' => date('Y-m-d', strtotime('+90 days')),
            'instructions' => 'Present this voucher code when booking your next service'
        ];
    }
}

// Processar requisições
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_profile';
    $customerId = $_GET['customer_id'] ?? $_POST['customer_id'] ?? 'CUST_001';
    
    switch ($action) {
        case 'get_profile':
            $result = LoyaltySystem::getLoyaltyProfile($customerId);
            break;
            
        case 'add_points':
            $pointAction = $_POST['point_action'] ?? null;
            $metadata = json_decode($_POST['metadata'] ?? '{}', true);
            
            if (!$pointAction) {
                throw new Exception('Point action is required');
            }
            
            $result = LoyaltySystem::addPoints($customerId, $pointAction, $metadata);
            break;
            
        case 'redeem_reward':
            $rewardId = $_POST['reward_id'] ?? null;
            $metadata = json_decode($_POST['metadata'] ?? '{}', true);
            
            if (!$rewardId) {
                throw new Exception('Reward ID is required');
            }
            
            $result = LoyaltySystem::redeemReward($customerId, $rewardId, $metadata);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Loyalty API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'system_error',
        'message' => $e->getMessage()
    ]);
}

?>
