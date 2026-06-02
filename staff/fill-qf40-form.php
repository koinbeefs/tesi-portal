<?php
// fill-qf40-form.php
// QF-40: Similarity Index Certificate

use PhpOffice\PhpWord\TemplateProcessor;

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

//requireApplicantLogin();

$is_staff = isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff', 'admin']);

// Get queue number from session or GET parameter (for modal access)
$queue_number = $_SESSION['queue_number'] ?? $_GET['queue'] ?? '';

if (empty($queue_number)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No queue number provided']);
        exit;
    }
    die('Error: No queue number provided');
}

// Set queue number in session if coming from GET parameter
if (isset($_GET['queue']) && !isset($_SESSION['queue_number'])) {
    $_SESSION['queue_number'] = $_GET['queue'];
}

// Get QF-39 data for auto-filling
$conn = getDBConnection();
$qf39_data = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf39'");
$qf39_data->bind_param('s', $queue_number);
$qf39_data->execute();
$qf39_result = $qf39_data->get_result()->fetch_assoc();
$qf39_form_data = [];
if ($qf39_result && !empty($qf39_result['form_data'])) {
    $qf39_form_data = json_decode($qf39_result['form_data'], true) ?? [];
}

// Get applicant data for auto-filling
$conn = getDBConnection();
$app_data = $conn->prepare("SELECT applicant_name, research_title, similarity_index, research_type, college, program_course, research_date_started, research_date_finished, applicant_type FROM applications WHERE queue_number = ?");
$app_data->bind_param('s', $queue_number);
$app_data->execute();
$application_data = $app_data->get_result()->fetch_assoc();
$applicant_name = $application_data['applicant_name'] ?? '';
$research_title = $application_data['research_title'] ?? '';
$similarity_score = $application_data['similarity_index'] ?? '';
$research_type = $application_data['research_type'] ?? '';
$college = $application_data['college'] ?? '';
$program_course = $application_data['program_course'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $data = $_POST;

    // Type of research checkboxes → ☑ or ☐
    $soc = (isset($data['research_type']) && $data['research_type'] === 'social') ? '☑' : '☐';
    $tech = (isset($data['research_type']) && $data['research_type'] === 'technical') ? '☑' : '☐';

    try {
        $templateProcessor = new TemplateProcessor('qf40.docx');

        $templateProcessor->setValues([
            'RESEARCH_TITLE' => $data['research_title'] ?? $qf39_form_data['research_title'] ?? '',
            'PROPONENTS' => $data['proponents'] ?? $qf39_form_data['proponents'] ?? '',
            'SOC' => $soc,
            'TECH' => $tech,
            'BUDGET' => $data['budget'] ?? $qf39_form_data['budget'] ?? (in_array($application_data['applicant_type'] ?? '', ['student', 'undergraduate']) ? 'N/A' : ''),
            'STARTED' => !empty($data['date_started']) ? date('F Y', strtotime($data['date_started'])) : (!empty($application_data['research_date_started']) ? date('F Y', strtotime($application_data['research_date_started'])) : ''),
            'COMPLETED' => !empty($data['date_completed']) ? date('F Y', strtotime($data['date_completed'])) : (!empty($application_data['research_date_finished']) ? date('F Y', strtotime($application_data['research_date_finished'])) : ''),
            'TOOLS' => $data['tools'] ?? 'Turnitin',
            'REQUESTOR' => $data['requestor_name'] ?? $qf39_form_data['requestor_name'] ?? '',
            'SCORE' => $data['score'] ?? $similarity_score,
            'DATE_FILLED' => formatDate($data['date_filled'] ?? ''),
            'DATE_REL' => formatDate($data['date_released'] ?? date('Y-m-d')),
            'DIRECTOR' => $data['director_name'] ?? 'Noel J. Petero, Ph.D.',
            'AUTH' => $queue_number, // Certificate of Authenticity No. = Queue Number
        ]);

        $outputFile = 'TAU-DRD-QF-40_Filled_' . $queue_number . '.docx';
        $templateProcessor->saveAs($outputFile);

        // Save file to QF40 folder structure
        $qf40Folder = __DIR__ . '/../uploads/QF40/' . $queue_number . '/';
        if (!is_dir($qf40Folder)) {
            if (!mkdir($qf40Folder, 0755, true)) {
                error_log("Failed to create QF40 folder: " . $qf40Folder);
            }
        }
        $savedFile = $qf40Folder . 'QF-40_' . $queue_number . '.docx';

        // Copy the generated file to the permanent location
        if (file_exists($outputFile)) {
            if (copy($outputFile, $savedFile)) {
                error_log("QF-40 file saved to: " . $savedFile);
            } else {
                error_log("Failed to save QF-40 file to: " . $savedFile);
            }
        }

        // Persist form record
        $formDataJson = json_encode($data);
        $formType = 'qf40';

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

        // APPROVAL LOGIC: Change application status to APPROVED and send email
        $approval_stmt = $conn->prepare("UPDATE applications SET current_status = 'APPROVED', last_updated = NOW() WHERE queue_number = ?");
        $approval_stmt->bind_param("s", $queue_number);
        $approval_stmt->execute();

        // Log the approval activity
        logStaffActivity($_SESSION['user_id'], $queue_number, 'approved', 'Application approved via QF-40 certificate generation');

        // Add status history entry
        $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by_type, changed_by, notes) VALUES (?, 'REQUIREMENTS_SENT', 'APPROVED', 'staff', ?, ?)");
        $notes = "Application approved - QF-40 certificate generated and ready for pickup";
        $history_stmt->bind_param("sis", $queue_number, $_SESSION['user_id'], $notes);
        $history_stmt->execute();

        // 📧 SEND EMAIL TO APPLICANT
        $applicant_email = $application_data['applicant_email'] ?? '';
        if (!empty($applicant_email)) {
            $email_subject = "Certificate Printed - Ready for Pickup - Queue #" . $queue_number;
            $email_body = "
            <html>
            <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #006400, #228B22); color: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 20px;'>
                    <h1 style='margin: 0; font-size: 24px;'>🎓 Certificate Ready for Pickup</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>TAU-DRD Research Ethics Portal</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;'>
                    <h2 style='color: #2c3e50; margin-top: 0;'>Dear " . htmlspecialchars($application_data['applicant_name']) . ",</h2>
                    
                    <p style='color: #333; line-height: 1.6;'>Good news! Your certificate has been printed and is ready for pickup.</p>
                    
                    <div style='background: white; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; border-radius: 5px;'>
                        <h3 style='color: #2c3e50; margin-top: 0;'>📋 Certificate Details:</h3>
                        <ul style='color: #333; line-height: 1.6;'>
                            <li><strong>Queue Number:</strong> " . htmlspecialchars($queue_number) . "</li>
                            <li><strong>Research Title:</strong> " . htmlspecialchars($application_data['research_title']) . "</li>
                            <li><strong>Certificate Type:</strong> QF-40 Similarity Index Certificate</li>
                            <li><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>✅ Printed and Ready</span></li>
                        </ul>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; border-radius: 5px;'>
                        <h3 style='color: #856404; margin-top: 0;'>⏰ Next Steps:</h3>
                        <ol style='color: #856404; line-height: 1.6;'>
                            <li>Please confirm receipt of this email</li>
                            <li>Visit the DRD office to pick up your certificate</li>
                            <li>Bring a valid ID for verification</li>
                        </ol>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . BASE_URL . "track-application.php?queue=" . urlencode($queue_number) . "' 
                           style='background: linear-gradient(135deg, #006400, #228B22); color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: bold;'>
                            📊 Track Application Status
                        </a>
                    </div>
                </div>
                
                <div style='text-align: center; color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;'>
                    <p>This is an automated message from TAU-DRD Research Ethics Portal</p>
                    <p>If you have questions, please contact the DRD office</p>
                </div>
            </body>
            </html>";
            
            // Send email using the email function
            $email_sent = sendEmail($applicant_email, $email_subject, $email_body);
            
            if ($email_sent) {
                error_log("Certificate pickup email sent to: $applicant_email");
            } else {
                error_log("Failed to send certificate pickup email to: $applicant_email");
            }
        }

        if (file_exists($outputFile)) {
            $fileContent = base64_encode(file_get_contents($outputFile));
            unlink($outputFile);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'QF-40 Certificate generated successfully! Application has been approved and email sent to applicant.',
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
    <title>TAU-DRD-QF-40 Similarity Index Certificate Filler</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            line-height: 1.6;
            background: #f8f9fa;
            padding: 0 20px;
        }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 8px; }
        .subtitle { text-align: center; color: #777; margin-bottom: 30px; font-size: 0.95rem; }
        h2 { color: #2c3e50; margin-bottom: 15px; }
        .section {
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .staff-only {
            border: 2px dashed #e67e22;
            background: #fef9f0;
        }
        .staff-badge {
            display: inline-block;
            background: #e67e22;
            color: white;
            font-size: 0.78rem;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 8px;
            vertical-align: middle;
        }
        label { display: block; margin: 12px 0 5px; font-weight: bold; color: #333; }
        input[type="text"],
        input[type="date"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }
        textarea { min-height: 80px; resize: vertical; }
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
        .row2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .legend-box {
            background: #e8f4f8;
            border-left: 4px solid #2980b9;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 0.93rem;
            color: #333;
            margin-bottom: 20px;
        }
        .score-display {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-top: 8px;
        }
        .score-ok   { background: #e8f5e9; color: #2e7d32; }
        .score-fail { background: #ffebee; color: #c62828; }
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
<h1>TAU-DRD-QF-40</h1>
<p class="subtitle">Similarity Index Certificate</p>

<div class="legend-box">
    <strong>Similarity Index Legend:</strong><br>
    &nbsp;&nbsp;0–25% — Acceptable<br>
    &nbsp;&nbsp;26% and above — Not Acceptable
</div>

<form id="qf40Form" method="post">

    <!-- ===================== BASIC INFORMATION ===================== -->
    <div class="section">
        <h2>Basic Information of the Research</h2>

        <label>Research Title</label>
        <input type="text" name="research_title"
               value="<?php echo htmlspecialchars($research_title); ?>"
               required placeholder="Full title of the research">

        <label>Researcher(s) / Proponent(s)</label>
        <textarea name="proponents"
                  placeholder="Full name(s) of all researchers. Separate with commas."><?php echo htmlspecialchars($qf39_form_data['proponents'] ?? $applicant_name); ?></textarea>

        <label>Type of Research</label>
        <input type="text" name="research_type" value="<?php echo htmlspecialchars($research_type); ?>" readonly>
        <input type="hidden" name="research_type_hidden" value="<?php echo htmlspecialchars($research_type); ?>">

        <label>Proposed Budget (optional)</label>
        <input type="text" name="budget" placeholder="e.g. PHP 50,000 or N/A" value="<?php 
            $budget_value = $qf39_form_data['budget'] ?? '';
            if (empty($budget_value)) {
                $budget_value = (in_array($application_data['applicant_type'] ?? '', ['student', 'graduate_student']) ? 'N/A' : '');
            }
            echo htmlspecialchars($budget_value);
        ?>" <?php echo (in_array($application_data['applicant_type'] ?? '', ['student', 'graduate_student']) ? 'readonly' : ''); ?>>

        <div class="row2">
            <div>
                <label>Date Started</label>
                <div style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa;">
                    <?php 
                    // Get date from applications table (research_date_started)
                    $date_started = $application_data['research_date_started'] ?? '';
                    
                    if (!empty($date_started)) {
                        // Convert YYYY-MM-DD to Month YYYY format
                        $timestamp = strtotime($date_started);
                        $formatted_date = date('F Y', $timestamp);
                        echo htmlspecialchars($formatted_date);
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </div>
            </div>
            <div>
                <label>Date Completed</label>
                <div style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa;">
                    <?php 
                    // Get date from applications table (research_date_finished)
                    $date_completed = $application_data['research_date_finished'] ?? '';
                    
                    if (!empty($date_completed)) {
                        // Convert YYYY-MM-DD to Month YYYY format
                        $timestamp = strtotime($date_completed);
                        $formatted_date = date('F Y', $timestamp);
                        echo htmlspecialchars($formatted_date);
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </div>
            </div>
        </div>

        <label>Anti-Plagiarism Software Used</label>
        <input type="text" name="tools" value="Turnitin" placeholder="e.g. Turnitin">
    </div>

    <!-- ===================== CERTIFICATION (staff-only fields) ===================== -->
    <div class="section<?php echo $is_staff ? ' staff-only' : ''; ?>">
        <h2>
            Certification Details
            <?php if ($is_staff): ?>
                <span class="staff-badge">DRD Staff Only</span>
            <?php
endif; ?>
        </h2>

        <?php if (!$is_staff): ?>
        <p style="color:#777; font-style:italic; margin:0 0 15px;">
            The fields below (similarity score, DRD Director, certificate numbers) will be filled in by DRD staff.
            You may leave them blank.
        </p>
        <?php
endif; ?>

        <label>Similarity Score (%)</label>
        <input type="number" name="score" min="0" max="100" step="0.01"
               placeholder="e.g. 18" id="scoreInput"
               value="<?php echo htmlspecialchars($similarity_score); ?>"
               <?php echo !empty($similarity_score) || !$is_staff ? 'readonly' : ''; ?>>
        <div id="scoreDisplay" class="score-display" style="display:none;"></div>

        <label>Name of DRD Director</label>
        <input type="text" name="director_name"
               value="Noel J. Petero, Ph.D."
               <?php echo !$is_staff ? 'readonly' : ''; ?>>

        <div class="row2">
            <div>
                <label>Certificate of Authenticity No.</label>
                <input type="text" name="auth_number" value="<?php echo htmlspecialchars($queue_number); ?>"
                       <?php echo !$is_staff ? 'readonly' : ''; ?>>
            </div>
        </div>

        <label>Date Released</label>
        <input type="date" name="date_released"
               value="<?php echo date('Y-m-d'); ?>"
               <?php echo !$is_staff ? 'readonly' : ''; ?>>
    </div>

    <!-- ===================== DECLARATION ===================== -->
    <div class="section">
        <h2>Declaration / Received By</h2>

        <label>Name of Requestor (Signature over Printed Name)</label>
        <input type="text" name="requestor_name"
               value="<?php echo htmlspecialchars($applicant_name); ?>"
               required placeholder="Your full name">
    </div>

    <button type="submit" id="submitBtn">Generate Certificate</button>
</form>

<script>
// Live similarity score feedback
const scoreInput = document.getElementById('scoreInput');
const scoreDisplay = document.getElementById('scoreDisplay');

if (scoreInput && !scoreInput.readOnly) {
    scoreInput.addEventListener('input', function() {
        const val = parseFloat(this.value);
        if (isNaN(val) || this.value === '') {
            scoreDisplay.style.display = 'none';
            return;
        }
        scoreDisplay.style.display = 'block';
        if (val <= 25) {
            scoreDisplay.className = 'score-display score-ok';
            scoreDisplay.textContent = val.toFixed(2) + '% — Acceptable ✓';
        } else {
            scoreDisplay.className = 'score-display score-fail';
            scoreDisplay.textContent = val.toFixed(2) + '% — Not Acceptable ';
        }
    });
}

// AJAX submit
document.getElementById('qf40Form').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const formData = new FormData(this);

    fetch(window.location.href, {
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
                // Send success message to parent window
                window.parent.postMessage({
                    type: 'qf40Completed',
                    formType: 'qf40',
                    message: data.message,
                    success: true
                }, '*');
            } else {
                alert(data.message);
                window.location.href = 'view-application.php?queue=' + encodeURIComponent('<?php echo $queue_number; ?>');
            }
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'Generate & Download QF-40';
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Generate & Download QF-40';
    });
});
</script>
</body>
</html>