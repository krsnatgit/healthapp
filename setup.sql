-- FitQuest Database Setup
-- Run this script to create the database and tables

CREATE DATABASE IF NOT EXISTS fitquest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE fitquest_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    character_class ENUM('warrior', 'mage', 'ranger', 'monk') NOT NULL,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    total_activities INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_activity_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User health data table
CREATE TABLE IF NOT EXISTS user_health_data (
    health_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    target_weight DECIMAL(5,2),
    start_weight DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activities table
CREATE TABLE IF NOT EXISTS activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    duration_minutes INT NOT NULL,
    calories_burned INT DEFAULT 0,
    notes TEXT,
    xp_earned INT NOT NULL,
    activity_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_date (activity_date),
    INDEX idx_activity_type (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions table (for token-based authentication)
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Achievements table (for future expansion)
CREATE TABLE IF NOT EXISTS achievements (
    achievement_id INT AUTO_INCREMENT PRIMARY KEY,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    icon VARCHAR(50),
    xp_reward INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements table (tracks which users have which achievements)
CREATE TABLE IF NOT EXISTS user_achievements (
    user_achievement_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(achievement_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default achievements
INSERT INTO achievements (achievement_name, achievement_description, icon, xp_reward) VALUES
('First Steps', 'Complete your first activity', '🎯', 50),
('Week Warrior', 'Maintain a 7-day streak', '🔥', 100),
('Century Club', 'Log 100 activities', '💯', 200),
('Marathon Master', 'Complete an activity over 60 minutes', '🏃', 75),
('Early Bird', 'Log an activity before 6 AM', '🌅', 50),
('Night Owl', 'Log an activity after 10 PM', '🦉', 50),
('Consistency King', 'Maintain a 30-day streak', '👑', 300),
('Level 10', 'Reach level 10', '⭐', 150),
('Health Guru', 'Track your BMI for 30 days', '📊', 100),
('Goal Getter', 'Reach your target weight', '🎯', 500);
