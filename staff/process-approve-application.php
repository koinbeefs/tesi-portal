<?php
/**
 * Process Approve Application
 * TAU-TeSI Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$review_type = $_POST['review_type'] ?? '';

if (empty($queue_number) || empty($review_type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit();
}

// Validate review type
$valid_review_types = ['exempt', 'expedited', 'full'];
if (!in_array($review_type, $valid_review_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid review type.']);
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit();
}

// Check if user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to approve this application.']);
    exit();
}

// Check if application is in a valid state for approval
$valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
if (!in_array($application['current_status'], $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Application is not in a valid state for approval.']);
    exit();
}

// Update application status and category
$update_stmt = $conn->prepare("UPDATE applications SET current_status = 'APPROVED', category = ?, approved_at = NOW(), approved_by = ? WHERE queue_number = ?");
$update_stmt->bind_param("sis", $review_type, $_SESSION['user_id'], $queue_number);

if ($update_stmt->execute()) {
    // Log the approval activity
    logStaffActivity($_SESSION['user_id'], $queue_number, 'application_approved', "Application approved with $review_type review");

    // Add status history entry
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, old_status, new_status, changed_by_type, changed_by, notes) VALUES (?, ?, 'APPROVED', 'staff', ?, ?)");
    $notes = "Application approved for " . ucfirst($review_type) . " Review";
    $history_stmt->bind_param("ssis", $queue_number, $application['current_status'], $_SESSION['user_id'], $notes);
    $history_stmt->execute();

    // Send approval notification email
    $template_code = 'APPLICATION_APPROVED';
    $subject = "Application Approved - " . ucfirst($review_type) . " Review Required";
    $body = getEmailTemplate($template_code);

    if ($body) {
        // Replace placeholders
        $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
        $body = str_replace('{{queue_number}}', $queue_number, $body);
        $body = str_replace('{{review_type}}', ucfirst($review_type), $body);

        // Send email
        sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'approval');
    }

    // Log system message
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, subject, message_body, message_type, created_by) VALUES (?, ?, ?, 'approval', ?)");
    $msg_stmt->bind_param("sssi", $queue_number, $subject, $body, $_SESSION['user_id']);
    $msg_stmt->execute();

    closeDBConnection($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Application approved successfully.',
        'review_type' => $review_type
    ]);
}
else {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to approve application. Please try again.']);
}

exit();
?>