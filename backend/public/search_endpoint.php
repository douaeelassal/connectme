<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Just handle the search endpoint
$query = $_GET['search'] ?? '';

// Mock users data
$mockUsers = [
    1 => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'avatar' => null, 'bio' => 'Test bio'],
    2 => ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'avatar' => null, 'bio' => 'Hello world'],
    3 => ['id' => 3, 'name' => 'John Smith', 'email' => 'john@example.com', 'avatar' => null, 'bio' => 'Laravel developer']
];

$results = [];
foreach ($mockUsers as $user) {
    if (stripos($user['name'], $query) !== false || 
        stripos($user['email'], $query) !== false) {
        $results[] = $user;
    }
}

echo json_encode([
    'users' => $results
]);
