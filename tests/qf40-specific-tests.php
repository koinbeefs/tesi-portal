<?php
/**
 * QF-40 Form Specific Functionality Tests
 * 
 * Specialized testing for QF-40 certificate generation and date handling
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

class QF40TestSuite {
    private $tests = [];
    private $results = ['passed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0];
    
    public function addTest($name, $callback) {
        $this->tests[] = ['name' => $name, 'callback' => $callback];
    }
    
    public function runTests() {
        $results = [];
        
        foreach ($this->tests as $test) {
            $this->results['total']++;
            
            try {
                $result = call_user_func($test['callback']);
                
                if ($result['status'] === 'PASS') {
                    $this->results['passed']++;
                } elseif ($result['status'] === 'FAIL') {
                    $this->results['failed']++;
                } else {
                    $this->results['skipped']++;
                }
                
                $results[] = [
                    'name' => $test['name'],
                    'result' => $result
                ];
                
            } catch (Exception $e) {
                $this->results['failed']++;
                $results[] = [
                    'name' => $test['name'],
                    'result' => [
                        'status' => 'FAIL',
                        'message' => 'Exception: ' . $e->getMessage()
                    ]
                ];
            }
        }
        
        return ['tests' => $results, 'summary' => $this->results];
    }
}

$testSuite = new QF40TestSuite();

// ========================================
// QF-40 SPECIFIC TESTS
// ========================================

$testSuite->addTest('QF-40 Template File', function() {
    $templatePath = '../staff/qf40.docx';
    
    if (!file_exists($templatePath)) {
        return ['status' => 'FAIL', 'message' => 'QF-40 template file not found'];
    }
    
    if (!is_readable($templatePath)) {
        return ['status' => 'FAIL', 'message' => 'QF-40 template file not readable'];
    }
    
    $fileSize = filesize($templatePath);
    if ($fileSize < 1000) { // Less than 1KB seems too small
        return ['status' => 'FAIL', 'message' => 'Template file too small: ' . $fileSize . ' bytes'];
    }
    
    return ['status' => 'PASS', 'message' => 'Template file OK: ' . number_format($fileSize) . ' bytes'];
});

$testSuite->addTest('Date Columns in Applications', function() {
    try {
        $conn = getDBConnection();
        
        // Check if date columns exist
        $result = $conn->query("DESCRIBE applications");
        $columns = [];
        
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $requiredColumns = ['research_date_started', 'research_date_finished'];
        $missing = array_diff($requiredColumns, $columns);
        
        if (!empty($missing)) {
            return ['status' => 'FAIL', 'message' => 'Missing columns: ' . implode(', ', $missing)];
        }
        
        // Check if any applications have date data
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE research_date_started IS NOT NULL OR research_date_finished IS NOT NULL");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            return ['status' => 'SKIP', 'message' => 'No applications with date data found'];
        }
        
        return ['status' => 'PASS', 'message' => "Found {$result['count']} applications with date data"];
        
    } catch (Exception $e) {
        return ['status' => 'FAIL', 'message' => 'Database error: ' . $e->getMessage()];
    }
});

$testSuite->addTest('Date Format Validation', function() {
    try {
        $conn = getDBConnection();
        
        // Get sample date data
        $stmt = $conn->prepare("SELECT research_date_started, research_date_finished FROM applications WHERE research_date_started IS NOT NULL LIMIT 5");
        $stmt->execute();
        $results = $stmt->get_result();
        
        $invalidDates = [];
        
        while ($row = $results->fetch_assoc()) {
            foreach (['research_date_started', 'research_date_finished'] as $field) {
                $date = $row[$field];
                if ($date) {
                    // Test if date is valid YYYY-MM-DD format
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $invalidDates[] = "$field: $date (invalid format)";
                    } elseif (!strtotime($date)) {
                        $invalidDates[] = "$field: $date (invalid date)";
                    }
                }
            }
        }
        
        if (!empty($invalidDates)) {
            return ['status' => 'FAIL', 'message' => 'Invalid dates found: ' . implode(', ', $invalidDates)];
        }
        
        return ['status' => 'PASS', 'message' => 'Date format validation passed'];
        
    } catch (Exception $e) {
        return ['status' => 'FAIL', 'message' => 'Date validation error: ' . $e->getMessage()];
    }
});

$testSuite->addTest('QF-39 Data Integration', function() {
    try {
        $conn = getDBConnection();
        
        // Check if QF-39 forms exist
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fillable_forms WHERE form_type = 'qf39'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            return ['status' => 'SKIP', 'message' => 'No QF-39 forms found'];
        }
        
        // Test JSON structure
        $stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE form_type = 'qf39' LIMIT 3");
        $stmt->execute();
        $results = $stmt->get_result();
        
        $invalidJson = [];
        
        while ($row = $results->fetch_assoc()) {
            $data = json_decode($row['form_data'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $invalidJson[] = 'JSON error: ' . json_last_error_msg();
            } else {
                // Check for expected fields (basic fields that should exist)
                $expectedFields = ['proponents', 'research_title'];
                $missingFields = array_diff($expectedFields, array_keys($data));
                
                if (!empty($missingFields)) {
                    $invalidJson[] = 'Missing basic fields: ' . implode(', ', $missingFields);
                }
            }
        }
        
        if (!empty($invalidJson)) {
            return ['status' => 'FAIL', 'message' => 'QF-39 data issues: ' . implode('; ', $invalidJson)];
        }
        
        // Now check if applications have the date columns that QF-40 uses
        $dateCheck = $conn->query("DESCRIBE applications");
        $columns = [];
        while ($row = $dateCheck->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $requiredDateColumns = ['research_date_started', 'research_date_finished'];
        $missingDateColumns = array_diff($requiredDateColumns, $columns);
        
        if (!empty($missingDateColumns)) {
            return ['status' => 'FAIL', 'message' => 'Missing date columns in applications: ' . implode(', ', $missingDateColumns)];
        }
        
        return ['status' => 'PASS', 'message' => "QF-39 integration OK ({$result['count']} forms) + date columns available"];
        
    } catch (Exception $e) {
        return ['status' => 'FAIL', 'message' => 'QF-39 integration error: ' . $e->getMessage()];
    }
});

$testSuite->addTest('Budget Auto-fill Logic', function() {
    try {
        $conn = getDBConnection();
        
        // Check if applicant_type column exists
        $result = $conn->query("DESCRIBE applications");
        $columns = [];
        
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        if (!in_array('applicant_type', $columns)) {
            return ['status' => 'FAIL', 'message' => 'applicant_type column missing'];
        }
        
        // Test budget logic with sample data
        $stmt = $conn->prepare("SELECT applicant_type FROM applications WHERE applicant_type IS NOT NULL LIMIT 3");
        $stmt->execute();
        $results = $stmt->get_result();
        
        $studentTypes = ['student', 'graduate_student', 'undergraduate'];
        $foundStudentTypes = [];
        
        while ($row = $results->fetch_assoc()) {
            if (in_array($row['applicant_type'], $studentTypes)) {
                $foundStudentTypes[] = $row['applicant_type'];
            }
        }
        
        if (empty($foundStudentTypes)) {
            return ['status' => 'SKIP', 'message' => 'No student applications found to test budget logic'];
        }
        
        return ['status' => 'PASS', 'message' => 'Budget auto-fill logic ready for: ' . implode(', ', $foundStudentTypes)];
        
    } catch (Exception $e) {
        return ['status' => 'FAIL', 'message' => 'Budget logic test error: ' . $e->getMessage()];
    }
});

$testSuite->addTest('PhpWord Library', function() {
    // Check if PhpWord is available
    if (!class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
        return ['status' => 'FAIL', 'message' => 'PhpWord library not loaded'];
    }
    
    // Test basic functionality
    try {
        $templatePath = '../staff/qf40.docx';
        if (file_exists($templatePath)) {
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            
            // Test setting a value
            $templateProcessor->setValues(['TEST' => 'Test Value']);
            
            return ['status' => 'PASS', 'message' => 'PhpWord library working correctly'];
        } else {
            return ['status' => 'SKIP', 'message' => 'Template file missing, cannot test PhpWord'];
        }
        
    } catch (Exception $e) {
        return ['status' => 'FAIL', 'message' => 'PhpWord test failed: ' . $e->getMessage()];
    }
});

$testSuite->addTest('Date Formatting Function', function() {
    // Test date formatting like in QF-40 form
    $testDates = [
        '2024-01-15' => 'January 2024',
        '2024-12-01' => 'December 2024',
        '2023-06-30' => 'June 2023'
    ];
    
    $formatErrors = [];
    
    foreach ($testDates as $input => $expected) {
        $formatted = date('F Y', strtotime($input));
        
        if ($formatted !== $expected) {
            $formatErrors[] = "$input → $formatted (expected $expected)";
        }
    }
    
    if (!empty($formatErrors)) {
        return ['status' => 'FAIL', 'message' => 'Date formatting errors: ' . implode(', ', $formatErrors)];
    }
    
    return ['status' => 'PASS', 'message' => 'Date formatting working correctly'];
});

$testSuite->addTest('QF-40 Form File Structure', function() {
    $formFile = '../staff/fill-qf40-form.php';
    
    if (!file_exists($formFile)) {
        return ['status' => 'FAIL', 'message' => 'QF-40 form file not found'];
    }
    
    $content = file_get_contents($formFile);
    
    // Check for critical components
    $requiredComponents = [
        'getDBConnection' => 'Database connection',
        'TemplateProcessor' => 'PhpWord template processor',
        'research_date_started' => 'Date started field',
        'research_date_finished' => 'Date finished field',
        'applicant_type' => 'Applicant type field',
        'date(\'F Y\'' => 'Month YYYY date format'
    ];
    
    $missingComponents = [];
    
    foreach ($requiredComponents as $component => $description) {
        if (strpos($content, $component) === false) {
            $missingComponents[] = "$description ($component)";
        }
    }
    
    if (!empty($missingComponents)) {
        return ['status' => 'FAIL', 'message' => 'Missing components: ' . implode(', ', $missingComponents)];
    }
    
    return ['status' => 'PASS', 'message' => 'QF-40 form structure complete'];
});

// Run tests if requested
if (isset($_GET['run_qf40_tests'])) {
    $results = $testSuite->runTests();
    
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QF-40 Form Specific Tests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-pass { color: #198754; }
        .test-fail { color: #dc3545; }
        .test-skip { color: #6c757d; }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-file-earmark-text"></i>
                    QF-40 Form Specific Tests
                </h2>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="runQF40Tests()">
                            <i class="bi bi-play-circle"></i> Run QF-40 Tests
                        </button>
                        <a href="test-runner.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Back to All Tests
                        </a>
                    </div>
                </div>
                
                <div id="loadingIndicator" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Running QF-40 specific tests...</p>
                </div>
                
                <div id="testResults"></div>
            </div>
        </div>
    </div>
    
    <script>
        function runQF40Tests() {
            const loading = document.getElementById('loadingIndicator');
            const results = document.getElementById('testResults');
            
            loading.style.display = 'block';
            results.innerHTML = '';
            
            fetch('?run_qf40_tests=1&ajax=1')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    displayResults(data);
                })
                .catch(error => {
                    loading.style.display = 'none';
                    results.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Error: ${error.message}
                        </div>
                    `;
                });
        }
        
        function displayResults(data) {
            const results = document.getElementById('testResults');
            
            // Summary
            let html = `
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Test Summary</h5>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="h4">${data.summary.total}</div>
                                <small>Total</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 test-pass">${data.summary.passed}</div>
                                <small>Passed</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 test-fail">${data.summary.failed}</div>
                                <small>Failed</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 test-skip">${data.summary.skipped}</div>
                                <small>Skipped</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Individual tests
            data.tests.forEach(test => {
                const statusClass = test.result.status.toLowerCase();
                const icon = test.result.status === 'PASS' ? '✓' : 
                             test.result.status === 'FAIL' ? '✗' : '⊘';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">${test.name}</h6>
                                <span class="badge status-badge bg-${getStatusColor(test.result.status)}">
                                    ${icon} ${test.result.status}
                                </span>
                            </div>
                            <p class="text-muted small mt-2 mb-0">${test.result.message}</p>
                        </div>
                    </div>
                `;
            });
            
            results.innerHTML = html;
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'PASS': return 'success';
                case 'FAIL': return 'danger';
                case 'SKIP': return 'secondary';
                default: return 'warning';
            }
        }
        
        // Auto-run tests on page load
        document.addEventListener('DOMContentLoaded', runQF40Tests);
    </script>
</body>
</html>
