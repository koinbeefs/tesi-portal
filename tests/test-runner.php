<?php
/**
 * TESI Portal System Validation and Functionality Testing Suite
 * 
 * This file provides comprehensive testing for:
 * - Database connections and schema validation
 * - Form functionality testing
 * - User authentication and authorization
 * - File generation and download
 * - API endpoints validation
 * - Security checks
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

class TestSuite {
    private $tests = [];
    private $results = [
        'passed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total' => 0
    ];
    
    public function addTest($name, $description, $callback, $category = 'General') {
        $this->tests[] = [
            'name' => $name,
            'description' => $description,
            'callback' => $callback,
            'category' => $category
        ];
    }
    
    public function runTest($test) {
        $this->results['total']++;
        
        try {
            $result = call_user_func($test['callback']);
            
            if ($result === true) {
                $this->results['passed']++;
                return [
                    'status' => 'PASS',
                    'message' => 'Test passed successfully',
                    'details' => ''
                ];
            } elseif (is_array($result) && isset($result['status'])) {
                if ($result['status'] === 'PASS') {
                    $this->results['passed']++;
                } elseif ($result['status'] === 'FAIL') {
                    $this->results['failed']++;
                } elseif ($result['status'] === 'SKIP') {
                    $this->results['skipped']++;
                }
                return $result;
            } else {
                $this->results['failed']++;
                return [
                    'status' => 'FAIL',
                    'message' => 'Invalid test result format',
                    'details' => 'Test must return true, false, or array with status'
                ];
            }
        } catch (Exception $e) {
            $this->results['failed']++;
            return [
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage(),
                'details' => 'File: ' . $e->getFile() . ' Line: ' . $e->getLine()
            ];
        }
    }
    
    public function runAllTests() {
        $results = [];
        
        foreach ($this->tests as $test) {
            $results[] = [
                'name' => $test['name'],
                'description' => $test['description'],
                'category' => $test['category'],
                'result' => $this->runTest($test)
            ];
        }
        
        return $results;
    }
    
    public function getResults() {
        return $this->results;
    }
}

// Initialize test suite
$testSuite = new TestSuite();

// ========================================
// DATABASE VALIDATION TESTS
// ========================================

$testSuite->addTest(
    'Database Connection',
    'Test if database connection is working',
    function() {
        try {
            $conn = getDBConnection();
            if ($conn && $conn->ping()) {
                return ['status' => 'PASS', 'message' => 'Database connection successful'];
            } else {
                return ['status' => 'FAIL', 'message' => 'Database connection failed'];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Database exception: ' . $e->getMessage()];
        }
    },
    'Database'
);

$testSuite->addTest(
    'Required Tables Exist',
    'Check if all required database tables exist',
    function() {
        $required_tables = [
            'applications', 'users', 'fillable_forms'
        ];
        
        try {
            $conn = getDBConnection();
            $missing_tables = [];
            
            foreach ($required_tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows == 0) {
                    $missing_tables[] = $table;
                }
            }
            
            if (empty($missing_tables)) {
                return ['status' => 'PASS', 'message' => 'All required tables exist'];
            } else {
                return [
                    'status' => 'FAIL',
                    'message' => 'Missing tables: ' . implode(', ', $missing_tables)
                ];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Error checking tables: ' . $e->getMessage()];
        }
    },
    'Database'
);

$testSuite->addTest(
    'Applications Table Schema',
    'Validate applications table has required columns',
    function() {
        $required_columns = [
            'queue_number', 'applicant_name', 'research_title', 'research_type',
            'college', 'program_course', 'similarity_index', 'current_status',
            'assigned_staff_id', 'research_date_started',
            'research_date_finished', 'applicant_type'
        ];
        
        try {
            $conn = getDBConnection();
            $result = $conn->query("DESCRIBE applications");
            $existing_columns = [];
            
            while ($row = $result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
            
            $missing_columns = array_diff($required_columns, $existing_columns);
            
            if (empty($missing_columns)) {
                return ['status' => 'PASS', 'message' => 'All required columns exist'];
            } else {
                return [
                    'status' => 'FAIL',
                    'message' => 'Missing columns: ' . implode(', ', $missing_columns)
                ];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Error checking schema: ' . $e->getMessage()];
        }
    },
    'Database'
);

// ========================================
// AUTHENTICATION TESTS
// ========================================

$testSuite->addTest(
    'Session Security',
    'Check if session is properly configured',
    function() {
        $issues = [];
        
        if (session_status() === PHP_SESSION_NONE) {
            $issues[] = 'Session not started';
        }
        
        if (!isset($_SESSION['role'])) {
            $issues[] = 'No role set in session';
        }
        
        if (empty($issues)) {
            return ['status' => 'PASS', 'message' => 'Session security OK'];
        } else {
            return ['status' => 'FAIL', 'message' => implode(', ', $issues)];
        }
    },
    'Authentication'
);

$testSuite->addTest(
    'Staff Authorization',
    'Test if current user has staff privileges',
    function() {
        if (!isset($_SESSION['role'])) {
            return ['status' => 'SKIP', 'message' => 'No active session'];
        }
        
        $allowed_roles = ['staff', 'admin'];
        if (in_array($_SESSION['role'], $allowed_roles)) {
            return ['status' => 'PASS', 'message' => 'User has staff privileges'];
        } else {
            return ['status' => 'FAIL', 'message' => 'User lacks staff privileges'];
        }
    },
    'Authentication'
);

// ========================================
// FORM FUNCTIONALITY TESTS
// ========================================

$testSuite->addTest(
    'QF-39 Form Processing',
    'Test QF-39 form data processing',
    function() {
        try {
            $conn = getDBConnection();
            
            // Check if QF-39 forms exist
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fillable_forms WHERE form_type = 'qf39'");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                // Test JSON parsing
                $stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE form_type = 'qf39' LIMIT 1");
                $stmt->execute();
                $form_data = $stmt->get_result()->fetch_assoc();
                
                $decoded = json_decode($form_data['form_data'], true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'status' => 'PASS',
                        'message' => "Found {$result['count']} QF-39 forms, JSON parsing OK"
                    ];
                } else {
                    return [
                        'status' => 'FAIL',
                        'message' => 'JSON parsing error: ' . json_last_error_msg()
                    ];
                }
            } else {
                return ['status' => 'SKIP', 'message' => 'No QF-39 forms found to test'];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Error: ' . $e->getMessage()];
        }
    },
    'Forms'
);

$testSuite->addTest(
    'QF-40 Form Generation',
    'Test QF-40 certificate generation capability',
    function() {
        // Check if template file exists
        $template_file = '../staff/qf40.docx';
        
        if (!file_exists($template_file)) {
            return ['status' => 'FAIL', 'message' => 'QF-40 template file not found'];
        }
        
        // Check if PhpWord is available
        if (!class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
            return ['status' => 'FAIL', 'message' => 'PhpWord library not available'];
        }
        
        return ['status' => 'PASS', 'message' => 'QF-40 generation ready'];
    },
    'Forms'
);

// ========================================
// FILE SYSTEM TESTS
// ========================================

$testSuite->addTest(
    'Required Files Exist',
    'Check if critical system files exist',
    function() {
        $required_files = [
            '../config/config.php',
            '../includes/functions.php',
            '../index.php',
            '../staff/view-application.php',
            '../staff/fill-qf40-form.php'
        ];
        
        $missing_files = [];
        
        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                $missing_files[] = $file;
            }
        }
        
        if (empty($missing_files)) {
            return ['status' => 'PASS', 'message' => 'All required files exist'];
        } else {
            return [
                'status' => 'FAIL',
                'message' => 'Missing files: ' . implode(', ', $missing_files)
            ];
        }
    },
    'File System'
);

$testSuite->addTest(
    'Directory Permissions',
    'Check if directories have proper permissions',
    function() {
        $directories = [
            '../' => 'readable',
            '../uploads/' => 'writable',
            '../database/migrations/' => 'readable'
        ];
        
        $issues = [];
        
        foreach ($directories as $dir => $permission) {
            if (!file_exists($dir)) {
                $issues[] = "$dir does not exist";
                continue;
            }
            
            if ($permission === 'writable' && !is_writable($dir)) {
                $issues[] = "$dir is not writable";
            } elseif ($permission === 'readable' && !is_readable($dir)) {
                $issues[] = "$dir is not readable";
            }
        }
        
        if (empty($issues)) {
            return ['status' => 'PASS', 'message' => 'Directory permissions OK'];
        } else {
            return ['status' => 'FAIL', 'message' => implode(', ', $issues)];
        }
    },
    'File System'
);

// ========================================
// API ENDPOINT TESTS
// ========================================

// ========================================
// SECURITY TESTS
// ========================================

$testSuite->addTest(
    'SQL Injection Protection',
    'Test if prepared statements are used',
    function() {
        $files_to_check = [
            '../staff/view-application.php',
            '../staff/fill-qf40-form.php'
        ];
        
        $vulnerable_files = [];
        
        foreach ($files_to_check as $file) {
            if (!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            
            // Check for direct SQL concatenation (vulnerable)
            if (preg_match('/\$[^=]*\s*\.\s*["\'][^"\']*["\']/', $content)) {
                $vulnerable_files[] = $file;
            }
            
            // Check for prepare statements (good)
            if (strpos($content, 'prepare(') === false) {
                $vulnerable_files[] = $file . ' (no prepared statements)';
            }
        }
        
        if (empty($vulnerable_files)) {
            return ['status' => 'PASS', 'message' => 'SQL injection protection OK'];
        } else {
            return [
                'status' => 'FAIL',
                'message' => 'Potential SQL injection risks in: ' . implode(', ', $vulnerable_files)
            ];
        }
    },
    'Security'
);

$testSuite->addTest(
    'XSS Protection',
    'Test if output is properly escaped',
    function() {
        $files_to_check = [
            '../staff/view-application.php',
            '../staff/fill-qf40-form.php'
        ];
        
        $vulnerable_files = [];
        
        foreach ($files_to_check as $file) {
            if (!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            
            // Check for unescaped echo
            if (preg_match('/echo\s+\$[^;]+;/', $content) && 
                strpos($content, 'htmlspecialchars') === false) {
                $vulnerable_files[] = $file;
            }
        }
        
        if (empty($vulnerable_files)) {
            return ['status' => 'PASS', 'message' => 'XSS protection OK'];
        } else {
            return [
                'status' => 'FAIL',
                'message' => 'Potential XSS risks in: ' . implode(', ', $vulnerable_files)
            ];
        }
    },
    'Security'
);

// ========================================
// PERFORMANCE TESTS
// ========================================

$testSuite->addTest(
    'Database Query Performance',
    'Test database query response time',
    function() {
        try {
            $conn = getDBConnection();
            $start_time = microtime(true);
            
            // Test a simple query
            $result = $conn->query("SELECT COUNT(*) as count FROM applications");
            $row = $result->fetch_assoc();
            
            $end_time = microtime(true);
            $query_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
            
            if ($query_time < 1000) { // Less than 1 second
                return [
                    'status' => 'PASS',
                    'message' => "Query time: " . number_format($query_time, 2) . "ms"
                ];
            } else {
                return [
                    'status' => 'FAIL',
                    'message' => "Slow query: " . number_format($query_time, 2) . "ms"
                ];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Performance test failed: ' . $e->getMessage()];
        }
    },
    'Performance'
);

// ========================================
// INTEGRATION TESTS
// ========================================

$testSuite->addTest(
    'QF-40 Date Integration',
    'Test QF-40 date autofill integration',
    function() {
        try {
            $conn = getDBConnection();
            
            // Check if applications have date data
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE research_date_started IS NOT NULL");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                return [
                    'status' => 'PASS',
                    'message' => "Found {$result['count']} applications with date data"
                ];
            } else {
                return ['status' => 'SKIP', 'message' => 'No applications with date data found'];
            }
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'message' => 'Integration test failed: ' . $e->getMessage()];
        }
    },
    'Integration'
);

// Run all tests if requested
if (isset($_GET['run_tests'])) {
    $test_results = $testSuite->runAllTests();
    $summary = $testSuite->getResults();
    
    // Return JSON response for AJAX requests
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'results' => $test_results,
            'summary' => $summary
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESI Portal - System Validation & Testing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-pass { color: #198754; }
        .test-fail { color: #dc3545; }
        .test-skip { color: #6c757d; }
        .category-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-top: 2rem;
        }
        .test-item { 
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .test-item:hover { 
            background-color: #f8f9fa;
            border-left-color: #0d6efd;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: block;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="bi bi-shield-check"></i>
                            TESI Portal System Validation
                        </h1>
                        <p class="text-muted mb-0">Comprehensive functionality and security testing suite</p>
                    </div>
                    <button class="btn btn-primary" onclick="runTests()">
                        <i class="bi bi-play-circle"></i> Run All Tests
                    </button>
                </div>

                <!-- Summary Card -->
                <div class="card summary-card mb-4" id="summaryCard" style="display: none;">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="h2 mb-0" id="totalTests">0</div>
                                <small>Total Tests</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h2 mb-0 test-pass" id="passedTests">0</div>
                                <small>Passed</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h2 mb-0 test-fail" id="failedTests">0</div>
                                <small>Failed</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h2 mb-0 test-skip" id="skippedTests">0</div>
                                <small>Skipped</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div class="card loading" id="loadingCard">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0">Running system tests...</p>
                    </div>
                </div>

                <!-- Test Results -->
                <div id="testResults"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runTests() {
            const loadingCard = document.getElementById('loadingCard');
            const resultsContainer = document.getElementById('testResults');
            const summaryCard = document.getElementById('summaryCard');
            
            // Show loading
            loadingCard.classList.add('show');
            resultsContainer.innerHTML = '';
            summaryCard.style.display = 'none';
            
            // Run tests via AJAX
            fetch('?run_tests=1&ajax=1')
                .then(response => response.json())
                .then(data => {
                    loadingCard.classList.remove('show');
                    displayResults(data);
                })
                .catch(error => {
                    loadingCard.classList.remove('show');
                    resultsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Error running tests: ${error.message}
                        </div>
                    `;
                });
        }
        
        function displayResults(data) {
            const resultsContainer = document.getElementById('testResults');
            const summaryCard = document.getElementById('summaryCard');
            
            // Update summary
            document.getElementById('totalTests').textContent = data.summary.total;
            document.getElementById('passedTests').textContent = data.summary.passed;
            document.getElementById('failedTests').textContent = data.summary.failed;
            document.getElementById('skippedTests').textContent = data.summary.skipped;
            summaryCard.style.display = 'block';
            
            // Group results by category
            const categories = {};
            data.results.forEach(test => {
                if (!categories[test.category]) {
                    categories[test.category] = [];
                }
                categories[test.category].push(test);
            });
            
            // Display results by category
            let html = '';
            for (const [category, tests] of Object.entries(categories)) {
                html += `
                    <div class="card category-header">
                        <div class="card-body">
                            <h5 class="mb-0">
                                <i class="bi bi-folder"></i>
                                ${category}
                                <span class="badge bg-white text-dark ms-2">${tests.length}</span>
                            </h5>
                        </div>
                    </div>
                `;
                
                tests.forEach(test => {
                    const statusClass = test.result.status.toLowerCase();
                    const icon = getStatusIcon(test.result.status);
                    
                    html += `
                        <div class="card test-item">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">${test.name}</h6>
                                        <p class="text-muted small mb-2">${test.description}</p>
                                        ${test.result.details ? `<p class="text-muted small mb-0">${test.result.details}</p>` : ''}
                                    </div>
                                    <div class="ms-3">
                                        <span class="badge status-badge bg-${getStatusColor(test.result.status)}">
                                            ${icon} ${test.result.status}
                                        </span>
                                    </div>
                                </div>
                                ${test.result.message ? `
                                    <div class="mt-2">
                                        <small class="text-${statusClass}">
                                            <i class="bi bi-info-circle"></i>
                                            ${test.result.message}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
            }
            
            resultsContainer.innerHTML = html;
        }
        
        function getStatusIcon(status) {
            switch(status) {
                case 'PASS': return '✓';
                case 'FAIL': return '✗';
                case 'SKIP': return '⊘';
                default: return '?';
            }
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
        document.addEventListener('DOMContentLoaded', function() {
            runTests();
        });
    </script>
</body>
</html>
