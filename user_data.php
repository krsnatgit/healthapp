<?php
// api/user_data.php - User data management endpoints

require_once 'config.php';

// Verify authentication
function verifyAuth() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        sendResponse(false, 'Unauthorized');
    }
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT s.user_id, u.is_active
            FROM user_sessions s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.session_token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        
        if (!$session || !$session['is_active']) {
            sendResponse(false, 'Unauthorized');
        }
        
        return $session['user_id'];
        
    } catch(PDOException $e) {
        sendResponse(false, 'Authentication failed');
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['action']) ? $_GET['action'] : '';

switch($request) {
    case 'update_stats':
        if ($method === 'POST') {
            updateUserStats();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'save_health':
        if ($method === 'POST') {
            saveHealthData();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'get_health':
        if ($method === 'GET') {
            getHealthData();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'add_activity':
        if ($method === 'POST') {
            addActivity();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'get_activities':
        if ($method === 'GET') {
            getActivities();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'delete_activity':
        if ($method === 'DELETE') {
            deleteActivity();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    default:
        sendResponse(false, 'Invalid action');
}

// Update user stats (level, xp, streak, etc.)
function updateUserStats() {
    $userId = verifyAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $conn = getDBConnection();
        
        $updates = [];
        $params = [];
        
        if (isset($data['level'])) {
            $updates[] = "level = ?";
            $params[] = $data['level'];
        }
        if (isset($data['xp'])) {
            $updates[] = "xp = ?";
            $params[] = $data['xp'];
        }
        if (isset($data['total_activities'])) {
            $updates[] = "total_activities = ?";
            $params[] = $data['total_activities'];
        }
        if (isset($data['streak_days'])) {
            $updates[] = "streak_days = ?";
            $params[] = $data['streak_days'];
        }
        if (isset($data['last_activity_date'])) {
            $updates[] = "last_activity_date = ?";
            $params[] = $data['last_activity_date'];
        }
        
        if (empty($updates)) {
            sendResponse(false, 'No data to update');
        }
        
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        sendResponse(true, 'Stats updated successfully');
        
    } catch(PDOException $e) {
        sendResponse(false, 'Update failed: ' . $e->getMessage());
    }
}

// Save health data
function saveHealthData() {
    $userId = verifyAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['weight']) || !isset($data['height'])) {
        sendResponse(false, 'Weight and height are required');
    }
    
    try {
        $conn = getDBConnection();
        
        $weight = $data['weight'];
        $height = $data['height'];
        $bmi = isset($data['bmi']) ? $data['bmi'] : null;
        $targetWeight = isset($data['target_weight']) ? $data['target_weight'] : null;
        $startWeight = isset($data['start_weight']) ? $data['start_weight'] : null;
        
        $stmt = $conn->prepare("
            INSERT INTO user_health_data 
            (user_id, weight, height, bmi, target_weight, start_weight) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $weight, $height, $bmi, $targetWeight, $startWeight]);
        
        sendResponse(true, 'Health data saved successfully', [
            'health_id' => $conn->lastInsertId()
        ]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Save failed: ' . $e->getMessage());
    }
}

// Get latest health data
function getHealthData() {
    $userId = verifyAuth();
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT health_id, weight, height, bmi, target_weight, start_weight, recorded_at
            FROM user_health_data
            WHERE user_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $healthData = $stmt->fetch();
        
        if (!$healthData) {
            sendResponse(true, 'No health data found', ['health_data' => null]);
        }
        
        sendResponse(true, 'Health data retrieved', ['health_data' => $healthData]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Retrieval failed: ' . $e->getMessage());
    }
}

// Add activity
function addActivity() {
    $userId = verifyAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['activity_type']) || !isset($data['duration']) || !isset($data['xp_earned'])) {
        sendResponse(false, 'Missing required fields');
    }
    
    try {
        $conn = getDBConnection();
        
        $activityType = $data['activity_type'];
        $duration = $data['duration'];
        $calories = isset($data['calories']) ? $data['calories'] : 0;
        $notes = isset($data['notes']) ? $data['notes'] : null;
        $xpEarned = $data['xp_earned'];
        $activityDate = isset($data['activity_date']) ? $data['activity_date'] : date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            INSERT INTO activities 
            (user_id, activity_type, duration_minutes, calories_burned, notes, xp_earned, activity_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $activityType, $duration, $calories, $notes, $xpEarned, $activityDate]);
        
        $activityId = $conn->lastInsertId();
        
        // Update user stats
        $stmt = $conn->prepare("
            UPDATE users 
            SET total_activities = total_activities + 1,
                last_activity_date = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$activityDate, $userId]);
        
        sendResponse(true, 'Activity logged successfully', [
            'activity_id' => $activityId
        ]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Failed to log activity: ' . $e->getMessage());
    }
}

// Get activities
function getActivities() {
    $userId = verifyAuth();
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT activity_id, activity_type, duration_minutes, calories_burned, 
                   notes, xp_earned, activity_date, created_at
            FROM activities
            WHERE user_id = ?
            ORDER BY activity_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $activities = $stmt->fetchAll();
        
        // Get total count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM activities WHERE user_id = ?");
        $stmt->execute([$userId]);
        $total = $stmt->fetch()['total'];
        
        sendResponse(true, 'Activities retrieved', [
            'activities' => $activities,
            'total' => $total
        ]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Retrieval failed: ' . $e->getMessage());
    }
}

// Delete activity
function deleteActivity() {
    $userId = verifyAuth();
    
    $activityId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$activityId) {
        sendResponse(false, 'Activity ID is required');
    }
    
    try {
        $conn = getDBConnection();
        
        // Verify activity belongs to user
        $stmt = $conn->prepare("SELECT user_id FROM activities WHERE activity_id = ?");
        $stmt->execute([$activityId]);
        $activity = $stmt->fetch();
        
        if (!$activity || $activity['user_id'] != $userId) {
            sendResponse(false, 'Activity not found or unauthorized');
        }
        
        // Delete activity
        $stmt = $conn->prepare("DELETE FROM activities WHERE activity_id = ?");
        $stmt->execute([$activityId]);
        
        // Update total activities count
        $stmt = $conn->prepare("
            UPDATE users 
            SET total_activities = GREATEST(0, total_activities - 1)
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        sendResponse(true, 'Activity deleted successfully');
        
    } catch(PDOException $e) {
        sendResponse(false, 'Delete failed: ' . $e->getMessage());
    }
}
?>