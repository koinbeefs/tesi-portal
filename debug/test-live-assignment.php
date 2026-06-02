<?php
require_once __DIR__ . '/../config/config.php';

// Simulate logging in as a staff user
session_start();
$_SESSION['user_id'] = 2; // jdirector
$_SESSION['role'] = 'staff';
$_SESSION['username'] = 'jdirector';
$_SESSION['full_name'] = 'Dr. Juan Dela Cruz';

echo "<h2>Simulating Staff Login and Assignment</h2>";
echo "Logged in as: User ID {$_SESSION['user_id']} ({$_SESSION['username']})<br>";

$staff_id = $_SESSION['user_id'];
$queue_number = 'PLA-0008'; // Use an unassigned application

echo "Attempting to access application: $queue_number<br>";

$conn = getDBConnection();

// Get application details (like view-application.php does)
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo "❌ Application not found<br>";
    exit;
}

echo "Application found:<br>";
echo "- assigned_staff_id: " . ($application['assigned_staff_id'] ?? 'NULL') . "<br>";
echo "- current_status: {$application['current_status']}<br>";

// Test the exact assignment logic from view-application.php
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    echo "✅ Application is eligible for assignment<br>";
    
    // Use a transaction to prevent race conditions
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
                $just_claimed = true;
                $application['assigned_staff_id'] = $staff_id;
                echo "✅ Assignment successful! Affected rows: {$claim_stmt->affected_rows}<br>";
                
                // Log the auto-claim activity
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (staff_id, queue_number, activity_type, description, timestamp) VALUES (?, ?, 'other', 'Auto-claimed application for review', NOW())");
                $log_stmt->bind_param("is", $staff_id, $queue_number);
                $log_stmt->execute();
                echo "✅ Activity logged<br>";
            } else {
                echo "❌ Assignment failed - no rows affected<br>";
                echo "SQL Error: " . $claim_stmt->error . "<br>";
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
    echo "- current_status: {$application['current_status']}<br>";
}

// Verify final assignment
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "<br><strong>Final assigned_staff_id: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "</strong><br>";

// Test if the staff can access their own application
echo "<h3>Testing Staff Access</h3>";
$can_edit = ($verify_result['assigned_staff_id'] == $staff_id || !$verify_result['assigned_staff_id']);
echo "Can edit: " . ($can_edit ? 'YES' : 'NO') . "<br>";

// Get staff name for display
$assigned_staff_name = null;
if ($verify_result['assigned_staff_id']) {
    $assigned_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $assigned_stmt->bind_param("i", $verify_result['assigned_staff_id']);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result()->fetch_assoc();
    $assigned_staff_name = $assigned_result['full_name'];
}

echo "Assigned staff name: " . ($assigned_staff_name ?? 'NULL') . "<br>";
?>
