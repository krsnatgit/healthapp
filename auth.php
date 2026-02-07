<?php
// api/auth.php - Authentication endpoints

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['action']) ? $_GET['action'] : '';

switch($request) {
    case 'register':
        if ($method === 'POST') {
            register();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'login':
        if ($method === 'POST') {
            login();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'logout':
        if ($method === 'POST') {
            logout();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    case 'verify':
        if ($method === 'GET') {
            verifySession();
        } else {
            sendResponse(false, 'Method not allowed');
        }
        break;
    
    default:
        sendResponse(false, 'Invalid action');
}

// Register new user
function register() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($data['username']) || !isset($data['password']) || !isset($data['character'])) {
        sendResponse(false, 'Missing required fields');
    }
    
    $username = trim($data['username']);
    $password = $data['password'];
    $character = $data['character'];
    $email = isset($data['email']) ? trim($data['email']) : null;
    
    // Validation
    if (strlen($username) < 3) {
        sendResponse(false, 'Username must be at least 3 characters long');
    }
    
    if (strlen($password) < 6) {
        sendResponse(false, 'Password must be at least 6 characters long');
    }
    
    if (!in_array($character, ['warrior', 'mage', 'ranger', 'monk'])) {
        sendResponse(false, 'Invalid character class');
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }
    
    try {
        $conn = getDBConnection();
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            sendResponse(false, 'Username already taken');
        }
        
        // Check if email exists (if provided)
        if ($email) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                sendResponse(false, 'Email already registered');
            }
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password_hash, character_class) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $character]);
        
        $userId = $conn->lastInsertId();
        
        // Create session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $conn->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionToken, $expiresAt]);
        
        // Get user data
        $stmt = $conn->prepare("
            SELECT user_id, username, email, character_class, level, xp, 
                   total_activities, streak_days, created_at 
            FROM users WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        sendResponse(true, 'Registration successful', [
            'token' => $sessionToken,
            'user' => $user
        ]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Registration failed: ' . $e->getMessage());
    }
}

// Login user
function login() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendResponse(false, 'Missing username or password');
    }
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    try {
        $conn = getDBConnection();
        
        // Get user
        $stmt = $conn->prepare("
            SELECT user_id, username, email, password_hash, character_class, 
                   level, xp, total_activities, streak_days, last_activity_date, 
                   created_at, is_active 
            FROM users WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendResponse(false, 'Invalid username or password');
        }
        
        if (!$user['is_active']) {
            sendResponse(false, 'Account is disabled');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            sendResponse(false, 'Invalid username or password');
        }
        
        // Remove password hash from response
        unset($user['password_hash']);
        
        // Create session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Delete old sessions (optional: keep last 5)
        $stmt = $conn->prepare("
            DELETE FROM user_sessions 
            WHERE user_id = ? AND session_id NOT IN (
                SELECT session_id FROM (
                    SELECT session_id FROM user_sessions 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ) AS recent_sessions
            )
        ");
        $stmt->execute([$user['user_id'], $user['user_id']]);
        
        // Insert new session
        $stmt = $conn->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['user_id'], $sessionToken, $expiresAt]);
        
        sendResponse(true, 'Login successful', [
            'token' => $sessionToken,
            'user' => $user
        ]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Login failed: ' . $e->getMessage());
    }
}

// Logout user
function logout() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        sendResponse(false, 'No session token provided');
    }
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$token]);
        
        sendResponse(true, 'Logout successful');
        
    } catch(PDOException $e) {
        sendResponse(false, 'Logout failed: ' . $e->getMessage());
    }
}

// Verify session
function verifySession() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        sendResponse(false, 'No session token provided');
    }
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT s.session_id, s.expires_at, u.user_id, u.username, u.email, 
                   u.character_class, u.level, u.xp, u.total_activities, 
                   u.streak_days, u.last_activity_date, u.is_active
            FROM user_sessions s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.session_token = ?
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        
        if (!$session) {
            sendResponse(false, 'Invalid session token');
        }
        
        if (!$session['is_active']) {
            sendResponse(false, 'Account is disabled');
        }
        
        // Check if session expired
        if (strtotime($session['expires_at']) < time()) {
            // Delete expired session
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$token]);
            sendResponse(false, 'Session expired');
        }
        
        unset($session['session_id']);
        unset($session['expires_at']);
        unset($session['is_active']);
        
        sendResponse(true, 'Session valid', ['user' => $session]);
        
    } catch(PDOException $e) {
        sendResponse(false, 'Verification failed: ' . $e->getMessage());
    }
}
?>