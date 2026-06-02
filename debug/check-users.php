<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>All Users in Database</h2>";

$result = $conn->query("SELECT user_id, username, full_name, role FROM users ORDER BY user_id");
while ($row = $result->fetch_assoc()) {
    echo "- User ID {$row['user_id']}: {$row['username']} ({$row['full_name']}) - Role: {$row['role']}<br>";
}

echo "<h2>Testing Assignment with Valid User</h2>";

// Try with user ID 1 (admin) which we know exists
$staff_id = 1;
$queue_number = 'PLA-0004';

echo "Staff ID: $staff_id<br>";
echo "Queue Number: $queue_number<br>";

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

echo "Current assigned_staff_id: " . ($application['assigned_staff_id'] ?? 'NULL') . "<br>";

// Test assignment
if (!$application['assigned_staff_id']) {
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ?");
    $claim_stmt->bind_param("is", $staff_id, $queue_number);
    $claim_stmt->execute();
    
    if ($claim_stmt->affected_rows > 0) {
        echo "✅ Assignment successful with user ID 1!<br>";
    } else {
        echo "❌ Assignment failed<br>";
    }
}

// Verify
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "Final assigned_staff_id: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "<br>";
?>
