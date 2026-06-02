<?php
// edit-qf40-remarks.php - Staff interface to add remarks to QF-40 forms

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
use PhpOffice\PhpWord\TemplateProcessor;

requireLogin();

if (!isset($_GET['queue'])) {
    header('Location: dashboard.php');
    exit;
}

$queue_number = $_GET['queue'];
$conn = getDBConnection();

// Handle file download if requested
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    if (file_exists($filename)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filename));
        readfile($filename);
        unlink($filename);
        exit;
    }
    else {
        die('File not found');
    }
}

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param('s', $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header('Location: dashboard.php?error=not_found');
    exit;
}

// Auto-claim unassigned applications
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ? WHERE queue_number = ? AND assigned_staff_id IS NULL");
    $claim_stmt->bind_param("is", $_SESSION['user_id'], $queue_number);
    $claim_stmt->execute();

    if ($claim_stmt->affected_rows > 0) {
        $just_claimed = true;
        $application['assigned_staff_id'] = $_SESSION['user_id']; // Update the local copy

        // Log the auto-claim activity
        logStaffActivity($_SESSION['user_id'], $queue_number, 'other', 'Auto-claimed request for QF-40 remarks editing');
    }
}

// Check if current user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);

// Get QF-40 form data if exists
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf40'");
$form_stmt->bind_param('s', $queue_number);
$form_stmt->execute();
$form_result = $form_stmt->get_result()->fetch_assoc();

$form_data = [];
if ($form_result) {
    $form_data = json_decode($form_result['form_data'], true) ?? [];
}

if (empty($form_data)) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=qf40_not_completed');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check permissions
    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    require_once '../vendor/autoload.php';

    // Update remarks in form data
    $criteria = range(1, 20);
    foreach ($criteria as $num) {
        if (isset($_POST["crit_{$num}_remarks"])) {
            $form_data["crit_{$num}_remarks"] = trim($_POST["crit_{$num}_remarks"]);
        }
    }

    // Save updated form data
    $updated_json = json_encode($form_data);
    $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'qf40'");
    $update_stmt->bind_param('ss', $updated_json, $queue_number);

    if ($update_stmt->execute()) {
        // Generate updated DOCX file with remarks
        try {
            $templateProcessor = new TemplateProcessor('../applicant/qf40.docx');

            // Prepare all fields
            $yesNoFields = [];
            foreach ($criteria as $num) {
                $yesNoFields["{$num}Y"] = isset($form_data["crit_{$num}_yes"]) && $form_data["crit_{$num}_yes"] === 'on' ? '✓' : '';
                $yesNoFields["{$num}N"] = isset($form_data["crit_{$num}_no"]) && $form_data["crit_{$num}_no"] === 'on' ? '✓' : '';
                $yesNoFields["{$num}R"] = $form_data["crit_{$num}_remarks"] ?? '';
            }

            $templateProcessor->setValues($yesNoFields);
            $templateProcessor->setValue('TITLE', $form_data['title'] ?? '');
            $templateProcessor->setValue('PARTICIPANTS', $form_data['participants'] ?? '');
            $templateProcessor->setValue('PROPONENT', $form_data['proponent_name'] ?? '');
            $templateProcessor->setValue('FILLED', formatDate($form_data['date_filled'] ?? ''));
            $templateProcessor->setValue('ADVISER', $form_data['adviser_name'] ?? '');
            $templateProcessor->setValue('SIGNED', formatDate($form_data['date_signed'] ?? ''));

            $outputFile = 'TAU-TeSI-QF-40_WithRemarks_' . $queue_number . '.docx';
            $templateProcessor->saveAs($outputFile);

            // Log activity
            logStaffActivity($_SESSION['user_id'], $queue_number, 'other', 'Updated QF-40 remarks');

            // Return success response for AJAX
            echo json_encode([
                'success' => true,
                'message' => 'QF-40 remarks saved successfully! The updated document is ready for download.',
                'download_url' => $outputFile
            ]);
            exit;

        }
        catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error generating document: ' . $e->getMessage()]);
            exit;
        }
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Error saving remarks.']);
        exit;
    }
}

closeDBConnection($conn);

$page_title = 'Edit QF-40 Remarks';
$base_url = '../';
$active_menu = 'dashboard';
$is_modal = isset($_GET['modal']) && $_GET['modal'] === '1';

if (!$is_modal) {
    include '../includes/auth_header.php';
}
?>

<?php if ($is_modal): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - TAU-TeSI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php
endif; ?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
}

.main-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
    padding: 1rem 0;
}

.page-header {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.queue-badge {
    background: linear-gradient(135deg, #006400, #228B22);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.criteria-card {
    border-left: 4px solid #007bff !important;
    margin-bottom: 1rem;
}

.criteria-card.criteria-yes {
    border-left-color: #198754 !important;
}

.criteria-card.criteria-no {
    border-left-color: #6c757d !important;
}

.criteria-card.criteria-na {
    border-left-color: #ffc107 !important;
}

.criteria-number {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.response-badge {
    border-radius: 20px;
    font-weight: 600;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.form-control:focus {
    border-color: #006400;
    box-shadow: 0 0 0 0.2rem rgba(0, 100, 0, 0.25);
}

.btn-modern {
    border-radius: 25px;
    font-weight: 600;
    padding: 0.6rem 1.5rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.alert-modern {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.progress-section {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.progress-bar-custom {
    background: linear-gradient(135deg, #006400, #228B22);
    border-radius: 8px;
}

@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }

    .queue-badge {
        font-size: 1rem;
        padding: 0.5rem 1rem;
    }

    .criteria-card .row > div {
        margin-bottom: 0.5rem;
    }
}
</style>

<div class="main-container">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="queue-badge mb-2">
                        <i class="bi bi-ticket-detailed me-1"></i><?php echo htmlspecialchars($queue_number); ?>
                    </div>
                    <h4 class="mb-1 text-dark fw-bold">
                        <i class="bi bi-pencil-square text-primary me-2"></i>QF-40 Remarks Editor
                    </h4>
                    <p class="text-muted mb-0 small">Add staff remarks to QF-40 form criteria for clarification or revision requests</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <h6 class="text-primary mb-1 fw-bold"><?php echo htmlspecialchars($application['applicant_name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($form_data['title'] ?? 'Research Title Not Available'); ?></small>
                    </div>
                </div>
            </div>
        </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-modern">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php
endif; ?>

    <?php if ($just_claimed): ?>
        <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
            <i class="bi bi-hand-index-thumb me-2"></i>Request has been automatically assigned to you for QF-40 remarks editing.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>

    <?php if (!$can_edit): ?>
        <div class="alert alert-warning alert-modern">
            <i class="bi bi-lock me-2"></i>This application is assigned to another staff member and cannot be edited.
        </div>
    <?php
endif; ?>

    <!-- Progress Section -->
    <div class="progress-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="mb-2 fw-bold text-dark">
                    <i class="bi bi-bar-chart-line me-2"></i>QF-40 Review Progress
                </h6>
                <p class="text-muted mb-0 small">Track your progress through the 20 criteria for comprehensive review</p>
            </div>
            <div class="col-md-4">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar progress-bar-custom" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted mt-1 d-block">0 of 20 criteria reviewed</small>
            </div>
        </div>
    </div>

    <!-- Main Form Card -->
    <div class="card">
        <div class="card-header bg-white border-bottom-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1 fw-bold text-dark">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Add Remarks to QF-40 Form
                    </h5>
                    <p class="text-muted mb-0 small">Review each criterion and add staff remarks for clarification or revision requests</p>
                </div>
                <div class="text-end">
                    <div class="d-flex flex-column align-items-end">
                        <small class="text-muted">Applicant</small>
                        <strong class="text-dark"><?php echo htmlspecialchars($application['applicant_name']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form id="remarksForm" <?php echo !$can_edit ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                <div class="row">
                    <?php
$criteriaList = [
    1 => "The research involves interaction with human participants, such as surveys, interviews, or clinical tests.",
    2 => "The research involves the collection of identifiable or sensitive data (e.g. health data, biometric information)",
    3 => "The research involves a vulnerable population (e.g. minors, pregnant women, elderly, persons with disabilities)",
    4 => "The research involves physical, psychological, social, legal, or economic risk.",
    5 => "The study requires informed consent from the participants.",
    6 => "The research involves live animals for experimentation.",
    7 => "The research involves procedures that could cause pain, distress, or discomfort to the animal.",
    8 => "The research involves working with endangered, protected, or non-domestic species",
    9 => "The research protocol is aligned with Bureau of Animal Industry (BAI) requirements for animal care and use.",
    10 => "The research involves genetically modified organisms (GMOs) or new varieties.",
    11 => "The research involves field trials, environmental release, or agricultural practices that may affect biodiversity.",
    12 => "The research involves the importation, exportation, or propagation of plant materials.",
    13 => "The research involves handling of pathogenic microorganisms or bio-hazardous materials.",
    14 => "The research involves the use of microorganisms that have potential health, safety, or environmental risks.",
    15 => "The research involves the collection of personal data (e.g. data from social media, health data, or private information).",
    16 => "The research involves software development, algorithms, or IT or computer systems to be tested with human participants.",
    17 => "The research involves cyber security, privacy concerns, or data protection issues.",
    18 => "The research involves the development or testing of machinery, equipment, or prototypes that could have risks to users.",
    19 => "The research have a negative impact to the environment (e.g. waste management, emissions, or energy consumption).",
    20 => "The research involves potentially hazardous food production techniques (e.g. chemical additive, genetic modification)."
];

$reviewedCount = 0;
foreach ($criteriaList as $num => $text):
    $yes_checked = (isset($form_data["crit_{$num}_yes"]) && $form_data["crit_{$num}_yes"] === 'on');
    $no_checked = (isset($form_data["crit_{$num}_no"]) && $form_data["crit_{$num}_no"] === 'on');
    $current_remark = $form_data["crit_{$num}_remarks"] ?? '';

    // Determine card class based on response
    $cardClass = 'criteria-card';
    if ($yes_checked) {
        $cardClass .= ' criteria-yes';
    }
    elseif ($no_checked) {
        $cardClass .= ' criteria-no';
    }
    else {
        $cardClass .= ' criteria-na';
    }

    // Count reviewed criteria
    if ($yes_checked || $no_checked || !empty($current_remark)) {
        $reviewedCount++;
    }
?>
                        <div class="col-12">
                            <div class="card <?php echo $cardClass; ?>">
                                <div class="card-body">
                                    <div class="row align-items-start">
                                        <div class="col-auto">
                                            <div class="criteria-number">
                                                <?php echo $num; ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-8">
                                                    <p class="mb-2 fw-medium"><?php echo htmlspecialchars($text); ?></p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <div class="d-flex flex-column align-items-end">
                                                        <small class="text-muted mb-1">Applicant's Response</small>
                                                        <?php if ($yes_checked): ?>
                                                            <span class="response-badge bg-success text-white">
                                                                <i class="bi bi-check-circle me-1"></i>Yes
                                                            </span>
                                                        <?php
    elseif ($no_checked): ?>
                                                            <span class="response-badge bg-secondary text-white">
                                                                <i class="bi bi-x-circle me-1"></i>No
                                                            </span>
                                                        <?php
    else: ?>
                                                            <span class="response-badge bg-light text-dark">
                                                                <i class="bi bi-dash-circle me-1"></i>Not Answered
                                                            </span>
                                                        <?php
    endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <label class="form-label small fw-semibold mb-2">
                                                        <i class="bi bi-chat-dots me-1"></i>Staff Remarks
                                                    </label>
                                                    <input type="text"
                                                           class="form-control"
                                                           name="crit_<?php echo $num; ?>_remarks"
                                                           value="<?php echo htmlspecialchars($current_remark); ?>"
                                                           placeholder="Add remarks for clarification or revision requests..."
                                                           <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
endforeach; ?>
                </div>

                <!-- Submit Section -->
                <div class="card mt-3 border-primary">
                    <div class="card-body text-center py-3">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="mb-2 fw-bold text-dark">
                                    <i class="bi bi-save text-primary me-2"></i>Save Remarks & Generate Updated QF-40
                                </h6>
                                <p class="text-muted mb-0 small">This will save all remarks and generate an updated QF-40 document with your annotations</p>
                            </div>
                            <div class="col-md-4">
                                <button type="button" id="saveRemarksBtn" class="btn btn-secondary btn-modern btn-lg px-4" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <i class="bi bi-save me-2"></i>Save & Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update progress bar
document.addEventListener('DOMContentLoaded', function() {
    const totalCriteria = 20;
    const reviewedCriteria = <?php echo $reviewedCount; ?>;
    const progressPercentage = (reviewedCriteria / totalCriteria) * 100;

    const progressBar = document.querySelector('.progress-bar');
    const progressText = document.querySelector('.progress-section small');

    if (progressBar && progressText) {
        progressBar.style.width = progressPercentage + '%';
        progressText.textContent = reviewedCriteria + ' of ' + totalCriteria + ' criteria reviewed';
    }

    // Handle form submission
    document.getElementById('saveRemarksBtn').addEventListener('click', function() {
        const btn = this;
        const originalHtml = btn.innerHTML;

        // Disable button and show loading
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        // Prepare form data
        const formData = new FormData(document.getElementById('remarksForm'));

        // Submit via AJAX
        fetch('edit-qf40-remarks.php?queue=<?php echo urlencode($queue_number); ?>&modal=1', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Notify parent window and close modal
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'qf40RemarksCompleted',
                        success: true,
                        message: data.message,
                        download_url: data.download_url
                    }, '*');
                }
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(error => {
            alert('Error saving remarks: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });
});
</script>

<?php if (!$is_modal): ?>
<?php include '../includes/auth_footer.php'; ?>
<?php
else: ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
endif; ?>
