<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>🔧 Fixing PLA-0023 Status</h2>";

// Reset PLA-0023 status back to REQUIREMENTS_SENT
$stmt = $conn->prepare("UPDATE applications SET current_status = 'REQUIREMENTS_SENT' WHERE queue_number = ?");
$queue_number = "PLA-0023";
$stmt->bind_param("s", $queue_number);

if ($stmt->execute()) {
    echo "✅ PLA-0023 status reset to REQUIREMENTS_SENT<br>";
} else {
    echo "❌ Failed to reset status: " . $conn->error . "<br>";
}

// Verify the change
$check_stmt = $conn->prepare("SELECT current_status, last_updated FROM applications WHERE queue_number = ?");
$check_stmt->bind_param("s", $queue_number);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();

echo "<h3>Updated Status:</h3>";
echo "- Current Status: <strong>{$result['current_status']}</strong><br>";
echo "- Last Updated: " . ($result['last_updated'] ?? 'NULL') . "<br>";

// Create QF39 folder for PLA-0023
$qf39_folder = __DIR__ . '/../uploads/QF39/' . $queue_number;
if (!is_dir($qf39_folder)) {
    if (mkdir($qf39_folder, 0755, true)) {
        echo "✅ Created QF39 folder for PLA-0023<br>";
    } else {
        echo "❌ Failed to create QF39 folder<br>";
    }
} else {
    echo "ℹ️ QF39 folder already exists for PLA-0023<br>";
}

echo "<h3>🎯 Next Steps:</h3>";
echo "<ol>";
echo "<li>✅ Status fixed - No longer auto-approved</li>";
echo "<li>✅ Folder structure ready for QF-39 files</li>";
echo "<li>📝 Test QF-39 generation to ensure file saving works</li>";
echo "<li>🔍 Test QF-39 download functionality</li>";
echo "</ol>";
?>
