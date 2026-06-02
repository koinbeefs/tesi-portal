<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Fix Category Field for All Applications</h2>";

$conn = getDBConnection();

// Update category field to match current_status for all applications
$update_stmt = $conn->prepare("UPDATE applications SET category = current_status WHERE category IS NULL OR category = ''");
if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    echo "✅ Updated $affected_rows applications<br>";
} else {
    echo "❌ Failed to update applications<br>";
}

// Verify the update
echo "<h3>Verification:</h3>";
$result = $conn->query("SELECT queue_number, category, current_status FROM applications ORDER BY queue_number");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Queue</th><th>Category</th><th>Current Status</th><th>Match?</th></tr>";

$match_count = 0;
$total_count = 0;

while ($row = $result->fetch_assoc()) {
    $total_count++;
    $match = ($row['category'] && strtolower($row['category']) === strtolower($row['current_status'])) ? '✅' : '❌';
    if ($match === '✅') $match_count++;
    echo "<tr><td>{$row['queue_number']}</td><td>{$row['category']}</td><td>{$row['current_status']}</td><td>$match</td></tr>";
}
echo "</table>";

echo "<h3>Results:</h3>";
echo "✅ Matching: $match_count / $total_count applications<br>";

if ($match_count === $total_count) {
    echo "🎉 All applications have been fixed!<br>";
} else {
    echo "⚠️ Some applications may need manual attention<br>";
}

echo "<h3>Next Steps:</h3>";
echo "1. ✅ Category field now matches current_status<br>";
echo "2. 🔄 Refresh the application view page<br>";
echo "3. 📊 Review Category should now display correctly<br>";
?>
