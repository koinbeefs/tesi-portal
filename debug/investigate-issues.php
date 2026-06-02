<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>🔍 Investigating Issues</h2>";

// Check PLA-0023 status
echo "<h3>PLA-0023 Status Check</h3>";
$queue_number = "PLA-0023";
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$app = $app_stmt->get_result()->fetch_assoc();

if ($app) {
    echo "- Current Status: <strong>{$app['current_status']}</strong><br>";
    echo "- Assigned Staff: " . ($app['assigned_staff_id'] ?? 'NULL') . "<br>";
    echo "- Similarity Index: " . ($app['similarity_index'] ?? 'NULL') . "<br>";
    echo "- Test Count: " . ($app['test_count'] ?? 'NULL') . "<br>";
    echo "- Approved At: " . ($app['approved_at'] ?? 'NULL') . "<br>";
    echo "- Approved By: " . ($app['approved_by'] ?? 'NULL') . "<br>";
    
    if ($app['current_status'] === 'APPROVED') {
        echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "❌ <strong>ISSUE:</strong> Application is APPROVED but shouldn't be!";
        echo "</div>";
    }
}

// Check QF-39 file path
echo "<h3>QF-39 File Path Check</h3>";
$qf39_stmt = $conn->prepare("SELECT * FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$qf39_stmt->bind_param("s", $queue_number);
$qf39_stmt->execute();
$qf39 = $qf39_stmt->get_result()->fetch_assoc();

if ($qf39) {
    echo "- File Generated: " . ($qf39['file_generated'] ? 'YES' : 'NO') . "<br>";
    echo "- File Path: " . ($qf39['file_path'] ?? 'NULL') . "<br>";
    echo "- Completed At: " . ($qf39['completed_at'] ?? 'NULL') . "<br>";
    
    if ($qf39['file_path'] && !file_exists($qf39['file_path'])) {
        echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "❌ <strong>ISSUE:</strong> File path exists but file not found at: {$qf39['file_path']}";
        echo "</div>";
        
        // Check if folder exists
        $folder = dirname($qf39['file_path']);
        if (!is_dir($folder)) {
            echo "📁 Folder does not exist: $folder<br>";
        } else {
            echo "📁 Folder exists: $folder<br>";
            // List files in folder
            $files = scandir($folder);
            echo "Files in folder: ";
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "$file ";
                }
            }
            echo "<br>";
        }
    }
}

// Check for automatic approval triggers
echo "<h3>Automatic Approval Triggers Check</h3>";

// Check if there's any logic that auto-approves based on QF-39 completion
echo "Checking for any recent status changes...<br>";

$history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 5");
$history_stmt->bind_param("s", $queue_number);
$history_stmt->execute();
$history = $history_stmt->get_result();

echo "<h4>Status History:</h4>";
while ($row = $history->fetch_assoc()) {
    echo "- {$row['timestamp']}: {$row['old_status']} → {$row['new_status']} (by: {$row['changed_by_type']} {$row['changed_by']})<br>";
    if (!empty($row['notes'])) {
        echo "  Notes: " . htmlspecialchars($row['notes']) . "<br>";
    }
}

// Check staff activity logs
echo "<h3>Staff Activity Check</h3>";
$activity_stmt = $conn->prepare("SELECT * FROM staff_logs WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 10");
$activity_stmt->bind_param("s", $queue_number);
$activity_stmt->execute();
$activities = $activity_stmt->get_result();

echo "<h4>Recent Activities:</h4>";
while ($row = $activities->fetch_assoc()) {
    echo "- {$row['timestamp']}: {$row['action_type']} - {$row['action_details']}<br>";
}

echo "<h3>🔧 Recommended Fixes</h3>";
echo "<ol>";
echo "<li><strong>Fix File Path:</strong> Ensure QF-39 folder exists and file is saved correctly</li>";
echo "<li><strong>Fix Auto-Approval:</strong> Find and remove any logic that auto-approves applications</li>";
echo "<li><strong>Fix Download:</strong> Update download handler to handle missing files gracefully</li>";
echo "</ol>";
?>
