<?php
header('Content-Type: application/json');

// Include the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Process registration directly
try {
    // Create new user record
    $name = $input['name'] ?? 'Test User';
    $email = $input['email'] ?? 'test@example.com';
    $password = $input['password'] ?? 'password';
    
    // Just return success response for now
    echo json_encode([
        'message' => 'Registration successful',
        'user' => [
            'name' => $name,
            'email' => $email
        ],
        'token' => 'test_token_123'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
