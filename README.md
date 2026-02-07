# FitQuest - Gamified Health Tracking Application

A gamified health and fitness tracking application with MySQL backend for user management, activity tracking, and BMI monitoring.

## Features

### User Management
- Secure user registration and authentication
- Character class selection (Warrior, Mage, Ranger, Monk)
- Session-based token authentication
- Multi-user support with isolated data

### Game Mechanics
- Level and XP progression system
- Activity-based XP rewards
- Streak tracking for consecutive days
- Achievement system (database ready)
- Character customization

### Health Tracking
- BMI calculator with categorization
- Weight and height tracking
- Goal setting and progress monitoring
- Historical health data storage

### Activity Logging
- Multiple activity types (Running, Walking, Cycling, Swimming, Gym, Yoga, Sports)
- Duration and calories burned tracking
- Personal notes for each activity
- Activity history with timestamps
- XP rewards per activity

## Technology Stack

### Frontend
- HTML5, CSS3, JavaScript (Vanilla)
- Press Start 2P font (retro gaming theme)
- Nunito font (body text)
- Responsive design

### Backend
- PHP 7.4+ with PDO
- MySQL 5.7+ / MariaDB 10.2+
- RESTful API architecture
- Token-based authentication

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)
- Modern web browser

### Database Setup

1. Create the database:
```bash
mysql -u root -p < database/setup.sql
```

Or manually execute the SQL commands in `database/setup.sql`

2. Update database credentials in `api/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fitquest_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Web Server Setup

#### Apache
1. Place files in your web root (e.g., `/var/www/html/fitquest/`)
2. Ensure mod_rewrite is enabled
3. Create `.htaccess` file if needed for API routing

#### Nginx
Add this to your server block:
```nginx
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}
```

### File Structure
```
fitquest/
├── api/
│   ├── config.php          # Database configuration
│   ├── auth.php            # Authentication endpoints
│   └── user_data.php       # User data management
├── database/
│   └── setup.sql           # Database schema
├── fitquest-login.html     # Login/signup page
└── fitquest-game.html      # Main application
```

### API Configuration

Update the API base URL in both HTML files:

**fitquest-login.html** (line ~308):
```javascript
const API_BASE = 'api'; // Change to your API path
```

**fitquest-game.html** (line ~644):
```javascript
const API_BASE = 'api'; // Change to your API path
```

For production, use full URL:
```javascript
const API_BASE = 'https://yourdomain.com/api';
```

## API Endpoints

### Authentication (`api/auth.php`)

#### Register
```
POST /api/auth.php?action=register
Body: {
  "username": "string",
  "password": "string",
  "character": "warrior|mage|ranger|monk",
  "email": "string" (optional)
}
```

#### Login
```
POST /api/auth.php?action=login
Body: {
  "username": "string",
  "password": "string"
}
```

#### Logout
```
POST /api/auth.php?action=logout
Headers: Authorization: Bearer {token}
```

#### Verify Session
```
GET /api/auth.php?action=verify
Headers: Authorization: Bearer {token}
```

### User Data (`api/user_data.php`)

All endpoints require: `Authorization: Bearer {token}`

#### Update User Stats
```
POST /api/user_data.php?action=update_stats
Body: {
  "level": int,
  "xp": int,
  "total_activities": int,
  "streak_days": int,
  "last_activity_date": "datetime"
}
```

#### Save Health Data
```
POST /api/user_data.php?action=save_health
Body: {
  "weight": float,
  "height": float,
  "bmi": float,
  "target_weight": float,
  "start_weight": float
}
```

#### Get Health Data
```
GET /api/user_data.php?action=get_health
```

#### Add Activity
```
POST /api/user_data.php?action=add_activity
Body: {
  "activity_type": "string",
  "duration": int,
  "calories": int,
  "notes": "string",
  "xp_earned": int,
  "activity_date": "datetime"
}
```

#### Get Activities
```
GET /api/user_data.php?action=get_activities&limit=50&offset=0
```

#### Delete Activity
```
DELETE /api/user_data.php?action=delete_activity&id={activity_id}
```

## Database Schema

### Tables
- `users` - User accounts and game stats
- `user_health_data` - BMI and weight tracking
- `activities` - Activity logs
- `user_sessions` - Authentication tokens
- `achievements` - Available achievements
- `user_achievements` - Earned achievements

See `database/setup.sql` for complete schema.

## Security Considerations

1. **Passwords**: Hashed using PHP's `password_hash()` with bcrypt
2. **Sessions**: 30-day expiration, token-based authentication
3. **SQL Injection**: Protected via PDO prepared statements
4. **CORS**: Configure appropriately for production
5. **HTTPS**: Always use HTTPS in production

## Future Enhancements

The database is ready for:
- Achievement system implementation
- Leaderboards and social features
- Activity analytics and charts
- Nutrition tracking
- Workout plans
- Friend system
- Challenges and competitions

## Troubleshooting

### Database Connection Failed
- Check database credentials in `api/config.php`
- Ensure MySQL service is running
- Verify database user has proper permissions

### API Not Working
- Check file permissions (PHP files should be readable)
- Verify web server configuration
- Check browser console for errors
- Ensure CORS headers are properly set

### Session Expired
- Tokens expire after 30 days
- User needs to log in again
- Old sessions are automatically cleaned up

## License

This project is provided as-is for educational and personal use.

## Credits

- Fonts: Press Start 2P, Nunito (Google Fonts)
- Design: Retro gaming aesthetic
- Icons: Emoji-based

## Support

For issues or questions, please check:
1. Database connection settings
2. API endpoint URLs
3. Browser console for errors
4. PHP error logs

---

**Start your FitQuest journey today! 🎮⚔️**
