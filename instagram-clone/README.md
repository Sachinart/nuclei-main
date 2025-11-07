# Instagram Clone - Full-Featured Social Media Platform

A complete, feature-rich Instagram clone built with pure PHP (no frameworks) and MySQL. This application replicates all major Instagram features including posts, stories, reels, messaging, and an advanced feed algorithm.

## Features

### Core Functionality
- **User Authentication**: Secure registration, login, and session management
- **User Profiles**: Customizable profiles with bio, profile picture, and statistics
- **Posts**: Create multi-media posts with images/videos, captions, locations, and hashtags
- **Stories**: 24-hour temporary content with view tracking
- **Reels**: Short-form vertical video content
- **Feed**: Personalized feed with Instagram-like ranking algorithm
- **Explore**: Discover new content and trending hashtags
- **Search**: Real-time user search functionality

### Social Features
- **Follow/Unfollow**: Build your network
- **Likes & Comments**: Engage with content
- **Saved Posts**: Bookmark content for later
- **Mentions**: Tag users in posts and comments
- **Hashtags**: Categorize and discover content
- **Notifications**: Real-time activity updates

### Messaging
- **Direct Messages**: One-on-one conversations
- **Group Chats**: Multiple participants
- **Message Reactions**: React to messages with emojis
- **Read Receipts**: Track message status
- **Media Sharing**: Send photos, videos, posts, and reels

### Advanced Features
- **Feed Algorithm**: Personalized content ranking based on:
  - Recency (when posted)
  - Engagement (likes, comments, saves)
  - Relationship strength (interaction history)
  - User interests (derived from activity)
- **Activity Tracking**: User behavior analytics for algorithm optimization
- **User Interests**: Auto-generated based on interaction patterns
- **Suggested Users**: Smart recommendations based on mutual follows and interests
- **Trending Content**: Popular posts and hashtags
- **Account Privacy**: Public/private account settings
- **User Blocking**: Block unwanted users
- **Content Reporting**: Report inappropriate content

## Technical Specifications

### Technology Stack
- **Backend**: Pure PHP 7.4+ (no frameworks)
- **Database**: MySQL 5.7+ with InnoDB engine
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Architecture**: MVC-inspired class-based structure
- **Security**: PDO prepared statements, password hashing, session management

### Code Statistics
- **Total Lines**: ~15,000 lines of code
- **PHP Files**: 30+ files
- **Database Tables**: 30+ tables with proper indexing
- **API Endpoints**: 10+ RESTful endpoints

### Database Schema
The application uses a comprehensive database schema with:
- Users and authentication
- Posts and media
- Stories with expiration
- Reels with view tracking
- Conversations and messages
- Notifications
- Follow relationships
- Activity tracking
- Hashtags and mentions
- Content moderation

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- GD Library (for image processing)
- mod_rewrite enabled (Apache)

### Setup Instructions

1. **Clone or Download the Repository**
   ```bash
   cd /path/to/your/webroot
   cp -r instagram-clone/ ./
   ```

2. **Create Database**
   ```bash
   mysql -u root -p
   ```
   ```sql
   CREATE DATABASE instagram_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

3. **Import Database Schema**
   ```bash
   mysql -u root -p instagram_clone < database.sql
   ```

4. **Configure Application**
   Edit `config.php` and update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'instagram_clone');
   ```

5. **Set Permissions**
   ```bash
   chmod 755 instagram-clone/
   chmod 777 instagram-clone/uploads/
   chmod 777 instagram-clone/uploads/posts/
   chmod 777 instagram-clone/uploads/profiles/
   chmod 777 instagram-clone/uploads/stories/
   chmod 777 instagram-clone/uploads/reels/
   chmod 777 instagram-clone/uploads/messages/
   chmod 777 instagram-clone/uploads/thumbnails/
   ```

6. **Configure Virtual Host (Optional)**

   For Apache, create a virtual host:
   ```apache
   <VirtualHost *:80>
       ServerName instagram.local
       DocumentRoot "/path/to/instagram-clone"
       <Directory "/path/to/instagram-clone">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

7. **Access the Application**
   - Open your browser and navigate to `http://localhost/instagram-clone`
   - Or if using virtual host: `http://instagram.local`

8. **Create Your First Account**
   - Click "Sign up"
   - Fill in the registration form
   - Start using the platform!

## Project Structure

```
instagram-clone/
├── classes/              # Core PHP classes
│   ├── Database.php      # Database connection singleton
│   ├── User.php          # User management and authentication
│   ├── Post.php          # Posts and interactions
│   ├── Feed.php          # Feed algorithm
│   ├── Follow.php        # Follow relationships
│   ├── Story.php         # Stories functionality
│   ├── Reel.php          # Reels functionality
│   ├── Message.php       # Messaging system
│   ├── Notification.php  # Notifications
│   └── FileUpload.php    # File handling
├── api/                  # API endpoints
│   ├── post-actions.php  # Like, save actions
│   ├── comment.php       # Comment handling
│   ├── follow.php        # Follow/unfollow
│   ├── search.php        # User search
│   ├── suggestions.php   # User suggestions
│   ├── feed.php          # Feed data
│   ├── reel-actions.php  # Reel interactions
│   └── update-last-seen.php
├── assets/
│   ├── css/
│   │   ├── style.css     # Main stylesheet
│   │   └── auth.css      # Authentication pages
│   └── js/
│       └── main.js       # Core JavaScript
├── uploads/              # User uploaded content
│   ├── posts/
│   ├── profiles/
│   ├── stories/
│   ├── reels/
│   ├── messages/
│   └── thumbnails/
├── config.php            # Configuration
├── database.sql          # Database schema
├── index.php             # Home feed
├── login.php             # Login page
├── register.php          # Registration
├── logout.php            # Logout handler
├── explore.php           # Explore page
├── reels.php             # Reels page
└── README.md             # This file
```

## Usage

### Creating Posts
1. Click the "+" icon in the navigation
2. Select one or more images/videos
3. Add a caption, location, and hashtags
4. Click "Share"

### Viewing Stories
1. Stories appear at the top of the feed
2. Click on a user's story to view
3. Tap left/right to navigate between stories
4. Swipe up/down to switch between users

### Watching Reels
1. Click the Reels icon in navigation
2. Scroll vertically to browse reels
3. Tap to pause/play
4. Like, comment, or save reels

### Messaging
1. Click the message icon in navigation
2. Select or search for a user
3. Type your message and press Enter
4. Share posts, reels, or media directly

### Exploring Content
1. Click the compass icon in navigation
2. Browse trending hashtags
3. Discover posts from new users
4. Click any post to view details

## Algorithm Details

### Feed Ranking Algorithm
The feed uses a weighted scoring system:
- **Recency (30%)**: Newer posts score higher
- **Engagement (30%)**: Likes + (Comments × 2) + (Saves × 3)
- **Relationship (25%)**: Based on follow status and interaction history
- **Interest (15%)**: Matches user's inferred interests

### Interest Inference
User interests are automatically calculated from:
- Hashtags in liked posts
- Accounts frequently interacted with
- Search history
- Time spent viewing content

### Suggested Users
Recommendations based on:
- Mutual follows (friends of friends)
- Common interests (similar hashtag interactions)
- Popular accounts in your interest areas
- Geographic location (if provided)

## Performance Optimization

### Database Optimization
- Strategic indexes on frequently queried columns
- Composite indexes for complex queries
- Optimized JOIN operations
- Query result caching where appropriate

### File Handling
- Automatic thumbnail generation for images
- Image compression to reduce file size
- Lazy loading for media content
- CDN-ready structure (UPLOAD_URL constant)

### Frontend Optimization
- Infinite scroll pagination
- AJAX requests for dynamic content
- Debounced search input
- Optimized CSS and JavaScript

## Security Features

### Authentication
- Bcrypt password hashing
- Secure session management
- Session token validation
- CSRF protection ready

### Data Protection
- PDO prepared statements (SQL injection prevention)
- XSS protection through htmlspecialchars
- File upload validation
- File type verification

### Privacy
- Private account option
- User blocking
- Content reporting
- Activity logging for security audits

## API Documentation

### POST /api/post-actions.php
```json
{
  "action": "like|unlike|save|unsave",
  "post_id": 123
}
```

### POST /api/comment.php
```json
{
  "post_id": 123,
  "comment_text": "Nice post!",
  "parent_comment_id": null
}
```

### POST /api/follow.php
```json
{
  "action": "follow|unfollow",
  "user_id": 456
}
```

### POST /api/search.php
```json
{
  "query": "username"
}
```

### GET /api/feed.php
```
?offset=0&limit=10
```

## Troubleshooting

### File Upload Issues
- Ensure upload directories have write permissions (777)
- Check PHP upload_max_filesize and post_max_size in php.ini
- Verify GD library is installed: `php -m | grep gd`

### Database Connection Errors
- Verify MySQL service is running
- Check database credentials in config.php
- Ensure database user has proper privileges

### Session Issues
- Check session.save_path in php.ini
- Ensure cookies are enabled in browser
- Clear browser cache and cookies

### Performance Issues
- Enable MySQL query caching
- Implement Redis/Memcached for session storage
- Use a CDN for static assets
- Enable Gzip compression

## Future Enhancements

Potential features to add:
- Live video streaming
- Video calls in messages
- Multiple photo/video editing
- Filters and effects
- Archive posts
- Close friends list
- Shopping/marketplace features
- Analytics dashboard
- Two-factor authentication
- Email verification
- Password reset functionality
- Mobile responsive improvements
- Progressive Web App (PWA)

## Contributing

This is an educational project demonstrating a full-stack social media platform. Feel free to:
- Report bugs
- Suggest features
- Submit pull requests
- Use as learning material

## License

This project is created for educational purposes. Feel free to use and modify as needed.

## Credits

Built with pure PHP and MySQL - no frameworks, no dependencies, just clean code demonstrating modern web development practices.

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review the code comments
3. Check database schema documentation
4. Test with default configuration first

---

**Note**: This is a demonstration project. For production use, consider adding:
- Rate limiting
- Advanced caching
- CDN integration
- Email services
- Image optimization services
- Video encoding services
- Monitoring and logging
- Backup systems
- Load balancing
