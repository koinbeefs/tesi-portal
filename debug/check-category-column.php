<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Check Category Column Structure</h2>";

$conn = getDBConnection();

// Check the applications table structure, specifically the category column
$result = $conn->query("DESCRIBE applications");
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'category') {
        echo "<h3>Category Column Details:</h3>";
        echo "- Field: {$row['Field']}<br>";
        echo "- Type: {$row['Type']}<br>";
        echo "- Null: {$row['Null']}<br>";
        echo "- Key: {$row['Key']}<br>";
        echo "- Default: {$row['Default']}<br>";
        echo "- Extra: {$row['Extra']}<br>";
        
        // Check max length
        if (preg_match('/varchar\((\d+)\)/', $row['Type'], $matches)) {
            $max_length = $matches[1];
            echo "- Max Length: $max_length characters<br>";
            
            // Check current status values
            echo "<h3>Current Status Values:</h3>";
            $status_result = $conn->query("SELECT DISTINCT current_status FROM applications WHERE current_status IS NOT NULL");
            while ($status_row = $status_result->fetch_assoc()) {
                $status = $status_row['current_status'];
                $length = strlen($status);
                $fits = $length <= $max_length ? '✅' : '❌';
                echo "- '$status' ($length chars) $fits<br>";
            }
        }
        break;
    }
}

echo "<h3>Fix Options:</h3>";
echo "1. Update category with shorter status names<br>";
echo "2. Modify the category column to be longer<br>";
echo "3. Use abbreviations for status names<br>";

echo "<h3>Recommended Status Mapping:</h3>";
echo "- 'REQUIREMENTS_SENT' → 'REQUIREMENTS'<br>";
echo "- 'REVISIONS_REQUIRED' → 'REVISIONS'<br>";
echo "- 'CATEGORIZED' → 'CATEGORIZED'<br>";
echo "- 'APPROVED' → 'APPROVED'<br>";
echo "- 'REJECTED' → 'REJECTED'<br>";
?>
