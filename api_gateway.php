<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
$conn = new mysqli('db', 'connectme', 'password', 'connectme');
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Get the requested endpoint from the URL
$requestUri = $_SERVER['REQUEST_URI'];
$endpoint = str_replace('/api_gateway.php/', '', $requestUri);
$parts = explode('/', $endpoint);
$mainEndpoint = $parts[0] ?? '';
$id = $parts[1] ?? null;
$subAction = $parts[2] ?? null;

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Generate a JWT token
function generateToken() {
    $payload = [
        'user_id' => 1, // Default user ID for now
        'iat' => time(),
        'exp' => time() + (60 * 60) // 1 hour expiration
    ];
    
    return 'auth_token_' . base64_encode(json_encode($payload));
}

// Get current user ID from token (simplified)
function getCurrentUserId() {
    // For now, just return 1 as the current user
    return 1;
}

// Enhanced posts with user data and comments
function getEnhancedPosts($conn, $postIds = null) {
    $currentUserId = getCurrentUserId();
    $posts = [];
    
    $sql = "SELECT p.*, u.name, u.email, u.avatar, u.bio 
            FROM posts p 
            JOIN users u ON p.user_id = u.id";
    
    if ($postIds !== null && is_array($postIds) && !empty($postIds)) {
        $ids = implode(',', array_map(function($id) use ($conn) {
            return (int)$id;
        }, $postIds));
        $sql .= " WHERE p.id IN ($ids)";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get comments for this post
            $comments = [];
            $commentSql = "SELECT c.*, u.name, u.email, u.avatar, u.bio 
                          FROM comments c 
                          JOIN users u ON c.user_id = u.id 
                          WHERE c.post_id = ?
                          ORDER BY c.created_at ASC";
            
            $stmt = $conn->prepare($commentSql);
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $commentsResult = $stmt->get_result();
            
            while ($commentRow = $commentsResult->fetch_assoc()) {
                $comments[] = [
                    'id' => $commentRow['id'],
                    'content' => $commentRow['content'],
                    'created_at' => $commentRow['created_at'],
                    'user' => [
                        'id' => $commentRow['user_id'],
                        'name' => $commentRow['name'],
                        'email' => $commentRow['email'],
                        'avatar' => $commentRow['avatar'],
                        'bio' => $commentRow['bio']
                    ]
                ];
            }
            
            // Get likes count
            $likesSql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ?";
            $stmt = $conn->prepare($likesSql);
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $likesResult = $stmt->get_result();
            $likesCount = $likesResult->fetch_assoc()['count'];
            
            // Check if current user liked this post
            $likedByMeSql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ? AND user_id = ?";
            $stmt = $conn->prepare($likedByMeSql);
            $stmt->bind_param('ii', $row['id'], $currentUserId);
            $stmt->execute();
            $likedByMeResult = $stmt->get_result();
            $likedByMe = $likedByMeResult->fetch_assoc()['count'] > 0;
            
            $posts[] = [
                'id' => $row['id'],
                'content' => $row['content'],
                'media_url' => $row['media_url'],
                'media_type' => $row['media_type'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'avatar' => $row['avatar'],
                    'bio' => $row['bio']
                ],
                'comments' => $comments,
                'likes' => $likesCount,
                'liked_by_me' => $likedByMe
            ];
        }
    }
    
    return $posts;
}

// Route handling
switch ($mainEndpoint) {
    // AUTH ENDPOINTS
    case 'register':
        if ($method === 'POST') {
            $name = $input['name'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            // Check if email already exists
            $checkSql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->fetch_assoc()['count'] > 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Email already exists'
                ]);
                break;
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $name, $email, $hashedPassword);
            
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                
                // Get the created user
                $userSql = "SELECT * FROM users WHERE id = ?";
                $stmt = $conn->prepare($userSql);
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $userResult = $stmt->get_result();
                $user = $userResult->fetch_assoc();
                
                // Remove password from response
                unset($user['password']);
                
                echo json_encode([
                    'message' => 'User registered successfully',
                    'user' => $user,
                    'token' => generateToken()
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Registration failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    case 'login':
        if ($method === 'POST') {
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            // Find user by email
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(401);
                echo json_encode([
                    'error' => 'Invalid email or password'
                ]);
                break;
            }
            
            $user = $result->fetch_assoc();
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode([
                    'error' => 'Invalid email or password'
                ]);
                break;
            }
            
            // Remove password from response
            unset($user['password']);
            
            echo json_encode([
                'message' => 'Login successful',
                'user' => $user,
                'token' => generateToken()
            ]);
        }
        break;
        
    case 'logout':
        if ($method === 'POST') {
            echo json_encode([
                'message' => 'Successfully logged out'
            ]);
        }
        break;
        
    // USER ENDPOINTS
    case 'profile':
        if ($method === 'GET') {
            $userId = getCurrentUserId();
            
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'User not found'
                ]);
                break;
            }
            
            $user = $result->fetch_assoc();
            
            // Remove password from response
            unset($user['password']);
            
            echo json_encode([
                'user' => $user
            ]);
        } elseif ($method === 'PUT') {
            $userId = getCurrentUserId();
            $name = $input['name'] ?? null;
            $email = $input['email'] ?? null;
            $bio = $input['bio'] ?? null;
            
            $updateFields = [];
            $params = [];
            $types = '';
            
            if ($name !== null) {
                $updateFields[] = "name = ?";
                $params[] = $name;
                $types .= 's';
            }
            
            if ($email !== null) {
                $updateFields[] = "email = ?";
                $params[] = $email;
                $types .= 's';
            }
            
            if ($bio !== null) {
                $updateFields[] = "bio = ?";
                $params[] = $bio;
                $types .= 's';
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No fields to update'
                ]);
                break;
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $userId;
            $types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Get updated user
                $userSql = "SELECT * FROM users WHERE id = ?";
                $stmt = $conn->prepare($userSql);
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $userResult = $stmt->get_result();
                $user = $userResult->fetch_assoc();
                
                // Remove password from response
                unset($user['password']);
                
                echo json_encode([
                    'message' => 'Profile updated successfully',
                    'user' => $user
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Profile update failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    case 'users':
        if ($method === 'GET' && isset($_GET['search'])) {
            // Search users
            $query = '%' . $_GET['search'] . '%';
            
            $sql = "SELECT * FROM users WHERE name LIKE ? OR email LIKE ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $query, $query);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                // Remove password from response
                unset($row['password']);
                $users[] = $row;
            }
            
            echo json_encode([
                'users' => $users
            ]);
        } elseif ($method === 'GET' && $id) {
            // Get specific user
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'User not found'
                ]);
                break;
            }
            
            $user = $result->fetch_assoc();
            
            // Remove password from response
            unset($user['password']);
            
            echo json_encode([
                'user' => $user
            ]);
        }
        break;
        
    // POST ENDPOINTS
    case 'posts':
        if ($method === 'GET' && !$id) {
            // Get all posts (feed)
            echo json_encode([
                'posts' => getEnhancedPosts($conn)
            ]);
        } elseif ($method === 'POST' && !$id) {
            // Create a new post
            $userId = getCurrentUserId();
            $content = $input['content'] ?? '';
            $mediaUrl = $input['media_url'] ?? null;
            $mediaType = $input['media_type'] ?? 'text';
            
            $sql = "INSERT INTO posts (user_id, content, media_url, media_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isss', $userId, $content, $mediaUrl, $mediaType);
            
            if ($stmt->execute()) {
                $postId = $conn->insert_id;
                
                // Get the created post
                $postSql = "SELECT * FROM posts WHERE id = ?";
                $stmt = $conn->prepare($postSql);
                $stmt->bind_param('i', $postId);
                $stmt->execute();
                $postResult = $stmt->get_result();
                $post = $postResult->fetch_assoc();
                
                echo json_encode([
                    'message' => 'Post created successfully',
                    'post' => $post
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Post creation failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'GET' && $id) {
            // Get a specific post
            $post = getEnhancedPosts($conn, [$id]);
            if (!empty($post)) {
                echo json_encode([
                    'post' => $post[0]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Post not found'
                ]);
            }
        } elseif ($method === 'PUT' && $id) {
            // Update a post
            $userId = getCurrentUserId();
            $content = $input['content'] ?? null;
            $mediaUrl = $input['media_url'] ?? null;
            $mediaType = $input['media_type'] ?? null;
            
            // Check if post exists and belongs to current user
            $checkSql = "SELECT * FROM posts WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'You do not have permission to update this post'
                ]);
                break;
            }
            
            $updateFields = [];
            $params = [];
            $types = '';
            
            if ($content !== null) {
                $updateFields[] = "content = ?";
                $params[] = $content;
                $types .= 's';
            }
            
            if ($mediaUrl !== null) {
                $updateFields[] = "media_url = ?";
                $params[] = $mediaUrl;
                $types .= 's';
            }
            
            if ($mediaType !== null) {
                $updateFields[] = "media_type = ?";
                $params[] = $mediaType;
                $types .= 's';
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No fields to update'
                ]);
                break;
            }
            
            $sql = "UPDATE posts SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Get updated post
                $post = getEnhancedPosts($conn, [$id]);
                
                echo json_encode([
                    'message' => 'Post updated successfully',
                    'post' => $post[0]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Post update failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'DELETE' && $id) {
            // Delete a post
            $userId = getCurrentUserId();
            
            // Check if post exists and belongs to current user
            $checkSql = "SELECT * FROM posts WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'You do not have permission to delete this post'
                ]);
                break;
            }
            
            $sql = "DELETE FROM posts WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Post deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Post deletion failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'POST' && $id && $subAction === 'like') {
            // Like a post
            $userId = getCurrentUserId();
            
            // Check if already liked
            $checkSql = "SELECT * FROM likes WHERE post_id = ? AND user_id = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Post already liked'
                ]);
                break;
            }
            
            $sql = "INSERT INTO likes (post_id, user_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $id, $userId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Post liked successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Like failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'DELETE' && $id && $subAction === 'like') {
            // Unlike a post
            $userId = getCurrentUserId();
            
            $sql = "DELETE FROM likes WHERE post_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $id, $userId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Post unliked successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Unlike failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    // COMMENT ENDPOINTS
    case 'comments':
        if ($method === 'PUT' && $id) {
            // Update a comment
            $userId = getCurrentUserId();
            $content = $input['content'] ?? null;
            
            // Check if comment exists and belongs to current user
            $checkSql = "SELECT * FROM comments WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'You do not have permission to update this comment'
                ]);
                break;
            }
            
            if ($content === null) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Content is required'
                ]);
                break;
            }
            
            $sql = "UPDATE comments SET content = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $content, $id);
            
            if ($stmt->execute()) {
                // Get updated comment
                $commentSql = "SELECT c.*, u.name, u.email, u.avatar, u.bio 
                               FROM comments c 
                               JOIN users u ON c.user_id = u.id 
                               WHERE c.id = ?";
                $stmt = $conn->prepare($commentSql);
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $commentRow = $result->fetch_assoc();
                
                $comment = [
                    'id' => $commentRow['id'],
                    'content' => $commentRow['content'],
                    'created_at' => $commentRow['created_at'],
                    'updated_at' => $commentRow['updated_at'],
                    'user' => [
                        'id' => $commentRow['user_id'],
                        'name' => $commentRow['name'],
                        'email' => $commentRow['email'],
                        'avatar' => $commentRow['avatar'],
                        'bio' => $commentRow['bio']
                    ]
                ];
                
                echo json_encode([
                    'message' => 'Comment updated successfully',
                    'comment' => $comment
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Comment update failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'DELETE' && $id) {
            // Delete a comment
            $userId = getCurrentUserId();
            
            // Check if comment exists and belongs to current user
            $checkSql = "SELECT * FROM comments WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'You do not have permission to delete this comment'
                ]);
                break;
            }
            
            $sql = "DELETE FROM comments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Comment deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Comment deletion failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    // Special case for adding comments to posts
    case 'posts' && $id && $subAction === 'comments':
        if ($method === 'POST') {
            // Add a comment to a post
            $userId = getCurrentUserId();
            $content = $input['content'] ?? '';
            
            if (empty($content)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Content is required'
                ]);
                break;
            }
            
            $sql = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iis', $id, $userId, $content);
            
            if ($stmt->execute()) {
                $commentId = $conn->insert_id;
                
                // Get the created comment with user data
                $commentSql = "SELECT c.*, u.name, u.email, u.avatar, u.bio 
                               FROM comments c 
                               JOIN users u ON c.user_id = u.id 
                               WHERE c.id = ?";
                $stmt = $conn->prepare($commentSql);
                $stmt->bind_param('i', $commentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $commentRow = $result->fetch_assoc();
                
                $comment = [
                    'id' => $commentRow['id'],
                    'content' => $commentRow['content'],
                    'created_at' => $commentRow['created_at'],
                    'user' => [
                        'id' => $commentRow['user_id'],
                        'name' => $commentRow['name'],
                        'email' => $commentRow['email'],
                        'avatar' => $commentRow['avatar'],
                        'bio' => $commentRow['bio']
                    ]
                ];
                
                echo json_encode([
                    'message' => 'Comment added successfully',
                    'comment' => $comment
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Comment creation failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    // FRIEND ENDPOINTS
    case 'friends':
        if ($method === 'GET' && !$id && !$subAction) {
            // Get friends list
            $userId = getCurrentUserId();
            
            $sql = "SELECT u.* FROM users u
                    INNER JOIN friend_requests fr ON (fr.sender_id = ? AND fr.receiver_id = u.id) OR (fr.receiver_id = ? AND fr.sender_id = u.id)
                    WHERE fr.status = 'accepted'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $userId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $friends = [];
            while ($row = $result->fetch_assoc()) {
                // Remove password from response
                unset($row['password']);
                $friends[] = $row;
            }
            
            echo json_encode([
                'friends' => $friends
            ]);
        } elseif ($method === 'POST' && $subAction === 'request' && $id) {
            // Send friend request
            $userId = getCurrentUserId();
            
            // Check if request already exists
            $checkSql = "SELECT * FROM friend_requests WHERE 
                         (sender_id = ? AND receiver_id = ?) OR 
                         (sender_id = ? AND receiver_id = ?)";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('iiii', $userId, $id, $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Friend request already exists'
                ]);
                break;
            }
            
            $sql = "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $userId, $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Friend request sent successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Friend request failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'PUT' && $subAction === 'request' && $id && isset($parts[3]) && $parts[3] === 'accept') {
            // Accept friend request
            $userId = getCurrentUserId();
            
            // Check if request exists and is pending
            $checkSql = "SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Friend request not found or already processed'
                ]);
                break;
            }
            
            $sql = "UPDATE friend_requests SET status = 'accepted' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Friend request accepted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Accept friend request failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'PUT' && $subAction === 'request' && $id && isset($parts[3]) && $parts[3] === 'reject') {
            // Reject friend request
            $userId = getCurrentUserId();
            
            // Check if request exists and is pending
            $checkSql = "SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Friend request not found or already processed'
                ]);
                break;
            }
            
            $sql = "UPDATE friend_requests SET status = 'rejected' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Friend request rejected successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Reject friend request failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'DELETE' && $subAction === 'request' && $id) {
            // Cancel friend request
            $userId = getCurrentUserId();
            
            // Check if request exists and was sent by current user
            $checkSql = "SELECT * FROM friend_requests WHERE id = ? AND sender_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Friend request not found or cannot be cancelled'
                ]);
                break;
            }
            
            $sql = "DELETE FROM friend_requests WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Friend request cancelled successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Cancel friend request failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'GET' && $subAction === 'requests') {
            // Get pending friend requests
            $userId = getCurrentUserId();
            
            // Get received requests
            $receivedSql = "SELECT fr.*, u.name, u.email, u.avatar, u.bio 
                           FROM friend_requests fr 
                           JOIN users u ON fr.sender_id = u.id 
                           WHERE fr.receiver_id = ? AND fr.status = 'pending'";
            $stmt = $conn->prepare($receivedSql);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $receivedResult = $stmt->get_result();
            
            $receivedRequests = [];
            while ($row = $receivedResult->fetch_assoc()) {
                $receivedRequests[] = [
                    'id' => $row['id'],
                    'sender_id' => $row['sender_id'],
                    'receiver_id' => $row['receiver_id'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'sender' => [
                        'id' => $row['sender_id'],
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'avatar' => $row['avatar'],
                        'bio' => $row['bio']
                    ]
                ];
            }
            
            // Get sent requests
            $sentSql = "SELECT fr.*, u.name, u.email, u.avatar, u.bio 
                       FROM friend_requests fr 
                       JOIN users u ON fr.receiver_id = u.id 
                       WHERE fr.sender_id = ? AND fr.status = 'pending'";
            $stmt = $conn->prepare($sentSql);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $sentResult = $stmt->get_result();
            
            $sentRequests = [];
            while ($row = $sentResult->fetch_assoc()) {
                $sentRequests[] = [
                    'id' => $row['id'],
                    'sender_id' => $row['sender_id'],
                    'receiver_id' => $row['receiver_id'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'receiver' => [
                        'id' => $row['receiver_id'],
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'avatar' => $row['avatar'],
                        'bio' => $row['bio']
                    ]
                ];
            }
            
            echo json_encode([
                'received_requests' => $receivedRequests,
                'sent_requests' => $sentRequests
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Remove friend
            $userId = getCurrentUserId();
            
            $sql = "DELETE FROM friend_requests WHERE 
                    ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
                    AND status = 'accepted'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iiii', $userId, $id, $id, $userId);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode([
                    'message' => 'Friend removed successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Friend not found or already removed'
                ]);
            }
        }
        break;
        
    // MESSAGE ENDPOINTS
    case 'messages':
        if ($method === 'POST' && !$id) {
            // Send a message
            $userId = getCurrentUserId();
            $receiverId = $input['receiver_id'] ?? null;
            $content = $input['content'] ?? '';
            $mediaUrl = $input['media_url'] ?? null;
            
            if ($receiverId === null) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Receiver ID is required'
                ]);
                break;
            }
            
            if (empty($content) && empty($mediaUrl)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Message content or media is required'
                ]);
                break;
            }
            
            $sql = "INSERT INTO messages (sender_id, receiver_id, content, media_url) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iiss', $userId, $receiverId, $content, $mediaUrl);
            
            if ($stmt->execute()) {
                $messageId = $conn->insert_id;
                
                // Get the created message
                $messageSql = "SELECT * FROM messages WHERE id = ?";
                $stmt = $conn->prepare($messageSql);
                $stmt->bind_param('i', $messageId);
                $stmt->execute();
                $result = $stmt->get_result();
                $message = $result->fetch_assoc();
                
                echo json_encode([
                    'message' => 'Message sent successfully',
                    'data' => $message
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Message sending failed: ' . $conn->error
                ]);
            }
        } elseif ($method === 'GET' && $id) {
            // Get conversation with specific user
            $userId = getCurrentUserId();
            
            $sql = "SELECT * FROM messages 
                   WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                   ORDER BY created_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iiii', $userId, $id, $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
                
                // Mark messages as read if they were sent to current user
                if ($row['receiver_id'] == $userId && $row['read_at'] === null) {
                    $updateSql = "UPDATE messages SET read_at = NOW() WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param('i', $row['id']);
                    $updateStmt->execute();
                }
            }
            
            echo json_encode([
                'messages' => $messages
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Delete a message
            $userId = getCurrentUserId();
            
            // Check if message exists and belongs to current user
            $checkSql = "SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('iii', $id, $userId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'You do not have permission to delete this message'
                ]);
                break;
            }
            
            $sql = "DELETE FROM messages WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'message' => 'Message deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Message deletion failed: ' . $conn->error
                ]);
            }
        }
        break;
        
    case 'conversations':
        if ($method === 'GET') {
            // Get list of conversations
            $userId = getCurrentUserId();
            
            // Get all users that current user has exchanged messages with
            $usersSql = "SELECT DISTINCT 
                        CASE 
                            WHEN sender_id = ? THEN receiver_id 
                            ELSE sender_id 
                        END as user_id 
                        FROM messages 
                        WHERE sender_id = ? OR receiver_id = ?";
            $stmt = $conn->prepare($usersSql);
            $stmt->bind_param('iii', $userId, $userId, $userId);
            $stmt->execute();
            $usersResult = $stmt->get_result();
            
            $conversations = [];
            while ($userRow = $usersResult->fetch_assoc()) {
                $otherUserId = $userRow['user_id'];
                
                // Get user details
                $userSql = "SELECT * FROM users WHERE id = ?";
                $stmt = $conn->prepare($userSql);
                $stmt->bind_param('i', $otherUserId);
                $stmt->execute();
                $userResult = $stmt->get_result();
                $user = $userResult->fetch_assoc();
                unset($user['password']);
                
                // Get latest message
                $latestSql = "SELECT * FROM messages 
                             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                             ORDER BY created_at DESC LIMIT 1";
                $stmt = $conn->prepare($latestSql);
                $stmt->bind_param('iiii', $userId, $otherUserId, $otherUserId, $userId);
                $stmt->execute();
                $latestResult = $stmt->get_result();
                $latestMessage = $latestResult->fetch_assoc();
                
                // Get unread count
                $unreadSql = "SELECT COUNT(*) as count FROM messages 
                             WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL";
                $stmt = $conn->prepare($unreadSql);
                $stmt->bind_param('ii', $otherUserId, $userId);
                $stmt->execute();
                $unreadResult = $stmt->get_result();
                $unreadCount = $unreadResult->fetch_assoc()['count'];
                
                $conversations[] = [
                    'user' => $user,
                    'latest_message' => $latestMessage,
                    'unread_count' => (int)$unreadCount
                ];
            }
            
            // Sort by latest message time
            usort($conversations, function($a, $b) {
                return strtotime($b['latest_message']['created_at']) - strtotime($a['latest_message']['created_at']);
            });
            
            echo json_encode([
                'conversations' => $conversations
            ]);
        }
        break;
        
    default:
        // Unknown endpoint
        http_response_code(404);
        echo json_encode([
            'error' => 'Unknown endpoint',
            'requested' => $endpoint,
            'method' => $method
        ]);
}

$conn->close();