<?php
/**
 * Performance and Load Testing Suite
 * 
 * Tests system performance under various conditions
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

class PerformanceTestSuite {
    private $tests = [];
    private $results = [];
    
    public function addTest($name, $description, $callback) {
        $this->tests[] = [
            'name' => $name,
            'description' => $description,
            'callback' => $callback
        ];
    }
    
    public function runTests() {
        $results = [];
        
        foreach ($this->tests as $test) {
            $start_time = microtime(true);
            $start_memory = memory_get_usage();
            
            try {
                $result = call_user_func($test['callback']);
                $end_time = microtime(true);
                $end_memory = memory_get_usage();
                
                $results[] = [
                    'name' => $test['name'],
                    'description' => $test['description'],
                    'execution_time' => ($end_time - $start_time) * 1000, // milliseconds
                    'memory_used' => $end_memory - $start_memory, // bytes
                    'result' => $result
                ];
                
            } catch (Exception $e) {
                $end_time = microtime(true);
                $end_memory = memory_get_usage();
                
                $results[] = [
                    'name' => $test['name'],
                    'description' => $test['description'],
                    'execution_time' => ($end_time - $start_time) * 1000,
                    'memory_used' => $end_memory - $start_memory,
                    'result' => ['status' => 'FAIL', 'message' => $e->getMessage()]
                ];
            }
        }
        
        return $results;
    }
}

$perfSuite = new PerformanceTestSuite();

// ========================================
// PERFORMANCE TESTS
// ========================================

$perfSuite->addTest(
    'Database Connection Speed',
    'Test how fast database connections are established',
    function() {
        $start = microtime(true);
        $conn = getDBConnection();
        $end = microtime(true);
        
        if (!$conn) {
            return ['status' => 'FAIL', 'message' => 'Database connection failed'];
        }
        
        $time = ($end - $start) * 1000;
        
        if ($time > 100) { // More than 100ms is slow
            return ['status' => 'FAIL', 'message' => "Slow connection: {$time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "Connection time: {$time}ms"];
    }
);

$perfSuite->addTest(
    'Query Performance - Applications Count',
    'Test performance of counting applications',
    function() {
        $conn = getDBConnection();
        
        $start = microtime(true);
        $result = $conn->query("SELECT COUNT(*) as count FROM applications");
        $row = $result->fetch_assoc();
        $end = microtime(true);
        
        $time = ($end - $start) * 1000;
        
        if ($time > 500) { // More than 500ms is slow for a simple count
            return ['status' => 'FAIL', 'message' => "Slow query: {$time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "Query time: {$time}ms, Count: {$row['count']}"];
    }
);

$perfSuite->addTest(
    'Complex Query Performance',
    'Test performance of complex JOIN queries',
    function() {
        $conn = getDBConnection();
        
        $start = microtime(true);
        $stmt = $conn->prepare("
            SELECT a.queue_number, a.applicant_name, f.form_type, f.completed_at 
            FROM applications a 
            LEFT JOIN fillable_forms f ON a.queue_number = f.queue_number 
            WHERE a.current_status != 'REJECTED' 
            ORDER BY a.queue_number 
            LIMIT 50
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $end = microtime(true);
        
        $time = ($end - $start) * 1000;
        
        if ($time > 1000) { // More than 1 second is slow
            return ['status' => 'FAIL', 'message' => "Slow complex query: {$time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "Complex query time: {$time}ms, Rows: " . count($rows)];
    }
);

$perfSuite->addTest(
    'JSON Processing Performance',
    'Test JSON encoding/decoding performance',
    function() {
        // Create test data
        $testData = [
            'research_title' => str_repeat('Test Research Title ', 10),
            'proponents' => str_repeat('Test Proponent, ', 20),
            'date_started_month' => '2024-01',
            'date_finished_month' => '2024-12',
            'budget' => 'PHP 50,000',
            'description' => str_repeat('Test description for performance testing. ', 50)
        ];
        
        // Test JSON encoding
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $json = json_encode($testData);
        }
        $encode_time = (microtime(true) - $start) * 1000;
        
        // Test JSON decoding
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $decoded = json_decode($json, true);
        }
        $decode_time = (microtime(true) - $start) * 1000;
        
        $total_time = $encode_time + $decode_time;
        
        if ($total_time > 100) { // More than 100ms for 100 operations is slow
            return ['status' => 'FAIL', 'message' => "Slow JSON processing: {$total_time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "JSON processing: {$total_time}ms (encode: {$encode_time}ms, decode: {$decode_time}ms)"];
    }
);

$perfSuite->addTest(
    'File I/O Performance',
    'Test file read/write performance',
    function() {
        $testFile = '../temp/performance_test.txt';
        $testContent = str_repeat('Test content for file I/O performance testing. ', 100);
        
        // Ensure temp directory exists
        if (!is_dir('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        // Test file write
        $start = microtime(true);
        file_put_contents($testFile, $testContent);
        $write_time = (microtime(true) - $start) * 1000;
        
        // Test file read
        $start = microtime(true);
        $content = file_get_contents($testFile);
        $read_time = (microtime(true) - $start) * 1000;
        
        // Cleanup
        unlink($testFile);
        
        $total_time = $write_time + $read_time;
        
        if ($total_time > 50) { // More than 50ms for simple file I/O is slow
            return ['status' => 'FAIL', 'message' => "Slow file I/O: {$total_time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "File I/O: {$total_time}ms (write: {$write_time}ms, read: {$read_time}ms)"];
    }
);

$perfSuite->addTest(
    'Memory Usage - Large Dataset',
    'Test memory usage with large datasets',
    function() {
        $start_memory = memory_get_usage();
        
        // Simulate processing large dataset
        $large_dataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $large_dataset[] = [
                'queue_number' => 'TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'applicant_name' => 'Test Applicant ' . $i,
                'research_title' => 'Test Research Title ' . $i,
                'data' => str_repeat('Sample data ', 10)
            ];
        }
        
        // Process the dataset
        foreach ($large_dataset as $item) {
            $processed = strtoupper($item['research_title']);
        }
        
        $end_memory = memory_get_usage();
        $memory_used = $end_memory - $start_memory;
        $memory_mb = $memory_used / 1024 / 1024;
        
        // Clear memory
        unset($large_dataset);
        
        if ($memory_mb > 50) { // More than 50MB is concerning
            return ['status' => 'FAIL', 'message' => "High memory usage: " . number_format($memory_mb, 2) . "MB"];
        }
        
        return ['status' => 'PASS', 'message' => "Memory usage: " . number_format($memory_mb, 2) . "MB"];
    }
);

$perfSuite->addTest(
    'Session Performance',
    'Test session read/write performance',
    function() {
        // Test session write
        $start = microtime(true);
        $_SESSION['performance_test'] = [
            'test_data' => str_repeat('Session test data ', 100),
            'timestamp' => time(),
            'user_id' => 'test_user'
        ];
        session_write_close();
        $write_time = (microtime(true) - $start) * 1000;
        
        // Restart session and test read
        session_start();
        $start = microtime(true);
        $data = $_SESSION['performance_test'];
        $read_time = (microtime(true) - $start) * 1000;
        
        // Cleanup
        unset($_SESSION['performance_test']);
        
        $total_time = $write_time + $read_time;
        
        if ($total_time > 100) { // More than 100ms is slow
            return ['status' => 'FAIL', 'message' => "Slow session operations: {$total_time}ms"];
        }
        
        return ['status' => 'PASS', 'message' => "Session operations: {$total_time}ms (write: {$write_time}ms, read: {$read_time}ms)"];
    }
);

// Run tests if requested
if (isset($_GET['run_performance_tests'])) {
    $results = $perfSuite->runTests();
    
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
    <title>Performance Tests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .performance-good { color: #198754; }
        .performance-warning { color: #ffc107; }
        .performance-bad { color: #dc3545; }
        .metric-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-speedometer2"></i>
                    Performance Tests
                </h2>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="runPerformanceTests()">
                            <i class="bi bi-play-circle"></i> Run Performance Tests
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
                    <p class="mt-3">Running performance tests...</p>
                </div>
                
                <div id="testResults"></div>
            </div>
        </div>
    </div>
    
    <script>
        function runPerformanceTests() {
            const loading = document.getElementById('loadingIndicator');
            const results = document.getElementById('testResults');
            
            loading.style.display = 'block';
            results.innerHTML = '';
            
            fetch('?run_performance_tests=1&ajax=1')
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
            
            let html = '';
            
            data.forEach(test => {
                const statusClass = test.result.status.toLowerCase();
                const performanceClass = getPerformanceClass(test.execution_time, test.memory_used);
                
                html += `
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">${test.name}</h6>
                                    <p class="text-muted small mb-0">${test.description}</p>
                                </div>
                                <span class="badge bg-${getStatusColor(test.result.status)}">
                                    ${test.result.status}
                                </span>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <small class="text-muted">Execution Time</small>
                                    <div class="fw-bold ${performanceClass}">
                                        ${test.execution_time.toFixed(2)}ms
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Memory Used</small>
                                    <div class="fw-bold ${performanceClass}">
                                        ${formatBytes(test.memory_used)}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-${statusClass}">
                                    ${test.result.message}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            results.innerHTML = html;
        }
        
        function getPerformanceClass(time, memory) {
            if (time > 1000 || memory > 10485760) return 'performance-bad'; // >1s or >10MB
            if (time > 500 || memory > 5242880) return 'performance-warning'; // >500ms or >5MB
            return 'performance-good';
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'PASS': return 'success';
                case 'FAIL': return 'danger';
                case 'SKIP': return 'secondary';
                default: return 'warning';
            }
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Auto-run tests on page load
        document.addEventListener('DOMContentLoaded', runPerformanceTests);
    </script>
</body>
</html>
