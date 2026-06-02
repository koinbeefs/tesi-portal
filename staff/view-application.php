<?php
/**
 * View Application Details (Staff)
 * TAU-TeSI Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';
$staff_id = $_SESSION['user_id'];

if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

// Handle file download if requested
if (isset($_GET['download']) && isset($_SESSION['download_file'])) {
    $file = $_SESSION['download_file'];
    $filename = $_SESSION['download_filename'];

    if (file_exists($file) && !empty($filename)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        unlink($file);
    }

    unset($_SESSION['download_file']);
    unset($_SESSION['download_filename']);
    exit;
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: dashboard.php?error=notfound");
    exit();
}

// Auto-claim unassigned applications (improved logic)
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    // Use a transaction to prevent race conditions
    $conn->begin_transaction();
    
    try {
        // Check if still unassigned (double-check)
        $check_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ? FOR UPDATE");
        $check_stmt->bind_param("s", $queue_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if (!$check_result['assigned_staff_id']) {
            // Assign to current staff member
            $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ? AND assigned_staff_id IS NULL");
            $claim_stmt->bind_param("is", $staff_id, $queue_number);
            $claim_stmt->execute();
            
            if ($claim_stmt->affected_rows > 0) {
                $just_claimed = true;
                $application['assigned_staff_id'] = $staff_id; // Update the local copy
                
                // Log the auto-claim activity (using correct column names)
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details, timestamp) VALUES (?, ?, 'other', 'Auto-claimed application for review', NOW())");
                $log_stmt->bind_param("is", $staff_id, $queue_number);
                $log_stmt->execute();
            }
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Assignment error: " . $e->getMessage());
    }
}

// Get assigned staff name if application is assigned
$assigned_staff_name = null;
if ($application['assigned_staff_id']) {
    $assigned_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $assigned_stmt->bind_param("i", $application['assigned_staff_id']);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result()->fetch_assoc();
    $assigned_staff_name = $assigned_result['full_name'];
}

// Check if current user can edit this application
// Allow all staff to view, but only assigned staff (or unassigned) can edit
$can_edit = ($application['assigned_staff_id'] == $staff_id || !$application['assigned_staff_id']);

// Allow all staff to access any application (view access)
// Edit access is controlled by $can_edit variable above

// Log activity
logStaffActivity($staff_id, $queue_number, 'viewed_application', 'Viewed application details');

// Get fillable forms status
$forms_stmt = $conn->prepare("SELECT form_type, form_data, file_generated, completed_at FROM fillable_forms WHERE queue_number = ?");
$forms_stmt->bind_param("s", $queue_number);
$forms_stmt->execute();
$forms_result = $forms_stmt->get_result();
$fillable_forms_status = [];
while ($row = $forms_result->fetch_assoc()) {
    $form_data = json_decode($row['form_data'], true);
    
    // For QF-39, consider it completed if form data exists and has required fields
    $is_completed = false;
    if ($row['form_type'] === 'qf39') {
        $is_completed = !empty($form_data) && !empty($form_data['requestor_name']) && !empty($form_data['research_title']);
    } else {
        // For other forms (like QF-40), check file_generated
        $is_completed = (bool)$row['file_generated'];
    }
    
    $fillable_forms_status[$row['form_type']] = [
        'completed' => $is_completed,
        'completed_at' => $row['completed_at'],
        'data' => $form_data
    ];
}

// Check for AI classification
$ai_classification = null;
$ai_file_path = '../uploads/' . $queue_number . '/ai_classification.json';
if (file_exists($ai_file_path)) {
    $ai_classification = json_decode(file_get_contents($ai_file_path), true);
}

// Get documents
$docs_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? ORDER BY upload_timestamp DESC");
$docs_stmt->bind_param("s", $queue_number);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();

// Handle Similarity Index submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_similarity'])) {
    $similarity_score = floatval($_POST['similarity_score']);
    $test_number = intval($_POST['test_number']);
    $conn = getDBConnection();

    // Update application with similarity index
    $update_app = $conn->prepare("UPDATE applications SET similarity_index = ?, test_count = ? WHERE queue_number = ?");
    $update_app->bind_param("dis", $similarity_score, $test_number, $queue_number);
    $update_app->execute();

    // Check threshold and update status
    $classification = $application['applicant_type'] ?? 'graduate'; // Fallback
    if (meetsSimilarityRequirement($similarity_score, $classification)) {
        updateApplicationStatus($queue_number, 'CATEGORIZED', $staff_id, 'staff', "Similarity index met threshold ({$similarity_score}%). Ready for certificate.");
    }
    else {
        $status = ($test_number < 5) ? 'REVISIONS_REQUIRED' : 'REJECTED';
        $note = ($test_number < 5) ? "Similarity index exceeds threshold ({$similarity_score}%). Revision required." : "Maximum re-tests (5) exceeded.";
        updateApplicationStatus($queue_number, $status, $staff_id, 'staff', $note);
    }

    closeDBConnection($conn);
    header("Location: view-application.php?queue=$queue_number&success=similarity_saved");
    exit();
}

$page_title = 'View TeSI Request';
$base_url = '../';
$active_menu = 'dashboard';
include '../includes/auth_header.php';
?>

<style>
    :root {
        --tau-green-dark: #006400;
        --tau-green-primary: #228B22;
        --tau-green-light: #e8f5e9;
        --tau-accent: #ffd700;
    }

    .app-header {
        background: white;
        padding: 2rem 2.5rem;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 2rem;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .queue-badge {
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 700;
        font-family: 'Monaco', 'Consolas', monospace;
        letter-spacing: 1px;
        box-shadow: 0 4px 10px rgba(0, 100, 0, 0.2);
    }

    .section-card {
        border: none;
        border-radius: 16px;
        background: white;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .section-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }

    .section-card .card-header {
        background: white;
        border-bottom: 2px solid #f8f9fa;
        padding: 1.5rem 2rem;
        font-weight: 700;
        color: var(--tau-green-dark);
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.1rem;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }

    .status-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .document-item {
        padding: 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        border: 1px solid #eee;
        transition: all 0.2s;
        margin-bottom: 0.75rem;
    }

    .document-item:hover {
        background: white;
        border-color: var(--tau-green-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }

    .chat-container {
        height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: #fcfcfc;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .msg-bubble {
        max-width: 85%;
        padding: 0.8rem 1.2rem;
        border-radius: 15px;
        font-size: 0.95rem;
        position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }

    .msg-sent {
        align-self: flex-end;
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        border-bottom-right-radius: 2px;
    }

    .msg-received {
        align-self: flex-start;
        background: white;
        border: 1px solid #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .msg-meta {
        font-size: 0.7rem;
        margin-top: 0.4rem;
        opacity: 0.8;
    }

    .timeline-wrapper {
        padding-left: 1.5rem;
        border-left: 2px solid #f0f0f0;
        margin-left: 0.75rem;
        position: relative;
    }

    .timeline-point {
        position: absolute;
        left: -9px;
        width: 16px;
        height: 16px;
        color: var(--tau-green-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }

    .status-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .document-item {
        padding: 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        border: 1px solid #eee;
        transition: all 0.2s;
        margin-bottom: 0.75rem;
    }

    .document-item:hover {
        background: white;
        border-color: var(--tau-green-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }

    .chat-container {
        height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: #fcfcfc;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .msg-bubble {
        max-width: 85%;
        padding: 0.8rem 1.2rem;
        border-radius: 15px;
        font-size: 0.95rem;
        position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }

    .msg-sent {
        align-self: flex-end;
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        border-bottom-right-radius: 2px;
    }

    .msg-received {
        align-self: flex-start;
        background: white;
        border: 1px solid #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .msg-meta {
        font-size: 0.7rem;
        margin-top: 0.4rem;
        opacity: 0.8;
    }

    .timeline-wrapper {
        padding-left: 1.5rem;
        border-left: 2px solid #f0f0f0;
        margin-left: 0.75rem;
        position: relative;
    }

    .timeline-point {
        position: absolute;
        left: -9px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: white;
        border: 3px solid var(--tau-green-primary);
    }

    .action-btn {
        border-radius: 8px;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .nav-tabs-custom {
        border-bottom: 3px solid #f8f9fa;
        gap: 2rem;
        padding: 0 1rem;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 600;
        padding: 1.25rem 1rem;
        position: relative;
        background: transparent;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .nav-tabs-custom .nav-link:hover {
        color: var(--tau-green-primary);
        background: rgba(0, 100, 0, 0.05);
    }

    .nav-tabs-custom .nav-link.active {
        color: white;
        background: var(--tau-green-primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .nav-tabs-custom .nav-link.active::after {
        display: none;
    }

    .ai-badge {
        background: #6f42c1;
        color: white;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .document-item {
        padding: 1rem;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        background: white;
        transition: all 0.2s;
        margin-bottom: 0.5rem;
    }

    .document-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-color: #dee2e6;
    }

    .message-content {
        transition: all 0.2s ease;
    }

    .message-content:hover {
        background-color: #f8f9fa !important;
        border-color: var(--tau-green-primary) !important;
        transform: scale(1.01);
    }
</style>

<div class="container-fluid py-4">
    <?php if ($just_claimed): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-hand-index-thumb"></i> Application has been automatically assigned to you for review.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET['success'] === 'message_sent'): ?>
                <i class="bi bi-check-circle"></i> System message sent successfully!
            <?php
    elseif ($_GET['success'] === 'action_completed'): ?>
                <i class="bi bi-check-circle"></i> Action completed successfully!
            <?php
    elseif ($_GET['success'] === 'remarks_saved'): ?>
                <i class="bi bi-check-circle"></i> QF-40 remarks saved successfully! Document will download automatically.
            <?php
    endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php if ($_GET['error'] === 'empty_message'): ?>
                <i class="bi bi-exclamation-triangle"></i> Please enter a message.
            <?php
    elseif ($_GET['error'] === 'send_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to send message. Please try again.
            <?php
    elseif ($_GET['error'] === 'action_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to process action. Please try again.
            <?php
    elseif ($_GET['error'] === 'message_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to send system message. Please try again.
            <?php
    elseif ($_GET['error'] === 'invalid_request'): ?>
                <i class="bi bi-exclamation-triangle"></i> Invalid request parameters.
            <?php
    endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>
    
    <!-- Header -->
    <div class="app-header">
        <div class="d-flex align-items-center gap-3">
            <a href="applications.php" class="btn btn-light border btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark">Request Details</h4>
                <small class="text-muted">Review and manage similarity index request</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="queue-badge">
                    <i class="bi bi-ticket-detailed me-1"></i> <?php echo htmlspecialchars($queue_number); ?>
                </div>
            </div>
            <div class="status-pill bg-light border text-dark px-3 py-2 rounded-pill">
                <i class="bi bi-info-circle me-1"></i> <?php echo getStatusDisplayName($application['current_status']); ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Applicant Overview Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-file-earmark-person"></i> Applicant Overview
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="info-label">Applicant Name</div>
                                <div class="info-value fs-5"><?php echo htmlspecialchars($application['applicant_name']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['applicant_email']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Applicant Type</div>
                                <div class="info-value">
                                    <span class="badge bg-light text-dark border"><?php echo ucfirst($application['applicant_type']); ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="info-label">Assignment</div>
                                <div class="info-value">
                                    <?php if ($application['assigned_staff_id']): ?>
                                        <?php if ($application['assigned_staff_id'] == $staff_id): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Assigned to you</span>
                                        <?php
    else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($assigned_staff_name); ?></span>
                                        <?php
    endif; ?>
                                    <?php
else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Unassigned</span>
                                    <?php
endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="info-label">Research Title</div>
                                <div class="info-value" style="line-height: 1.4;"><?php echo htmlspecialchars($application['research_title']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Submission Date</div>
                                <div class="info-value"><?php echo formatDate($application['submission_timestamp']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Application Status</div>
                                <div class="info-value">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                        <?php 
                                        $status = $application['current_status'] ?? 'Unknown';
                                        $status_display = '';
                                        switch($status) {
                                            case 'REQUIREMENTS_SENT':
                                                $status_display = 'Requirements Sent';
                                                break;
                                            case 'APPROVED':
                                                $status_display = 'Approved';
                                                break;
                                            default:
                                                $status_display = 'Requirements Sent';
                                        }
                                        echo $status_display;
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Details -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-clipboard-check"></i> Form Details
                </div>
                <div class="card-body p-4">
                    <?php if (isset($fillable_forms_status['qf39']) && $fillable_forms_status['qf39']['completed'] && !empty($fillable_forms_status['qf39']['data'])): ?>
                        <?php $qf39_data = $fillable_forms_status['qf39']['data']; ?>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <div class="info-label">Requestor Name</div>
                                    <div class="info-value fs-5"><?php echo htmlspecialchars($qf39_data['requestor_name'] ?? 'Not specified'); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($qf39_data['email'] ?? $application['applicant_email']); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Contact Information</div>
                                    <div class="info-value"><?php echo htmlspecialchars($qf39_data['contacts'] ?? 'Not specified'); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Research Type</div>
                                    <div class="info-value">
                                        <span class="badge bg-light text-dark border"><?php 
                                            $research_type = $qf39_data['research_type'] ?? [];
                                            if (is_array($research_type)) {
                                                echo implode(', ', array_map('ucfirst', $research_type));
                                            } else {
                                                echo ucfirst($research_type);
                                            }
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <div class="info-label">Research Title</div>
                                    <div class="info-value" style="line-height: 1.4;"><?php echo htmlspecialchars($qf39_data['research_title'] ?? 'Not specified'); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Proponents</div>
                                    <div class="info-value" style="line-height: 1.4;"><?php echo htmlspecialchars($qf39_data['proponents'] ?? 'Not specified'); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Similarity Score</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['similarity_index'] ?? 'Not specified'); ?>%</div>
                                </div>
                                <div class="mb-4">
                                    <div class="info-label">Assignment</div>
                                    <div class="info-value">
                                        <?php if ($application['assigned_staff_id']): ?>
                                            <?php if ($application['assigned_staff_id'] == $staff_id): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Assigned to you</span>
                                            <?php
    else: ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($assigned_staff_name); ?></span>
                                            <?php
    endif; ?>
                                        <?php
else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Unassigned</span>
                                        <?php
endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-x display-4 d-block mb-3"></i>
                            <h5>QF-39 Form Not Completed</h5>
                            <p>The QF-39 form has not been filled yet for this application.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="section-card" id="reviewActionsSection">
                <div class="card-header">
                    <i class="bi bi-lightning"></i> Quick Actions
                </div>
                <div class="card-body p-4">
                    <?php if ($can_edit): ?>
                        <div class="d-grid gap-3">
                            <button class="action-btn btn btn-success" onclick="openQf40Modal()">
                                <i class="bi bi-file-earmark-plus"></i> Create Certificate
                            </button>
                            <?php if (isset($fillable_forms_status['qf39']) && $fillable_forms_status['qf39']['completed']): ?>
                                <button class="action-btn btn btn-outline-primary" onclick="downloadQf39()">
                                    <i class="bi bi-download"></i> Download QF-39
                                </button>
                            <?php endif; ?>
                            <?php if (isset($fillable_forms_status['qf40']) && $fillable_forms_status['qf40']['completed']): ?>
                                <button class="action-btn btn btn-outline-success" onclick="downloadQf40()">
                                    <i class="bi bi-download"></i> Download QF-40
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php
else: ?>
                        <div class="alert alert-warning border-0 small mb-0">
                            <i class="bi bi-lock-fill me-2"></i> This application is currently assigned to <strong><?php echo htmlspecialchars($assigned_staff_name); ?></strong>.
                        </div>
                    <?php
endif; ?>
                </div>
            </div>

            <!-- AI Insights Card -->
            <?php
$ai_data = null;
$ai_file_path = '../uploads/' . $queue_number . '/ai_classification.json';
if (file_exists($ai_file_path)) {
    $ai_data = json_decode(file_get_contents($ai_file_path), true);
}
if ($ai_data):
?>
                <div class="section-card border-primary-subtle">
                    <div class="card-header bg-primary bg-opacity-10">
                        <i class="bi bi-robot"></i> AI Classification <span class="ai-badge ms-auto">Beta</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <div class="info-label">Predicted Category</div>
                            <div class="fw-bold text-primary"><?php echo $ai_data['ai_prediction']['predicted'] ?? 'Unknown'; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Confidence Score</div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo($ai_data['ai_prediction']['max_score'] ?? 0) * 100; ?>%"></div>
                            </div>
                            <div class="text-end small mt-1"><?php echo round(($ai_data['ai_prediction']['max_score'] ?? 0) * 100); ?>%</div>
                        </div>
                        <button class="btn btn-sm btn-light border w-100" onclick="openAiClassification()">
                            <i class="bi bi-eye me-1"></i> Review AI Analysis
                        </button>
                    </div>
                </div>
            <?php
endif; ?>

        </div>
    </div>

    <!-- Approve Application Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #198754, #28a745); color: white;">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Approve Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="approveFrame" style="width: 100%; height: 60vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Revision Modal -->
    <div class="modal fade" id="revisionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-arrow-clockwise"></i> Request Revision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="revisionFrame" style="width: 100%; height: 70vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Classification Modal -->
    <?php if ($ai_classification): ?>
    <div class="modal fade" id="aiClassificationModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="modal-title"><i class="bi bi-robot"></i> AI Classification Review</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="aiClassificationFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php
endif; ?>

    <!-- QF40 Remarks Modal -->
    <div class="modal fade" id="qf40RemarksModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit QF-40 Remarks</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="qf40RemarksFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title" id="previewModalTitle"><i class="bi bi-files"></i> Document Preview</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" id="validateDocBtn" onclick="validateCurrentDocument()" style="display: none;">
                            <i class="bi bi-check-circle"></i> Validate
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="rejectDocBtn" onclick="rejectCurrentDocument()" style="display: none;">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="documentFrame" style="width: 100%; height: calc(100vh - 120px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="process-action.php">
                <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                <input type="hidden" name="action" value="reject">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This action will reject the application.
                        </div>
                        <p>Please provide a reason for rejection:</p>
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="notes" rows="5" required placeholder="Explain the reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" <?php echo !$can_edit ? 'disabled' : ''; ?>>Reject Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentDocumentId = null;

function previewDocument(path, name, documentId, validationStatus) {
    if (!path || path === '') {
        alert('Document path is not available.');
        return;
    }
    
    currentDocumentId = documentId;
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    document.getElementById('previewModalTitle').innerHTML = '<i class="bi bi-files"></i> ' + (name || 'Document Preview');
    
    const iframe = document.getElementById('documentFrame');
    
    // Clear previous content
    iframe.src = '';
    
    modal.show();
    
    // Set iframe source
    iframe.src = 'view-document.php?path=' + encodeURIComponent(path);
    
    // Update button visibility based on validation status and user permissions
    const validateBtn = document.getElementById('validateDocBtn');
    const rejectBtn = document.getElementById('rejectDocBtn');
    
    if (validateBtn && rejectBtn) {
        // Only show buttons if user can edit AND document is not already validated
        if (<?php echo $can_edit ? 'true' : 'false'; ?> && validationStatus !== 'validated') {
            validateBtn.style.display = 'inline-block';
            rejectBtn.style.display = 'inline-block';
        } else {
            validateBtn.style.display = 'none';
            rejectBtn.style.display = 'none';
        }
    }
}

function validateCurrentDocument() {
    if (!currentDocumentId) return;
    
    if (confirm('Are you sure you want to validate this document?')) {
        performDocumentAction('validate', currentDocumentId);
    }
}

function rejectCurrentDocument() {
    if (!currentDocumentId) return;
    
    const notes = prompt('Please provide a reason for rejection (optional):');
    if (notes !== null) {
        performDocumentAction('reject', currentDocumentId, notes);
    }
}

function performDocumentAction(action, documentId, notes = '') {
    const formData = new FormData();
    formData.append('document_id', documentId);
    formData.append('action', action);
    formData.append('notes', notes);
    
    fetch('validate-document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('previewModal')).hide();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error processing document: ' + error.message);
    });
}

function openAiClassification() {
    const iframe = document.getElementById('aiClassificationFrame');
    iframe.src = 'ai-classification.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('aiClassificationModal')).show();
}

function downloadQf39() {
    window.location.href = 'download-qf39.php?queue=<?php echo urlencode($queue_number); ?>';
}

function downloadQf40() {
    window.location.href = 'download-qf40.php?queue=<?php echo urlencode($queue_number); ?>';
}

function openQf02Remarks() {
    const iframe = document.getElementById('qf40RemarksFrame');
    iframe.src = 'edit-qf40-remarks.php?queue=<?php echo urlencode($queue_number); ?>&modal=1';
    
    new bootstrap.Modal(document.getElementById('qf40RemarksModal')).show();
}


function openApproveModal() {
    const iframe = document.getElementById('approveFrame');
    iframe.src = 'approve-application.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function updateReviewCategory(category) {
    fetch('update-review-category.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            queue_number: '<?php echo $queue_number; ?>',
            review_category: category
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Review category updated to: ' + category);
        } else {
            console.error('Failed to update review category:', data.message);
        }
    })
    .catch(error => {
        console.error('Error updating review category:', error);
    });
}

// Listen for messages from AI classification iframe
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'aiReviewCompleted') {
        if (event.data.success) {
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('aiClassificationModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle QF-40 form completion
    if (event.data && event.data.type === 'formCompleted' && event.data.formType === 'qf40') {
        if (event.data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('qf40Modal'));
            if (modal) modal.hide();
            
            // Update review category to Done
            updateReviewCategory('Done');
            
            // Remove Review Actions section
            const reviewActionsSection = document.getElementById('reviewActionsSection');
            if (reviewActionsSection) {
                reviewActionsSection.remove();
            }
            
            // Reload page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    }
    
    // Handle QF02 remarks modal messages
    if (event.data && event.data.type === 'qf40RemarksCompleted') {
        if (event.data.success) {
            // Trigger download if URL provided
            if (event.data.download_url) {
                const link = document.createElement('a');
                link.href = 'edit-qf40-remarks.php?download=' + encodeURIComponent(event.data.download_url);
                link.download = '';
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('qf40RemarksModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle revision modal messages
    if (event.data && event.data.type === 'closeRevisionModal') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('revisionModal'));
        if (modal) modal.hide();
    }
    
    if (event.data && event.data.type === 'revisionRequestSent') {
        if (event.data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('revisionModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle approve modal messages
    if (event.data && event.data.type === 'closeApproveModal') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
        if (modal) modal.hide();
    }
    
    if (event.data && event.data.type === 'applicationApproved') {
        if (event.data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
});

function openRevisionModal() {
    const iframe = document.getElementById('revisionFrame');
    iframe.src = 'request-revision.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('revisionModal')).show();
}

// Auto-dismiss alerts after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(function(alert) {
        // Set timeout to auto-dismiss after 3 seconds
        setTimeout(function() {
            // Check if alert still exists in DOM
            if (alert && alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 3000);
    });
});

// QF-40 Modal Functions
function openQf40Modal() {
    const qf40Modal = new bootstrap.Modal(document.getElementById('qf40Modal'));
    const iframe = document.getElementById('qf40Frame');
    
    // Load the QF-40 form
    iframe.src = 'fill-qf40-form.php?queue=<?php echo urlencode($queue_number); ?>';
    
    // Show the modal
    qf40Modal.show();
}

// Handle messages from QF-40 iframe
window.addEventListener('message', function(event) {
    // Verify origin for security
    if (event.origin !== window.location.origin) return;
    
    if (event.data && event.data.type === 'qf40Completed') {
        if (event.data.success) {
            // Close modal
            const qf40Modal = bootstrap.Modal.getInstance(document.getElementById('qf40Modal'));
            if (qf40Modal) qf40Modal.hide();
            
            // Show success message
            showAlert('success', 'QF-40 certificate created successfully!');
            
            // Reload page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }
});

// Helper function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the container
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 3000);
    }
}

</script>

<!-- QF-40 Modal -->
<div class="modal fade" id="qf40Modal" tabindex="-1" aria-labelledby="qf40ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qf40ModalLabel">
                    <i class="bi bi-file-earmark-text"></i> Create QF-40 Certificate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="qf40Frame" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/auth_footer.php'; ?>
