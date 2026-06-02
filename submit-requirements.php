<?php
/**
 * Submit Requirements (4-step Submission)
 * TAU-TeSI Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/email-template-functions.php';

$success_message = '';
$error_message = '';
$queue_number = '';

// Handle AJAX submission for saving similarity score
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tesi_score') {
    header('Content-Type: application/json');
    
    $queue_number = sanitizeInput($_POST['queue_number']);
    $tesi_score = sanitizeInput($_POST['tesi_score']);
    
    if (empty($queue_number) || !is_numeric($tesi_score) || $tesi_score < 0 || $tesi_score > 100) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Update the application with the similarity score
    $stmt = $conn->prepare("UPDATE applications SET similarity_index = ?, last_updated = NOW() WHERE queue_number = ?");
    $stmt->bind_param("ds", $tesi_score, $queue_number);
    
    if ($stmt->execute()) {
        // Check if similarity score exceeds 25% threshold
        $exceeds_threshold = $tesi_score > 25;
        $status_message = '';
        $alert_type = '';
        
        if ($exceeds_threshold) {
            $status_message = "⚠️ Warning: Similarity index of {$tesi_score}% exceeds the acceptable threshold of 25%. Please revise your research paper to reduce similarity. However, your score has been recorded.";
            $alert_type = 'warning';
        } else {
            $status_message = "✅ Similarity index of {$tesi_score}% is within the acceptable threshold (≤25%). Your score has been recorded successfully.";
            $alert_type = 'success';
        }
        
        // Also save to fillable_forms as a form record for tracking
        $formData = [
            'tesi_score' => $tesi_score, 
            'submitted_at' => date('Y-m-d H:i:s'),
            'exceeds_threshold' => $exceeds_threshold,
            'threshold_percent' => 25
        ];
        $formDataJson = json_encode($formData);
        
        $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = 'tesi_score'");
        $checkStmt->bind_param("s", $queue_number);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $formStmt = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW() WHERE queue_number = ? AND form_type = 'tesi_score'");
            $formStmt->bind_param("ss", $formDataJson, $queue_number);
        } else {
            $formStmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data) VALUES (?, 'tesi_score', ?)");
            $formStmt->bind_param("ss", $queue_number, $formDataJson);
        }
        $formStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => $status_message,
            'alert_type' => $alert_type,
            'tesi_score' => $tesi_score,
            'exceeds_threshold' => $exceeds_threshold,
            'threshold_percent' => 25
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    closeDBConnection($conn);
    exit;
}

// Handle AJAX submission for step 2 (Save to DB)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_logs') {
    header('Content-Type: application/json');

    $requestor_name = sanitizeInput($_POST['requestor_name']);
    $researchers_name = sanitizeInput($_POST['researchers_name']);
    $contact_number = sanitizeInput($_POST['contact_number']);
    $email_address = sanitizeInput($_POST['email_address']);
    $applicant_type = sanitizeInput($_POST['applicant_type']);
    $research_title = sanitizeInput($_POST['research_title']);
    $college = sanitizeInput($_POST['college']);
    $program_course = sanitizeInput($_POST['program_course']);
    
    // Handle month input which includes both month and year (YYYY-MM format)
    $date_started_month = sanitizeInput($_POST['date_started_month'] ?? '');
    $date_finished_month = sanitizeInput($_POST['date_finished_month'] ?? '');
    
    // Convert YYYY-MM to YYYY-MM-01 format for database
    $date_started = !empty($date_started_month) ? $date_started_month . '-01' : '';
    $date_finished = !empty($date_finished_month) ? $date_finished_month . '-01' : '';

    // Handle research type
    $research_type = sanitizeInput($_POST['research_type'] ?? '');
    $other_type = ($research_type === 'other') ? sanitizeInput($_POST['other_type'] ?? '') : '';

    // Determine fee
    $application_fee = 0;
    $requires_receipt = false;
    switch ($applicant_type) {
        case 'student':
            $application_fee = 0;
            break;
        case 'graduate_student':
            $application_fee = 500;
            $requires_receipt = true;
            break;
        case 'faculty_university':
            $application_fee = 0;
            break;
        case 'faculty_external':
            $application_fee = 1000;
            $requires_receipt = true;
            break;
    }

    if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Handle Receipt Upload if required
    $receipt_path = '';
    if ($requires_receipt) {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Official Receipt is required for this applicant type.']);
            exit;
        }

        $upload_dir = 'uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format for receipt. Allowed: PDF, JPG, PNG.']);
            exit;
        }

        $receipt_filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_ext;
        $receipt_path = $upload_dir . $receipt_filename;

        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save receipt file.']);
            exit;
        }
    }

    $conn = getDBConnection();

    // Check for existing application by email to prevent duplicates
    $check_email = $conn->prepare("SELECT queue_number, applicant_name, current_status, submission_timestamp FROM applications WHERE applicant_email = ? ORDER BY submission_timestamp DESC LIMIT 1");
    $check_email->bind_param("s", $email_address);
    $check_email->execute();
    $existing_app = $check_email->get_result()->fetch_assoc();

    if ($existing_app) {
        echo json_encode([
            'success' => false, 
            'message' => 'You have already submitted an application. Your existing queue number is ' . $existing_app['queue_number'] . '. Please contact the DRD office if you need to make changes.',
            'existing_queue' => $existing_app['queue_number'],
            'existing_status' => $existing_app['current_status']
        ]);
        exit;
    }

    $queue_number = generateQueueNumber($conn);

    $stmt = $conn->prepare("INSERT INTO applications (
        queue_number, applicant_name, applicant_email, applicant_type, 
        research_title, researchers_name, contact_number, research_type, 
        other_type, application_fee, college, program_course, 
        research_date_started, research_date_finished, current_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $status = STATUS_INTENT_RECEIVED;
    $stmt->bind_param("sssssssssisssss",
        $queue_number, $requestor_name, $email_address, $applicant_type,
        $research_title, $researchers_name, $contact_number, $research_type,
        $other_type, $application_fee, $college, $program_course,
        $date_started, $date_finished, $status
    );

    if ($stmt->execute()) {
        // If receipt uploaded, record it in documents table
        if ($requires_receipt && $receipt_path) {
            $doc_stmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, validation_status) VALUES (?, 'official_receipt', 'Official Receipt', ?, 'pending')");
            $doc_stmt->bind_param("ss", $queue_number, $receipt_path);
            $doc_stmt->execute();
        }

        updateApplicationStatus($queue_number, STATUS_REQUIREMENTS_SENT, null, 'system', 'Automated requirements list sent');

        // Notification logic
        $placeholders = [
            'applicant_name' => $requestor_name,
            'queue_number' => $queue_number,
            'applicant_email' => $email_address,
            'research_title' => $research_title,
            'current_status' => STATUS_REQUIREMENTS_SENT,
            'submission_date' => date('F d, Y'),
            'current_date' => date('F d, Y')
        ];

        $template = getEmailTemplate('REPLY_INTENT');
        $email_subject = $template ? processEmailTemplate($template['subject'], $placeholders) : "Application Received: $queue_number";
        $email_body = $template ? processEmailTemplate($template['body'], $placeholders) : "Hello $requestor_name, your queue number is $queue_number.";

        $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'acknowledgment', ?, ?)");
        $msg_stmt->bind_param("sss", $queue_number, $email_subject, $email_body);
        $msg_stmt->execute();

        // Set session for QF-39 generation and automatic "login"
        $_SESSION['queue_number'] = $queue_number;
        $_SESSION['applicant_authenticated'] = true;
        $_SESSION['applicant_email'] = $email_address;
        $_SESSION['applicant_name'] = $requestor_name;

        echo json_encode(['success' => true, 'queue_number' => $queue_number]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    closeDBConnection($conn);
    exit;
}

$page_title = 'Submit Requirements';
$active_page = 'submit_req';
$base_url = './';
include 'includes/header.php';
?>

<style>
    :root {
        --tau-green: #006400;
        --tau-green-light: #228B22;
        --sidebar-bg: #ffffff;
        --content-bg: #f8f9fa;
        --primary-gradient: linear-gradient(135deg, #006400 0%, #228B22 100%);
    }

    .submission-wrapper {
        display: flex;
        height: calc(100vh - 140px);
        margin: 20px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .form-sidebar {
        width: 300px;
        background: var(--sidebar-bg);
        border-right: 1px solid #eee;
        padding: 30px 20px;
        flex-shrink: 0;
        height: 100%;
        overflow-y: hidden;
    }

    .step-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 10px;
        color: #666;
        transition: all 0.3s;
        cursor: pointer;
        opacity: 0.6;
        pointer-events: none;
    }

    .step-item.active {
        background: var(--tau-green-light);
        color: white;
        opacity: 1;
        pointer-events: auto;
    }

    .step-item.completed {
        color: var(--tau-green);
        opacity: 1;
        pointer-events: auto;
    }

    .step-counter {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .step-item.active .step-counter {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .form-content {
        flex: 1;
        background: var(--content-bg);
        padding: 40px;
        overflow-y: auto;
    }

    .form-section { display: none; }
    .form-section.active { display: block; animation: fadeIn 0.5s ease; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .preview-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border: 1px solid #e0e0e0;
    }

    .preview-item {
        margin-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 8px;
    }

    .preview-label {
        font-size: 12px;
        text-transform: uppercase;
        color: #888;
        font-weight: 700;
    }

    .preview-value {
        font-size: 16px;
        color: #333;
        font-weight: 500;
    }

    /* QF-39 Form styles within tab */
    .qf39-tab-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
</style>

<div class="submission-wrapper">
    <!-- Sidebar -->
    <div class="form-sidebar">
        <h4 class="mb-4 fw-bold">Submit Requirements</h4>
        <div class="step-item active" id="sidebar-step-1">
            <div class="step-counter">1</div>
            <div>
                <div class="fw-bold">Fill Logs</div>
                <small>Basic Information</small>
            </div>
        </div>
        <div class="step-item" id="sidebar-step-2">
            <div class="step-counter">2</div>
            <div>
                <div class="fw-bold">Preview</div>
                <small>Review Data</small>
            </div>
        </div>
        <div class="step-item" id="sidebar-step-3">
            <div class="step-counter">3</div>
            <div>
                <div class="fw-bold">QF-39 Form</div>
                <small>Auto-fill & Print</small>
            </div>
        </div>
        <div class="step-item" id="sidebar-step-4">
            <div class="step-counter">4</div>
            <div>
                <div class="fw-bold">Turnitin Submission</div>
                <small>Submit & Enter Score</small>
            </div>
        </div>
        <div class="step-item" id="sidebar-step-5">
            <div class="step-counter">5</div>
            <div>
                <div class="fw-bold">Success</div>
                <small>Queue Number</small>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="form-content">
        <!-- Step 1: Fill Logs -->
        <div class="form-section active" id="section-1">
            <h3 class="mb-4">Step 1: Fill Logs</h3>
            <form id="submissionForm" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Requestor's Name</label>
                        <input type="text" name="requestor_name" id="field_requestor_name" class="form-control" required placeholder="Full Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Researchers' Names</label>
                        <input type="text" name="researchers_name" id="field_researchers_name" class="form-control" required placeholder="Comma separated">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Contact Number</label>
                        <input type="tel" name="contact_number" id="field_contact_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email_address" id="field_email_address" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Research Title</label>
                        <textarea name="research_title" id="field_research_title" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">College</label>
                        <select name="college" id="field_college" class="form-select" required>
                            <option value="">Select College...</option>
                            <option value="CAF">CAF</option>
                            <option value="CAS">CAS</option>
                            <option value="CBM">CBM</option>
                            <option value="CED">CED</option>
                            <option value="CET">CET</option>
                            <option value="CVM">CVM</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Degree Level</label>
                        <select name="degree_level" id="field_degree_level" class="form-select" required>
                            <option value="">Select Degree Level...</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Program/Course</label>
                        <select name="program_course" id="field_program_course" class="form-select" required>
                            <option value="">Select Program...</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Started</label>
                        <input type="month" name="date_started_month" id="field_date_started_month" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Finished</label>
                        <input type="month" name="date_finished_month" id="field_date_finished_month" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Applicant Type</label>
                        <select name="applicant_type" id="field_applicant_type" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="student">Student</option>
                            <option value="graduate_student">Graduate Student</option>
                            <option value="faculty_university">Faculty (University)</option>
                            <option value="faculty_external">Faculty (External)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Research Type</label>
                        <select name="research_type" id="field_research_type" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="social">Social</option>
                            <option value="technical">Technical</option>
                            <option value="social_technical">Social/Technical</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-12" id="other_type_div" style="display:none;">
                        <input type="text" name="other_type" id="field_other_type" class="form-control" placeholder="Specify other type">
                    </div>
                </div>

                <!-- Fee Information Section -->
                <div id="fee_info_section" class="mt-4" style="display:none;">
                    <div class="alert alert-info border-0 shadow-sm">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-cash-coin fs-4 text-primary me-2"></i>
                            <h5 class="mb-0 fw-bold">Application Fee</h5>
                        </div>
                        <p id="fee_message" class="mb-2 fs-5"></p>
                        <div id="receipt_upload_div" style="display:none;">
                            <hr>
                            <label class="form-label fw-bold"><i class="bi bi-upload"></i> Upload Scanned Official Receipt <span class="text-danger">*</span></label>
                            <input type="file" name="receipt" id="field_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Allowed formats: PDF, JPG, PNG (Max 5MB)</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="button" class="btn btn-secondary px-5 py-2" onclick="showPreview()">Save & Next</button>
                </div>
            </form>
        </div>

        <!-- Step 2: Preview -->
        <div class="form-section" id="section-2">
            <h3 class="mb-4">Step 2: Preview & Confirm</h3>
            <div class="preview-card" id="previewArea">
                <!-- Preview data injected here -->
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(1)">Back to Edit</button>
                <button type="button" class="btn btn-secondary px-5" id="btnSubmitFinal" onclick="submitFinal()">Confirm & Next to QF-39</button>
            </div>
        </div>

        <!-- Step 3: QF-39 Form -->
        <div class="form-section" id="section-3">
            <h3 class="mb-4">Step 3: QF-39 Form Generation</h3>
            <div class="qf39-tab-content">
                <p class="text-muted mb-4">The following information has been mapped from your submission. Please verify and click generate to download your QF-39 form.</p>
                <form id="qf39TabForm" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-success">Research Title</label>
                            <input type="text" name="research_title" id="qf_research_title" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-success">Researcher(s) / Proponent(s)</label>
                            <textarea name="proponents" id="qf_proponents" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Contact Number</label>
                            <input type="tel" name="contacts" id="qf_contacts" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Email Address</label>
                            <input type="email" name="email" id="qf_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Name of Requestor</label>
                            <input type="text" name="requestor_name" id="qf_requestor_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Type of Research</label>
                            <div class="d-flex gap-4 pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="research_type[]" id="qf_type_social" value="social">
                                    <label class="form-check-label" for="qf_type_social">Social</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="research_type[]" id="qf_type_technical" value="technical">
                                    <label class="form-check-label" for="qf_type_technical">Technical</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-success">Proponent's E-Signature</label>
                            <input type="file" name="proponent_signature" id="proponent_signature" class="form-control" accept=".png,.jpg,.jpeg">
                            <small class="text-muted">Upload your e-signature (PNG or JPG format only)</small>
                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <button type="button" class="btn btn-secondary btn-lg px-5" id="btnDownloadQF39" onclick="generateQF39()">
                            <i class="bi bi-file-earmark-word"></i> Generate & Download QF-39
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 4: Turnitin Submission -->
        <div class="form-section" id="section-4">
            <h3 class="mb-4">Step 4: Turnitin Submission & Similarity Score</h3>
            <div class="qf39-tab-content">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-info-circle me-2"></i>Important Instructions for Turnitin Submission
                    </h5>
                    <div id="turnitinInstructions">
                        <!-- Instructions will be dynamically inserted here based on college -->
                    </div>
                </div>
                
                <div class="card border-success shadow-sm">
                    <div class="card-header bg-success bg-opacity-10 border-success">
                        <h5 class="mb-0 text-success fw-bold">
                            <i class="bi bi-percent me-2"></i>Enter Your Similarity Index Score
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="tesiScoreForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Similarity Index Score (%)</label>
                                    <input type="number" name="tesi_score" id="tesi_score" class="form-control" 
                                           min="0" max="100" step="0.01" required placeholder="e.g. 18.5">
                                    <small class="text-muted">Enter the similarity percentage from your Turnitin result</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Status</label>
                                    <div id="scoreStatus" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <button type="button" class="btn btn-success btn-lg px-5" id="btnSaveScore" onclick="saveTesIScore()">
                                    <i class="bi bi-check-circle me-2"></i>Save Similarity Score & Continue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: Success -->
        <div class="form-section" id="section-5">
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>
                <h2 class="text-success fw-bold">Application Complete!</h2>
                <p class="fs-5">Your research has been successfully queued for similarity testing.</p>
                <div class="alert alert-info d-inline-block px-5 py-3 mt-3">
                    <h3 class="mb-0">Your Queue Number: <strong id="displayQueue">...</strong></h3>
                </div>
                
                <div class="mt-5 bg-white p-4 rounded-3 border border-success-subtle shadow-sm">
                    <h5 class="fw-bold mb-3">What's Next?</h5>
                    <ul class="text-start d-inline-block list-unstyled">
                        <li class="mb-2"><i class="bi bi-1-circle text-success me-2"></i> An acknowledgement message was sent to your email.</li>
                        <li class="mb-2"><i class="bi bi-2-circle text-success me-2"></i> Access your <a href="applicant/login.php" class="text-success fw-bold">Applicant Dashboard</a> for updates.</li>
                        <li class="mb-2"><i class="bi bi-3-circle text-success me-2"></i> Use your queue number to <a href="track-application.php" class="text-success fw-bold">Track Progress</a> anytime.</li>
                    </ul>
                </div>
                
                <div class="mt-5">
                    <a href="index.php" class="btn btn-secondary px-5">Return to Portal Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let formDataObj = {};
    let currentQueue = '';

    // Autosave functionality
    const AUTOSAVE_KEY = 'tesi_submission_autosave';

    function saveFormData() {
        const form = document.getElementById('submissionForm');
        if (form) {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            localStorage.setItem(AUTOSAVE_KEY, JSON.stringify(data));
        }
    }

    function loadFormData() {
        const saved = localStorage.getItem(AUTOSAVE_KEY);
        if (saved) {
            try {
                const data = JSON.parse(saved);
                const form = document.getElementById('submissionForm');
                if (form) {
                    Object.keys(data).forEach(key => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && input.type !== 'file') {
                            input.value = data[key];
                            // Trigger change events for dropdowns
                            if (input.tagName === 'SELECT') {
                                input.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                    formDataObj = data;
                }
            } catch (e) {
                console.error('Error loading saved data:', e);
            }
        }
    }

    function clearSavedData() {
        localStorage.removeItem(AUTOSAVE_KEY);
    }

    // Add autosave listeners
    document.addEventListener('DOMContentLoaded', function() {
        loadFormData();
        
        const form = document.getElementById('submissionForm');
        if (form) {
            form.addEventListener('input', function() {
                saveFormData();
            });
            form.addEventListener('change', function() {
                saveFormData();
            });
        }
    });

    const degreePrograms = {
    'CAF': {
        bachelor: [
            'Bachelor of Science in Agriculture (BSA)',
            'Bachelor of Animal Science (BAS)',
            'Bachelor of Science in Food Technology (BSFT)',
            'Bachelor of Science in Forestry (BSF)'
        ],
        master: [
            'MS major in Agronomy',
            'MS major in Animal Science'
        ],
        doctoral: [
            'Ph.D. major in Poultry Production',
            'Ph.D. major in Agronomy'
        ]
    },
    'CAS': {
        bachelor: [
            'Bachelor of Arts in Economics (AB-ECON)',
            'Bachelor of Science in Psychology (BS-PSYCH)',
            'BS Development Communication (BS-DEVCOM)'
        ]
    },
    'CBM': {
        bachelor: [
            'Bachelor of Science and Business Administration (BSBA)',
            'Bachelor of Science in Tourism Management (BSTM)',
            'Bachelor of Science in Entrepreneurship (BS-ENTREP)'
        ]
    },
    'CED': {
        bachelor: [
            'Bachelor of Elementary Education (BEEd)',
            'Bachelor of Early Childhood Education (BECEd)',
            'Bachelor of Secondary Education (BSEd)',
            'Bachelor of Technology and Livelihood Education (BTLEd)'
        ],
        master: [
            'MA major in Education Management',
            'MA major in Science',
            'MA major in Technology Livelihood Education',
            'MA major in Mathematics'
        ],
        doctoral: [
            'Ph.D. major in Development Education'
        ]
    },
    'CET': {
        bachelor: [
            'Bachelor of Science in Agricultural & Biosystems Engineering (BSABE)',
            'Bachelor of Science in Geodetic Engineering (BSGE)',
            'Bachelor of Science in Information Technology (BSIT)'
        ]
    },
    'CVM': {
        doctoral: [
            'Doctor of Veterinary Medicine (CVM)'
        ]
    }
};

    document.getElementById('field_research_type').addEventListener('change', function() {
        document.getElementById('other_type_div').style.display = (this.value === 'other') ? 'block' : 'none';
    });

    document.getElementById('field_college').addEventListener('change', function() {
        const college = this.value;
        const degreeLevel = document.getElementById('field_degree_level');
        const programCourse = document.getElementById('field_program_course');
        
        // Clear existing options
        degreeLevel.innerHTML = '<option value="">Select Degree Level...</option>';
        programCourse.innerHTML = '<option value="">Select Program...</option>';
        
        if (college && degreePrograms[college]) {
            const programs = degreePrograms[college];
            
            // Populate degree level options
            if (programs.bachelor) {
                degreeLevel.innerHTML += '<option value="bachelor">Bachelor\'s Degrees</option>';
            }
            if (programs.master) {
                degreeLevel.innerHTML += '<option value="master">Master\'s Degrees</option>';
            }
            if (programs.doctoral) {
                degreeLevel.innerHTML += '<option value="doctoral">Doctoral Degrees</option>';
            }
            
            // Handle degree level change
            degreeLevel.addEventListener('change', function() {
                const level = this.value;
                programCourse.innerHTML = '<option value="">Select Program...</option>';
                
                if (level && programs[level]) {
                    programs[level].forEach(program => {
                        programCourse.innerHTML += `<option value="${program}">${program}</option>`;
                    });
                }
            });
        }
    });

    document.getElementById('field_applicant_type').addEventListener('change', function() {
        const type = this.value;
        const feeSection = document.getElementById('fee_info_section');
        const feeMsg = document.getElementById('fee_message');
        const receiptDiv = document.getElementById('receipt_upload_div');
        const receiptInput = document.getElementById('field_receipt');

        feeSection.style.display = type ? 'block' : 'none';
        receiptDiv.style.display = 'none';
        receiptInput.required = false;

        switch (type) {
            case 'student':
                feeMsg.innerHTML = '<strong>Free Application</strong>. No fee required for students.';
                break;
            case 'graduate_student':
                feeMsg.innerHTML = '<strong>Fee: ₱500.00</strong>. Please upload your receipt below.';
                receiptDiv.style.display = 'block';
                receiptInput.required = true;
                break;
            case 'faculty_university':
                feeMsg.innerHTML = '<strong>Free Application</strong>. No fee required for university-funded faculty.';
                break;
            case 'faculty_external':
                feeMsg.innerHTML = '<strong>Fee: ₱1,000.00</strong>. Please upload your receipt below.';
                receiptDiv.style.display = 'block';
                receiptInput.required = true;
                break;
        }
    });

    function goToStep(step) {
        document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.step-item').forEach(i => i.classList.remove('active'));
        
        document.getElementById(`section-${step}`).classList.add('active');
        document.getElementById(`sidebar-step-${step}`).classList.add('active');
        
        // Mark previous steps as enabled
        for(let i=1; i<=step; i++){
            document.getElementById(`sidebar-step-${i}`).style.pointerEvents = 'auto';
            document.getElementById(`sidebar-step-${i}`).style.opacity = '1';
        }
    }

    function showPreview() {
        const form = document.getElementById('submissionForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        formDataObj = Object.fromEntries(formData.entries());
        
        let html = '<div class="row">';
        const labels = {
            'requestor_name': "Requestor's Name",
            'researchers_name': "Researchers' Names",
            'contact_number': "Contact Number",
            'email_address': "Email Address",
            'research_title': "Research Title",
            'college': "College",
            'program_course': "Program/Course",
            'date_started_month': "Date Started",
            'date_finished_month': "Date Finished",
            'applicant_type': "Applicant Type",
            'research_type': "Research Type"
        };

        for (const [key, label] of Object.entries(labels)) {
            html += `
                <div class="col-md-6 preview-item">
                    <div class="preview-label">${label}</div>
                    <div class="preview-value">${formDataObj[key] || 'N/A'}</div>
                </div>
            `;
        }
        html += '</div>';
        
        document.getElementById('previewArea').innerHTML = html;
        goToStep(2);
    }

    function submitFinal() {
        const btn = document.getElementById('btnSubmitFinal');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving Application...';

        const form = document.getElementById('submissionForm');
        const data = new FormData(form);
        data.append('action', 'submit_logs');

        fetch('submit-requirements.php', {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                clearSavedData(); // Clear saved data after successful submission
                currentQueue = res.queue_number;
                document.getElementById('displayQueue').innerText = res.queue_number;
                
                // Auto-fill Step 3 QF-39 Form
                document.getElementById('qf_research_title').value = formDataObj.research_title;
                document.getElementById('qf_proponents').value = formDataObj.researchers_name;
                document.getElementById('qf_contacts').value = formDataObj.contact_number;
                document.getElementById('qf_email').value = formDataObj.email_address;
                document.getElementById('qf_requestor_name').value = formDataObj.requestor_name;
                
                // Explicitly check the checkboxes for research type
                if (formDataObj.research_type === 'social') {
                    document.getElementById('qf_type_social').checked = true;
                    document.getElementById('qf_type_technical').checked = false;
                } else if (formDataObj.research_type === 'technical') {
                    document.getElementById('qf_type_social').checked = false;
                    document.getElementById('qf_type_technical').checked = true;
                } else if (formDataObj.research_type === 'social_technical') {
                    document.getElementById('qf_type_social').checked = true;
                    document.getElementById('qf_type_technical').checked = true;
                } else {
                    document.getElementById('qf_type_social').checked = false;
                    document.getElementById('qf_type_technical').checked = false;
                }

                goToStep(3);
            } else {
                alert('Error: ' + res.message);
                btn.disabled = false;
                btn.innerText = 'Confirm & Next to QF-39';
            }
        });
    }

    function generateQF39() {
        const btn = document.getElementById('btnDownloadQF39');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating Document...';
        
        const qfData = new FormData(document.getElementById('qf39TabForm'));

        fetch('fill-qf39-form.php', {
            method: 'POST',
            body: qfData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const link = document.createElement('a');
                link.href = 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,' + data.fileContent;
                link.download = data.filename;
                link.click();
                
                // Show Turnitin instructions after generating QF-39
                setTimeout(() => goToStep(4), 1000);
            } else {
                alert('Generation Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-file-earmark-word"></i> Generate & Download QF-39';
            }
        })
        .catch(err => {
            alert('Network error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-word"></i> Generate & Download QF-39';
        });
    }

    function getTurnitinCredentials(college) {
        const currentYear = new Date().getFullYear();
        const credentials = {
            'CAF': { id: '45860297', key: `TAU-CAF${currentYear}` },
            'CAS': { id: '45860319', key: `TAU-CAS${currentYear}` },
            'CBM': { id: '45860259', key: `TAU-CBM${currentYear}` },
            'CED': { id: '45860345', key: `TAU-CED${currentYear}` },
            'CET': { id: '45860338', key: `TAU-CET${currentYear}` },
            'CVM': { id: '45860361', key: `TAU-CVM${currentYear}` }
        };
        
        const collegeCode = college ? college.toUpperCase().replace(/[^A-Z]/g, '') : '';
        return credentials[collegeCode] || { id: '45860338', key: `TAU-CET${currentYear}` }; // Default to CET
    }

    function displayTurnitinInstructions() {
        const college = formDataObj.college || '';
        const credentials = getTurnitinCredentials(college);
        
        const instructions = `
            <div class="instructions-content">
                <p class="mb-3">Hello,</p>
                <p class="mb-3">Greetings from the Department of Research and Development (DRD)!</p>
                <p class="mb-3">Your application has been approved!</p>
                <p class="mb-3">Kindly follow the steps below to get your TeSI Result:</p>
                
                <ol class="mb-3">
                    <li class="mb-2">Go to <a href="https://www.turnitin.com/newuser_type.asp?r=1.70669158195729&svr=39&lang=en_us&" target="_blank" class="text-primary fw-bold">https://www.turnitin.com/newuser_type.asp?r=1.70669158195729&svr=39&lang=en_us&</a></li>
                    <li class="mb-2">Click "Create Account" and select "Student"</li>
                    <li class="mb-2">Fill up all the information needed, then, Click "I Agree" to create a student account.</li>
                    <li class="mb-2 ps-4">
                        <strong>Class ID:</strong> <span class="badge bg-primary">${credentials.id}</span><br>
                        <strong>Enrollment key:</strong> <span class="badge bg-primary">${credentials.key}</span>
                    </li>
                    <li class="mb-2">After registering, you will be directed to the Turnitin Class Dashboard, then, click the class (ex. TESI TAU-CBM).</li>
                    <li class="mb-2">After clicking the Class, select the "Submit" button.</li>
                    <li class="mb-2">To submit your research paper, Click the dropdown arrow and select "Single File Upload"</li>
                    <li class="mb-2 ps-4">
                        -Encode your "First Name, Last Name, and Research Title"<br>
                        -Then, Choose File to Upload<br>
                        <small class="text-muted"><strong>Reminder:</strong> Upload your TITLE, and CHAPTERS 1-5 ONLY in one file.</small>
                    </li>
                    <li class="mb-2">And wait for the TeSI Result to Generate</li>
                    <li class="mb-2 ps-4">
                        <strong>Note:</strong> Acceptable Similarity Index for Undergraduate Thesis is 25%<br>
                        If your similarity index is higher than 25% revised your research paper
                    </li>
                    <li class="mb-2">Finally, research paper with 25% similarity index will be given a "Similarity Index Certificate"</li>
                    <li class="mb-2 ps-4">To claim the certificate, print all TeSI forms (Form 39, and Turnitin Result).<br>Check Certification status, then proceed to the DRD Office.</li>
                </ol>
                
                <p class="mb-3">Regards,<br>
                Turnitin Administrator/DRD Technical Staff<br>
                Department for Research and Development<br>
                Tarlac Agricultural University</p>
            </div>
        `;
        
        document.getElementById('turnitinInstructions').innerHTML = instructions;
    }

    function saveTesIScore() {
        const score = document.getElementById('tesi_score').value;
        
        if (!score || score < 0 || score > 100) {
            alert('Please enter a valid similarity score between 0 and 100');
            return;
        }
        
        const btn = document.getElementById('btnSaveScore');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        
        // Save the similarity score to the database
        const data = new FormData();
        data.append('action', 'save_tesi_score');
        data.append('queue_number', currentQueue);
        data.append('tesi_score', score);
        
        fetch('submit-requirements.php', {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Show appropriate alert based on threshold
                if (res.exceeds_threshold) {
                    // Show danger alert for scores exceeding 25%
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <strong>⚠️ Similarity Index Exceeds Acceptable Threshold!</strong><br>
                        ${res.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    const container = document.querySelector('.step-content.active') || document.body;
                    container.insertBefore(alertDiv, container.firstChild);
                    
                    // Scroll to top to show the alert
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    // Show success alert for acceptable scores
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <strong>✅ Similarity Index Accepted!</strong><br>
                        ${res.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    const container = document.querySelector('.step-content.active') || document.body;
                    container.insertBefore(alertDiv, container.firstChild);
                }
                
                goToStep(5);
            } else {
                alert('Error: ' + res.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Save Similarity Score & Continue';
            }
        })
        .catch(err => {
            alert('Network error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Save Similarity Score & Continue';
        });
    }

    // Update the submitFinal function to show Turnitin instructions when moving to step 4
    const originalGoToStep = goToStep;
    goToStep = function(step) {
        originalGoToStep(step);
        
        if (step === 4) {
            displayTurnitinInstructions();
        }
    };

    // Add similarity score validation
    document.getElementById('tesi_score')?.addEventListener('input', function() {
        const score = parseFloat(this.value);
        const statusDiv = document.getElementById('scoreStatus');
        
        if (!isNaN(score) && score >= 0 && score <= 100) {
            if (score <= 25) {
                statusDiv.innerHTML = '<span class="badge bg-success">✓ Acceptable (≤25%)</span>';
            } else {
                statusDiv.innerHTML = '<span class="badge bg-danger">✗ Not Acceptable (>25%)</span>';
            }
        } else {
            statusDiv.innerHTML = '';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
