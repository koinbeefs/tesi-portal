<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Status History Table Structure</h2>";

$conn = getDBConnection();

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'status_history'");
if ($result->num_rows == 0) {
    echo "❌ status_history table does not exist<br>";
    echo "💡 Need to create the table or use a different logging approach<br>";
} else {
    echo "✅ status_history table exists<br>";
    
    // Show structure
    $result = $conn->query("DESCRIBE status_history");
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
    }
    
    // Show recent entries
    echo "<h3>Recent Status History:</h3>";
    $result = $conn->query("SELECT * FROM status_history ORDER BY timestamp DESC LIMIT 3");
    if ($result->num_rows == 0) {
        echo "No status history entries found<br>";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['timestamp']}: " . json_encode($row) . "<br>";
        }
    }
}

echo "<h3>Alternative: Check staff_logs for status changes</h3>";
$result = $conn->query("SELECT * FROM staff_logs WHERE action_type = 'approved' ORDER BY timestamp DESC LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['timestamp']}: {$row['action_type']} - {$row['action_details']}<br>";
}

echo "<h3>💡 Solution Options:</h3>";
echo "1. Remove status_history insertion (use only staff_logs)<br>";
echo "2. Create status_history table with proper columns<br>";
echo "3. Use existing logging system only<br>";
?>
