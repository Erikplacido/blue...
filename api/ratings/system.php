<?php
/**
 * API de Sistema de Avaliações - Blue Project V2
 * Sistema bidirecional de avaliações entre clientes e profissionais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    $action = $_GET['action'] ?? 'submit_rating';
    
    switch ($action) {
        case 'submit_rating':
            handleSubmitRating();
            break;
        case 'get_ratings':
            handleGetRatings();
            break;
        case 'get_rating_summary':
            handleGetRatingSummary();
            break;
        case 'respond_to_rating':
            handleRespondToRating();
            break;
        case 'report_rating':
            handleReportRating();
            break;
        case 'upload_rating_photos':
            handleUploadRatingPhotos();
            break;
        case 'get_rating_analytics':
            handleGetRatingAnalytics();
            break;
        case 'moderate_rating':
            handleModerateRating();
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
 * Submit a new rating
 */
function handleSubmitRating() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Required fields
    $bookingId = $input['booking_id'] ?? null;
    $raterId = $input['rater_id'] ?? $_SESSION['user_id'] ?? null;
    $ratedId = $input['rated_id'] ?? null;
    $overallRating = (float)($input['overall_rating'] ?? 0);
    
    // Optional fields
    $categoryRatings = $input['category_ratings'] ?? [];
    $comment = trim($input['comment'] ?? '');
    $photos = $input['photos'] ?? [];
    $isPrivate = (bool)($input['is_private'] ?? false);
    $allowContact = (bool)($input['allow_contact'] ?? true);
    
    // Validation
    if (!$bookingId || !$raterId || !$ratedId) {
        throw new InvalidArgumentException('Booking ID, Rater ID, and Rated ID are required');
    }
    
    if ($overallRating < 1 || $overallRating > 5) {
        throw new InvalidArgumentException('Overall rating must be between 1 and 5');
    }
    
    if ($raterId === $ratedId) {
        throw new InvalidArgumentException('Cannot rate yourself');
    }
    
    // Verify booking and rating permissions
    $bookingInfo = getBookingInfo($bookingId);
    if (!$bookingInfo) {
        throw new InvalidArgumentException('Booking not found');
    }
    
    if (!canRateBooking($raterId, $ratedId, $bookingId)) {
        throw new InvalidArgumentException('You are not authorized to rate this booking');
    }
    
    // Check if rating already exists
    $existingRating = getRatingByBooking($bookingId, $raterId, $ratedId);
    if ($existingRating) {
        throw new InvalidArgumentException('Rating already submitted for this booking');
    }
    
    // Validate category ratings
    $validatedCategoryRatings = validateCategoryRatings($categoryRatings, $bookingInfo['service_type']);
    
    // Process and validate photos
    $processedPhotos = processRatingPhotos($photos);
    
    // Create rating record
    $ratingData = [
        'booking_id' => $bookingId,
        'rater_id' => $raterId,
        'rated_id' => $ratedId,
        'overall_rating' => $overallRating,
        'category_ratings' => json_encode($validatedCategoryRatings),
        'comment' => $comment,
        'photos' => json_encode($processedPhotos),
        'is_private' => $isPrivate,
        'allow_contact' => $allowContact,
        'status' => 'pending_moderation',
        'created_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    $ratingId = saveRating($ratingData);
    
    if ($ratingId) {
        // Update user's rating statistics
        updateRatingStatistics($ratedId);
        
        // Send notification to rated user
        sendRatingNotification($ratedId, $raterId, $ratingId, $overallRating);
        
        // Trigger rating moderation if needed
        triggerRatingModeration($ratingId);
        
        // Check for incentives/rewards
        $rewards = checkRatingRewards($raterId);
        
        echo json_encode([
            'success' => true,
            'rating_id' => $ratingId,
            'message' => 'Rating submitted successfully',
            'status' => 'pending_moderation',
            'rewards' => $rewards,
            'estimated_moderation_time' => '24 hours',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to save rating');
    }
}

/**
 * Get ratings for a user or booking
 */
function handleGetRatings() {
    $userId = $_GET['user_id'] ?? null;
    $bookingId = $_GET['booking_id'] ?? null;
    $ratingType = $_GET['type'] ?? 'received'; // received, given
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $sortBy = $_GET['sort_by'] ?? 'created_at'; // created_at, rating, helpful_votes
    $sortOrder = $_GET['sort_order'] ?? 'desc'; // asc, desc
    $filterRating = $_GET['filter_rating'] ?? null; // 1-5
    $includePhotos = $_GET['include_photos'] ?? 'true';
    
    if (!$userId && !$bookingId) {
        throw new InvalidArgumentException('Either User ID or Booking ID is required');
    }
    
    // Get ratings based on criteria
    $ratings = getRatings([
        'user_id' => $userId,
        'booking_id' => $bookingId,
        'type' => $ratingType,
        'page' => $page,
        'limit' => $limit,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder,
        'filter_rating' => $filterRating,
        'include_photos' => $includePhotos === 'true',
        'status' => 'approved'
    ]);
    
    // Add additional data for each rating
    foreach ($ratings as &$rating) {
        $rating['helpful_votes'] = getHelpfulVotes($rating['id']);
        $rating['rater_info'] = getRaterInfo($rating['rater_id']);
        $rating['service_info'] = getServiceInfo($rating['booking_id']);
        $rating['can_respond'] = canRespondToRating($rating['id'], $_SESSION['user_id'] ?? null);
        $rating['response'] = getRatingResponse($rating['id']);
    }
    
    // Get summary statistics
    $statistics = getRatingStatistics($userId, $ratingType);
    
    echo json_encode([
        'success' => true,
        'ratings' => $ratings,
        'statistics' => $statistics,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_count' => count($ratings),
            'has_more' => count($ratings) === $limit
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get rating summary for a user
 */
function handleGetRatingSummary() {
    $userId = $_GET['user_id'] ?? null;
    $timeRange = $_GET['time_range'] ?? 'all'; // all, 30d, 90d, 1y
    
    if (!$userId) {
        throw new InvalidArgumentException('User ID is required');
    }
    
    // Get overall statistics
    $overallStats = getRatingStatistics($userId, 'received', $timeRange);
    
    // Get category breakdown
    $categoryBreakdown = getCategoryRatingBreakdown($userId, $timeRange);
    
    // Get recent ratings
    $recentRatings = getRecentRatings($userId, 5);
    
    // Get rating trends
    $trends = getRatingTrends($userId, $timeRange);
    
    // Get achievements/badges
    $achievements = getRatingAchievements($userId);
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'overall_stats' => $overallStats,
        'category_breakdown' => $categoryBreakdown,
        'recent_ratings' => $recentRatings,
        'trends' => $trends,
        'achievements' => $achievements,
        'time_range' => $timeRange,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Respond to a rating
 */
function handleRespondToRating() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ratingId = $input['rating_id'] ?? null;
    $responderId = $input['responder_id'] ?? $_SESSION['user_id'] ?? null;
    $response = trim($input['response'] ?? '');
    
    if (!$ratingId || !$responderId || empty($response)) {
        throw new InvalidArgumentException('Rating ID, Responder ID, and response are required');
    }
    
    // Verify permission to respond
    if (!canRespondToRating($ratingId, $responderId)) {
        throw new InvalidArgumentException('You are not authorized to respond to this rating');
    }
    
    // Check if response already exists
    $existingResponse = getRatingResponse($ratingId);
    if ($existingResponse) {
        throw new InvalidArgumentException('Response already exists for this rating');
    }
    
    // Save response
    $responseId = saveRatingResponse([
        'rating_id' => $ratingId,
        'responder_id' => $responderId,
        'response' => $response,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($responseId) {
        // Notify the original rater
        $rating = getRatingById($ratingId);
        sendResponseNotification($rating['rater_id'], $responderId, $ratingId);
        
        echo json_encode([
            'success' => true,
            'response_id' => $responseId,
            'message' => 'Response submitted successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to save response');
    }
}

/**
 * Report inappropriate rating
 */
function handleReportRating() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ratingId = $input['rating_id'] ?? null;
    $reporterId = $input['reporter_id'] ?? $_SESSION['user_id'] ?? null;
    $reason = $input['reason'] ?? null;
    $description = trim($input['description'] ?? '');
    
    if (!$ratingId || !$reporterId || !$reason) {
        throw new InvalidArgumentException('Rating ID, Reporter ID, and reason are required');
    }
    
    $validReasons = [
        'inappropriate_language',
        'false_information',
        'spam',
        'harassment',
        'irrelevant_content',
        'privacy_violation',
        'other'
    ];
    
    if (!in_array($reason, $validReasons)) {
        throw new InvalidArgumentException('Invalid report reason');
    }
    
    // Check if already reported by this user
    if (hasUserReportedRating($reporterId, $ratingId)) {
        throw new InvalidArgumentException('You have already reported this rating');
    }
    
    // Save report
    $reportId = saveRatingReport([
        'rating_id' => $ratingId,
        'reporter_id' => $reporterId,
        'reason' => $reason,
        'description' => $description,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($reportId) {
        // Trigger moderation review
        triggerModerationReview($ratingId, $reportId);
        
        echo json_encode([
            'success' => true,
            'report_id' => $reportId,
            'message' => 'Rating reported successfully. Our team will review it.',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to submit report');
    }
}

/**
 * Upload photos for rating
 */
function handleUploadRatingPhotos() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $ratingId = $_POST['rating_id'] ?? null;
    $uploaderId = $_POST['uploader_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$ratingId || !$uploaderId) {
        throw new InvalidArgumentException('Rating ID and Uploader ID are required');
    }
    
    // Verify permission
    if (!canUploadRatingPhotos($ratingId, $uploaderId)) {
        throw new InvalidArgumentException('You are not authorized to upload photos for this rating');
    }
    
    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        throw new InvalidArgumentException('No photos uploaded');
    }
    
    $uploadedPhotos = [];
    $errors = [];
    
    // Process multiple photos
    $photoCount = count($_FILES['photos']['name']);
    
    if ($photoCount > 5) {
        throw new InvalidArgumentException('Maximum 5 photos allowed per rating');
    }
    
    for ($i = 0; $i < $photoCount; $i++) {
        $photo = [
            'name' => $_FILES['photos']['name'][$i],
            'type' => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error' => $_FILES['photos']['error'][$i],
            'size' => $_FILES['photos']['size'][$i]
        ];
        
        if ($photo['error'] === UPLOAD_ERR_OK) {
            $validation = validateRatingPhoto($photo);
            
            if ($validation['valid']) {
                $uploadResult = uploadRatingPhoto($photo, $ratingId, $uploaderId);
                
                if ($uploadResult['success']) {
                    $uploadedPhotos[] = $uploadResult;
                } else {
                    $errors[] = "Photo " . ($i + 1) . ": " . $uploadResult['error'];
                }
            } else {
                $errors[] = "Photo " . ($i + 1) . ": " . $validation['error'];
            }
        } else {
            $errors[] = "Photo " . ($i + 1) . ": Upload error";
        }
    }
    
    echo json_encode([
        'success' => count($uploadedPhotos) > 0,
        'uploaded_photos' => $uploadedPhotos,
        'total_uploaded' => count($uploadedPhotos),
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get rating analytics (admin only)
 */
function handleGetRatingAnalytics() {
    $requesterId = $_SESSION['user_id'] ?? $_GET['admin_id'] ?? null;
    
    // Verify admin permissions
    if (!isAdmin($requesterId)) {
        throw new InvalidArgumentException('Admin access required');
    }
    
    $timeRange = $_GET['time_range'] ?? '30d';
    $serviceType = $_GET['service_type'] ?? null;
    
    // Get overall analytics
    $analytics = [
        'total_ratings' => getTotalRatingsCount($timeRange, $serviceType),
        'average_rating' => getAverageRating($timeRange, $serviceType),
        'rating_distribution' => getRatingDistribution($timeRange, $serviceType),
        'category_averages' => getCategoryAverages($timeRange, $serviceType),
        'trend_data' => getRatingTrendData($timeRange, $serviceType),
        'top_performers' => getTopPerformers($timeRange, $serviceType),
        'flagged_ratings' => getFlaggedRatings($timeRange),
        'response_rate' => getResponseRate($timeRange, $serviceType)
    ];
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'time_range' => $timeRange,
        'service_type' => $serviceType,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Moderate rating (admin only)
 */
function handleModerateRating() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ratingId = $input['rating_id'] ?? null;
    $moderatorId = $input['moderator_id'] ?? $_SESSION['user_id'] ?? null;
    $action = $input['action'] ?? null; // approve, reject, edit
    $moderationNotes = trim($input['moderation_notes'] ?? '');
    
    // Verify admin permissions
    if (!isAdmin($moderatorId)) {
        throw new InvalidArgumentException('Admin access required');
    }
    
    if (!$ratingId || !$action) {
        throw new InvalidArgumentException('Rating ID and action are required');
    }
    
    $validActions = ['approve', 'reject', 'edit', 'flag'];
    if (!in_array($action, $validActions)) {
        throw new InvalidArgumentException('Invalid moderation action');
    }
    
    // Get rating details
    $rating = getRatingById($ratingId);
    if (!$rating) {
        throw new InvalidArgumentException('Rating not found');
    }
    
    // Process moderation action
    $result = processModerationAction($ratingId, $action, $moderatorId, $moderationNotes, $input);
    
    if ($result['success']) {
        // Log moderation action
        logModerationAction($ratingId, $moderatorId, $action, $moderationNotes);
        
        // Send notification to rating author
        sendModerationNotification($rating['rater_id'], $action, $ratingId);
        
        echo json_encode([
            'success' => true,
            'rating_id' => $ratingId,
            'action' => $action,
            'new_status' => $result['new_status'],
            'message' => 'Moderation action completed successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Helper Functions
 */

function getBookingInfo($bookingId) {
    // Get booking information from database
    return [
        'id' => $bookingId,
        'customer_id' => 'cust_123',
        'professional_id' => 'prof_456',
        'service_type' => 'cleaning',
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ];
}

function canRateBooking($raterId, $ratedId, $bookingId) {
    // Verify the rater is participant in the booking and service is completed
    $booking = getBookingInfo($bookingId);
    return $booking && 
           ($booking['customer_id'] === $raterId || $booking['professional_id'] === $raterId) &&
           ($booking['customer_id'] === $ratedId || $booking['professional_id'] === $ratedId) &&
           $booking['status'] === 'completed';
}

function getRatingByBooking($bookingId, $raterId, $ratedId) {
    // Check if rating already exists
    return null; // No existing rating
}

function validateCategoryRatings($categoryRatings, $serviceType) {
    $validCategories = [
        'cleaning' => ['quality', 'timeliness', 'professionalism', 'communication'],
        'gardening' => ['quality', 'timeliness', 'expertise', 'cleanup'],
        'handyman' => ['quality', 'timeliness', 'skills', 'problem_solving']
    ];
    
    $serviceCategories = $validCategories[$serviceType] ?? $validCategories['cleaning'];
    $validated = [];
    
    foreach ($categoryRatings as $category => $rating) {
        if (in_array($category, $serviceCategories) && $rating >= 1 && $rating <= 5) {
            $validated[$category] = (float)$rating;
        }
    }
    
    return $validated;
}

function processRatingPhotos($photos) {
    // Process and validate photos
    $processed = [];
    
    foreach ($photos as $photo) {
        if (isset($photo['url']) && filter_var($photo['url'], FILTER_VALIDATE_URL)) {
            $processed[] = [
                'url' => $photo['url'],
                'thumbnail_url' => $photo['thumbnail_url'] ?? null,
                'caption' => $photo['caption'] ?? '',
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    return $processed;
}

function saveRating($ratingData) {
    // Save rating to database
    return 'rating_' . uniqid();
}

function updateRatingStatistics($userId) {
    // Update user's overall rating statistics
}

function sendRatingNotification($ratedUserId, $raterUserId, $ratingId, $rating) {
    // Send push notification about new rating
}

function triggerRatingModeration($ratingId) {
    // Queue rating for moderation if needed
}

function checkRatingRewards($raterId) {
    // Check for rewards/incentives for rating
    return [
        'points_earned' => 10,
        'badges_unlocked' => [],
        'discount_applied' => null
    ];
}

// Additional helper functions would be implemented here...
function getRatings($criteria) { return []; }
function getHelpfulVotes($ratingId) { return rand(0, 10); }
function getRaterInfo($raterId) { return ['name' => 'User Name', 'avatar' => '/avatar.jpg']; }
function getServiceInfo($bookingId) { return ['type' => 'cleaning', 'date' => date('Y-m-d')]; }
function canRespondToRating($ratingId, $userId) { return true; }
function getRatingResponse($ratingId) { return null; }
function getRatingStatistics($userId, $type, $timeRange = 'all') { return ['average' => 4.8, 'total' => 45]; }
function getCategoryRatingBreakdown($userId, $timeRange) { return []; }
function getRecentRatings($userId, $limit) { return []; }
function getRatingTrends($userId, $timeRange) { return []; }
function getRatingAchievements($userId) { return []; }
function getRatingById($ratingId) { return ['id' => $ratingId, 'rater_id' => 'user_123']; }
function saveRatingResponse($data) { return 'response_' . uniqid(); }
function sendResponseNotification($raterId, $responderId, $ratingId) {}
function hasUserReportedRating($userId, $ratingId) { return false; }
function saveRatingReport($data) { return 'report_' . uniqid(); }
function triggerModerationReview($ratingId, $reportId) {}
function canUploadRatingPhotos($ratingId, $userId) { return true; }
function validateRatingPhoto($photo) { return ['valid' => true]; }
function uploadRatingPhoto($photo, $ratingId, $userId) { return ['success' => true, 'url' => '/photo.jpg']; }
function isAdmin($userId) { return true; }
function getTotalRatingsCount($timeRange, $serviceType) { return 1247; }
function getAverageRating($timeRange, $serviceType) { return 4.7; }
function getRatingDistribution($timeRange, $serviceType) { return []; }
function getCategoryAverages($timeRange, $serviceType) { return []; }
function getRatingTrendData($timeRange, $serviceType) { return []; }
function getTopPerformers($timeRange, $serviceType) { return []; }
function getFlaggedRatings($timeRange) { return []; }
function getResponseRate($timeRange, $serviceType) { return 85.2; }
function processModerationAction($ratingId, $action, $moderatorId, $notes, $input) { return ['success' => true, 'new_status' => 'approved']; }
function logModerationAction($ratingId, $moderatorId, $action, $notes) {}
function sendModerationNotification($userId, $action, $ratingId) {}
?>
