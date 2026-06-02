<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Final Assignment Test</h2>";

$conn = getDBConnection();

// Find a truly unassigned application
$result = $conn->query("SELECT queue_number FROM applications WHERE assigned_staff_id IS NULL LIMIT 1");
if ($result->num_rows == 0) {
    echo "❌ No unassigned applications found<br>";
    exit;
}

$unassigned_app = $result->fetch_assoc();
$queue_number = $unassigned_app['queue_number'];

echo "Testing with unassigned application: $queue_number<br>";

// Simulate staff login
session_start();
$_SESSION['user_id'] = 3; // mreyes
$_SESSION['role'] = 'staff';
$_SESSION['username'] = 'mreyes';

$staff_id = $_SESSION['user_id'];
echo "Staff ID: $staff_id<br>";

// Test the exact assignment logic from view-application.php
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

echo "Before assignment - assigned_staff_id: " . ($application['assigned_staff_id'] ?? 'NULL') . "<br>";

// Assignment logic
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    echo "✅ Application eligible for assignment<br>";
    
    $conn->begin_transaction();
    
    try {
        // Double-check
        $check_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ? FOR UPDATE");
        $check_stmt->bind_param("s", $queue_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if (!$check_result['assigned_staff_id']) {
            // Assign to current staff member
            $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ? AND assigned_staff_id IS NULL");
            $claim_stmt->bind_param("is", $staff_id, $queue_number);
            $claim_stmt->execute();
            
            if ($claim_stmt->affected_rows > 0) {
                $just_claimed = true;
                echo "✅ Assignment successful! Affected rows: {$claim_stmt->affected_rows}<br>";
                
                // Log the auto-claim activity (using correct column names)
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details, timestamp) VALUES (?, ?, 'other', 'Auto-claimed application for review', NOW())");
                $log_stmt->bind_param("is", $staff_id, $queue_number);
                $log_stmt->execute();
                echo "✅ Activity logged<br>";
            } else {
                echo "❌ Assignment failed - no rows affected<br>";
            }
        } else {
            echo "❌ Application was assigned during check<br>";
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Assignment error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Application not eligible for assignment<br>";
}

// Verify final assignment
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "<br><strong>Final assigned_staff_id: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "</strong><br>";

// Test if assignment shows in dashboard
echo "<h3>Dashboard View Test</h3>";
$apps_stmt = $conn->prepare("
    SELECT a.*, u.full_name as assigned_staff_name
    FROM applications a
    LEFT JOIN users u ON a.assigned_staff_id = u.user_id
    WHERE a.queue_number = ?
");
$apps_stmt->bind_param("s", $queue_number);
$apps_stmt->execute();
$dashboard_app = $apps_stmt->get_result()->fetch_assoc();

echo "Dashboard would show: " . ($dashboard_app['assigned_staff_name'] ?? 'Unassigned') . "<br>";
echo "Is assigned to current user: " . (($dashboard_app['assigned_staff_id'] == $staff_id) ? 'YES' : 'NO') . "<br>";
?>
