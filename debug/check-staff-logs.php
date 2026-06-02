<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Staff Logs Table Structure</h2>";

// Check if staff_logs table exists
$result = $conn->query("SHOW TABLES LIKE 'staff_logs'");
if ($result->num_rows > 0) {
    echo "✅ staff_logs table exists<br>";
    
    $result = $conn->query("DESCRIBE staff_logs");
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
    }
} else {
    echo "❌ staff_logs table does not exist<br>";
    
    // Check for staff_activity_logs instead
    $result = $conn->query("SHOW TABLES LIKE 'staff_activity_logs'");
    if ($result->num_rows > 0) {
        echo "✅ staff_activity_logs table exists<br>";
        
        $result = $conn->query("DESCRIBE staff_activity_logs");
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
        }
    } else {
        echo "❌ No staff logs table found<br>";
    }
}

echo "<h2>Testing Assignment Without Logging</h2>";

// Test assignment without the problematic logging
session_start();
$_SESSION['user_id'] = 2; // jdirector
$_SESSION['role'] = 'staff';

$staff_id = $_SESSION['user_id'];
$queue_number = 'PLA-0005'; // Use an unassigned application

$conn->begin_transaction();

try {
    // Assign to current staff member (without logging)
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ? AND assigned_staff_id IS NULL");
    $claim_stmt->bind_param("is", $staff_id, $queue_number);
    $claim_stmt->execute();
    
    if ($claim_stmt->affected_rows > 0) {
        echo "✅ Assignment successful! Affected rows: {$claim_stmt->affected_rows}<br>";
    } else {
        echo "❌ Assignment failed - no rows affected<br>";
    }
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Assignment error: " . $e->getMessage() . "<br>";
}

// Verify
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "Final assigned_staff_id: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "<br>";
?>
