/**
 * GPS Tracking System with Real-time Updates
 * Blue Cleaning Services - Location Monitoring & Route Optimization
 */

<?php
require_once __DIR__ . '/../config/australian-database.php';
require_once __DIR__ . '/websocket-server.php';

class GPSTrackingSystem {
    private $pdo;
    private $wsServer;
    private $trackingSettings;
    private $geofenceRadius;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->wsServer = new WebSocketServer();
        $this->geofenceRadius = 100; // 100 meters default
        
        $this->trackingSettings = [
            'update_interval' => 30, // seconds
            'battery_optimization' => true,
            'accuracy_threshold' => 50, // meters
            'max_tracking_duration' => 8 * 3600, // 8 hours
            'offline_storage_limit' => 100 // number of locations
        ];
    }
    
    /**
     * Start tracking session
     */
    public function startTracking($professionalId, $bookingId) {
        try {
            // Validate professional and booking
            if (!$this->validateTrackingPermission($professionalId, $bookingId)) {
                throw new Exception('Unauthorized tracking request');
            }
            
            // Check if tracking already active
            $existingSession = $this->getActiveTrackingSession($professionalId);
            if ($existingSession) {
                return [
                    'success' => false,
                    'error' => 'Tracking session already active',
                    'session_id' => $existingSession['id']
                ];
            }
            
            // Create new tracking session
            $stmt = $this->pdo->prepare("
                INSERT INTO gps_tracking_sessions (
                    professional_id, booking_id, status, started_at,
                    settings, expected_duration
                ) VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            
            $expectedDuration = $this->calculateExpectedDuration($bookingId);
            
            $stmt->execute([
                $professionalId,
                $bookingId,
                'active',
                json_encode($this->trackingSettings),
                $expectedDuration
            ]);
            
            $sessionId = $this->pdo->lastInsertId();
            
            // Notify customer about tracking start
            $this->notifyTrackingStarted($bookingId, $sessionId);
            
            // Log tracking event
            $this->logTrackingEvent($sessionId, 'tracking_started', [
                'professional_id' => $professionalId,
                'booking_id' => $bookingId
            ]);
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'settings' => $this->trackingSettings
            ];
            
        } catch (Exception $e) {
            error_log("GPS tracking start error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update GPS location
     */
    public function updateLocation($sessionId, $latitude, $longitude, $accuracy = null, $timestamp = null) {
        try {
            // Validate session
            $session = $this->getTrackingSession($sessionId);
            if (!$session || $session['status'] !== 'active') {
                throw new Exception('Invalid or inactive tracking session');
            }
            
            $timestamp = $timestamp ?: time();
            
            // Validate coordinates
            if (!$this->isValidCoordinate($latitude, $longitude)) {
                throw new Exception('Invalid GPS coordinates');
            }
            
            // Check accuracy threshold
            if ($accuracy && $accuracy > $this->trackingSettings['accuracy_threshold']) {
                // Store but mark as low accuracy
                $this->logTrackingEvent($sessionId, 'low_accuracy_location', [
                    'accuracy' => $accuracy,
                    'threshold' => $this->trackingSettings['accuracy_threshold']
                ]);
            }
            
            // Calculate distance from last location
            $lastLocation = $this->getLastLocation($sessionId);
            $distanceTraveled = 0;
            $speed = 0;
            
            if ($lastLocation) {
                $distanceTraveled = $this->calculateDistance(
                    $lastLocation['latitude'], $lastLocation['longitude'],
                    $latitude, $longitude
                );
                
                $timeDiff = $timestamp - strtotime($lastLocation['timestamp']);
                if ($timeDiff > 0) {
                    $speed = ($distanceTraveled / $timeDiff) * 3.6; // km/h
                }
            }
            
            // Insert location update
            $stmt = $this->pdo->prepare("
                INSERT INTO gps_locations (
                    session_id, latitude, longitude, accuracy, speed,
                    distance_from_previous, timestamp, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
            ");
            
            $stmt->execute([
                $sessionId,
                $latitude,
                $longitude,
                $accuracy,
                $speed,
                $distanceTraveled,
                $timestamp
            ]);
            
            $locationId = $this->pdo->lastInsertId();
            
            // Check geofencing
            $geofenceEvents = $this->checkGeofences($sessionId, $latitude, $longitude);
            
            // Update session statistics
            $this->updateSessionStatistics($sessionId, $distanceTraveled);
            
            // Prepare real-time data
            $locationData = [
                'session_id' => $sessionId,
                'location_id' => $locationId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'timestamp' => $timestamp,
                'distance_traveled' => $distanceTraveled,
                'geofence_events' => $geofenceEvents
            ];
            
            // Broadcast to interested parties (customer, admin)
            $this->broadcastLocationUpdate($session['booking_id'], $locationData);
            
            // Check for route optimization opportunities
            $this->analyzeRouteOptimization($sessionId, $latitude, $longitude);
            
            return [
                'success' => true,
                'location_id' => $locationId,
                'data' => $locationData
            ];
            
        } catch (Exception $e) {
            error_log("GPS location update error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * End tracking session
     */
    public function endTracking($sessionId, $reason = 'completed') {
        try {
            $session = $this->getTrackingSession($sessionId);
            if (!$session) {
                throw new Exception('Tracking session not found');
            }
            
            // Calculate session summary
            $summary = $this->generateSessionSummary($sessionId);
            
            // Update session status
            $stmt = $this->pdo->prepare("
                UPDATE gps_tracking_sessions 
                SET status = ?, ended_at = NOW(), end_reason = ?,
                    total_distance = ?, total_duration = ?,
                    summary_data = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                'completed',
                $reason,
                $summary['total_distance'],
                $summary['duration'],
                json_encode($summary),
                $sessionId
            ]);
            
            // Notify customer about tracking completion
            $this->notifyTrackingCompleted($session['booking_id'], $summary);
            
            // Log tracking event
            $this->logTrackingEvent($sessionId, 'tracking_ended', [
                'reason' => $reason,
                'summary' => $summary
            ]);
            
            return [
                'success' => true,
                'summary' => $summary
            ];
            
        } catch (Exception $e) {
            error_log("GPS tracking end error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get real-time location for a booking
     */
    public function getCurrentLocation($bookingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT gts.*, gl.latitude, gl.longitude, gl.accuracy,
                       gl.speed, gl.timestamp as last_update,
                       p.name as professional_name,
                       p.phone as professional_phone
                FROM gps_tracking_sessions gts
                LEFT JOIN gps_locations gl ON gts.id = gl.session_id
                JOIN professionals p ON gts.professional_id = p.id
                WHERE gts.booking_id = ? AND gts.status = 'active'
                ORDER BY gl.timestamp DESC
                LIMIT 1
            ");
            
            $stmt->execute([$bookingId]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$location) {
                return [
                    'success' => false,
                    'error' => 'No active tracking session found'
                ];
            }
            
            // Calculate estimated arrival time
            $eta = $this->calculateETA($bookingId, $location['latitude'], $location['longitude']);
            
            return [
                'success' => true,
                'location' => [
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'accuracy' => $location['accuracy'],
                    'speed' => $location['speed'],
                    'last_update' => $location['last_update'],
                    'professional_name' => $location['professional_name'],
                    'professional_phone' => $location['professional_phone'],
                    'eta' => $eta
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get current location error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get tracking history for a session
     */
    public function getTrackingHistory($sessionId, $simplify = true) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT latitude, longitude, accuracy, speed, 
                       distance_from_previous, timestamp
                FROM gps_locations 
                WHERE session_id = ? 
                ORDER BY timestamp ASC
            ");
            
            $stmt->execute([$sessionId]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Simplify route if requested (Douglas-Peucker algorithm)
            if ($simplify && count($locations) > 10) {
                $locations = $this->simplifyRoute($locations, 0.0001); // ~10m tolerance
            }
            
            return [
                'success' => true,
                'locations' => $locations,
                'total_points' => count($locations)
            ];
            
        } catch (Exception $e) {
            error_log("Get tracking history error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create geofence for location monitoring
     */
    public function createGeofence($name, $latitude, $longitude, $radius, $type = 'customer_location') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO geofences (
                    name, latitude, longitude, radius, type,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $name,
                $latitude,
                $longitude,
                $radius,
                $type,
                'active'
            ]);
            
            return [
                'success' => true,
                'geofence_id' => $this->pdo->lastInsertId()
            ];
            
        } catch (Exception $e) {
            error_log("Create geofence error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Private helper methods
     */
    private function validateTrackingPermission($professionalId, $bookingId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM bookings 
            WHERE id = ? AND professional_id = ? 
            AND status IN ('confirmed', 'in_progress')
        ");
        
        $stmt->execute([$bookingId, $professionalId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    private function getActiveTrackingSession($professionalId) {
        $stmt = $this->pdo->prepare("
            SELECT id, booking_id 
            FROM gps_tracking_sessions 
            WHERE professional_id = ? AND status = 'active'
        ");
        
        $stmt->execute([$professionalId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTrackingSession($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM gps_tracking_sessions WHERE id = ?
        ");
        
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function calculateExpectedDuration($bookingId) {
        $stmt = $this->pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, start_time, end_time) as duration
            FROM bookings 
            WHERE id = ?
        ");
        
        $stmt->execute([$bookingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['duration'] : 3600; // Default 1 hour
    }
    
    private function isValidCoordinate($latitude, $longitude) {
        return is_numeric($latitude) && is_numeric($longitude) &&
               $latitude >= -90 && $latitude <= 90 &&
               $longitude >= -180 && $longitude <= 180;
    }
    
    private function getLastLocation($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude, timestamp 
            FROM gps_locations 
            WHERE session_id = ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    private function checkGeofences($sessionId, $latitude, $longitude) {
        $stmt = $this->pdo->prepare("
            SELECT id, name, latitude, longitude, radius, type
            FROM geofences 
            WHERE status = 'active'
        ");
        
        $stmt->execute();
        $geofences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $events = [];
        
        foreach ($geofences as $geofence) {
            $distance = $this->calculateDistance(
                $latitude, $longitude,
                $geofence['latitude'], $geofence['longitude']
            );
            
            if ($distance <= $geofence['radius']) {
                // Check if this is a new entry
                $lastEvent = $this->getLastGeofenceEvent($sessionId, $geofence['id']);
                
                if (!$lastEvent || $lastEvent['event_type'] !== 'enter') {
                    $this->recordGeofenceEvent($sessionId, $geofence['id'], 'enter', $distance);
                    $events[] = [
                        'geofence_id' => $geofence['id'],
                        'name' => $geofence['name'],
                        'type' => $geofence['type'],
                        'event' => 'enter',
                        'distance' => $distance
                    ];
                }
            } else {
                // Check for exit events
                $lastEvent = $this->getLastGeofenceEvent($sessionId, $geofence['id']);
                
                if ($lastEvent && $lastEvent['event_type'] === 'enter') {
                    $this->recordGeofenceEvent($sessionId, $geofence['id'], 'exit', $distance);
                    $events[] = [
                        'geofence_id' => $geofence['id'],
                        'name' => $geofence['name'],
                        'type' => $geofence['type'],
                        'event' => 'exit',
                        'distance' => $distance
                    ];
                }
            }
        }
        
        return $events;
    }
    
    private function getLastGeofenceEvent($sessionId, $geofenceId) {
        $stmt = $this->pdo->prepare("
            SELECT event_type 
            FROM geofence_events 
            WHERE session_id = ? AND geofence_id = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$sessionId, $geofenceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function recordGeofenceEvent($sessionId, $geofenceId, $eventType, $distance) {
        $stmt = $this->pdo->prepare("
            INSERT INTO geofence_events (
                session_id, geofence_id, event_type, distance, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$sessionId, $geofenceId, $eventType, $distance]);
    }
    
    private function updateSessionStatistics($sessionId, $additionalDistance) {
        $stmt = $this->pdo->prepare("
            UPDATE gps_tracking_sessions 
            SET total_distance = COALESCE(total_distance, 0) + ?
            WHERE id = ?
        ");
        
        $stmt->execute([$additionalDistance, $sessionId]);
    }
    
    private function broadcastLocationUpdate($bookingId, $locationData) {
        // Get customer ID for this booking
        $stmt = $this->pdo->prepare("
            SELECT customer_id FROM bookings WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $this->wsServer->sendToUser($booking['customer_id'], [
                'type' => 'location_update',
                'booking_id' => $bookingId,
                'location' => $locationData
            ]);
        }
        
        // Also broadcast to admins
        $this->wsServer->broadcastToRole('admin', [
            'type' => 'professional_location_update',
            'booking_id' => $bookingId,
            'location' => $locationData
        ]);
    }
    
    private function analyzeRouteOptimization($sessionId, $latitude, $longitude) {
        // Get booking destination
        $stmt = $this->pdo->prepare("
            SELECT b.latitude as dest_lat, b.longitude as dest_lon,
                   gts.professional_id
            FROM gps_tracking_sessions gts
            JOIN bookings b ON gts.booking_id = b.id
            WHERE gts.id = ?
        ");
        
        $stmt->execute([$sessionId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $distanceToDestination = $this->calculateDistance(
                $latitude, $longitude,
                $booking['dest_lat'], $booking['dest_lon']
            );
            
            // If professional is moving away from destination (simple heuristic)
            $lastDistanceCheck = $this->getLastDistanceToDestination($sessionId);
            
            if ($lastDistanceCheck && $distanceToDestination > $lastDistanceCheck * 1.2) {
                $this->logTrackingEvent($sessionId, 'route_deviation_detected', [
                    'current_distance' => $distanceToDestination,
                    'last_distance' => $lastDistanceCheck,
                    'deviation_factor' => $distanceToDestination / $lastDistanceCheck
                ]);
            }
            
            $this->updateDistanceToDestination($sessionId, $distanceToDestination);
        }
    }
    
    private function getLastDistanceToDestination($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT distance_to_destination 
            FROM gps_locations 
            WHERE session_id = ? AND distance_to_destination IS NOT NULL
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['distance_to_destination'] : null;
    }
    
    private function updateDistanceToDestination($sessionId, $distance) {
        $stmt = $this->pdo->prepare("
            UPDATE gps_locations 
            SET distance_to_destination = ?
            WHERE session_id = ?
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$distance, $sessionId]);
    }
    
    private function calculateETA($bookingId, $currentLat, $currentLon) {
        // Get booking destination and time
        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude, start_time
            FROM bookings 
            WHERE id = ?
        ");
        
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) return null;
        
        $distance = $this->calculateDistance(
            $currentLat, $currentLon,
            $booking['latitude'], $booking['longitude']
        );
        
        // Simple ETA calculation (assuming 30 km/h average speed)
        $averageSpeed = 30 / 3.6; // m/s
        $estimatedSeconds = $distance / $averageSpeed;
        
        return [
            'distance' => round($distance),
            'estimated_seconds' => round($estimatedSeconds),
            'estimated_arrival' => date('H:i', time() + $estimatedSeconds)
        ];
    }
    
    private function generateSessionSummary($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_points,
                   SUM(distance_from_previous) as total_distance,
                   MIN(timestamp) as start_time,
                   MAX(timestamp) as end_time,
                   AVG(speed) as avg_speed,
                   MAX(speed) as max_speed
            FROM gps_locations 
            WHERE session_id = ?
        ");
        
        $stmt->execute([$sessionId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $duration = strtotime($stats['end_time']) - strtotime($stats['start_time']);
        
        return [
            'total_distance' => round($stats['total_distance'], 2),
            'duration' => $duration,
            'total_points' => $stats['total_points'],
            'avg_speed' => round($stats['avg_speed'], 2),
            'max_speed' => round($stats['max_speed'], 2),
            'start_time' => $stats['start_time'],
            'end_time' => $stats['end_time']
        ];
    }
    
    private function simplifyRoute($locations, $tolerance) {
        if (count($locations) <= 2) return $locations;
        
        // Implement Douglas-Peucker algorithm for route simplification
        return $this->douglasPeucker($locations, $tolerance);
    }
    
    private function douglasPeucker($points, $tolerance) {
        if (count($points) <= 2) return $points;
        
        // Find the point with maximum distance from line
        $maxDistance = 0;
        $maxIndex = 0;
        
        for ($i = 1; $i < count($points) - 1; $i++) {
            $distance = $this->perpendicularDistance(
                $points[$i], $points[0], $points[count($points) - 1]
            );
            
            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $maxIndex = $i;
            }
        }
        
        // If max distance is greater than tolerance, recursively simplify
        if ($maxDistance > $tolerance) {
            $firstHalf = $this->douglasPeucker(
                array_slice($points, 0, $maxIndex + 1), $tolerance
            );
            $secondHalf = $this->douglasPeucker(
                array_slice($points, $maxIndex), $tolerance
            );
            
            // Combine results (remove duplicate middle point)
            return array_merge(array_slice($firstHalf, 0, -1), $secondHalf);
        } else {
            // Return endpoints only
            return [$points[0], $points[count($points) - 1]];
        }
    }
    
    private function perpendicularDistance($point, $lineStart, $lineEnd) {
        $lat = $point['latitude'];
        $lon = $point['longitude'];
        $lat1 = $lineStart['latitude'];
        $lon1 = $lineStart['longitude'];
        $lat2 = $lineEnd['latitude'];
        $lon2 = $lineEnd['longitude'];
        
        // Calculate perpendicular distance from point to line
        $A = $lat - $lat1;
        $B = $lon - $lon1;
        $C = $lat2 - $lat1;
        $D = $lon2 - $lon1;
        
        $dot = $A * $C + $B * $D;
        $lenSq = $C * $C + $D * $D;
        
        if ($lenSq == 0) {
            return sqrt($A * $A + $B * $B);
        }
        
        $param = $dot / $lenSq;
        
        if ($param < 0) {
            return sqrt($A * $A + $B * $B);
        } else if ($param > 1) {
            $A = $lat - $lat2;
            $B = $lon - $lon2;
            return sqrt($A * $A + $B * $B);
        } else {
            $A = $lat - ($lat1 + $param * $C);
            $B = $lon - ($lon1 + $param * $D);
            return sqrt($A * $A + $B * $B);
        }
    }
    
    private function logTrackingEvent($sessionId, $eventType, $data = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gps_tracking_events (
                    session_id, event_type, event_data, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $sessionId,
                $eventType,
                json_encode($data)
            ]);
            
        } catch (Exception $e) {
            error_log("GPS tracking event log failed: " . $e->getMessage());
        }
    }
    
    private function notifyTrackingStarted($bookingId, $sessionId) {
        // Get customer ID
        $stmt = $this->pdo->prepare("
            SELECT customer_id FROM bookings WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $this->wsServer->sendToUser($booking['customer_id'], [
                'type' => 'tracking_started',
                'booking_id' => $bookingId,
                'session_id' => $sessionId,
                'message' => 'O profissional iniciou o trajeto até sua localização'
            ]);
        }
    }
    
    private function notifyTrackingCompleted($bookingId, $summary) {
        $stmt = $this->pdo->prepare("
            SELECT customer_id FROM bookings WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $this->wsServer->sendToUser($booking['customer_id'], [
                'type' => 'tracking_completed',
                'booking_id' => $bookingId,
                'summary' => $summary,
                'message' => 'O profissional chegou ao destino'
            ]);
        }
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $gpsTracking = new GPSTrackingSystem();
    $action = $_POST['action'] ?? '';
    
    // Validate user session
    session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'start_tracking':
            $bookingId = $_POST['booking_id'] ?? '';
            echo json_encode($gpsTracking->startTracking($userId, $bookingId));
            break;
            
        case 'update_location':
            $sessionId = $_POST['session_id'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $accuracy = $_POST['accuracy'] ?? null;
            $timestamp = $_POST['timestamp'] ?? null;
            
            echo json_encode($gpsTracking->updateLocation(
                $sessionId, $latitude, $longitude, $accuracy, $timestamp
            ));
            break;
            
        case 'end_tracking':
            $sessionId = $_POST['session_id'] ?? '';
            $reason = $_POST['reason'] ?? 'completed';
            
            echo json_encode($gpsTracking->endTracking($sessionId, $reason));
            break;
            
        case 'get_current_location':
            $bookingId = $_POST['booking_id'] ?? '';
            echo json_encode($gpsTracking->getCurrentLocation($bookingId));
            break;
            
        case 'get_tracking_history':
            $sessionId = $_POST['session_id'] ?? '';
            $simplify = $_POST['simplify'] ?? true;
            
            echo json_encode($gpsTracking->getTrackingHistory($sessionId, $simplify));
            break;
            
        case 'create_geofence':
            if ($_SESSION['user_type'] !== 'admin') {
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                exit;
            }
            
            $name = $_POST['name'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $radius = $_POST['radius'] ?? 100;
            $type = $_POST['type'] ?? 'customer_location';
            
            echo json_encode($gpsTracking->createGeofence($name, $latitude, $longitude, $radius, $type));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
