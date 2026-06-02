<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>🧹 Database Cleanup Utility</h2>";
echo "<p style='color: red; font-weight: bold;'>⚠️ WARNING: This will permanently delete data. Backup your database before proceeding!</p>";

$conn = getDBConnection();

// Function to get table row count
function getRowCount($conn, $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    return $result->fetch_assoc()['count'];
}

// Function to get table size
function getTableSize($conn, $table) {
    $result = $conn->query("SELECT 
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB' 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE() AND table_name = '$table'");
    $row = $result->fetch_assoc();
    return $row['Size_MB'] ?? 0;
}

echo "<h3>📊 Current Database Status</h3>";

$tables = ['applications', 'documents', 'fillable_forms', 'staff_logs', 'status_history', 'system_messages'];

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Table</th><th>Rows</th><th>Size (MB)</th><th>Action</th></tr>";

foreach ($tables as $table) {
    $count = getRowCount($conn, $table);
    $size = getTableSize($conn, $table);
    echo "<tr>";
    echo "<td>$table</td>";
    echo "<td>$count</td>";
    echo "<td>$size</td>";
    echo "<td><button onclick='cleanTable(\"$table\")' style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;'>Clean</button></td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>🔧 Cleanup Options</h3>";

// 1. Clean old applications (keep only recent ones)
echo "<h4>1. Applications Table</h4>";
echo "<p>Remove applications older than 1 year or test applications:</p>";

// Show old applications
$old_apps = $conn->query("
    SELECT queue_number, applicant_name, submission_timestamp, current_status 
    FROM applications 
    WHERE submission_timestamp < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    OR queue_number LIKE 'TEST%'
    OR queue_number LIKE 'DEMO%'
    ORDER BY submission_timestamp DESC
    LIMIT 10
");

if ($old_apps->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr><th>Queue</th><th>Name</th><th>Submitted</th><th>Status</th></tr>";
    while ($row = $old_apps->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['queue_number']}</td>";
        echo "<td>{$row['applicant_name']}</td>";
        echo "<td>{$row['submission_timestamp']}</td>";
        echo "<td>{$row['current_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>✅ No old applications found.</p>";
}

// 2. Clean orphaned records
echo "<h4>2. Orphaned Records Cleanup</h4>";
echo "<p>Remove records that don't have corresponding parent records:</p>";

// Check for orphaned documents
$orphaned_docs = $conn->query("
    SELECT d.document_id, d.queue_number 
    FROM documents d 
    LEFT JOIN applications a ON d.queue_number = a.queue_number 
    WHERE a.queue_number IS NULL
")->num_rows;

echo "- Orphaned documents: $orphaned_docs<br>";

// Check for orphaned fillable_forms
$orphaned_forms = $conn->query("
    SELECT f.form_id, f.queue_number 
    FROM fillable_forms f 
    LEFT JOIN applications a ON f.queue_number = a.queue_number 
    WHERE a.queue_number IS NULL
")->num_rows;

echo "- Orphaned fillable_forms: $orphaned_forms<br>";

// Check for orphaned status_history
$orphaned_history = $conn->query("
    SELECT h.history_id, h.queue_number 
    FROM status_history h 
    LEFT JOIN applications a ON h.queue_number = a.queue_number 
    WHERE a.queue_number IS NULL
")->num_rows;

echo "- Orphaned status_history: $orphaned_history<br>";

// 3. Clean old logs
echo "<h4>3. Logs Cleanup</h4>";
echo "<p>Remove staff logs older than 6 months:</p>";

$old_logs = $conn->query("
    SELECT COUNT(*) as count 
    FROM staff_logs 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)
")->fetch_assoc()['count'];

echo "- Old staff logs (6+ months): $old_logs<br>";

// 4. Clean system messages
echo "<h4>4. System Messages</h4>";
echo "<p>Remove read system messages older than 3 months:</p>";

$old_messages = $conn->query("
    SELECT COUNT(*) as count 
    FROM system_messages 
    WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
")->fetch_assoc()['count'];

echo "- Old read messages (3+ months): $old_messages<br>";

echo "<h3>🚀 Execute Cleanup</h3>";
echo "<button onclick='executeFullCleanup()' style='background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; font-size: 16px;'>Execute Full Cleanup</button>";
echo "<div id='cleanupResults' style='margin-top: 20px;'></div>";
?>

<script>
function cleanTable(table) {
    if (!confirm(`Are you sure you want to clean the ${table} table? This will delete all data.`)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=clean_table&table=${table}`
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('cleanupResults').innerHTML = data;
        location.reload();
    });
}

function executeFullCleanup() {
    if (!confirm('Are you sure you want to execute full database cleanup? This will delete old data and optimize tables.')) {
        return;
    }
    
    document.getElementById('cleanupResults').innerHTML = '<p>⏳ Executing cleanup...</p>';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=full_cleanup'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('cleanupResults').innerHTML = data;
    });
}
</script>

<?php
// Handle cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clean_table') {
        $table = $_POST['table'] ?? '';
        if (in_array($table, $tables)) {
            $conn->query("DELETE FROM $table");
            echo "✅ Cleaned $table table";
        }
    } elseif ($action === 'full_cleanup') {
        $deleted = 0;
        
        // 1. Clean old applications
        $old_apps = $conn->query("
            DELETE FROM applications 
            WHERE submission_timestamp < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            OR queue_number LIKE 'TEST%'
            OR queue_number LIKE 'DEMO%'
        ");
        $deleted += $old_apps->affected_rows;
        
        // 2. Clean orphaned records
        $orphaned_docs = $conn->query("
            DELETE d FROM documents d 
            LEFT JOIN applications a ON d.queue_number = a.queue_number 
            WHERE a.queue_number IS NULL
        ");
        $deleted += $orphaned_docs->affected_rows;
        
        $orphaned_forms = $conn->query("
            DELETE f FROM fillable_forms f 
            LEFT JOIN applications a ON f.queue_number = a.queue_number 
            WHERE a.queue_number IS NULL
        ");
        $deleted += $orphaned_forms->affected_rows;
        
        $orphaned_history = $conn->query("
            DELETE h FROM status_history h 
            LEFT JOIN applications a ON h.queue_number = a.queue_number 
            WHERE a.queue_number IS NULL
        ");
        $deleted += $orphaned_history->affected_rows;
        
        // 3. Clean old logs
        $old_logs = $conn->query("
            DELETE FROM staff_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $deleted += $old_logs->affected_rows;
        
        // 4. Clean old messages
        $old_messages = $conn->query("
            DELETE FROM system_messages 
            WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");
        $deleted += $old_messages->affected_rows;
        
        // 5. Optimize tables
        foreach ($tables as $table) {
            $conn->query("OPTIMIZE TABLE $table");
        }
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h3>✅ Cleanup Complete!</h3>";
        echo "<p>Total records deleted: $deleted</p>";
        echo "<p>Tables optimized</p>";
        echo "<p><a href=''>Refresh page to see updated status</a></p>";
        echo "</div>";
    }
}
?>
