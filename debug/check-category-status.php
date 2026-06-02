<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Check Application Category Status</h2>";

$conn = getDBConnection();

// Check a specific application (let's use PLA-0023 as example)
$queue_number = 'PLA-0023';

echo "<h3>Application: $queue_number</h3>";

$stmt = $conn->prepare("SELECT queue_number, category, current_status, similarity_index, test_count FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Queue Number</td><td>{$result['queue_number']}</td></tr>";
    echo "<tr><td>Category</td><td>{$result['category']}</td></tr>";
    echo "<tr><td>Current Status</td><td>{$result['current_status']}</td></tr>";
    echo "<tr><td>Similarity Index</td><td>{$result['similarity_index']}</td></tr>";
    echo "<tr><td>Test Count</td><td>{$result['test_count']}</td></tr>";
    echo "</table>";
    
    echo "<h3>Expected Category Based on Status:</h3>";
    
    $status = $result['current_status'];
    $similarity = $result['similarity_index'];
    
    if ($status === 'REQUIREMENTS_SENT') {
        echo "Should show: <strong>Pending Review</strong><br>";
    } elseif ($status === 'CATEGORIZED') {
        echo "Should show: <strong>Categorized</strong><br>";
    } elseif ($status === 'APPROVED') {
        echo "Should show: <strong>Approved</strong><br>";
    } elseif ($status === 'REVISIONS_REQUIRED') {
        echo "Should show: <strong>Revisions Required</strong><br>";
    } else {
        echo "Should show: <strong>" . ucfirst($status) . "</strong><br>";
    }
    
    echo "<h3>Issue Analysis:</h3>";
    if (empty($result['category'])) {
        echo "❌ Category field is empty/null<br>";
        echo "💡 Need to update category based on current status<br>";
    } else {
        echo "✅ Category field has value: {$result['category']}<br>";
        echo "🔍 Check if this matches expected status<br>";
    }
    
} else {
    echo "❌ Application not found<br>";
}

echo "<h3>All Applications Status Check:</h3>";

$result = $conn->query("SELECT queue_number, category, current_status FROM applications ORDER BY queue_number");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Queue</th><th>Category</th><th>Current Status</th><th>Match?</th></tr>";

while ($row = $result->fetch_assoc()) {
    $match = ($row['category'] && strtolower($row['category']) === strtolower($row['current_status'])) ? '✅' : '❌';
    echo "<tr><td>{$row['queue_number']}</td><td>{$row['category']}</td><td>{$row['current_status']}</td><td>$match</td></tr>";
}
echo "</table>";

echo "<h3>Fix Recommendation:</h3>";
echo "Update the category field to match current_status for all applications:<br>";
echo "<code>UPDATE applications SET category = current_status WHERE category IS NULL OR category = '';</code>";
?>
