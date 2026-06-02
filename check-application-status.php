<?php
/**
 * Check application status and existing forms
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$queue_number = sanitizeInput($_POST['queue_number'] ?? '');

if (empty($queue_number)) {
    echo json_encode(['success' => false, 'message' => 'Missing queue number']);
    exit;
}

$conn = getDBConnection();

// Get application status
$stmt = $conn->prepare("SELECT current_status, submission_timestamp FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

// Get existing forms
$forms_stmt = $conn->prepare("SELECT form_type, completed_at, file_generated FROM fillable_forms WHERE queue_number = ? ORDER BY completed_at");
$forms_stmt->bind_param("s", $queue_number);
$forms_stmt->execute();
$existing_forms = [];

while ($form = $forms_stmt->get_result()->fetch_assoc()) {
    $existing_forms[] = [
        'form_type' => $form['form_type'],
        'completed_at' => formatDate($form['completed_at']),
        'file_generated' => $form['file_generated']
    ];
}

echo json_encode([
    'success' => true,
    'current_status' => $application['current_status'],
    'submission_timestamp' => formatDate($application['submission_timestamp']),
    'existing_forms' => $existing_forms,
    'is_approved' => $application['current_status'] === 'APPROVED'
]);

closeDBConnection($conn);
?>
