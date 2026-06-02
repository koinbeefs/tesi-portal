<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Setting up QF-40 Folder Structure</h2>";

$base_path = __DIR__ . '/../uploads/QF40';
$qf40_folder = $base_path;

// Create main QF40 folder
if (!is_dir($qf40_folder)) {
    if (mkdir($qf40_folder, 0755, true)) {
        echo "✅ Created QF40 folder: $qf40_folder<br>";
    } else {
        echo "❌ Failed to create QF40 folder<br>";
        exit;
    }
} else {
    echo "✅ QF40 folder already exists<br>";
}

// Create individual queue number folders
$conn = getDBConnection();
$result = $conn->query("SELECT DISTINCT queue_number FROM applications ORDER BY queue_number");

$created_folders = 0;
while ($row = $result->fetch_assoc()) {
    $queue_number = $row['queue_number'];
    $queue_folder = $qf40_folder . '/' . $queue_number;
    
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
echo "<h3>Current QF40 Folder Structure:</h3>";
if (is_dir($qf40_folder)) {
    $folders = scandir($qf40_folder);
    foreach ($folders as $folder) {
        if ($folder !== '.' && $folder !== '..') {
            $folder_path = $qf40_folder . '/' . $folder;
            if (is_dir($folder_path)) {
                echo "📁 $folder<br>";
            }
        }
    }
} else {
    echo "❌ QF40 folder not found<br>";
}

echo "<h3>Next Steps:</h3>";
echo "1. ✅ Folder structure is ready<br>";
echo "2. 📝 Need to update QF-40 generation to save files to these folders<br>";
echo "3. 🔄 Need to update QF-40 download to use saved files<br>";
echo "4. 📊 Need to add file_path column for QF-40 forms<br>";
?>
