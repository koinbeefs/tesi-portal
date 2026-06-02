<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Current Application Statuses</h2>";

$result = $conn->query("SELECT queue_number, current_status, assigned_staff_id, similarity_index FROM applications ORDER BY queue_number LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['queue_number']}: {$row['current_status']} (assigned to: {$row['assigned_staff_id']}, similarity: {$row['similarity_index']})<br>";
}

echo "<h2>Check for QF-40 Certificates</h2>";

$result = $conn->query("SELECT queue_number, form_type, file_generated, completed_at FROM fillable_forms WHERE form_type = 'qf40'");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['queue_number']}: QF-40 file_generated = " . ($row['file_generated'] ? 'YES' : 'NO') . " ({$row['completed_at']})<br>";
}

echo "<h2>Status Logic Check</h2>";

// Check if there's any automatic approval logic
$test_queue = 'PLA-0004';
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $test_queue);
$app_stmt->execute();
$app = $app_stmt->get_result()->fetch_assoc();

if ($app) {
    echo "Application $test_queue:<br>";
    echo "- Current status: {$app['current_status']}<br>";
    echo "- Similarity index: {$app['similarity_index']}<br>";
    echo "- Test count: {$app['test_count']}<br>";
    
    // Check if QF-40 exists
    $qf40_stmt = $conn->prepare("SELECT file_generated FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf40'");
    $qf40_stmt->bind_param("s", $test_queue);
    $qf40_stmt->execute();
    $qf40 = $qf40_stmt->get_result()->fetch_assoc();
    
    echo "- QF-40 generated: " . ($qf40['file_generated'] ? 'YES' : 'NO') . "<br>";
    
    // Suggest what status should be
    if (!$qf40['file_generated']) {
        echo "- Should be: REQUIREMENTS_SENT or similar (not approved)<br>";
    }
}
?>
