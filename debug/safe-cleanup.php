<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>🧹 Safe Database Cleanup</h2>";
echo "<p style='color: #28a745; font-weight: bold;'>✅ Safe operations that won't affect active applications</p>";

$conn = getDBConnection();

// Get current statistics
echo "<h3>📊 Current Database Statistics</h3>";

$tables = [
    'applications' => 'Active Applications',
    'documents' => 'Uploaded Documents', 
    'fillable_forms' => 'Generated Forms',
    'staff_logs' => 'Activity Logs',
    'status_history' => 'Status Changes',
    'system_messages' => 'System Messages'
];

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f8f9fa;'><th>Table</th><th>Description</th><th>Records</th><th>Cleanup Action</th></tr>";

foreach ($tables as $table => $description) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $result->fetch_assoc()['count'];
    
    echo "<tr>";
    echo "<td><strong>$table</strong></td>";
    echo "<td>$description</td>";
    echo "<td>$count</td>";
    echo "<td>";
    
    switch($table) {
        case 'staff_logs':
            $old_logs = $conn->query("SELECT COUNT(*) as count FROM staff_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)")->fetch_assoc()['count'];
            echo "<button onclick='cleanOldLogs()' style='background: #ffc107; color: black; border: none; padding: 5px 10px; cursor: pointer;'>Clean Old Logs ($old_logs)</button>";
            break;
            
        case 'system_messages':
            $old_messages = $conn->query("SELECT COUNT(*) as count FROM system_messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)")->fetch_assoc()['count'];
            echo "<button onclick='cleanOldMessages()' style='background: #ffc107; color: black; border: none; padding: 5px 10px; cursor: pointer;'>Clean Read Messages ($old_messages)</button>";
            break;
            
        case 'documents':
            $orphaned_docs = $conn->query("
                SELECT COUNT(*) as count FROM documents d 
                LEFT JOIN applications a ON d.queue_number = a.queue_number 
                WHERE a.queue_number IS NULL
            ")->fetch_assoc()['count'];
            echo "<button onclick='cleanOrphanedDocs()' style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;'>Clean Orphaned ($orphaned_docs)</button>";
            break;
            
        case 'fillable_forms':
            $orphaned_forms = $conn->query("
                SELECT COUNT(*) as count FROM fillable_forms f 
                LEFT JOIN applications a ON f.queue_number = a.queue_number 
                WHERE a.queue_number IS NULL
            ")->fetch_assoc()['count'];
            echo "<button onclick='cleanOrphanedForms()' style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;'>Clean Orphaned ($orphaned_forms)</button>";
            break;
            
        case 'status_history':
            $orphaned_history = $conn->query("
                SELECT COUNT(*) as count FROM status_history h 
                LEFT JOIN applications a ON h.queue_number = a.queue_number 
                WHERE a.queue_number IS NULL
            ")->fetch_assoc()['count'];
            echo "<button onclick='cleanOrphanedHistory()' style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;'>Clean Orphaned ($orphaned_history)</button>";
            break;
            
        case 'applications':
            $test_apps = $conn->query("SELECT COUNT(*) as count FROM applications WHERE queue_number LIKE 'TEST%' OR queue_number LIKE 'DEMO%'")->fetch_assoc()['count'];
            echo "<button onclick='cleanTestApps()' style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;'>Clean Test Apps ($test_apps)</button>";
            break;
    }
    
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>🔍 Detailed Analysis</h3>";

// Check for test/demo applications
echo "<h4>Test/Demo Applications</h4>";
$test_apps = $conn->query("
    SELECT queue_number, applicant_name, submission_timestamp, current_status 
    FROM applications 
    WHERE queue_number LIKE 'TEST%' OR queue_number LIKE 'DEMO%'
    ORDER BY submission_timestamp DESC
    LIMIT 10
");

if ($test_apps->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr style='background: #fff3cd;'><th>Queue</th><th>Name</th><th>Submitted</th><th>Status</th></tr>";
    while ($row = $test_apps->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['queue_number']}</strong></td>";
        echo "<td>{$row['applicant_name']}</td>";
        echo "<td>{$row['submission_timestamp']}</td>";
        echo "<td>{$row['current_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>✅ No test/demo applications found.</p>";
}

// Check for very old applications
echo "<h4>Applications Older Than 2 Years</h4>";
$old_apps = $conn->query("
    SELECT queue_number, applicant_name, submission_timestamp, current_status 
    FROM applications 
    WHERE submission_timestamp < DATE_SUB(NOW(), INTERVAL 2 YEAR)
    ORDER BY submission_timestamp ASC
    LIMIT 10
");

if ($old_apps->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr style='background: #f8d7da;'><th>Queue</th><th>Name</th><th>Submitted</th><th>Status</th><th>Age (days)</th></tr>";
    while ($row = $old_apps->fetch_assoc()) {
        $age = floor((time() - strtotime($row['submission_timestamp'])) / (60 * 60 * 24));
        echo "<tr>";
        echo "<td><strong>{$row['queue_number']}</strong></td>";
        echo "<td>{$row['applicant_name']}</td>";
        echo "<td>{$row['submission_timestamp']}</td>";
        echo "<td>{$row['current_status']}</td>";
        echo "<td><strong>$age</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>✅ No applications older than 2 years found.</p>";
}

echo "<h3>🚀 Execute Safe Cleanup</h3>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<p><strong>Recommended cleanup order:</strong></p>";
echo "<ol>";
echo "<li>Clean orphaned records (documents, forms, history)</li>";
echo "<li>Clean old staff logs (6+ months)</li>";
echo "<li>Clean read system messages (3+ months)</li>";
echo "<li>Clean test/demo applications (if any)</li>";
echo "</ol>";
echo "</div>";

echo "<button onclick='executeAllSafeCleanup()' style='background: #28a745; color: white; border: none; padding: 12px 24px; cursor: pointer; font-size: 16px; font-weight: bold;'>Execute All Safe Cleanup</button>";
echo "<div id='cleanupResults' style='margin-top: 20px;'></div>";
?>

<script>
function cleanOldLogs() {
    if (!confirm('Remove staff logs older than 6 months?')) return;
    executeCleanup('clean_old_logs');
}

function cleanOldMessages() {
    if (!confirm('Remove read system messages older than 3 months?')) return;
    executeCleanup('clean_old_messages');
}

function cleanOrphanedDocs() {
    if (!confirm('Remove orphaned documents (no matching application)?')) return;
    executeCleanup('clean_orphaned_docs');
}

function cleanOrphanedForms() {
    if (!confirm('Remove orphaned fillable forms (no matching application)?')) return;
    executeCleanup('clean_orphaned_forms');
}

function cleanOrphanedHistory() {
    if (!confirm('Remove orphaned status history (no matching application)?')) return;
    executeCleanup('clean_orphaned_history');
}

function cleanTestApps() {
    if (!confirm('Remove all TEST/DEMO applications? This will also delete their related records.')) return;
    executeCleanup('clean_test_apps');
}

function executeAllSafeCleanup() {
    if (!confirm('Execute all safe cleanup operations? This will remove old logs, read messages, and orphaned records.')) return;
    
    document.getElementById('cleanupResults').innerHTML = '<div style="background: #d1ecf1; padding: 15px; border-radius: 5px;"><p>⏳ Executing safe cleanup...</p></div>';
    
    executeCleanup('full_safe_cleanup');
}

function executeCleanup(action) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}`
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('cleanupResults').innerHTML = data;
        setTimeout(() => location.reload(), 3000);
    });
}
</script>

<?php
// Handle cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = getDBConnection();
    $deleted = 0;
    $results = [];
    
    switch($action) {
        case 'clean_old_logs':
            $result = $conn->query("DELETE FROM staff_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted old staff logs";
            break;
            
        case 'clean_old_messages':
            $result = $conn->query("DELETE FROM system_messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted old read messages";
            break;
            
        case 'clean_orphaned_docs':
            $result = $conn->query("DELETE d FROM documents d LEFT JOIN applications a ON d.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted orphaned documents";
            break;
            
        case 'clean_orphaned_forms':
            $result = $conn->query("DELETE f FROM fillable_forms f LEFT JOIN applications a ON f.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted orphaned fillable forms";
            break;
            
        case 'clean_orphaned_history':
            $result = $conn->query("DELETE h FROM status_history h LEFT JOIN applications a ON h.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted orphaned status history records";
            break;
            
        case 'clean_test_apps':
            // First delete related records
            $conn->query("DELETE d FROM documents d LEFT JOIN applications a ON d.queue_number = a.queue_number WHERE a.queue_number LIKE 'TEST%' OR a.queue_number LIKE 'DEMO%'");
            $conn->query("DELETE f FROM fillable_forms f LEFT JOIN applications a ON f.queue_number = a.queue_number WHERE a.queue_number LIKE 'TEST%' OR a.queue_number LIKE 'DEMO%'");
            $conn->query("DELETE h FROM status_history h LEFT JOIN applications a ON h.queue_number = a.queue_number WHERE a.queue_number LIKE 'TEST%' OR a.queue_number LIKE 'DEMO%'");
            // Then delete applications
            $result = $conn->query("DELETE FROM applications WHERE queue_number LIKE 'TEST%' OR queue_number LIKE 'DEMO%'");
            $deleted = $result->affected_rows;
            $results[] = "✅ Deleted $deleted test/demo applications and related records";
            break;
            
        case 'full_safe_cleanup':
            // Execute all safe cleanup operations
            $operations = [
                'clean_orphaned_docs',
                'clean_orphaned_forms', 
                'clean_orphaned_history',
                'clean_old_logs',
                'clean_old_messages'
            ];
            
            foreach ($operations as $op) {
                $_POST['action'] = $op;
                // This would recursively call the same logic, but for simplicity, let's do it directly:
            }
            
            // Do all operations directly
            $total_deleted = 0;
            
            $result = $conn->query("DELETE d FROM documents d LEFT JOIN applications a ON d.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $total_deleted += $result->affected_rows;
            
            $result = $conn->query("DELETE f FROM fillable_forms f LEFT JOIN applications a ON f.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $total_deleted += $result->affected_rows;
            
            $result = $conn->query("DELETE h FROM status_history h LEFT JOIN applications a ON h.queue_number = a.queue_number WHERE a.queue_number IS NULL");
            $total_deleted += $result->affected_rows;
            
            $result = $conn->query("DELETE FROM staff_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
            $total_deleted += $result->affected_rows;
            
            $result = $conn->query("DELETE FROM system_messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)");
            $total_deleted += $result->affected_rows;
            
            // Optimize tables
            $tables = ['documents', 'fillable_forms', 'status_history', 'staff_logs', 'system_messages'];
            foreach ($tables as $table) {
                $conn->query("OPTIMIZE TABLE $table");
            }
            
            echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px;'>";
            echo "<h3>✅ Safe Cleanup Complete!</h3>";
            echo "<p><strong>Total records deleted:</strong> $total_deleted</p>";
            echo "<p><strong>Tables optimized:</strong> " . implode(', ', $tables) . "</p>";
            echo "<p><strong>Operations completed:</strong></p>";
            echo "<ul>";
            echo "<li>✅ Removed orphaned documents</li>";
            echo "<li>✅ Removed orphaned fillable forms</li>";
            echo "<li>✅ Removed orphaned status history</li>";
            echo "<li>✅ Removed old staff logs (6+ months)</li>";
            echo "<li>✅ Removed old read messages (3+ months)</li>";
            echo "<li>✅ Optimized all tables</li>";
            echo "</ul>";
            echo "<p><em>Page will refresh in 3 seconds...</em></p>";
            echo "</div>";
            break;
    }
    
    if ($action !== 'full_safe_cleanup' && !empty($results)) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ Cleanup Operation Complete</h4>";
        foreach ($results as $result) {
            echo "<p>$result</p>";
        }
        echo "<p><em>Page will refresh in 3 seconds...</em></p>";
        echo "</div>";
    }
}
?>
