<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>🧪 Testing QF-39 Regeneration</h2>";

$queue_number = "PLA-0023";
$conn = getDBConnection();

// Check existing QF-39 data
echo "<h3>Existing QF-39 Data:</h3>";
$qf39_stmt = $conn->prepare("SELECT * FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$qf39_stmt->bind_param("s", $queue_number);
$qf39_stmt->execute();
$qf39 = $qf39_stmt->get_result()->fetch_assoc();

if ($qf39) {
    echo "- File Generated: " . ($qf39['file_generated'] ? 'YES' : 'NO') . "<br>";
    echo "- File Path: " . ($qf39['file_path'] ?? 'NULL') . "<br>";
    echo "- Completed At: " . ($qf39['completed_at'] ?? 'NULL') . "<br>";
    
    if ($qf39['file_path']) {
        echo "- File Exists: " . (file_exists($qf39['file_path']) ? 'YES' : 'NO') . "<br>";
        if (file_exists($qf39['file_path'])) {
            echo "- File Size: " . number_format(filesize($qf39['file_path']) / 1024, 2) . " KB<br>";
        }
    }
    
    // Check form data
    $form_data = json_decode($qf39['form_data'], true);
    if ($form_data) {
        echo "<h4>Form Data:</h4>";
        foreach (['requestor_name', 'research_title', 'email', 'contacts'] as $field) {
            echo "- $field: " . ($form_data[$field] ?? 'NULL') . "<br>";
        }
    }
} else {
    echo "❌ No QF-39 data found<br>";
}

// Check signature
echo "<h3>Signature Check:</h3>";
$sig_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? AND document_type = 'proponent_signature'");
$sig_stmt->bind_param("s", $queue_number);
$sig_stmt->execute();
$sig = $sig_stmt->get_result()->fetch_assoc();

if ($sig) {
    echo "- Signature Document: ✅ Found<br>";
    echo "- File Path: " . $sig['file_path'] . "<br>";
    echo "- File Exists: " . (file_exists($sig['file_path']) ? 'YES' : 'NO') . "<br>";
} else {
    echo "- Signature Document: ❌ Not found<br>";
}

// Check QF39 folder
echo "<h3>Folder Check:</h3>";
$qf39_folder = __DIR__ . '/../uploads/QF39/' . $queue_number . '/';
echo "- Folder Path: $qf39_folder<br>";
echo "- Folder Exists: " . (is_dir($qf39_folder) ? 'YES' : 'NO') . "<br>";

if (is_dir($qf39_folder)) {
    $files = scandir($qf39_folder);
    echo "- Files in folder: ";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "$file ";
        }
    }
    echo "<br>";
}

// Check template file
echo "<h3>Template Check:</h3>";
$template_path = __DIR__ . '/../qf39.docx';
echo "- Template Path: $template_path<br>";
echo "- Template Exists: " . (file_exists($template_path) ? 'YES' : 'NO') . "<br>";

echo "<h3>🎯 Test Regeneration:</h3>";
echo "Try accessing: <a href='../staff/regenerate-qf39.php?queue=$queue_number' target='_blank'>../staff/regenerate-qf39.php?queue=$queue_number</a><br>";
echo "Then check the error parameter in the URL to see what went wrong.";
?>
