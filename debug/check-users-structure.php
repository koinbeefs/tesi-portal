<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "<h2>Users Table Structure</h2>";

$result = $conn->query("DESCRIBE users");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']}) Null: {$row['Null']} Default: " . ($row['Default'] ?? 'NULL') . "<br>";
}

echo "<h2>Current Users</h2>";

$result = $conn->query("SELECT * FROM users");
while ($row = $result->fetch_assoc()) {
    echo "User ID {$row['user_id']}: {$row['username']}<br>";
    foreach ($row as $key => $value) {
        if ($key != 'user_id' && $key != 'username') {
            echo "  - $key: $value<br>";
        }
    }
    echo "<br>";
}
?>
