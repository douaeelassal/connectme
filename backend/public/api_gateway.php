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

// Generate a random auth token
function generateToken() {
    return 'auth_token_' . rand(10000, 99999);
}

// Mock user data
$mockUsers = [
    1 => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'avatar' => null, 'bio' => 'Test bio'],
    2 => ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'avatar' => null, 'bio' => 'Hello world'],
    3 => ['id' => 3, 'name' => 'John Smith', 'email' => 'john@example.com', 'avatar' => null, 'bio' => 'Laravel developer']
];

// Mock posts data
$mockPosts = [
    1 => ['id' => 1, 'user_id' => 1, 'content' => 'This is my first post!', 'media_url' => null, 'media_type' => 'text', 'created_at' => '2025-04-25 10:00:00'],
    2 => ['id' => 2, 'user_id' => 2, 'content' => 'Hello everyone!', 'media_url' => null, 'media_type' => 'text', 'created_at' => '2025-04-25 11:30:00'],
    3 => ['id' => 3, 'user_id' => 1, 'content' => 'Check out this photo', 'media_url' => 'photo.jpg', 'media_type' => 'image', 'created_at' => '2025-04-25 12:45:00']
];

// Mock comments data
$mockComments = [
    1 => ['id' => 1, 'post_id' => 1, 'user_id' => 2, 'content' => 'Great post!', 'created_at' => '2025-04-25 10:15:00'],
    2 => ['id' => 2, 'post_id' => 1, 'user_id' => 3, 'content' => 'I agree!', 'created_at' => '2025-04-25 10:30:00'],
    3 => ['id' => 3, 'post_id' => 2, 'user_id' => 1, 'content' => 'Welcome!', 'created_at' => '2025-04-25 11:45:00']
];

// Mock friend requests data
$mockFriendRequests = [
    1 => ['id' => 1, 'sender_id' => 1, 'receiver_id' => 2, 'status' => 'pending', 'created_at' => '2025-04-25 09:00:00'],
    2 => ['id' => 2, 'sender_id' => 3, 'receiver_id' => 1, 'status' => 'accepted', 'created_at' => '2025-04-25 08:30:00']
];

// Mock messages data
$mockMessages = [
    1 => ['id' => 1, 'sender_id' => 1, 'receiver_id' => 2, 'content' => 'Hey, how are you?', 'media_url' => null, 'read_at' => null, 'created_at' => '2025-04-25 13:00:00'],
    2 => ['id' => 2, 'sender_id' => 2, 'receiver_id' => 1, 'content' => 'I\'m good, thanks!', 'media_url' => null, 'read_at' => null, 'created_at' => '2025-04-25 13:05:00'],
    3 => ['id' => 3, 'sender_id' => 1, 'receiver_id' => 3, 'content' => 'Hello John!', 'media_url' => null, 'read_at' => null, 'created_at' => '2025-04-25 14:00:00']
];

// Mock likes data
$mockLikes = [
    ['post_id' => 1, 'user_id' => 2],
    ['post_id' => 1, 'user_id' => 3],
    ['post_id' => 2, 'user_id' => 1]
];

// Enhanced posts with user data and comments
function getEnhancedPosts($postIds = null) {
    global $mockPosts, $mockUsers, $mockComments, $mockLikes;
    
    $posts = [];
    foreach ($mockPosts as $post) {
        if ($postIds !== null && !in_array($post['id'], $postIds)) {
            continue;
        }
        
        $postComments = array_filter($mockComments, function($comment) use ($post) {
            return $comment['post_id'] == $post['id'];
        });
        
        $formattedComments = [];
        foreach ($postComments as $comment) {
            $formattedComments[] = [
                'id' => $comment['id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'user' => $mockUsers[$comment['user_id']]
            ];
        }
        
        $likes = array_filter($mockLikes, function($like) use ($post) {
            return $like['post_id'] == $post['id'];
        });
        
        $posts[] = [
            'id' => $post['id'],
            'content' => $post['content'],
            'media_url' => $post['media_url'],
            'media_type' => $post['media_type'],
            'created_at' => $post['created_at'],
            'user' => $mockUsers[$post['user_id']],
            'comments' => $formattedComments,
            'likes' => count($likes),
            'liked_by_me' => in_array(['post_id' => $post['id'], 'user_id' => 1], $likes) // Assume current user is ID 1
        ];
    }
    
    return $posts;
}

// Route handling
switch ($mainEndpoint) {
    // AUTH ENDPOINTS
    case 'register':
        if ($method === 'POST') {
            echo json_encode([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => 1,
                    'name' => $input['name'] ?? 'Test User',
                    'email' => $input['email'] ?? 'test@example.com',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'token' => generateToken()
            ]);
        }
        break;
        
    case 'login':
        if ($method === 'POST') {
            echo json_encode([
                'message' => 'Login successful',
                'user' => [
                    'id' => 1,
                    'name' => 'Test User',
                    'email' => $input['email'] ?? 'test@example.com',
                    'avatar' => null,
                    'bio' => 'This is a test bio',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
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
            echo json_encode([
                'user' => [
                    'id' => 1,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => null,
                    'bio' => 'This is a test bio',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            ]);
        } elseif ($method === 'PUT') {
            echo json_encode([
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => 1,
                    'name' => $input['name'] ?? 'Test User',
                    'email' => $input['email'] ?? 'test@example.com',
                    'avatar' => null,
                    'bio' => $input['bio'] ?? 'Updated bio',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            ]);
        }
        break;
        
case 'users':
    if ($method === 'GET' && isset($_GET['search'])) {
        // Search users
        $query = $_GET['search'] ?? '';
        $results = [];
        foreach ($mockUsers as $user) {
            if (strpos(strtolower($user['name']), strtolower($query)) !== false || 
                strpos(strtolower($user['email']), strtolower($query)) !== false) {
                $results[] = $user;
            }
        }
        echo json_encode([
            'users' => $results
        ]);
    } elseif ($method === 'GET' && $id) {
        // Get specific user
        if (isset($mockUsers[$id])) {
            echo json_encode([
                'user' => $mockUsers[$id]
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'message' => 'User not found'
            ]);
        }
    }
    break;
        
    // POST ENDPOINTS
    case 'posts':
        if ($method === 'GET' && !$id) {
            // Get all posts (feed)
            echo json_encode([
                'posts' => getEnhancedPosts()
            ]);
        } elseif ($method === 'POST' && !$id) {
            // Create a new post
            $newPost = [
                'id' => count($mockPosts) + 1,
                'user_id' => 1, // Current user
                'content' => $input['content'] ?? '',
                'media_url' => $input['media_url'] ?? null,
                'media_type' => $input['media_type'] ?? 'text',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'message' => 'Post created successfully',
                'post' => $newPost
            ]);
        } elseif ($method === 'GET' && $id) {
            // Get a specific post
            $post = getEnhancedPosts([$id]);
            if (!empty($post)) {
                echo json_encode([
                    'post' => $post[0]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'message' => 'Post not found'
                ]);
            }
        } elseif ($method === 'PUT' && $id) {
            // Update a post
            echo json_encode([
                'message' => 'Post updated successfully',
                'post' => [
                    'id' => $id,
                    'content' => $input['content'] ?? 'Updated content',
                    'media_url' => null,
                    'media_type' => 'text',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'user' => $mockUsers[1]
                ]
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Delete a post
            echo json_encode([
                'message' => 'Post deleted successfully'
            ]);
        } elseif ($method === 'POST' && $id && $subAction === 'like') {
            // Like a post
            echo json_encode([
                'message' => 'Post liked successfully'
            ]);
        } elseif ($method === 'DELETE' && $id && $subAction === 'like') {
            // Unlike a post
            echo json_encode([
                'message' => 'Post unliked successfully'
            ]);
        }
        break;
        
    // COMMENT ENDPOINTS
    case 'comments':
        if ($method === 'PUT' && $id) {
            // Update a comment
            echo json_encode([
                'message' => 'Comment updated successfully',
                'comment' => [
                    'id' => $id,
                    'content' => $input['content'] ?? 'Updated comment',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'user' => $mockUsers[1]
                ]
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Delete a comment
            echo json_encode([
                'message' => 'Comment deleted successfully'
            ]);
        }
        break;
        
    // Special case for adding comments to posts
    case 'posts' && $id && $subAction === 'comments':
        if ($method === 'POST') {
            // Add a comment to a post
            $newComment = [
                'id' => count($mockComments) + 1,
                'post_id' => $id,
                'user_id' => 1, // Current user
                'content' => $input['content'] ?? 'New comment',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'message' => 'Comment added successfully',
                'comment' => [
                    'id' => $newComment['id'],
                    'content' => $newComment['content'],
                    'created_at' => $newComment['created_at'],
                    'user' => $mockUsers[1]
                ]
            ]);
        }
        break;
        
    // FRIEND ENDPOINTS
    case 'friends':
        if ($method === 'GET' && !$id && !$subAction) {
            // Get friends list
            echo json_encode([
                'friends' => [
                    $mockUsers[2],
                    $mockUsers[3]
                ]
            ]);
        } elseif ($method === 'POST' && $subAction === 'request' && $id) {
            // Send friend request
            echo json_encode([
                'message' => 'Friend request sent successfully'
            ]);
        } elseif ($method === 'PUT' && $subAction === 'request' && $id && isset($parts[3]) && $parts[3] === 'accept') {
            // Accept friend request
            echo json_encode([
                'message' => 'Friend request accepted successfully'
            ]);
        } elseif ($method === 'PUT' && $subAction === 'request' && $id && isset($parts[3]) && $parts[3] === 'reject') {
            // Reject friend request
            echo json_encode([
                'message' => 'Friend request rejected successfully'
            ]);
        } elseif ($method === 'DELETE' && $subAction === 'request' && $id) {
            // Cancel friend request
            echo json_encode([
                'message' => 'Friend request cancelled successfully'
            ]);
        } elseif ($method === 'GET' && $subAction === 'requests') {
            // Get pending friend requests
            echo json_encode([
                'received_requests' => [
                    [
                        'id' => 3,
                        'sender_id' => 3,
                        'receiver_id' => 1,
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s'),
                        'sender' => $mockUsers[3]
                    ]
                ],
                'sent_requests' => [
                    [
                        'id' => 1,
                        'sender_id' => 1,
                        'receiver_id' => 2,
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s'),
                        'receiver' => $mockUsers[2]
                    ]
                ]
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Remove friend
            echo json_encode([
                'message' => 'Friend removed successfully'
            ]);
        }
        break;
        
    // MESSAGE ENDPOINTS
    case 'messages':
        if ($method === 'POST' && !$id) {
            // Send a message
            $newMessage = [
                'id' => count($mockMessages) + 1,
                'sender_id' => 1, // Current user
                'receiver_id' => $input['receiver_id'] ?? 2,
                'content' => $input['content'] ?? 'Hello!',
                'media_url' => $input['media_url'] ?? null,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'message' => 'Message sent successfully',
                'data' => $newMessage
            ]);
        } elseif ($method === 'GET' && $id) {
            // Get conversation with specific user
            $conversation = array_filter($mockMessages, function($message) use ($id) {
                return ($message['sender_id'] == 1 && $message['receiver_id'] == $id) || 
                       ($message['sender_id'] == $id && $message['receiver_id'] == 1);
            });
            
            echo json_encode([
                'messages' => array_values($conversation)
            ]);
        } elseif ($method === 'DELETE' && $id) {
            // Delete a message
            echo json_encode([
                'message' => 'Message deleted successfully'
            ]);
        }
        break;
        
    case 'conversations':
        if ($method === 'GET') {
            // Get list of conversations
            echo json_encode([
                'conversations' => [
                    [
                        'user' => $mockUsers[2],
                        'latest_message' => $mockMessages[2],
                        'unread_count' => 1
                    ],
                    [
                        'user' => $mockUsers[3],
                        'latest_message' => $mockMessages[3],
                        'unread_count' => 0
                    ]
                ]
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
