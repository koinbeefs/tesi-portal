<?php
/**
 * Check if form already exists for duplicate prevention
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$queue_number = sanitizeInput($_POST['queue_number'] ?? '');
$form_type = sanitizeInput($_POST['form_type'] ?? '');

if (empty($queue_number) || empty($form_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$conn = getDBConnection();

// Check if form exists
$stmt = $conn->prepare("SELECT form_id, completed_at, file_generated FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
$stmt->bind_param("ss", $queue_number, $form_type);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $exists = true;
    $completed_at = formatDate($result['completed_at']);
    $file_generated = $result['file_generated'];
    
    // Create appropriate message based on form type
    switch($form_type) {
        case 'qf39':
            $message = "QF-39 form has already been submitted for queue number $queue_number on $completed_at. You can download the existing form from the application page.";
            break;
        case 'qf40':
            $message = "QF-40 certificate has already been generated for queue number $queue_number on $completed_at. The application has been approved.";
            break;
        case 'tesi_score':
            // Allow similarity score updates
            $exists = false;
            $message = "Similarity score can be updated if needed.";
            break;
        default:
            $message = "Form has already been submitted for queue number $queue_number on $completed_at.";
    }
} else {
    $exists = false;
    $message = "Form not found. You can proceed with submission.";
}

echo json_encode([
    'success' => true,
    'exists' => $exists,
    'message' => $message,
    'completed_at' => $result['completed_at'] ?? null,
    'file_generated' => $result['file_generated'] ?? 0
]);

closeDBConnection($conn);
?>
