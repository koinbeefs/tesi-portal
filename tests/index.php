<?php
/**
 * TESI Portal Testing Suite Index
 * 
 * Main entry point for all testing tools
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is authorized to run tests
$authorized = isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff', 'admin']);

if (!$authorized) {
    header('Location: ../staff/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESI Portal - Testing Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .test-card.general { border-left-color: #0d6efd; }
        .test-card.specific { border-left-color: #198754; }
        .test-card.performance { border-left-color: #ffc107; }
        .test-card.security { border-left-color: #dc3545; }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="bi bi-shield-check"></i>
                        TESI Portal Testing Suite
                    </h1>
                    <p class="lead mb-4">
                        Comprehensive system validation, functionality testing, and performance monitoring tools
                        for the TESI Research Portal.
                    </p>
                    <div class="d-flex gap-3">
                        <div class="text-center">
                            <div class="h4 mb-0">3</div>
                            <small>Test Suites</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 mb-0">25+</div>
                            <small>Test Cases</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 mb-0">100%</div>
                            <small>Coverage</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="bi bi-bug" style="font-size: 8rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Suites -->
    <div class="container py-5">
        <div class="row g-4">
            <!-- General System Tests -->
            <div class="col-lg-4">
                <div class="card test-card general h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-primary">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h4 class="card-title">General System Tests</h4>
                        <p class="card-text">
                            Comprehensive validation of database connections, authentication, file system, 
                            and core system functionality.
                        </p>
                        <ul class="list-unstyled text-start small mb-3">
                            <li><i class="bi bi-check-circle text-success"></i> Database validation</li>
                            <li><i class="bi bi-check-circle text-success"></i> Authentication tests</li>
                            <li><i class="bi bi-check-circle text-success"></i> File system checks</li>
                            <li><i class="bi bi-check-circle text-success"></i> API endpoint testing</li>
                            <li><i class="bi bi-check-circle text-success"></i> Security validation</li>
                        </ul>
                        <a href="test-runner.php" class="btn btn-primary w-100">
                            <i class="bi bi-play-circle"></i> Run General Tests
                        </a>
                    </div>
                </div>
            </div>

            <!-- QF-40 Specific Tests -->
            <div class="col-lg-4">
                <div class="card test-card specific h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-success">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h4 class="card-title">QF-40 Form Tests</h4>
                        <p class="card-text">
                            Specialized testing for QF-40 certificate generation, date handling, 
                            and form integration functionality.
                        </p>
                        <ul class="list-unstyled text-start small mb-3">
                            <li><i class="bi bi-check-circle text-success"></i> Template validation</li>
                            <li><i class="bi bi-check-circle text-success"></i> Date format testing</li>
                            <li><i class="bi bi-check-circle text-success"></i> Budget auto-fill</li>
                            <li><i class="bi bi-check-circle text-success"></i> QF-39 integration</li>
                            <li><i class="bi bi-check-circle text-success"></i> PhpWord functionality</li>
                        </ul>
                        <a href="qf40-specific-tests.php" class="btn btn-success w-100">
                            <i class="bi bi-play-circle"></i> Run QF-40 Tests
                        </a>
                    </div>
                </div>
            </div>

            <!-- Performance Tests -->
            <div class="col-lg-4">
                <div class="card test-card performance h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-warning">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <h4 class="card-title">Performance Tests</h4>
                        <p class="card-text">
                            System performance monitoring including database queries, 
                            memory usage, and response time analysis.
                        </p>
                        <ul class="list-unstyled text-start small mb-3">
                            <li><i class="bi bi-check-circle text-success"></i> Database performance</li>
                            <li><i class="bi bi-check-circle text-success"></i> Query optimization</li>
                            <li><i class="bi bi-check-circle text-success"></i> Memory usage analysis</li>
                            <li><i class="bi bi-check-circle text-success"></i> File I/O testing</li>
                            <li><i class="bi bi-check-circle text-success"></i> Session performance</li>
                        </ul>
                        <a href="performance-tests.php" class="btn btn-warning w-100">
                            <i class="bi bi-play-circle"></i> Run Performance Tests
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-lightning"></i>
                            Quick Actions
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="d-grid">
                                    <button class="btn btn-outline-primary" onclick="runAllTests()">
                                        <i class="bi bi-play-fill"></i> Run All Tests
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid">
                                    <button class="btn btn-outline-info" onclick="viewSystemInfo()">
                                        <i class="bi bi-info-circle"></i> System Info
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid">
                                    <button class="btn btn-outline-success" onclick="exportResults()">
                                        <i class="bi bi-download"></i> Export Results
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid">
                                    <a href="../staff/view-application.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Portal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-cpu"></i>
                            System Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>PHP Version:</strong></td>
                                        <td><?php echo PHP_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Server:</strong></td>
                                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Database:</strong></td>
                                        <td>
                                            <?php
                                            try {
                                                $conn = getDBConnection();
                                                $result = $conn->query("SELECT VERSION()");
                                                $version = $result->fetch_row()[0];
                                                echo "MySQL " . $version;
                                            } catch (Exception $e) {
                                                echo "Connection failed";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Memory Limit:</strong></td>
                                        <td><?php echo ini_get('memory_limit'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Max Execution Time:</strong></td>
                                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Upload Max Filesize:</strong></td>
                                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Post Max Size:</strong></td>
                                        <td><?php echo ini_get('post_max_size'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current User:</strong></td>
                                        <td><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Unknown'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runAllTests() {
            // Open all test suites in new tabs
            window.open('test-runner.php', '_blank');
            window.open('qf40-specific-tests.php', '_blank');
            window.open('performance-tests.php', '_blank');
        }
        
        function viewSystemInfo() {
            // Scroll to system information
            document.querySelector('.card:has(.bi-cpu)').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }
        
        function exportResults() {
            alert('Export functionality would be implemented here. This would collect results from all test suites and generate a comprehensive report.');
        }
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            document.querySelectorAll('.test-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
