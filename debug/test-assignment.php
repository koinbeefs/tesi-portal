<?php
require_once __DIR__ . '/../config/config.php';

// Simulate a staff session
session_start();
$_SESSION['user_id'] = 2; // Simulate staff user ID 2
$_SESSION['role'] = 'staff';
$_SESSION['username'] = 'teststaff';

$staff_id = $_SESSION['user_id'];
$queue_number = 'PLA-0004'; // Test with an unassigned application

echo "<h2>Testing Assignment Logic</h2>";
echo "Staff ID: $staff_id<br>";
echo "Queue Number: $queue_number<br>";

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo "❌ Application not found<br>";
    exit;
}

echo "Current assigned_staff_id: " . ($application['assigned_staff_id'] ?? 'NULL') . "<br>";
echo "Current status: {$application['current_status']}<br>";

// Test assignment logic
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    echo "✅ Application is unassigned and eligible for assignment<br>";
    
    // Use transaction to prevent race conditions
    $conn->begin_transaction();
    
    try {
        // Check if still unassigned (double-check)
        $check_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ? FOR UPDATE");
        $check_stmt->bind_param("s", $queue_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if (!$check_result['assigned_staff_id']) {
            echo "✅ Double-check confirms unassigned<br>";
            
            // Assign to current staff member
            $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ? AND assigned_staff_id IS NULL");
            $claim_stmt->bind_param("is", $staff_id, $queue_number);
            $claim_stmt->execute();
            
            if ($claim_stmt->affected_rows > 0) {
                echo "✅ Assignment successful! Affected rows: {$claim_stmt->affected_rows}<br>";
                $application['assigned_staff_id'] = $staff_id;
            } else {
                echo "❌ Assignment failed - no rows affected<br>";
            }
        } else {
            echo "❌ Application was assigned by another user during check<br>";
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Assignment error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Application is not eligible for assignment<br>";
    echo "- assigned_staff_id: " . ($application['assigned_staff_id'] ?? 'NULL') . "<br>";
    echo "- status: {$application['current_status']}<br>";
}

// Verify the assignment
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "<br><strong>Final assigned_staff_id: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "</strong><br>";
?>
