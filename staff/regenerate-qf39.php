<?php
/**
 * Regenerate QF-39 Form
 * TAU-TeSI Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();

// Get application data
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application_data = $app_stmt->get_result()->fetch_assoc();

if (!$application_data) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=application_not_found');
    exit();
}

// Get existing QF-39 form data
$qf39_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$qf39_stmt->bind_param("s", $queue_number);
$qf39_stmt->execute();
$existing_qf39 = $qf39_stmt->get_result()->fetch_assoc();

if (!$existing_qf39) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=form_not_found');
    exit();
}

$form_data = json_decode($existing_qf39['form_data'], true);

// Simulate session data for QF-39 generation
$_SESSION['queue_number'] = $queue_number;

// Include the QF-39 generation logic
use PhpOffice\PhpWord\TemplateProcessor;

try {
    // Handle SOC/TECH checkboxes → ☑ or ☐
    $researchTypes = isset($form_data['research_type']) ? (is_array($form_data['research_type']) ? $form_data['research_type'] : [$form_data['research_type']]) : [];
    $soc = in_array('social', $researchTypes) ? '☑' : '☐';
    $tech = in_array('technical', $researchTypes) ? '☑' : '☐';

    $templateProcessor = new TemplateProcessor('qf39.docx');

    $templateProcessor->setValues([
        'RESEARCH_TITLE' => $form_data['research_title'] ?? '',
        'PROPONENTS' => $form_data['proponents'] ?? '',
        'CONTACTS' => $form_data['contacts'] ?? '',
        'EMAIL' => $form_data['email'] ?? '',
        'SOC' => $soc,
        'TECH' => $tech,
        'REQUESTOR' => $form_data['requestor_name'] ?? '',
        'FILLED' => date('F d, Y'),
        'STAFF' => '________________________',
        'SIGNED' => '________________________',
    ]);

    // Check for signature file
    $signature_doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND document_type = 'proponent_signature'");
    $signature_doc_stmt->bind_param("s", $queue_number);
    $signature_doc_stmt->execute();
    $signature_doc = $signature_doc_stmt->get_result()->fetch_assoc();

    if ($signature_doc && file_exists($signature_doc['file_path'])) {
        $signaturePath = $signature_doc['file_path'];
        $fileExt = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
        $imageFormats = ['png', 'jpg', 'jpeg'];
        
        if (in_array($fileExt, $imageFormats)) {
            try {
                $absolutePath = realpath($signaturePath);
                $widthCm = 4.72;
                $heightCm = 1.94;
                $targetWidth = round($widthCm * 1000);
                $targetHeight = round($heightCm * 1000);
                
                $resizedPath = $signaturePath . '_resized.' . $fileExt;
                
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
                
                $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
                
                if ($fileExt == 'png') {
                    imagealphablending($targetImage, false);
                    imagesavealpha($targetImage, true);
                    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                    imagefill($targetImage, 0, 0, $transparent);
                }
                
                imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($sourceImage), imagesy($sourceImage));
                
                switch($fileExt) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($targetImage, $resizedPath, 90);
                        break;
                    case 'png':
                        imagepng($targetImage, $resizedPath, 9);
                        break;
                }
                
                imagedestroy($sourceImage);
                imagedestroy($targetImage);
                
                $templateProcessor->setImageValue('SIGNATURE_PLACEHOLDER', [$resizedPath, $targetWidth, $targetHeight]);
                unlink($resizedPath);
                
            } catch (Exception $e) {
                error_log('Signature processing failed: ' . $e->getMessage());
            }
        }
    }

    $outputFile = 'TAU-DRD-QF-39_Filled_' . $queue_number . '.docx';
    $templateProcessor->saveAs($outputFile);

    // Save file to QF39 folder structure
    $qf39Folder = __DIR__ . '/../uploads/QF39/' . $queue_number . '/';
    if (!is_dir($qf39Folder)) {
        if (!mkdir($qf39Folder, 0755, true)) {
            error_log("Failed to create QF39 folder: " . $qf39Folder);
            header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=folder_creation_failed');
            exit();
        }
    }
    $savedFile = $qf39Folder . 'QF-39_' . $queue_number . '.docx';

    if (file_exists($outputFile)) {
        if (copy($outputFile, $savedFile)) {
            error_log("QF-39 file regenerated and saved to: " . $savedFile);
            unlink($outputFile);
            
            // Update file path in database
            $update_stmt = $conn->prepare("UPDATE fillable_forms SET file_path = ? WHERE queue_number = ? AND form_type = 'qf39'");
            $update_stmt->bind_param("ss", $savedFile, $queue_number);
            $update_stmt->execute();
            
            // Log the regeneration activity
            logStaffActivity($_SESSION['user_id'], $queue_number, 'regenerated_document', 'Regenerated QF-39 form');
            
            // Redirect to download
            header('Location: download-qf39.php?queue=' . urlencode($queue_number));
            exit();
        } else {
            error_log("Failed to copy QF-39 file to: " . $savedFile);
            header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=file_copy_failed');
            exit();
        }
    } else {
        error_log("QF-39 output file not found: " . $outputFile);
        header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=generation_failed');
        exit();
    }

} catch (Exception $e) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=' . urlencode($e->getMessage()));
    exit();
}
?>
