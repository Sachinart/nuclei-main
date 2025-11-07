/**
 * Instagram Clone - Main JavaScript
 * Handles all client-side interactions and AJAX requests
 */

// Global variables
let currentOffset = 10;
const postsPerLoad = 10;

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    loadSuggestions();
    updateLastSeen();

    // Update last seen every minute
    setInterval(updateLastSeen, 60000);
});

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');

    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.remove('active');
            return;
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-search')) {
            searchResults.classList.remove('active');
        }
    });
}

/**
 * Perform user search
 */
function performSearch(query) {
    fetch('api/search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySearchResults(data.users);
        }
    })
    .catch(error => console.error('Search error:', error));
}

/**
 * Display search results
 */
function displaySearchResults(users) {
    const searchResults = document.getElementById('search-results');

    if (users.length === 0) {
        searchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: #8e8e8e;">No users found</div>';
        searchResults.classList.add('active');
        return;
    }

    let html = '';
    users.forEach(user => {
        html += `
            <a href="profile.php?username=${user.username}" class="search-result-item">
                <img src="uploads/${user.profile_picture}" alt="${user.username}">
                <div>
                    <div class="username">${user.username} ${user.is_verified ? '<span class="verified">✓</span>' : ''}</div>
                    <div style="color: #8e8e8e; font-size: 12px;">${user.full_name}</div>
                </div>
            </a>
        `;
    });

    searchResults.innerHTML = html;
    searchResults.classList.add('active');
}

/**
 * Load suggested users
 */
function loadSuggestions() {
    const suggestionsList = document.getElementById('suggestions-list');
    if (!suggestionsList) return;

    fetch('api/suggestions.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.users.length > 0) {
            displaySuggestions(data.users);
        }
    })
    .catch(error => console.error('Suggestions error:', error));
}

/**
 * Display suggested users
 */
function displaySuggestions(users) {
    const suggestionsList = document.getElementById('suggestions-list');

    let html = '';
    users.slice(0, 5).forEach(user => {
        html += `
            <div class="suggestion-item">
                <a href="profile.php?username=${user.username}">
                    <img src="uploads/${user.profile_picture}" alt="${user.username}">
                </a>
                <div class="suggestion-info">
                    <a href="profile.php?username=${user.username}" class="username">${user.username}</a>
                    <div class="meta">${user.followers_count} followers</div>
                </div>
                <button class="btn-follow" onclick="followUser(${user.user_id}, this)">Follow</button>
            </div>
        `;
    });

    suggestionsList.innerHTML = html;
}

/**
 * Toggle like on post
 */
function toggleLike(postId, button) {
    const isLiked = button.classList.contains('liked');
    const action = isLiked ? 'unlike' : 'like';

    fetch('api/post-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: action,
            post_id: postId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('liked');

            // Update likes count
            const postCard = button.closest('.post-card');
            const likesCount = postCard.querySelector('.likes-count');
            if (likesCount) {
                let count = parseInt(likesCount.textContent);
                likesCount.textContent = isLiked ? count - 1 : count + 1;
            }
        }
    })
    .catch(error => console.error('Like error:', error));
}

/**
 * Toggle save on post
 */
function toggleSave(postId, button) {
    const isSaved = button.classList.contains('saved');
    const action = isSaved ? 'unsave' : 'save';

    fetch('api/post-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: action,
            post_id: postId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('saved');
        }
    })
    .catch(error => console.error('Save error:', error));
}

/**
 * Post comment
 */
function postComment(postId) {
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    const input = postCard.querySelector('.add-comment input');
    const commentText = input.value.trim();

    if (!commentText) return;

    fetch('api/comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            post_id: postId,
            comment_text: commentText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';

            // Update comments count
            const commentsCount = postCard.querySelector('.view-comments');
            if (commentsCount) {
                let count = parseInt(commentsCount.textContent.match(/\d+/)[0]) || 0;
                commentsCount.innerHTML = `<a href="post.php?id=${postId}">View all ${count + 1} comments</a>`;
            } else {
                const viewComments = document.createElement('div');
                viewComments.className = 'view-comments';
                viewComments.innerHTML = `<a href="post.php?id=${postId}">View 1 comment</a>`;
                postCard.querySelector('.post-caption').after(viewComments);
            }
        }
    })
    .catch(error => console.error('Comment error:', error));
}

/**
 * Handle comment keypress (Enter to post)
 */
function handleCommentKeypress(event, postId) {
    if (event.key === 'Enter') {
        event.preventDefault();
        postComment(postId);
    }
}

/**
 * Focus comment input
 */
function focusComment(postId) {
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    const input = postCard.querySelector('.add-comment input');
    input.focus();
}

/**
 * Slide media in post carousel
 */
function slideMedia(postId, direction) {
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    const slider = postCard.querySelector('.media-slider');
    const items = slider.querySelectorAll('.media-item');
    let current = parseInt(slider.dataset.current);

    items[current].classList.remove('active');

    current += direction;
    if (current < 0) current = items.length - 1;
    if (current >= items.length) current = 0;

    items[current].classList.add('active');
    slider.dataset.current = current;
}

/**
 * Follow user
 */
function followUser(userId, button) {
    fetch('api/follow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'follow',
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Following';
            button.style.color = '#8e8e8e';
            button.onclick = () => unfollowUser(userId, button);
        }
    })
    .catch(error => console.error('Follow error:', error));
}

/**
 * Unfollow user
 */
function unfollowUser(userId, button) {
    fetch('api/follow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'unfollow',
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Follow';
            button.style.color = '#0095f6';
            button.onclick = () => followUser(userId, button);
        }
    })
    .catch(error => console.error('Unfollow error:', error));
}

/**
 * Load more posts
 */
function loadMorePosts() {
    fetch(`api/feed.php?offset=${currentOffset}&limit=${postsPerLoad}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.posts.length > 0) {
            appendPosts(data.posts);
            currentOffset += postsPerLoad;
        } else {
            document.querySelector('.load-more button').textContent = 'No more posts';
            document.querySelector('.load-more button').disabled = true;
        }
    })
    .catch(error => console.error('Load more error:', error));
}

/**
 * Append posts to feed
 */
function appendPosts(posts) {
    const feedContainer = document.querySelector('.feed-container');
    const loadMore = document.querySelector('.load-more');

    posts.forEach(post => {
        const postElement = createPostElement(post);
        feedContainer.insertBefore(postElement, loadMore);
    });
}

/**
 * Create post element
 */
function createPostElement(post) {
    const div = document.createElement('div');
    div.className = 'post-card';
    div.dataset.postId = post.post_id;

    let mediaHtml = '';
    if (post.media.length > 0) {
        const media = post.media[0];
        if (media.media_type === 'image') {
            mediaHtml = `<img src="uploads/${media.media_url}" alt="Post image">`;
        } else {
            mediaHtml = `<video src="uploads/${media.media_url}" controls></video>`;
        }
    }

    div.innerHTML = `
        <div class="post-header">
            <img src="uploads/${post.profile_picture}" alt="${post.username}" class="profile-pic">
            <div class="post-info">
                <a href="profile.php?username=${post.username}" class="username">
                    ${post.username}${post.is_verified ? '<span class="verified">✓</span>' : ''}
                </a>
            </div>
            <button class="post-options">⋯</button>
        </div>
        <div class="post-media">${mediaHtml}</div>
        <div class="post-actions">
            <div class="action-buttons">
                <button class="btn-like ${post.user_liked ? 'liked' : ''}" onclick="toggleLike(${post.post_id}, this)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="${post.user_liked ? '#ed4956' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                </button>
                <button class="btn-comment" onclick="focusComment(${post.post_id})">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                </button>
            </div>
            <button class="btn-save ${post.user_saved ? 'saved' : ''}" onclick="toggleSave(${post.post_id}, this)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="${post.user_saved ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
            </button>
        </div>
        <div class="post-likes">
            <span class="likes-count">${post.likes_count}</span> likes
        </div>
        ${post.caption ? `
        <div class="post-caption">
            <a href="profile.php?username=${post.username}" class="username">${post.username}</a>
            <span class="caption-text">${post.caption}</span>
        </div>` : ''}
        <div class="add-comment">
            <input type="text" placeholder="Add a comment..." onkeypress="handleCommentKeypress(event, ${post.post_id})">
            <button onclick="postComment(${post.post_id})">Post</button>
        </div>
    `;

    return div;
}

/**
 * View story
 */
function viewStory(userId) {
    window.location.href = `story.php?user_id=${userId}`;
}

/**
 * Open story upload
 */
function openStoryUpload() {
    window.location.href = 'create-story.php';
}

/**
 * Update user's last seen
 */
function updateLastSeen() {
    fetch('api/update-last-seen.php', { method: 'POST' })
    .catch(error => console.error('Update last seen error:', error));
}

/**
 * Real-time message checking (for messages page)
 */
function startMessagePolling(conversationId) {
    setInterval(() => {
        checkNewMessages(conversationId);
    }, 3000);
}

/**
 * Check for new messages
 */
function checkNewMessages(conversationId) {
    fetch(`api/messages.php?conversation_id=${conversationId}&check_new=1`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.has_new) {
            loadMessages(conversationId);
        }
    })
    .catch(error => console.error('Check messages error:', error));
}

/**
 * Send message
 */
function sendMessage(conversationId, messageText) {
    fetch('api/send-message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            conversation_id: conversationId,
            message_text: messageText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMessages(conversationId);
        }
    })
    .catch(error => console.error('Send message error:', error));
}

/**
 * Load messages for conversation
 */
function loadMessages(conversationId) {
    fetch(`api/messages.php?conversation_id=${conversationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessages(data.messages);
        }
    })
    .catch(error => console.error('Load messages error:', error));
}

/**
 * Display messages
 */
function displayMessages(messages) {
    const messagesContainer = document.getElementById('messages-container');
    if (!messagesContainer) return;

    let html = '';
    messages.forEach(message => {
        const isOwn = message.is_own || false;
        html += `
            <div class="message ${isOwn ? 'own' : ''}">
                <div class="message-text">${escapeHtml(message.message_text)}</div>
                <div class="message-time">${message.created_at}</div>
            </div>
        `;
    });

    messagesContainer.innerHTML = html;
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
