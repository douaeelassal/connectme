<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connect to database
$conn = new mysqli('db', 'connectme', 'password', 'connectme');
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Get user ID from request or use default
$userId = $_POST['user_id'] ?? 1;
$type = $_POST['type'] ?? 'profile'; // Type can be 'profile' or 'post'

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$filename = uniqid() . '_' . basename($_FILES['file']['name']);
$uploadPath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
    $fileUrl = 'http://localhost:8000/' . $uploadPath;
    
    if ($type === 'profile') {
        // Update user avatar
        $sql = "UPDATE users SET avatar = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $fileUrl, $userId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'message' => 'Profile picture updated successfully',
                'file_url' => $fileUrl
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update profile picture: ' . $conn->error]);
        }
    } else {
        // Just return the file URL for posts (will be used in POST /posts)
        echo json_encode([
            'message' => 'File uploaded successfully',
            'file_url' => $fileUrl
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
}

$conn->close();
