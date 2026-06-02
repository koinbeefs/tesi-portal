<?php
/**
 * Application Tracking Page
 * TAU-TeSI Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$tracking_result = null;
$error_message = '';
$queue_number_search = '';

// Handle tracking search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_application'])) {
    $queue_number_input = sanitizeInput($_POST['queue_number']);

    // Auto-prepend TESI- prefix and pad to 4 digits
    $number_part = preg_replace('/[^0-9]/', '', $queue_number_input);
    $queue_number_search = QUEUE_PREFIX . str_pad($number_part, 4, '0', STR_PAD_LEFT);

    if (!empty($queue_number_search)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT a.queue_number, a.current_status, a.last_updated, a.submission_timestamp, a.category,
                   (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count,
                   (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND sender_type = 'staff') as staff_messages
            FROM applications a
            WHERE a.queue_number = ?
        ");
        $stmt->bind_param("s", $queue_number_search);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $tracking_result = $result->fetch_assoc();

            // Get status history
            $history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp ASC");
            $history_stmt->bind_param("s", $queue_number_search);
            $history_stmt->execute();
            $status_history = $history_stmt->get_result();
        }
        else {
            $error_message = "Queue number not found. Please check and try again.";
        }

        closeDBConnection($conn);
    }
    else {
        $error_message = "Please enter a queue number.";
    }
}

$page_title = 'Track Application';
$active_page = 'track';
include 'includes/header.php';
?>
   
</head>
<body style="font-family: 'Inter', sans-serif; background: #f9fafb;">

    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); color: white; padding: 3rem 0;">
        <div class="container text-center" style="max-width: 1280px; margin: 0 auto; padding: 0 1rem;">
            <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.75rem; letter-spacing: -0.025em; color: white;">
                <i class="bi bi-search"></i> Track Your Application
            </h1>
            <p style="font-size: 1.125rem; color: rgba(255, 255, 255, 0.95); margin-bottom: 2rem;">
                Enter your queue number to check the status of your similarity index request
            </p>
            
            <!-- Search Form -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <form method="POST" action="" style="background: white; border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
                        <div style="padding: 2rem;">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-0" style="border-radius: 0.5rem 0 0 0.5rem; font-weight: bold; color: #495057;">
                                    <?php echo QUEUE_PREFIX; ?>
                                </span>
                                <input type="text" class="form-control" name="queue_number" 
                                       placeholder="0001" required 
                                       pattern="\d{1,4}" title="Enter 1-4 digits"
                                       value="<?php echo htmlspecialchars(preg_replace('/^TESI-/', '', $queue_number_search)); ?>"
                                       maxlength="4" autofocus style="border-left: none;">
                                <button type="submit" name="track_application" class="btn btn-secondary" style="border-radius: 0 0.5rem 0.5rem 0;">
                                    <i class="bi bi-search"></i> Track
                                </button>
                            </div>
                            <div class="form-text text-start text-dark mt-2">
                                <i class="bi bi-info-circle"></i> Enter just the number part (e.g., 0001 or 1)
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Results Section -->
    <section class="container pb-5" style="max-width: 1280px; margin: 0 auto; padding: 2rem 1rem;">
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Not Found:</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-question-circle display-1 text-muted"></i>
                    <h3 class="mt-4">Can't find your queue number?</h3>
                    <p class="text-muted">If you haven't submitted an application yet:</p>
                    <a href="submit-requirements.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-file-earmark-text"></i> Submit Requirements
                    </a>
                </div>
            </div>
        <?php
endif; ?>

        <?php if ($tracking_result): ?>
            <!-- Application Info Card -->
            <div class="card shadow mb-4 fade-in">
                <div class="card-header text-white" style="background-color: #0a6e2c;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-file-earmark-check"></i> 
                            Application: <?php echo htmlspecialchars($tracking_result['queue_number']); ?>
                        </h4>
                        <span class="badge bg-light text-dark fs-6">
                            <?php echo getStatusDisplayName($tracking_result['current_status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-calendar-check text-primary display-6"></i>
                                <p class="mb-1 mt-2 small text-muted">Submitted</p>
                                <p class="mb-0 fw-bold"><?php echo date('M d, Y', strtotime($tracking_result['submission_timestamp'])); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-clock-history text-info display-6"></i>
                                <p class="mb-1 mt-2 small text-muted">Last Updated</p>
                                <p class="mb-0 fw-bold"><?php echo date('M d, Y', strtotime($tracking_result['last_updated'])); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-files text-success display-6"></i>
                                <p class="mb-1 mt-2 small text-muted">Documents</p>
                                <p class="mb-0 fw-bold"><?php echo $tracking_result['doc_count']; ?> Uploaded</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-tag text-warning display-6"></i>
                                <p class="mb-1 mt-2 small text-muted">Category</p>
                                <p class="mb-0 fw-bold text-capitalize">
                                    <?php echo $tracking_result['category'] ? htmlspecialchars($tracking_result['category']) : 'Pending'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Progress -->
            <div class="card shadow mb-4 fade-in">
                <div class="card-header text-white" style="background-color: #0a6e2c;">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Application Progress</h5>
                </div>
                <div class="card-body p-4">
                    <?php
    // Calculate progress metrics
    $current_status = $tracking_result['current_status'];
    $doc_count = $tracking_result['doc_count'];
    $staff_messages = $tracking_result['staff_messages'];
    $submission_date = strtotime($tracking_result['submission_timestamp']);
    $days_since_submission = floor((time() - $submission_date) / (60 * 60 * 24));

    // Determine progress percentage and status
    $progress_percentage = 0;
    $progress_status = '';
    $progress_color = 'secondary';

    switch ($current_status) {
        case 'INTENT_RECEIVED':
        case 'REQUIREMENTS_SENT':
            $progress_percentage = 20;
            $progress_status = 'Application Submitted';
            $progress_color = 'info';
            break;
        case 'REQUIREMENTS_PENDING':
        case 'REQUIREMENTS_INCOMPLETE':
            $progress_percentage = 40;
            $progress_status = 'Documents Required';
            $progress_color = 'warning';
            break;
        case 'UNDER_AUTO_REVIEW':
        case 'STAFF_REVIEW_REQUIRED':
            $progress_percentage = 60;
            $progress_status = 'Under Review';
            $progress_color = 'primary';
            break;
        case 'REGISTERED':
        case 'CATEGORIZED':
            $progress_percentage = 70;
            $progress_status = 'Registered & Categorized';
            $progress_color = 'primary';
            break;
        case 'FORWARDED_FOR_TESTING':
        case 'UNDER_SIMILARITY_TESTING':
        case 'COMPLIANCE_PENDING':
        case 'COMPLIANCE_REVIEW':
        case 'REVISIONS_REQUIRED':
            $progress_percentage = 80;
            $progress_status = 'Similarity Testing in Progress';
            $progress_color = 'warning';
            break;
        case 'APPROVED':
        case 'CERTIFICATE_ISSUED':
            $progress_percentage = 100;
            $progress_status = 'Approved';
            $progress_color = 'success';
            break;
        case 'REJECTED':
            $progress_percentage = 100;
            $progress_status = 'Application Rejected';
            $progress_color = 'danger';
            break;
        default:
            $progress_percentage = 10;
            $progress_status = 'Processing';
            $progress_color = 'secondary';
    }
?>

                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark"><?php echo $progress_status; ?></span>
                            <span class="badge bg-<?php echo $progress_color; ?>"><?php echo $progress_percentage; ?>% Complete</span>
                        </div>
                        <div class="progress" style="height: 12px; border-radius: 6px;">
                            <div class="progress-bar bg-<?php echo $progress_color; ?> progress-bar-striped progress-bar-animated"
                                 role="progressbar"
                                 style="width: <?php echo $progress_percentage; ?>%; border-radius: 6px;"
                                 aria-valuenow="<?php echo $progress_percentage; ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Progress Metrics -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-primary mb-1"><?php echo $days_since_submission; ?></div>
                                <div class="small text-muted">Days Since Submission</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-info mb-1"><?php echo $doc_count; ?></div>
                                <div class="small text-muted">Documents Uploaded</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-warning mb-1"><?php echo $staff_messages; ?></div>
                                <div class="small text-muted">Staff Messages</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-success mb-1"><?php echo getStatusDisplayName($current_status); ?></div>
                                <div class="small text-muted">Current Status</div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="alert alert-info border-0" style="background: #0a6e2c;">
                        <h6 class="alert-heading mb-2 text-white"><i class="bi bi-lightbulb"></i> What's Next?</h6>
                        <?php
    $next_steps = [];
    switch ($current_status) {
        case 'INTENT_RECEIVED':
        case 'REQUIREMENTS_SENT':
            $next_steps[] = "Wait for staff to review your registration";
            $next_steps[] = "Complete your requirements in the 'Submit Requirements' page if you haven't yet";
            break;
        case 'REQUIREMENTS_PENDING':
        case 'REQUIREMENTS_INCOMPLETE':
            $next_steps[] = "Verify your status in the 'Submit Requirements' page";
            $next_steps[] = "Upload any outstanding requirements requested by staff";
            break;
        case 'UNDER_AUTO_REVIEW':
        case 'STAFF_REVIEW_REQUIRED':
            $next_steps[] = "Your application is being reviewed by our staff";
            $next_steps[] = "Monitor your status here for any updates";
            break;
        case 'REVISIONS_REQUIRED':
            $next_steps[] = "Review staff feedback and make necessary revisions";
            $next_steps[] = "Coordinate with the TeSI office for re-submission";
            break;
        case 'FORWARDED_FOR_TESTING':
        case 'UNDER_SIMILARITY_TESTING':
            $next_steps[] = "Application forwarded to DRD-TeSI Office";
            $next_steps[] = "Await similarity testing results";
            break;
        case 'APPROVED':
            $next_steps[] = "Official certificate will be issued by the TeSI office";
            $next_steps[] = "Proceed with your research submission";
            break;
        default:
            $next_steps[] = "Your application is being processed";
            $next_steps[] = "Check back regularly for updates";
    }

    foreach ($next_steps as $step) {
        echo "<div class='mb-1 text-white'><i class='bi bi-check-circle text-white me-2'></i>{$step}</div>";
    }
?>
                    </div>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="card shadow mb-4 fade-in">
                <div class="card-header" style="background-color: #0a6e2c; color: white;">
                    <h5 class="mb-0" ><i class="bi bi-clock-history"></i> Detailed Status History</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($status_history) && $status_history->num_rows > 0): ?>
                        <div class="timeline">
                            <?php
        $history_array = [];
        while ($h = $status_history->fetch_assoc()) {
            $history_array[] = $h;
        }
        $history_array = array_reverse($history_array);

        foreach ($history_array as $index => $h):
            $is_active = ($h['new_status'] === $current_status);
            $is_completed = !$is_active;
?>
                                <div class="timeline-item <?php echo $is_active ? 'active' : ($is_completed ? 'completed' : ''); ?>">
                                    <div class="timeline-icon"></div>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo getStatusDisplayName($h['new_status']); ?>
                                                <?php if ($is_active): ?>
                                                    <span class="badge bg-primary ms-2">Current</span>
                                                <?php
            endif; ?>
                                            </h6>
                                            <?php if ($h['notes']): ?>
                                                <p class="text-muted mb-0 small">
                                                    <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($h['notes']); ?>
                                                </p>
                                            <?php
            endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatDate($h['timestamp']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php
        endforeach; ?>
                        </div>
                    <?php
    else: ?>
                        <p class="text-muted text-center mb-0">No history available.</p>
                    <?php
    endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card shadow fade-in">
                <div class="card-body text-center p-4">
                    <h5 class="mb-4">Need to take action?</h5>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="submit-requirements.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-file-earmark-text"></i> Submit Requirements
                        </a>
                    </div>
                    
                    <?php if (in_array($current_status, ['REQUIREMENTS_INCOMPLETE', 'REVISIONS_REQUIRED', 'COMPLIANCE_PENDING'])): ?>
                        <div class="alert alert-warning mt-4">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Action Required:</strong> Please coordinate with the TeSI office to submit the required documents or revisions.
                        </div>
                    <?php
    endif; ?>
                </div>
            </div>
        <?php
endif; ?>
    </section>

    <!-- How to Track Section -->
    <?php if (!$tracking_result && !$error_message): ?>
    <section class="container pb-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100 text-center">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="bi bi-1-circle-fill display-3 text-primary"></i>
                        </div>
                        <h5 class="card-title">Submit Application</h5>
                        <p class="card-text text-muted">Submit your letter of intent through our online portal.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100 text-center">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="bi bi-2-circle-fill display-3 text-success"></i>
                        </div>
                        <h5 class="card-title">Receive Queue Number</h5>
                        <p class="card-text text-muted">You'll receive a unique queue number via email (<?php echo QUEUE_PREFIX; ?>0000).</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100 text-center">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="bi bi-3-circle-fill display-3 text-info"></i>
                        </div>
                        <h5 class="card-title">Track Progress</h5>
                        <p class="card-text text-muted">Use your queue number to track your application status anytime.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
endif; ?>

<?php

$extra_js = "
<script>
// Auto-format queue number input (only numbers allowed)
document.addEventListener('DOMContentLoaded', function() {
    const queueInput = document.querySelector('input[name=\"queue_number\"]');
    if (queueInput) {
        queueInput.addEventListener('input', function(e) {
            // Only allow numbers
            let value = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = value;
        });
        
        // Auto-pad with zeros when user leaves the field
        queueInput.addEventListener('blur', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value) {
                e.target.value = value.padStart(4, '0');
            }
        });
    }
    
    // Add some animation to progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        bar.style.transition = 'width 1.5s ease-in-out';
    });
});
</script>
<style>
/* Enhanced progress section styles */
.progress {
    background-color: #e9ecef;
    box-shadow: inset 0 1px 2px rgba(17, 241, 21, 0.1);
}

.progress-bar {
    background: linear-gradient(90deg, var(--bs-primary) 0%, var(--bs-info) 100%);
    position: relative;
    overflow: hidden;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(3, 160, 11, 0.3),
        transparent
    );
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.alert-info {
    border-left: 4px solid #0dcaf0;
}

.bg-light {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
    border: 1px solid #dee2e6;
}

.fade-in {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
";
include 'includes/footer.php';

?>
