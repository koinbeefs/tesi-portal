<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Applications Table Structure</h2>";

$result = $conn->query("DESCRIBE applications");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
}

echo "<h2>PLA-0023 Current Status</h2>";

$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$queue_number = "PLA-0023";
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if ($app) {
    echo "- Current Status: <strong>{$app['current_status']}</strong><br>";
    echo "- Similarity Index: " . ($app['similarity_index'] ?? 'NULL') . "<br>";
    echo "- Assigned Staff: " . ($app['assigned_staff_id'] ?? 'NULL') . "<br>";
    echo "- Last Updated: " . ($app['last_updated'] ?? 'NULL') . "<br>";
    
    // Show all columns to see what's available
    echo "<h3>All Columns:</h3>";
    foreach ($app as $key => $value) {
        echo "- $key: " . ($value ?? 'NULL') . "<br>";
    }
} else {
    echo "❌ PLA-0023 not found<br>";
}
?>
