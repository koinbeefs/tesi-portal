<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Adding file_path column to fillable_forms table</h2>";

// Check if file_path column already exists
$result = $conn->query("SHOW COLUMNS FROM fillable_forms LIKE 'file_path'");
if ($result->num_rows > 0) {
    echo "✅ file_path column already exists<br>";
} else {
    echo "⚠️ file_path column does not exist, adding it...<br>";
    
    // Add the column
    $alter_sql = "ALTER TABLE fillable_forms ADD COLUMN file_path VARCHAR(500) AFTER file_generated";
    if ($conn->query($alter_sql)) {
        echo "✅ file_path column added successfully<br>";
    } else {
        echo "❌ Failed to add file_path column: " . $conn->error . "<br>";
    }
}

// Show current table structure
echo "<h3>Current fillable_forms table structure:</h3>";
$result = $conn->query("DESCRIBE fillable_forms");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']}<br>";
}

// Check existing QF-39 records
echo "<h3>Existing QF-39 records:</h3>";
$result = $conn->query("SELECT queue_number, form_type, file_generated, file_path FROM fillable_forms WHERE form_type = 'qf39'");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['queue_number']}: file_generated = " . ($row['file_generated'] ? 'YES' : 'NO') . ", file_path = " . ($row['file_path'] ?? 'NULL') . "<br>";
}
?>
