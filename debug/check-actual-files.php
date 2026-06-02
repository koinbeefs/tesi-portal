<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>🔍 Checking Actual QF-39 Files</h2>";

$queue_number = "PLA-0023";
$conn = getDBConnection();

// Check what's in database
echo "<h3>Database Record:</h3>";
$qf39_stmt = $conn->prepare("SELECT file_path FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$qf39_stmt->bind_param("s", $queue_number);
$qf39_stmt->execute();
$qf39 = $qf39_stmt->get_result()->fetch_assoc();

if ($qf39) {
    echo "- Database file_path: " . ($qf39['file_path'] ?? 'NULL') . "<br>";
}

// Check what's actually in the folder
echo "<h3>Actual Files in Folder:</h3>";
$qf39_folder = __DIR__ . '/../uploads/QF39/' . $queue_number . '/';
echo "- Folder: $qf39_folder<br>";
echo "- Folder exists: " . (is_dir($qf39_folder) ? 'YES' : 'NO') . "<br>";

if (is_dir($qf39_folder)) {
    $files = scandir($qf39_folder);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $full_path = $qf39_folder . $file;
            echo "- File: $file<br>";
            echo "  Path: $full_path<br>";
            echo "  Exists: " . (file_exists($full_path) ? 'YES' : 'NO') . "<br>";
            echo "  Size: " . number_format(filesize($full_path) / 1024, 2) . " KB<br>";
            echo "<br>";
        }
    }
}

// If file exists but database path is wrong, fix it
if (is_dir($qf39_folder)) {
    $files = scandir($qf39_folder);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && str_contains($file, 'QF-39')) {
            $actual_file_path = $qf39_folder . $file;
            echo "<h3>🔧 Fixing Database Path</h3>";
            echo "- Found actual file: $actual_file_path<br>";
            
            // Update database with correct path
            $update_stmt = $conn->prepare("UPDATE fillable_forms SET file_path = ? WHERE queue_number = ? AND form_type = 'qf39'");
            $update_stmt->bind_param("ss", $actual_file_path, $queue_number);
            
            if ($update_stmt->execute()) {
                echo "✅ Database updated with correct file path<br>";
            } else {
                echo "❌ Failed to update database: " . $conn->error . "<br>";
            }
            break;
        }
    }
}

echo "<h3>Test Download:</h3>";
echo "Try downloading: <a href='../staff/download-qf39.php?queue=$queue_number' target='_blank'>../staff/download-qf39.php?queue=$queue_number</a><br>";
?>
