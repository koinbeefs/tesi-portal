<?php
/**
 * Letter of Intent Submission
 * TAU-TeSI Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/email-template-functions.php';

$success_message = '';
$error_message = '';
$queue_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestor_name = sanitizeInput($_POST['requestor_name']);
    $researchers_name = sanitizeInput($_POST['researchers_name']);
    $contact_number = sanitizeInput($_POST['contact_number']);
    $email_address = sanitizeInput($_POST['email_address']);
    $applicant_type = sanitizeInput($_POST['applicant_type']);
    $research_title = sanitizeInput($_POST['research_title']);

    // Handle research type checkboxes
    $research_types = isset($_POST['research_type']) ? $_POST['research_type'] : [];
    $research_type_str = implode(', ', $research_types);
    $other_type = '';

    if (in_array('other', $research_types)) {
        $other_type = sanitizeInput($_POST['other_type']);
        $research_type_str = str_replace('other', $other_type, $research_type_str);
    }

    // Determine fee based on applicant type
    $application_fee = 0;

    switch ($applicant_type) {
        case 'student':
            $application_fee = 0;
            break;
        case 'graduate_student':
            $application_fee = 500;
            break;
        case 'faculty_university':
            $application_fee = 0;
            break;
        case 'faculty_external':
            $application_fee = 1000;
            break;
    }

    if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address.";
    }
    elseif (empty($research_types)) {
        $error_message = "Please select at least one research type.";
    }
    else {
        $conn = getDBConnection();

        // Check for existing application by email to prevent duplicates
        $check_email = $conn->prepare("SELECT queue_number, applicant_name, current_status, submission_timestamp FROM applications WHERE applicant_email = ? ORDER BY submission_timestamp DESC LIMIT 1");
        $check_email->bind_param("s", $email_address);
        $check_email->execute();
        $existing_app = $check_email->get_result()->fetch_assoc();

        if ($existing_app) {
            $error_message = 'You have already submitted an application. Your existing queue number is ' . $existing_app['queue_number'] . '. Please contact the DRD office if you need to make changes.';
        } else {
            // Generate queue number
            $queue_number = generateQueueNumber($conn);

        // Insert application
        $stmt = $conn->prepare("INSERT INTO applications (queue_number, applicant_name, applicant_email, applicant_type, research_title, researchers_name, contact_number, research_type, other_type, application_fee, current_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $status = STATUS_INTENT_RECEIVED;
        $stmt->bind_param("sssssssssis", $queue_number, $requestor_name, $email_address, $applicant_type, $research_title, $researchers_name, $contact_number, $research_type_str, $other_type, $application_fee, $status);

        if ($stmt->execute()) {
            // Update status to requirements sent
            updateApplicationStatus($queue_number, STATUS_REQUIREMENTS_SENT, null, 'system', 'Automated requirements list sent');

            // Prepare placeholders for email template
            $placeholders = [
                'applicant_name' => $requestor_name,
                'queue_number' => $queue_number,
                'applicant_email' => $email_address,
                'research_title' => $research_title,
                'current_status' => STATUS_REQUIREMENTS_SENT,
                'submission_date' => date('F d, Y'),
                'category' => 'Pending',
                'assigned_staff' => 'Not assigned',
                'approval_date' => date('F d, Y'),
                'current_date' => date('F d, Y')
            ];

            // Get template and process it
            $template = getEmailTemplate('REPLY_INTENT');
            if ($template) {
                $email_subject = processEmailTemplate($template['subject'], $placeholders);
                $email_body = processEmailTemplate($template['body'], $placeholders);
            }
            else {
                // Fallback if template not found
                $email_subject = "TAU-TeSI: Application Requirements - Queue Number: $queue_number";
                $email_body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <p>Greetings from TAU-TeSI!</p>
                    <p>Dear $applicant_name,</p>
                    <p>Your queue number is: <strong>$queue_number</strong></p>
                    <p>Please check your Document Management page for the required documents.</p>
                    <p>--Best regards,<br>TAU-TeSI</p>
                </body>
                </html>
                ";
            }

            // Save message to system instead of sending email
            $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'acknowledgment', ?, ?)");
            $msg_stmt->bind_param("sss", $queue_number, $email_subject, $email_body);
            $msg_stmt->execute();

            // Store system documents (the 2 TeSI forms)
            $system_docs = [
                ['name' => 'Similarity Index Guidelines', 'path' => 'assets/to_send/for_reply_to_letter_of_intent/General Guidelines.pdf', 'type' => 'guideline'],
                ['name' => 'TAU-DRD-QF-39 Application Form', 'path' => 'assets/to_send/for_reply_to_letter_of_intent/Rev. 02 TAU-DRD-QF-39-Testing-for-Similarity-Index-Form.docx', 'type' => 'template']
            ];

            $doc_stmt = $conn->prepare("INSERT INTO system_documents (queue_number, document_name, document_path, document_type) VALUES (?, ?, ?, ?)");
            foreach ($system_docs as $doc) {
                $doc_stmt->bind_param("ssss", $queue_number, $doc['name'], $doc['path'], $doc['type']);
                $doc_stmt->execute();
            }

            // Update status to requirements pending
            updateApplicationStatus($queue_number, STATUS_REQUIREMENTS_PENDING);

            $success_message = "Your application has been submitted successfully!";
        }
        else {
            $error_message = "Error submitting application. Please try again.";
        }

        closeDBConnection($conn);
        } // Close the else block for existing application check
    } // Close the main if block for POST request
}
?>
<?php
$page_title = 'Submit Letter of Intent';
$active_page = 'submit';
$base_url = './';
include 'includes/header.php';
?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($success_message): ?>
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h2 class="text-success mb-3"><?php echo $success_message; ?></h2>
                            <div class="alert alert-info">
                                <h4>Your Queue Number: <strong><?php echo $queue_number; ?></strong></h4>
                                <p class="mb-2">Your application has been received and is now being processed.</p>
                                <p class="mb-0"><small><i class="bi bi-info-circle"></i> Use your queue number to track your status on the tracking page.</small></p>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                <a href="track-application.php" class="btn btn-success btn-lg">
                                    <i class="bi bi-search"></i> Track Application
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-house"></i> Return Home
                                </a>
                            </div>
                        </div>
                    </div>
                <?php
else: ?>
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h2 class="card-title mb-4">
                                <i class="bi bi-file-earmark-text"></i> Submit Letter of Intent
                            </h2>
                            
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                                </div>
                            <?php
    endif; ?>
                            
                            <p class="text-muted mb-4">
                                Please fill out the form below to start your research ethics application. 
                                You will receive a queue number via email upon submission.
                            </p>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="requestor_name" class="form-label">Requestor's Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="requestor_name" name="requestor_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="researchers_name" class="form-label">Name of Researcher(s) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="researchers_name" name="researchers_name" required>
                                    <div class="form-text">List all researchers involved in this study.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                                    <div class="form-text">Mobile or telephone number for communication.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email_address" name="email_address" required>
                                    <div class="form-text">Your queue number and updates will be sent to this email.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Type of Research <span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="research_social" name="research_type[]" value="social">
                                        <label class="form-check-label" for="research_social">Social</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="research_technical" name="research_type[]" value="technical">
                                        <label class="form-check-label" for="research_technical">Technical</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="research_other" name="research_type[]" value="other">
                                        <label class="form-check-label" for="research_other">Others</label>
                                    </div>
                                    <div class="mb-2" id="other_type_div" style="display: none;">
                                        <input type="text" class="form-control" id="other_type" name="other_type" placeholder="Please specify other research type">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="research_title" class="form-label">Research Title <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="research_title" name="research_title" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="applicant_type" class="form-label">Applicant Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="applicant_type" name="applicant_type" required>
                                        <option value="">Select...</option>
                                        <option value="student">Student</option>
                                        <option value="graduate_student">Graduate Student</option>
                                        <option value="faculty_university">Faculty Researcher (University Funded)</option>
                                        <option value="faculty_external">Faculty Researcher (Externally Funded)</option>
                                    </select>
                                </div>
                                
                                <!-- Fee Information Section -->
                                <div id="feeSection" class="mb-3" style="display: none;">
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading"><i class="bi bi-cash"></i> Application Fee Information</h6>
                                        <p id="feeMessage" class="mb-0"></p>
                                        <p id="receiptNote" class="mb-0 mt-2" style="display: none;"><small><i class="bi bi-info-circle"></i> <strong>Note:</strong> Official receipt will be required during document submission.</small></p>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> What happens next?</h6>
                                    <ul class="mb-0">
                                        <li>You'll receive a <strong>Queue Number</strong> via email</li>
                                        <li>A list of <strong>required documents</strong> will be provided</li>
                                        <li>Login using your queue number to upload documents</li>
                                        <li>Your application will be automatically reviewed</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-secondary btn-lg">
                                        <i class="bi bi-send"></i> Submit Letter of Intent
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const applicantTypeSelect = document.getElementById('applicant_type');
        const feeSection = document.getElementById('feeSection');
        const feeMessage = document.getElementById('feeMessage');
        const receiptNote = document.getElementById('receiptNote');
        
        // Research type checkboxes
        const researchSocial = document.getElementById('research_social');
        const researchTechnical = document.getElementById('research_technical');
        const researchOther = document.getElementById('research_other');
        const otherTypeDiv = document.getElementById('other_type_div');
        const otherTypeInput = document.getElementById('other_type');
        
        // Applicant type change handler
        applicantTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Hide all sections first
            feeSection.style.display = 'none';
            receiptNote.style.display = 'none';
            
            if (selectedType) {
                feeSection.style.display = 'block';
                
                switch(selectedType) {
                    case 'student':
                        feeMessage.innerHTML = '<strong>Free Application:</strong> There is no fee for student applicants.';
                        break;
                    case 'graduate_student':
                        feeMessage.innerHTML = '<strong>Application Fee: ₱500</strong> for Graduate Student applicants.';
                        receiptNote.style.display = 'block';
                        break;
                    case 'faculty_university':
                        feeMessage.innerHTML = '<strong>Free Application:</strong> University Funded Faculty Researcher applicants are exempt from fees.';
                        break;
                    case 'faculty_external':
                        feeMessage.innerHTML = '<strong>Application Fee: ₱1,000</strong> for Externally Funded Faculty Researcher applicants.';
                        receiptNote.style.display = 'block';
                        break;
                }
            }
        });
        
        // Research type checkbox handlers
        researchSocial.addEventListener('change', function() {
            if (this.checked && researchOther.checked) {
                researchOther.checked = false;
                otherTypeDiv.style.display = 'none';
                otherTypeInput.removeAttribute('required');
                otherTypeInput.value = '';
            }
        });
        
        researchTechnical.addEventListener('change', function() {
            if (this.checked && researchOther.checked) {
                researchOther.checked = false;
                otherTypeDiv.style.display = 'none';
                otherTypeInput.removeAttribute('required');
                otherTypeInput.value = '';
            }
        });
        
        researchOther.addEventListener('change', function() {
            if (this.checked) {
                // Uncheck Social and Technical when Others is selected
                researchSocial.checked = false;
                researchTechnical.checked = false;
                otherTypeDiv.style.display = 'block';
                otherTypeInput.setAttribute('required', 'required');
            } else {
                otherTypeDiv.style.display = 'none';
                otherTypeInput.removeAttribute('required');
                otherTypeInput.value = '';
            }
        });
    });
    </script>

<?php include 'includes/footer.php'; ?>
