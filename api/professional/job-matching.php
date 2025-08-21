<?php
/**
 * API de Matching de Trabalhos - Blue Project V2
 * Sistema inteligente de correspondência entre profissionais e trabalhos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Include dependencies
require_once '../../booking2.php';

try {
    $action = $_GET['action'] ?? 'find_matches';
    
    switch ($action) {
        case 'find_matches':
            handleFindMatches();
            break;
        case 'accept_job':
            handleAcceptJob();
            break;
        case 'decline_job':
            handleDeclineJob();
            break;
        case 'get_nearby_jobs':
            handleGetNearbyJobs();
            break;
        case 'update_location':
            handleUpdateLocation();
            break;
        default:
            throw new InvalidArgumentException('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Find job matches for a professional
 */
function handleFindMatches() {
    $professionalId = $_GET['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $lat = (float)($_GET['lat'] ?? 0);
    $lng = (float)($_GET['lng'] ?? 0);
    $maxDistance = (int)($_GET['max_distance'] ?? 25);
    $serviceTypes = $_GET['service_types'] ?? [];
    
    if (!$professionalId) {
        throw new InvalidArgumentException('Professional ID is required');
    }
    
    // Get professional profile
    $professional = getProfessionalProfile($professionalId);
    if (!$professional) {
        throw new InvalidArgumentException('Professional not found');
    }
    
    // Find matching jobs
    $matches = findJobMatches($professional, $lat, $lng, $maxDistance, $serviceTypes);
    
    // Sort by matching score
    usort($matches, function($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
    
    // Apply filters
    $matches = applyJobFilters($matches, $_GET);
    
    echo json_encode([
        'success' => true,
        'professional_id' => $professionalId,
        'location' => ['lat' => $lat, 'lng' => $lng],
        'total_matches' => count($matches),
        'jobs' => array_slice($matches, 0, 20), // Limit to 20 jobs
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle job acceptance
 */
function handleAcceptJob() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = $input['job_id'] ?? null;
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    
    if (!$jobId || !$professionalId) {
        throw new InvalidArgumentException('Job ID and Professional ID are required');
    }
    
    // Verify job is still available
    $job = getJobDetails($jobId);
    if (!$job || $job['status'] !== 'available') {
        throw new InvalidArgumentException('Job is no longer available');
    }
    
    // Check if professional is eligible
    $eligibility = checkJobEligibility($professionalId, $jobId);
    if (!$eligibility['eligible']) {
        throw new InvalidArgumentException($eligibility['reason']);
    }
    
    // Accept the job
    $result = acceptJob($professionalId, $jobId);
    
    if ($result['success']) {
        // Send notifications
        sendJobAcceptanceNotifications($professionalId, $jobId);
        
        // Update professional status
        updateProfessionalStatus($professionalId, 'job_accepted');
        
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'professional_id' => $professionalId,
            'job_details' => $result['job_details'],
            'next_steps' => $result['next_steps'],
            'message' => 'Job accepted successfully!'
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Handle job decline
 */
function handleDeclineJob() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = $input['job_id'] ?? null;
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $reason = $input['reason'] ?? 'not_specified';
    
    if (!$jobId || !$professionalId) {
        throw new InvalidArgumentException('Job ID and Professional ID are required');
    }
    
    // Record decline
    recordJobDecline($professionalId, $jobId, $reason);
    
    // Update matching algorithm based on decline reason
    updateMatchingPreferences($professionalId, $reason);
    
    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'professional_id' => $professionalId,
        'message' => 'Job declined successfully'
    ]);
}

/**
 * Get nearby jobs
 */
function handleGetNearbyJobs() {
    $lat = (float)($_GET['lat'] ?? 0);
    $lng = (float)($_GET['lng'] ?? 0);
    $radius = (int)($_GET['radius'] ?? 15);
    $professionalId = $_GET['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    
    if (!$lat || !$lng) {
        throw new InvalidArgumentException('Latitude and longitude are required');
    }
    
    $nearbyJobs = getNearbyJobs($lat, $lng, $radius, $professionalId);
    
    echo json_encode([
        'success' => true,
        'location' => ['lat' => $lat, 'lng' => $lng],
        'radius' => $radius,
        'jobs' => $nearbyJobs,
        'count' => count($nearbyJobs),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update professional location
 */
function handleUpdateLocation() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $lat = (float)($input['latitude'] ?? 0);
    $lng = (float)($input['longitude'] ?? 0);
    $accuracy = (float)($input['accuracy'] ?? 0);
    
    if (!$professionalId || !$lat || !$lng) {
        throw new InvalidArgumentException('Professional ID, latitude, and longitude are required');
    }
    
    // Update location in database
    updateProfessionalLocation($professionalId, $lat, $lng, $accuracy);
    
    // Check for nearby urgent jobs
    $urgentJobs = getUrgentJobsNearby($lat, $lng, 10); // 10km radius
    
    echo json_encode([
        'success' => true,
        'professional_id' => $professionalId,
        'location_updated' => true,
        'urgent_jobs_nearby' => count($urgentJobs),
        'urgent_jobs' => $urgentJobs,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Advanced job matching algorithm
 */
function findJobMatches($professional, $lat, $lng, $maxDistance, $serviceTypes = []) {
    $availableJobs = getAvailableJobs($serviceTypes);
    $matches = [];
    
    foreach ($availableJobs as $job) {
        $matchScore = calculateMatchScore($professional, $job, $lat, $lng);
        
        if ($matchScore > 0) {
            $job['match_score'] = $matchScore;
            $job['distance'] = calculateDistance($lat, $lng, $job['latitude'], $job['longitude']);
            $job['travel_time'] = estimateTravelTime($lat, $lng, $job['latitude'], $job['longitude']);
            $job['earnings_potential'] = calculateEarningsPotential($job, $professional);
            
            // Only include jobs within max distance
            if ($job['distance'] <= $maxDistance) {
                $matches[] = $job;
            }
        }
    }
    
    return $matches;
}

/**
 * Calculate job match score based on multiple factors
 */
function calculateMatchScore($professional, $job, $professionalLat, $professionalLng) {
    $score = 0;
    $maxScore = 100;
    
    // Distance factor (30% weight)
    $distance = calculateDistance($professionalLat, $professionalLng, $job['latitude'], $job['longitude']);
    $distanceScore = max(0, 30 - ($distance * 2)); // Decreases as distance increases
    $score += $distanceScore;
    
    // Service type match (25% weight)
    if (in_array($job['service_type'], $professional['services'])) {
        $score += 25;
    }
    
    // Professional rating (20% weight)
    $ratingScore = ($professional['rating'] / 5) * 20;
    $score += $ratingScore;
    
    // Job value (15% weight)
    $valueScore = min(15, ($job['amount'] / 100) * 5); // Higher value jobs get higher score
    $score += $valueScore;
    
    // Availability match (10% weight)
    if (isProfessionalAvailable($professional['id'], $job['scheduled_date'], $job['time_window'])) {
        $score += 10;
    }
    
    // Bonus factors
    if ($job['urgent']) {
        $score += 5; // Urgent job bonus
    }
    
    if ($job['customer_rating'] >= 4.5) {
        $score += 3; // Good customer bonus
    }
    
    if (isProfessionalSpecialized($professional, $job)) {
        $score += 5; // Specialization bonus
    }
    
    return min($maxScore, $score);
}

/**
 * Apply various filters to job matches
 */
function applyJobFilters($matches, $filters) {
    $filtered = $matches;
    
    // Filter by minimum pay
    if (isset($filters['min_pay'])) {
        $minPay = (float)$filters['min_pay'];
        $filtered = array_filter($filtered, function($job) use ($minPay) {
            return $job['amount'] >= $minPay;
        });
    }
    
    // Filter by maximum distance
    if (isset($filters['max_distance'])) {
        $maxDistance = (float)$filters['max_distance'];
        $filtered = array_filter($filtered, function($job) use ($maxDistance) {
            return $job['distance'] <= $maxDistance;
        });
    }
    
    // Filter by urgency
    if (isset($filters['urgent_only']) && $filters['urgent_only'] === 'true') {
        $filtered = array_filter($filtered, function($job) {
            return $job['urgent'] === true;
        });
    }
    
    // Filter by job type
    if (isset($filters['job_type']) && !empty($filters['job_type'])) {
        $jobType = $filters['job_type'];
        $filtered = array_filter($filtered, function($job) use ($jobType) {
            return $job['service_type'] === $jobType;
        });
    }
    
    return array_values($filtered);
}

/**
 * Get available jobs from database
 */
function getAvailableJobs($serviceTypes = []) {
    // Simulate available jobs - replace with actual database query
    $sampleJobs = [
        [
            'id' => 'job_001',
            'service_type' => 'cleaning',
            'title' => 'House Cleaning - 3BR/2BA',
            'description' => 'Regular weekly cleaning for family home',
            'amount' => 85.00,
            'duration' => 2.5,
            'scheduled_date' => date('Y-m-d', strtotime('+1 day')),
            'time_window' => '10:00-13:00',
            'latitude' => -33.8915,
            'longitude' => 151.2767,
            'address' => 'Bondi Beach, NSW 2026',
            'customer_id' => 'cust_001',
            'customer_name' => 'Sarah M.',
            'customer_rating' => 4.8,
            'customer_reviews' => 42,
            'urgent' => false,
            'recurring' => true,
            'requirements' => ['Pet-friendly', 'Own supplies'],
            'notes' => 'Two cats in the house, please be careful with doors'
        ],
        [
            'id' => 'job_002',
            'service_type' => 'gardening',
            'title' => 'Garden Maintenance',
            'description' => 'Hedge trimming and lawn mowing',
            'amount' => 120.00,
            'duration' => 3.0,
            'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
            'time_window' => '08:00-12:00',
            'latitude' => -33.8820,
            'longitude' => 151.2069,
            'address' => 'Paddington, NSW 2021',
            'customer_id' => 'cust_002',
            'customer_name' => 'Mike T.',
            'customer_rating' => 4.9,
            'customer_reviews' => 38,
            'urgent' => true,
            'recurring' => false,
            'requirements' => ['Own equipment', 'Green waste removal'],
            'notes' => 'Large garden, access through side gate'
        ],
        [
            'id' => 'job_003',
            'service_type' => 'handyman',
            'title' => 'Furniture Assembly',
            'description' => 'IKEA furniture assembly - wardrobe and desk',
            'amount' => 0.00, // Amount should be loaded from database
            'duration' => 2.0,
            'scheduled_date' => date('Y-m-d'),
            'time_window' => '14:00-17:00',
            'latitude' => -33.8675,
            'longitude' => 151.2070,
            'address' => 'Sydney CBD, NSW 2000',
            'customer_id' => 'cust_003',
            'customer_name' => 'Emma L.',
            'customer_rating' => 4.6,
            'customer_reviews' => 15,
            'urgent' => true,
            'recurring' => false,
            'requirements' => ['Own tools'],
            'notes' => 'Apartment building, parking available'
        ]
    ];
    
    // Filter by service types if specified
    if (!empty($serviceTypes)) {
        $sampleJobs = array_filter($sampleJobs, function($job) use ($serviceTypes) {
            return in_array($job['service_type'], $serviceTypes);
        });
    }
    
    return array_values($sampleJobs);
}

/**
 * Get professional profile
 */
function getProfessionalProfile($professionalId) {
    // Simulate professional data - replace with actual database query
    return [
        'id' => $professionalId,
        'name' => 'Sarah Mitchell',
        'email' => 'sarah@example.com',
        'rating' => 4.9,
        'total_reviews' => 247,
        'services' => ['cleaning', 'gardening'],
        'specializations' => ['Deep cleaning', 'Move-in/Move-out', 'Lawn maintenance'],
        'service_radius' => 25,
        'hourly_rate' => 25.00,
        'experience_years' => 5,
        'completion_rate' => 98.5,
        'response_rate' => 95.2,
        'on_time_rate' => 96.8,
        'languages' => ['English', 'Spanish'],
        'has_vehicle' => true,
        'has_equipment' => true,
        'availability' => [
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'wednesday' => ['09:00-17:00'],
            'thursday' => ['09:00-17:00'],
            'friday' => ['09:00-17:00'],
            'saturday' => ['10:00-16:00'],
            'sunday' => ['off']
        ]
    ];
}

/**
 * Utility functions
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function estimateTravelTime($lat1, $lng1, $lat2, $lng2) {
    $distance = calculateDistance($lat1, $lng1, $lat2, $lng2);
    // Assume average speed of 40 km/h in urban areas
    return round(($distance / 40) * 60); // minutes
}

function calculateEarningsPotential($job, $professional) {
    $baseEarnings = $job['amount'];
    
    // Add surge pricing if applicable
    if ($job['urgent']) {
        $baseEarnings *= 1.5;
    }
    
    // Add tip potential based on customer rating
    if ($job['customer_rating'] >= 4.5) {
        $baseEarnings += ($baseEarnings * 0.1); // 10% tip potential
    }
    
    return round($baseEarnings, 2);
}

function isProfessionalAvailable($professionalId, $date, $timeWindow) {
    // Check professional's availability for the given date and time
    // This would typically check against a calendar/schedule system
    return true; // Simplified for now
}

function isProfessionalSpecialized($professional, $job) {
    // Check if professional has specializations that match the job
    $jobRequirements = $job['requirements'] ?? [];
    $professionalSpecializations = $professional['specializations'] ?? [];
    
    foreach ($jobRequirements as $requirement) {
        if (in_array($requirement, $professionalSpecializations)) {
            return true;
        }
    }
    
    return false;
}

function getJobDetails($jobId) {
    // Get detailed job information
    $jobs = getAvailableJobs();
    foreach ($jobs as $job) {
        if ($job['id'] === $jobId) {
            return array_merge($job, ['status' => 'available']);
        }
    }
    return null;
}

function checkJobEligibility($professionalId, $jobId) {
    // Check if professional is eligible for the job
    // This would include checks for:
    // - Service type match
    // - Location within service radius
    // - Availability
    // - Rating requirements
    // - Certification requirements
    
    return ['eligible' => true, 'reason' => null];
}

function acceptJob($professionalId, $jobId) {
    // Process job acceptance
    // This would:
    // - Update job status
    // - Create booking record
    // - Send notifications
    // - Update professional schedule
    
    return [
        'success' => true,
        'job_details' => getJobDetails($jobId),
        'next_steps' => [
            'Navigate to customer location',
            'Call customer 15 minutes before arrival',
            'Complete service and update status',
            'Collect payment and rating'
        ]
    ];
}

function recordJobDecline($professionalId, $jobId, $reason) {
    // Record the decline for analytics and matching improvement
    // This helps improve future job matching
}

function updateMatchingPreferences($professionalId, $declineReason) {
    // Update matching algorithm based on decline patterns
    // This helps improve future job suggestions
}

function getNearbyJobs($lat, $lng, $radius, $professionalId = null) {
    $allJobs = getAvailableJobs();
    $nearbyJobs = [];
    
    foreach ($allJobs as $job) {
        $distance = calculateDistance($lat, $lng, $job['latitude'], $job['longitude']);
        if ($distance <= $radius) {
            $job['distance'] = round($distance, 1);
            $job['travel_time'] = estimateTravelTime($lat, $lng, $job['latitude'], $job['longitude']);
            $nearbyJobs[] = $job;
        }
    }
    
    // Sort by distance
    usort($nearbyJobs, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    return $nearbyJobs;
}

function updateProfessionalLocation($professionalId, $lat, $lng, $accuracy) {
    // Update professional's current location in database
    // This is used for proximity-based job matching
}

function getUrgentJobsNearby($lat, $lng, $radius) {
    $nearbyJobs = getNearbyJobs($lat, $lng, $radius);
    return array_filter($nearbyJobs, function($job) {
        return $job['urgent'] === true;
    });
}

function sendJobAcceptanceNotifications($professionalId, $jobId) {
    // Send notifications to:
    // - Customer (job accepted)
    // - Professional (job details and next steps)
    // - Admin system (for tracking)
}

function updateProfessionalStatus($professionalId, $status) {
    // Update professional's current status
    // e.g., 'available', 'busy', 'offline', 'job_accepted'
}
?>
