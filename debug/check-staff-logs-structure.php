<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Staff Logs Table Structure</h2>";

$conn = getDBConnection();

$result = $conn->query("DESCRIBE staff_logs");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
}

echo "<h3>Recent Staff Logs:</h3>";

$result = $conn->query("SELECT * FROM staff_logs ORDER BY timestamp DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['timestamp']}: {$row['action_type']} - {$row['action_details']}<br>";
}

echo "<h3>Check action_type length issue:</h3>";

// Test the problematic action_type
$test_action_type = 'application_approved';
echo "- Testing action_type: '$test_action_type'<br>";
echo "- Length: " . strlen($test_action_type) . " characters<br>";

// Check what's the maximum allowed length
$result = $conn->query("SHOW COLUMNS FROM staff_logs LIKE 'action_type'");
$column_info = $result->fetch_assoc();
echo "- Column type: {$column_info['Type']}<br>";

// Extract max length from type (e.g., varchar(50))
if (preg_match('/varchar\((\d+)\)/', $column_info['Type'], $matches)) {
    $max_length = $matches[1];
    echo "- Max allowed length: $max_length characters<br>";
    
    if (strlen($test_action_type) > $max_length) {
        echo "❌ PROBLEM: '$test_action_type' is too long!<br>";
        echo "💡 SOLUTION: Use shorter action_type like 'approved'<br>";
    } else {
        echo "✅ Length should be fine<br>";
    }
}
?>
