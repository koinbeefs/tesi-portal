<?php
// fill-qf39-form.php
// QF-39: Request for Testing for Similarity Index

use PhpOffice\PhpWord\TemplateProcessor;
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

// requireApplicantLogin();

if (!isset($_SESSION['queue_number'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No queue number in session']);
        exit;
    }
    die('Error: No queue number in session');
}

$queue_number = $_SESSION['queue_number'];

// Get applicant data for auto-filling
$conn = getDBConnection();
$app_data = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_data->bind_param('s', $queue_number);
$app_data->execute();
$application_data = $app_data->get_result()->fetch_assoc();
$applicant_name = $application_data['applicant_name'] ?? '';
$research_title = $application_data['research_title'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $conn = getDBConnection();
    $data = $_POST;

    // Handle SOC/TECH checkboxes → ☑ or ☐
    $researchTypes = isset($data['research_type']) ? (is_array($data['research_type']) ? $data['research_type'] : [$data['research_type']]) : [];
    $soc = in_array('social', $researchTypes) ? '☑' : '☐';
    $tech = in_array('technical', $researchTypes) ? '☑' : '☐';

    // Handle signature file upload
    $signaturePath = '';
    if (isset($_FILES['proponent_signature']) && $_FILES['proponent_signature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/signatures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['proponent_signature']['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg'];
        if (in_array($fileExt, $allowed)) {
            $signatureFilename = 'signature_' . $queue_number . '_' . time() . '.' . $fileExt;
            $signaturePath = $uploadDir . $signatureFilename;
            
            if (move_uploaded_file($_FILES['proponent_signature']['tmp_name'], $signaturePath)) {
                // Save signature path to documents table
                $docStmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, validation_status) VALUES (?, 'proponent_signature', 'Proponent E-Signature', ?, 'pending')");
                $docStmt->bind_param('ss', $queue_number, $signaturePath);
                $docStmt->execute();
            }
        }
    }

    try {
        $templateProcessor = new TemplateProcessor('qf39.docx');

        $templateProcessor->setValues([
            'RESEARCH_TITLE' => $data['research_title'] ?? '',
            'PROPONENTS' => $data['proponents'] ?? '',
            'CONTACTS' => $data['contacts'] ?? '',
            'EMAIL' => $data['email'] ?? '',
            'SOC' => $soc,
            'TECH' => $tech,
            'REQUESTOR' => $data['requestor_name'] ?? '',
            'FILLED' => date('F d, Y'),
            'STAFF' => '________________________', // filled by DRD staff
            'SIGNED' => '________________________', // filled by DRD staff
        ]);

        // Add signature image if uploaded and is an image format (not PDF)
        error_log("=== SIGNATURE PROCESSING START ===");
        error_log("Signature path: " . $signaturePath);
        error_log("FILES data: " . print_r($_FILES, true));
        
        if (!empty($signaturePath) && file_exists($signaturePath)) {
            $fileExt = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
            $imageFormats = ['png', 'jpg', 'jpeg'];
            if (in_array($fileExt, $imageFormats)) {
                try {
                    // Debug: Log what we're doing
                    error_log("=== SIGNATURE DEBUG ===");
                    error_log("Signature file: " . $signaturePath);
                    error_log("File exists: " . (file_exists($signaturePath) ? 'YES' : 'NO'));
                    error_log("File size: " . filesize($signaturePath) . " bytes");
                    
                    // Get absolute path
                    $absolutePath = realpath($signaturePath);
                    error_log("Absolute path: " . $absolutePath);
                    
                    // Try setImageValue approach first
                    error_log("Trying setImageValue approach");
                    try {
                        // Convert cm to pixels (Word uses different DPI - try 96 DPI first, then adjust)
                        $widthCm = 4.72;  // Width in cm
                        $heightCm = 1.94; // Height in cm
                        
                        // Try reasonable DPI increase without memory overflow
                        $targetWidth = round($widthCm * 1000);  // 2000 DPI - large but manageable
                        $targetHeight = round($heightCm * 1000); // 2000 DPI - large but manageable
                        
                        error_log("Target dimensions: {$targetWidth}x{$targetHeight} pixels ({$widthCm}x{$heightCm}cm)");
                        
                        // Create a resized image to exact dimensions
                        $resizedPath = $signaturePath . '_resized.' . $fileExt;
                        
                        // Load original image
                        switch($fileExt) {
                            case 'jpg':
                            case 'jpeg':
                                $sourceImage = imagecreatefromjpeg($absolutePath);
                                break;
                            case 'png':
                                $sourceImage = imagecreatefrompng($absolutePath);
                                break;
                            default:
                                throw new Exception("Unsupported image format");
                        }
                        
                        // Get original dimensions
                        $originalWidth = imagesx($sourceImage);
                        $originalHeight = imagesy($sourceImage);
                        error_log("Original dimensions: {$originalWidth}x{$originalHeight}");
                        
                        // Create new image with exact target dimensions
                        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
                        
                        // Preserve transparency for PNG
                        if ($fileExt == 'png') {
                            imagealphablending($targetImage, false);
                            imagesavealpha($targetImage, true);
                            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                            imagefill($targetImage, 0, 0, $transparent);
                        }
                        
                        // Resize image to exact dimensions (may stretch)
                        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
                        
                        // Save resized image
                        switch($fileExt) {
                            case 'jpg':
                            case 'jpeg':
                                imagejpeg($targetImage, $resizedPath, 90);
                                break;
                            case 'png':
                                imagepng($targetImage, $resizedPath, 9);
                                break;
                        }
                        
                        // Clean up memory
                        imagedestroy($sourceImage);
                        imagedestroy($targetImage);
                        
                        error_log("Resized image created: {$resizedPath}");
                        
                        // Use the resized image with exact dimensions
                        $templateProcessor->setImageValue('SIGNATURE_PLACEHOLDER', [$resizedPath, $targetWidth, $targetHeight]);
                        error_log("setImageValue completed successfully with resized image");
                        
                        // Clean up resized file after use
                        unlink($resizedPath);
                        
                    } catch (Exception $imgEx) {
                        error_log("setImageValue failed: " . $imgEx->getMessage());
                        
                        // Fallback to text approaches
                        error_log("Trying approach 1: Direct path to SIGNATURE");
                        $templateProcessor->setValues(['SIGNATURE' => $absolutePath]);
                        
                        error_log("Trying approach 2: File path with forward slashes");
                        $forwardPath = str_replace('\\', '/', $absolutePath);
                        $templateProcessor->setValues(['SIGNATURE' => $forwardPath]);
                        
                        error_log("Trying approach 3: Relative path");
                        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'] . '/', '', $absolutePath);
                        $templateProcessor->setValues(['SIGNATURE' => $relativePath]);
                        
                        error_log("Trying approach 4: Just filename");
                        $filenameOnly = basename($signaturePath);
                        $templateProcessor->setValues(['SIGNATURE' => $filenameOnly]);
                    }
                    
                    error_log("All signature approaches completed");
                    
                    // Add fallback text confirmation
                    $signatureInfo = "E-Signature uploaded: " . basename($signaturePath);
                    $templateProcessor->setValues(['SIGNATURE_CONFIRMATION' => $signatureInfo]);
                    error_log("Added fallback confirmation: " . $signatureInfo);
                    
                } catch (Exception $e) {
                    error_log('Signature setting failed: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    
                    // Fallback: Just add text confirmation
                    $signatureInfo = "E-Signature uploaded: " . basename($signaturePath);
                    $templateProcessor->setValues(['SIGNATURE' => $signatureInfo]);
                    $templateProcessor->setValues(['SIGNATURE_CONFIRMATION' => $signatureInfo]);
                    error_log("Added fallback confirmation after error: " . $signatureInfo);
                }
            } else {
                error_log("Signature file is not an image format: " . $fileExt);
            }
        } else {
            error_log("No signature path available");
            error_log("FILES data: " . print_r($_FILES, true));
        }
        error_log("=== SIGNATURE PROCESSING END ===");

        $outputFile = 'TAU-DRD-QF-39_Filled_' . $queue_number . '.docx';
        $templateProcessor->saveAs($outputFile);

        // Save file to QF39 folder structure
        $qf39Folder = __DIR__ . '/uploads/QF39/' . $queue_number . '/';
        if (!is_dir($qf39Folder)) {
            if (!mkdir($qf39Folder, 0755, true)) {
                error_log("Failed to create QF39 folder: " . $qf39Folder);
            }
        }
        $savedFile = $qf39Folder . 'QF-39_' . $queue_number . '.docx';

        // Copy the generated file to the permanent location
        if (file_exists($outputFile)) {
            if (copy($outputFile, $savedFile)) {
                error_log("QF-39 file saved to: " . $savedFile);
            } else {
                error_log("Failed to save QF-39 file to: " . $savedFile);
            }
        }

        // Persist form record
        $formDataJson = json_encode($data);
        $formType = 'qf39';

        $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
        $checkStmt->bind_param('ss', $queue_number, $formType);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW(), file_generated = 1, file_path = ? WHERE queue_number = ? AND form_type = ?");
            $stmt->bind_param('ssss', $formDataJson, $savedFile, $queue_number, $formType);
        }
        else {
            $stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, file_generated, file_path) VALUES (?, ?, ?, 1, ?)");
            $stmt->bind_param('ssss', $queue_number, $formType, $formDataJson, $savedFile);
        }
        $stmt->execute();

        if (file_exists($outputFile)) {
            $fileContent = base64_encode(file_get_contents($outputFile));
            // Don't delete the file anymore since we have a copy in the QF39 folder
            unlink($outputFile);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'QF-39 form generated successfully!',
                'filename' => basename($outputFile),
                'fileContent' => $fileContent,
            ]);
            exit;
        }
        else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: Could not create the document.']);
            exit;
        }

    }
    catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage())]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAU-DRD-QF-39 Similarity Index Request Filler</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            line-height: 1.6;
            background: #f8f9fa;
            padding: 0 20px;
        }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        h2 { color: #2c3e50; margin-bottom: 15px; }
        .section {
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        label { display: block; margin: 12px 0 5px; font-weight: bold; color: #333; }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }
        textarea { min-height: 90px; resize: vertical; }
        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 8px;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        .radio-group input[type="radio"] {
            width: auto;
            transform: scale(1.3);
            cursor: pointer;
        }
        .note {
            background: #fff8e1;
            border-left: 4px solid #f9a825;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 0.93rem;
            color: #555;
            margin-top: 10px;
        }
        button {
            display: block;
            margin: 30px auto;
            padding: 14px 50px;
            background: #2980b9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #1f6391; }
        button:disabled { background: #95a5a6; cursor: not-allowed; }
    </style>
</head>
<body>
<h1>TAU-DRD-QF-39<br>Request for Testing for Similarity Index</h1>

<form id="qf39Form" method="post" enctype="multipart/form-data">
    <div class="section">
        <h2>Research Information</h2>

        <label>Research Title</label>
        <input type="text" name="research_title"
               value="<?php echo htmlspecialchars($research_title); ?>"
               required placeholder="Full title of the research">

        <label>Researcher(s) / Proponent(s)</label>
        <textarea name="proponents"
                  placeholder="Full name(s) of all researchers. Separate with commas."><?php echo htmlspecialchars($applicant_name); ?></textarea>

        <label>Contact Number</label>
        <input type="tel" name="contacts" placeholder="e.g. 09XX-XXX-XXXX">

        <label>Email Address</label>
        <input type="email" name="email" placeholder="youremail@example.com">

        <label>Type of Research</label>
        <div class="radio-group">
            <label>
                <input type="checkbox" name="research_type[]" value="social">
                Social
            </label>
            <label>
                <input type="checkbox" name="research_type[]" value="technical">
                Technical
            </label>
        </div>
    </div>

    <div class="section">
        <h2>E-Signature</h2>
        <label>Proponent's E-Signature</label>
        <input type="file" name="proponent_signature" accept=".png,.jpg,.jpeg">
        <small style="color:#777;">Upload your e-signature (PNG or JPG format only - will be embedded in the document)</small>
    </div>

    <div class="section">
        <h2>Declaration</h2>
        <div class="note">
            By submitting this form, you certify that the information given is true and correct,
            that the research is authentic, and that you commit to revising the paper per evaluation results.
        </div>

        <label>Name of Requestor (Signature over Printed Name)</label>
        <input type="text" name="requestor_name"
               value="<?php echo htmlspecialchars($applicant_name); ?>"
               required placeholder="Your full name">

        <p style="color:#777; font-size:0.9rem; margin-top:6px;">
            <em>Date will be automatically set to today: <?php echo date('F d, Y'); ?></em>
        </p>
    </div>

    <button type="submit" id="submitBtn">Generate &amp; Download QF-39</button>
</form>

<script>
// Include duplicate prevention
let isSubmitting = false;

document.getElementById('qf39Form').addEventListener('submit', function(e) {
    if (isSubmitting) {
        e.preventDefault();
        alert('Form is already being submitted. Please wait...');
        return false;
    }
    isSubmitting = true;
    
    setTimeout(() => {
        isSubmitting = false;
    }, 5000);
});

document.getElementById('qf39Form').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const formData = new FormData(this);

    fetch('fill-qf39-form.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const link = document.createElement('a');
            link.href = 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,' + data.fileContent;
            link.download = data.filename;
            link.click();

            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'formCompleted',
                    formType: 'qf39',
                    message: data.message
                }, '*');
            } else {
                alert(data.message);
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'Generate & Download QF-39';
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Generate & Download QF-39';
    });
});
</script>
</body>
</html>