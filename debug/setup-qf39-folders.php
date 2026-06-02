<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Setting up QF-39 Folder Structure</h2>";

$base_path = __DIR__ . '/../uploads/QF39';
$qf39_folder = $base_path;

// Create main QF39 folder
if (!is_dir($qf39_folder)) {
    if (mkdir($qf39_folder, 0755, true)) {
        echo "✅ Created QF39 folder: $qf39_folder<br>";
    } else {
        echo "❌ Failed to create QF39 folder<br>";
        exit;
    }
} else {
    echo "✅ QF39 folder already exists<br>";
}

// Create individual queue number folders
$conn = getDBConnection();
$result = $conn->query("SELECT DISTINCT queue_number FROM applications ORDER BY queue_number");

$created_folders = 0;
while ($row = $result->fetch_assoc()) {
    $queue_number = $row['queue_number'];
    $queue_folder = $qf39_folder . '/' . $queue_number;
    
    if (!is_dir($queue_folder)) {
        if (mkdir($queue_folder, 0755, true)) {
            echo "✅ Created folder for $queue_number<br>";
            $created_folders++;
        } else {
            echo "❌ Failed to create folder for $queue_number<br>";
        }
    } else {
        echo "ℹ️ Folder for $queue_number already exists<br>";
    }
}

echo "<h3>Folder Structure Created: $created_folders new folders</h3>";

// Show current structure
echo "<h3>Current QF39 Folder Structure:</h3>";
if (is_dir($qf39_folder)) {
    $folders = scandir($qf39_folder);
    foreach ($folders as $folder) {
        if ($folder !== '.' && $folder !== '..') {
            $folder_path = $qf39_folder . '/' . $folder;
            if (is_dir($folder_path)) {
                echo "📁 $folder<br>";
            }
        }
    }
} else {
    echo "❌ QF39 folder not found<br>";
}

echo "<h3>Next Steps:</h3>";
echo "1. ✅ Folder structure is ready<br>";
echo "2. 📝 Need to update QF-39 generation to save files to these folders<br>";
echo "3. 🔄 Need to update QF-39 download to use saved files<br>";
?>
