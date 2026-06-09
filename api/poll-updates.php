<?php
declare(strict_types=1);

/**
 * Silent Reload Polling Endpoint
 * Returns a lightweight fingerprint of the current state.
 * Clients compare against their stored fingerprint to detect real changes.
 * TAU-TeSI Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Must be authenticated staff
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'applications';

try {
    $conn = getDBConnection();
    $data = [];

    if ($type === 'applications' || $type === 'dashboard') {
        // Count + latest submission timestamp
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS total,
                    MAX(submission_timestamp) AS latest_submission,
                    SUM(CASE WHEN current_status IN (\'STAFF_REVIEW_REQUIRED\',\'COMPLIANCE_REVIEW\',\'REQUIREMENTS_PENDING\') THEN 1 ELSE 0 END) AS pending
             FROM applications'
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        // Unread messages count
        $msg_stmt = $conn->prepare(
            'SELECT COUNT(*) AS unread FROM messages WHERE read_status = 0 AND sender_type = \'applicant\''
        );
        $msg_stmt->execute();
        $msg = $msg_stmt->get_result()->fetch_assoc();

        $data = [
            'total_applications' => (int) ($row['total'] ?? 0),
            'pending'            => (int) ($row['pending'] ?? 0),
            'latest_submission'  => $row['latest_submission'] ?? '',
            'unread_messages'    => (int) ($msg['unread'] ?? 0),
        ];
    }

    if ($type === 'explorer') {
        // Fingerprint the uploads folder by counting files and recording latest mtime
        $uploads_path = __DIR__ . '/../uploads/';
        $file_count   = 0;
        $latest_mtime = 0;

        if (is_dir($uploads_path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $file_count++;
                    $mt = $file->getMTime();
                    if ($mt > $latest_mtime) {
                        $latest_mtime = $mt;
                    }
                }
            }
        }

        $data = [
            'file_count'   => $file_count,
            'latest_mtime' => $latest_mtime,
        ];
    }

    // Build a deterministic fingerprint string and hash it
    $fingerprint = md5(json_encode($data));

    echo json_encode([
        'fingerprint' => $fingerprint,
        'data'        => $data,
        'ts'          => time(),
    ]);

    closeDBConnection($conn);

} catch (Throwable $e) {
    error_log('[poll-updates] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Poll failed']);
}
