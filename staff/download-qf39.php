<?php
/**
 * Download QF-39 Form
 * TAU-TeSI Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();

// Get QF-39 form record
$stmt = $conn->prepare("SELECT file_path, completed_at FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=form_not_found');
    exit();
}

if (!$result['file_path'] || !file_exists($result['file_path'])) {
    // If file doesn't exist, try to find it in the standard location
    $standard_path = '../uploads/QF39/' . $queue_number . '/QF-39_' . $queue_number . '.docx';
    if (file_exists($standard_path)) {
        // Update database with correct path
        $update_stmt = $conn->prepare("UPDATE fillable_forms SET file_path = ? WHERE queue_number = ? AND form_type = 'qf39'");
        $update_stmt->bind_param("ss", $standard_path, $queue_number);
        $update_stmt->execute();
        $file_path = $standard_path;
    } else {
        header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=file_not_found');
        exit();
    }
} else {
    $file_path = $result['file_path'];
}

$file_name = 'TAU-DRD-QF-39_' . $queue_number . '.docx';

// Log the download activity
logStaffActivity($_SESSION['user_id'], $queue_number, 'downloaded_document', 'Downloaded QF-39 form');

// Set headers for file download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file
readfile($file_path);
exit;
?>
