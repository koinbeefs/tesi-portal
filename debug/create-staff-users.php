<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Creating Missing Staff Users</h2>";

$staff_users = [
    ['username' => 'dtabuan', 'full_name' => 'Daxton Jersield D. Tabuan', 'role' => 'staff', 'email' => 'djdtabuan2022@tau.edu.ph', 'department' => 'Research and Development'],
    ['username' => 'kbuenaventura', 'full_name' => 'Koleen Buenaventura', 'role' => 'staff', 'email' => 'kbuenaventura2022@tau.edu.ph', 'department' => 'Research and Development'],
    ['username' => 'rjgabriel', 'full_name' => 'Robert John Gabriel', 'role' => 'staff', 'email' => 'rjgabriel@tesi.edu.ph', 'department' => 'Research and Development']
];

foreach ($staff_users as $staff) {
    // Check if user already exists
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $staff['username']);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        // Create new user
        $password_hash = password_hash('admin', PASSWORD_DEFAULT);
        
        $insert_stmt = $conn->prepare("
            INSERT INTO users (username, email, full_name, password_hash, role, active_status, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $insert_stmt->bind_param("sssss", 
            $staff['username'], 
            $staff['email'], 
            $staff['full_name'], 
            $password_hash, 
            $staff['role']
        );
        
        if ($insert_stmt->execute()) {
            echo "✅ Created user: {$staff['username']} ({$staff['full_name']})<br>";
        } else {
            echo "❌ Failed to create user: {$staff['username']}<br>";
        }
    } else {
        echo "⚠️ User already exists: {$staff['username']}<br>";
    }
}

echo "<h2>All Users After Creation:</h2>";

$result = $conn->query("SELECT user_id, username, full_name, role FROM users ORDER BY user_id");
while ($row = $result->fetch_assoc()) {
    echo "- User ID {$row['user_id']}: {$row['username']} ({$row['full_name']}) - Role: {$row['role']}<br>";
}

echo "<h2>Testing Assignment with New Staff User</h2>";

// Test assignment with user ID 2 (should be jdirector now)
$staff_id = 2;
$queue_number = 'PLA-0005'; // Use another unassigned application

$claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ?");
$claim_stmt->bind_param("is", $staff_id, $queue_number);
$claim_stmt->execute();

if ($claim_stmt->affected_rows > 0) {
    echo "✅ Assignment successful with new staff user ID $staff_id!<br>";
} else {
    echo "❌ Assignment failed<br>";
}

// Verify
$verify_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
$verify_stmt->bind_param("s", $queue_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result()->fetch_assoc();

echo "Final assigned_staff_id for $queue_number: " . ($verify_result['assigned_staff_id'] ?? 'NULL') . "<br>";
?>
