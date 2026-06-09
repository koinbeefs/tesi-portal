<?php
/**
 * Secure File Download Handler for Folder Explorer
 * TAU-TeSI Portal - Staff Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Require staff login
requireLogin();

$folder_type = $_GET['folder'] ?? '';
$queue_number = $_GET['queue'] ?? '';
$filename = $_GET['file'] ?? '';

// Validate inputs
if (empty($folder_type) || empty($queue_number) || empty($filename)) {
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

// Validate folder type
if (!in_array($folder_type, ['QF39', 'QF40'])) {
    http_response_code(400);
    echo 'Invalid folder type';
    exit;
}

// Sanitize inputs
$folder_type = sanitizeInput($folder_type);
$queue_number = sanitizeInput($queue_number);
$filename = sanitizeInput($filename);

// Construct file path
$base_path = __DIR__ . '/../uploads/';
$file_path = $base_path . $folder_type . '/' . $queue_number . '/' . $filename;

// Security checks
if (!file_exists($file_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

if (!is_file($file_path)) {
    http_response_code(400);
    echo 'Invalid file request';
    exit;
}

// Check if file is in allowed directory
$real_base_path = realpath($base_path);
$real_file_path = realpath($file_path);

if ($real_file_path === false || strpos($real_file_path, $real_base_path) !== 0) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Get file info
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

$content_type = $content_types[$file_extension] ?? 'application/octet-stream';

// Log the download activity
$conn = getDBConnection();
$log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, queue_number, action_type, description, timestamp) VALUES (?, ?, 'download', ?, NOW())");
$description = "Downloaded {$folder_type} file: {$filename} from queue {$queue_number} via Folder Explorer";
$log_stmt->bind_param("isss", $_SESSION['user_id'], $queue_number, $description, $queue_number);
$log_stmt->execute();

// Set headers for download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($file_path);
exit;
?>
