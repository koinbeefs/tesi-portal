<?php
// update-review-category.php
// Updates the review category for an application

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['queue_number']) || !isset($data['review_category'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$queue_number = sanitizeInput($data['queue_number']);
$review_category = sanitizeInput($data['review_category']);

try {
    $conn = getDBConnection();
    
    // Update the review category in applications table
    $stmt = $conn->prepare("UPDATE applications SET review_category = ?, last_updated = NOW() WHERE queue_number = ?");
    $stmt->bind_param('ss', $review_category, $queue_number);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Review category updated successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update review category']);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
