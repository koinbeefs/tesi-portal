<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Checking Applications Table Structure</h2>";

// Check if assigned_staff_id column exists
$result = $conn->query("SHOW COLUMNS FROM applications LIKE 'assigned_staff_id'");
if ($result->num_rows > 0) {
    echo "✅ assigned_staff_id column exists<br>";
} else {
    echo "❌ assigned_staff_id column does NOT exist<br>";
}

// Show all columns
echo "<h3>All Columns in Applications Table:</h3>";
$result = $conn->query("DESCRIBE applications");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})<br>";
}

// Check sample data
echo "<h3>Sample Applications Data:</h3>";
$result = $conn->query("SELECT queue_number, assigned_staff_id, current_status FROM applications LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['queue_number']}: assigned_staff_id = " . ($row['assigned_staff_id'] ?? 'NULL') . ", status = {$row['current_status']}<br>";
}

// Check users table
echo "<h3>Users Table:</h3>";
$result = $conn->query("SELECT user_id, username, full_name FROM users LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "- User ID {$row['user_id']}: {$row['username']} ({$row['full_name']})<br>";
}
?>
