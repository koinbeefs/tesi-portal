/**
 * Duplicate Prevention JavaScript
 * Prevents multiple submissions of the same form type
 */

// Check if form already exists before submission
function checkFormExists(queueNumber, formType, callback) {
    fetch('check-form-exists.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `queue_number=${queueNumber}&form_type=${formType}`
    })
    .then(response => response.json())
    .then(data => {
        callback(data.exists, data.message);
    })
    .catch(error => {
        console.error('Error checking form existence:', error);
        callback(false, 'Error checking form status');
    });
}

// Prevent multiple form submissions
function preventDuplicateSubmission(formId, queueNumber, formType) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    let isSubmitting = false;
    
    form.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            alert('Form is already being submitted. Please wait...');
            return false;
        }
        
        isSubmitting = true;
        
        // Check if form already exists
        checkFormExists(queueNumber, formType, function(exists, message) {
            if (exists && formType !== 'tesi_score') {
                // Allow tesi_score updates, but prevent other duplicates
                isSubmitting = false;
                e.preventDefault();
                alert(message);
                return false;
            }
            
            // Disable submit button
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }
            
            // Re-enable after timeout (in case of errors)
            setTimeout(() => {
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.getAttribute('data-original-text') || 'Submit';
                }
            }, 10000);
        });
    });
    
    // Store original button text
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('data-original-text', submitBtn.textContent);
    }
}

// Initialize duplicate prevention for QF-39 form
function initQF39DuplicatePrevention(queueNumber) {
    preventDuplicateSubmission('qf39Form', queueNumber, 'qf39');
}

// Initialize duplicate prevention for similarity score
function initSimilarityScorePrevention(queueNumber) {
    // Allow similarity score updates (this is just to prevent double-clicks)
    const form = document.querySelector('form[action*="submit-requirements.php"]');
    if (form) {
        let isSubmitting = false;
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                alert('Score is already being submitted. Please wait...');
                return false;
            }
            isSubmitting = true;
            
            setTimeout(() => {
                isSubmitting = false;
            }, 5000);
        });
    }
}

// Check application status on page load
function checkApplicationStatus(queueNumber) {
    fetch('check-application-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `queue_number=${queueNumber}`
    })
    .then(response => response.json())
    .then(data => {
        // Disable forms if application is already approved
        if (data.current_status === 'APPROVED') {
            disableAllForms();
            showStatusMessage('This application has been approved. No further submissions allowed.');
        }
        
        // Show existing forms
        if (data.existing_forms) {
            data.existing_forms.forEach(form => {
                showExistingForm(form.form_type, form.completed_at);
            });
        }
    })
    .catch(error => {
        console.error('Error checking application status:', error);
    });
}

// Disable all form inputs
function disableAllForms() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => {
            input.disabled = true;
        });
    });
}

// Show status message
function showStatusMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-warning alert-dismissible fade show';
    alertDiv.innerHTML = `
        <strong>Notice:</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container, main, body');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
}

// Show existing form info
function showExistingForm(formType, completedAt) {
    const formNames = {
        'qf39': 'QF-39 Application Form',
        'qf40': 'QF-40 Similarity Certificate',
        'tesi_score': 'Similarity Score'
    };
    
    const message = `${formNames[formType] || formType} was completed on ${completedAt}`;
    
    const infoDiv = document.createElement('div');
    infoDiv.className = 'alert alert-info alert-dismissible fade show';
    infoDiv.innerHTML = `
        <strong>Information:</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container, main, body');
    if (container) {
        container.insertBefore(infoDiv, container.firstChild);
    }
}
