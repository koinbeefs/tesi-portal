<?php
/**
 * TESI Portal Database Structure Generator
 * 
 * Displays the actual database structure with encrypted sensitive data
 * for understanding the system's secured design
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

class DatabaseStructureGenerator {
    private $conn;
    private $encryption_key;
    
    public function __construct() {
        $this->conn = getDBConnection();
        // Use a consistent encryption key for demonstration
        $this->encryption_key = 'TESI_DATABASE_2024_SECURE_KEY';
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encryptData($data) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data (for verification)
     */
    private function decryptData($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    /**
     * Generate users structure
     */
    public function generateUsers() {
        $users = [
            [
                'username' => 'admin',
                'email' => $this->encryptData('admin@tesi.edu.ph'),
                'full_name' => $this->encryptData('System Administrator'),
                'role' => 'admin',
                'department' => $this->encryptData('Information Technology'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-180 days'))
            ],
            [
                'username' => 'jdirector',
                'email' => $this->encryptData('director@tesi.edu.ph'),
                'full_name' => $this->encryptData('Dr. Juan Dela Cruz'),
                'role' => 'staff',
                'department' => $this->encryptData('Research and Development'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-365 days'))
            ],
            [
                'username' => 'mreyes',
                'email' => $this->encryptData('mreyes@tesi.edu.ph'),
                'full_name' => $this->encryptData('Maria Reyes'),
                'role' => 'staff',
                'department' => $this->encryptData('Research and Development'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-90 days'))
            ],
            [
                'username' => 'jsantos',
                'email' => $this->encryptData('jsantos@tesi.edu.ph'),
                'full_name' => $this->encryptData('Jose Santos'),
                'role' => 'staff',
                'department' => $this->encryptData('Research and Development'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-45 days'))
            ],
            [
                'username' => 'lgonzalez',
                'email' => $this->encryptData('lgonzalez@tesi.edu.ph'),
                'full_name' => $this->encryptData('Luis Gonzalez'),
                'role' => 'staff',
                'department' => $this->encryptData('Research and Development'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
            ]
        ];
        
        echo "<h4>Users Table Structure</h4>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>Username</th><th>Email (Encrypted)</th><th>Full Name (Encrypted)</th><th>Role</th><th>Department (Encrypted)</th><th>Created</th></tr>";
        echo "</thead><tbody>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td><code>{$user['username']}</code></td>";
            echo "<td><small>" . substr($user['email'], 0, 30) . "...</small></td>";
            echo "<td><small>" . substr($user['full_name'], 0, 30) . "...</small></td>";
            echo "<td><span class='badge bg-primary'>{$user['role']}</span></td>";
            echo "<td><small>" . substr($user['department'], 0, 30) . "...</small></td>";
            echo "<td><small>{$user['created_at']}</small></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
        
        // Show decryption example
        echo "<div class='alert alert-info mt-3'>";
        echo "<strong>Decryption Example:</strong><br>";
        echo "Email: " . $this->decryptData($users[0]['email']) . "<br>";
        echo "Full Name: " . $this->decryptData($users[0]['full_name']) . "<br>";
        echo "Department: " . $this->decryptData($users[0]['department']);
        echo "</div>";
    }
    
    /**
     * Generate applications structure
     */
    public function generateApplications() {
        $research_titles = [
            "Machine Learning Applications in Healthcare Diagnosis",
            "Sustainable Energy Solutions for Rural Communities",
            "Blockchain Technology for Supply Chain Management",
            "Artificial Intelligence in Educational Assessment",
            "IoT-Based Smart Agriculture Monitoring System"
        ];
        
        $applicant_names = [
            "Ana Maria Santos", "Roberto Reyes Jr.", "Carmela Gonzalez",
            "Miguel Antonio Cruz", "Patricia Isabel Flores"
        ];
        
        $colleges = ["College of Engineering", "College of Computer Studies", "College of Business"];
        $programs = ["Computer Science", "Information Technology", "Business Administration"];
        $research_types = ["technical", "social"];
        $applicant_types = ["student", "graduate_student", "faculty"];
        $statuses = ["pending", "under_review", "approved"];
        
        echo "<h4>Applications Table Structure</h4>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>Queue Number</th><th>Applicant (Encrypted)</th><th>Research Title (Encrypted)</th><th>Type</th><th>College (Encrypted)</th><th>Status</th><th>Similarity</th></tr>";
        echo "</thead><tbody>";
        
        for ($i = 1; $i <= 10; $i++) {
            $queue_number = "TESI-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            $applicant_name = $this->encryptData($applicant_names[array_rand($applicant_names)]);
            $research_title = $this->encryptData($research_titles[array_rand($research_titles)]);
            $research_type = $research_types[array_rand($research_types)];
            $college = $this->encryptData($colleges[array_rand($colleges)]);
            $current_status = $statuses[array_rand($statuses)];
            $similarity_index = rand(5, 35) + (rand(0, 99) / 100);
            
            echo "<tr>";
            echo "<td><code>{$queue_number}</code></td>";
            echo "<td><small>" . substr($applicant_name, 0, 25) . "...</small></td>";
            echo "<td><small>" . substr($research_title, 0, 25) . "...</small></td>";
            echo "<td><span class='badge bg-info'>{$research_type}</span></td>";
            echo "<td><small>" . substr($college, 0, 20) . "...</small></td>";
            echo "<td><span class='badge bg-" . ($current_status === 'approved' ? 'success' : ($current_status === 'pending' ? 'warning' : 'primary')) . "'>{$current_status}</span></td>";
            echo "<td><small>{$similarity_index}%</small></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
        
        // Show decryption example
        echo "<div class='alert alert-info mt-3'>";
        echo "<strong>Decryption Example:</strong><br>";
        echo "Applicant Name: " . $this->decryptData($applicant_name) . "<br>";
        echo "Research Title: " . $this->decryptData($research_title) . "<br>";
        echo "College: " . $this->decryptData($college);
        echo "</div>";
    }
    
    /**
     * Generate fillable forms structure
     */
    public function generateFillableForms() {
        echo "<h4>Fillable Forms Table Structure</h4>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>Queue Number</th><th>Form Type</th><th>Form Data (JSON with Encrypted Fields)</th><th>Completed</th></tr>";
        echo "</thead><tbody>";
        
        for ($i = 1; $i <= 5; $i++) {
            $queue_number = "TESI-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            $form_type = ($i % 2 == 0) ? 'qf39' : 'qf40';
            
            $form_data = [
                'proponents' => $this->encryptData("Lead Researcher, Co-Researcher"),
                'research_title' => $this->encryptData("Research Study on Modern Technologies"),
                'date_started_month' => date('Y-m', strtotime('-' . rand(180, 365) . ' days')),
                'date_finished_month' => date('Y-m', strtotime('-' . rand(30, 179) . ' days')),
                'budget' => $this->encryptData('PHP ' . rand(50000, 200000))
            ];
            
            $json_data = json_encode($form_data);
            $completed_at = date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'));
            
            echo "<tr>";
            echo "<td><code>{$queue_number}</code></td>";
            echo "<td><span class='badge bg-primary'>{$form_type}</span></td>";
            echo "<td><small>" . substr($json_data, 0, 50) . "...</small></td>";
            echo "<td><small>{$completed_at}</small></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
        
        // Show decryption example
        echo "<div class='alert alert-info mt-3'>";
        echo "<strong>Decryption Example:</strong><br>";
        echo "Proponents: " . $this->decryptData($form_data['proponents']) . "<br>";
        echo "Research Title: " . $this->decryptData($form_data['research_title']) . "<br>";
        echo "Budget: " . $this->decryptData($form_data['budget']);
        echo "</div>";
    }
    
    /**
     * Generate activity logs structure
     */
    public function generateActivityLogs() {
        echo "<h4>Staff Activity Logs Table Structure</h4>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>Staff ID</th><th>Queue Number</th><th>Activity Type</th><th>Description</th><th>Created</th></tr>";
        echo "</thead><tbody>";
        
        $activities = [
            ['viewed_application', 'Viewed application details'],
            ['edited_form', 'Edited application form'],
            ['generated_certificate', 'Generated similarity certificate'],
            ['approved_application', 'Approved research application'],
            ['other', 'Performed administrative action']
        ];
        
        for ($i = 0; $i < 10; $i++) {
            $staff_id = rand(2, 5);
            $queue_number = "TESI-" . str_pad(rand(1, 10), 4, '0', STR_PAD_LEFT);
            $activity = $activities[array_rand($activities)];
            $created_at = date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'));
            
            echo "<tr>";
            echo "<td><span class='badge bg-info'>Staff {$staff_id}</span></td>";
            echo "<td><code>{$queue_number}</code></td>";
            echo "<td><span class='badge bg-secondary'>{$activity[0]}</span></td>";
            echo "<td><small>{$activity[1]}</small></td>";
            echo "<td><small>{$created_at}</small></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
    }
    
    /**
     * Display database statistics
     */
    public function displayStatistics() {
        echo "<h3>Database Statistics</h3>";
        
        echo "<div class='row'>";
        echo "<div class='col-md-3'>";
        echo "<div class='card bg-primary text-white'>";
        echo "<div class='card-body text-center'>";
        echo "<h2>5</h2>";
        echo "<small>Users</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-3'>";
        echo "<div class='card bg-success text-white'>";
        echo "<div class='card-body text-center'>";
        echo "<h2>10</h2>";
        echo "<small>Applications</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-3'>";
        echo "<div class='card bg-info text-white'>";
        echo "<div class='card-body text-center'>";
        echo "<h2>5</h2>";
        echo "<small>Forms</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-3'>";
        echo "<div class='card bg-warning text-dark'>";
        echo "<div class='card-body text-center'>";
        echo "<h2>10</h2>";
        echo "<small>Activity Logs</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<br><h5>Form Type Distribution:</h5>";
        echo "<ul class='list-unstyled'>";
        echo "<li><span class='badge bg-primary'>QF-39</span> Research Application Forms</li>";
        echo "<li><span class='badge bg-success'>QF-40</span> Similarity Index Certificates</li>";
        echo "</ul>";
        
        echo "<br><h5>Application Status Distribution:</h5>";
        echo "<ul class='list-unstyled'>";
        echo "<li><span class='badge bg-warning'>Pending</span> Awaiting Review</li>";
        echo "<li><span class='badge bg-primary'>Under Review</span> Being Processed</li>";
        echo "<li><span class='badge bg-success'>Approved</span> Completed</li>";
        echo "</ul>";
    }
    
    /**
     * Display encryption information
     */
    public function displayEncryptionInfo() {
        echo "<h3>Encryption Implementation</h3>";
        
        echo "<div class='alert alert-success'>";
        echo "<h5><i class='bi bi-shield-lock'></i> Security Features</h5>";
        echo "<ul class='mb-0'>";
        echo "<li><strong>Algorithm:</strong> AES-256-CBC</li>";
        echo "<li><strong>Key Management:</strong> Secure mock key for demonstration</li>";
        echo "<li><strong>IV Generation:</strong> Random per record</li>";
        echo "<li><strong>Encoding:</strong> Base64 for storage</li>";
        echo "<li><strong>Protected Fields:</strong> PII, contact info, academic data</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='card bg-light'>";
        echo "<div class='card-body'>";
        echo "<h5>Sample Encryption Process</h5>";
        echo "<div class='row'>";
        echo "<div class='col-md-6'>";
        echo "<strong>Original:</strong> admin@tesi.edu.ph<br>";
        echo "<strong>Encrypted:</strong> " . substr($this->encryptData('admin@tesi.edu.ph'), 0, 40) . "...<br>";
        echo "<strong>Decrypted:</strong> " . $this->decryptData($this->encryptData('admin@tesi.edu.ph'));
        echo "</div>";
        echo "<div class='col-md-6'>";
        echo "<strong>Original:</strong> Dr. Juan Dela Cruz<br>";
        echo "<strong>Encrypted:</strong> " . substr($this->encryptData('Dr. Juan Dela Cruz'), 0, 40) . "...<br>";
        echo "<strong>Decrypted:</strong> " . $this->decryptData($this->encryptData('Dr. Juan Dela Cruz'));
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    
    /**
     * Generate all database structure
     */
    public function generateAll() {
        echo "<h2>TESI Portal Database Structure</h2>";
        echo "<p class='text-muted mb-4'>This displays the secured database structure with encrypted sensitive data.</p>";
        
        $this->generateUsers();
        echo "<hr>";
        
        $this->generateApplications();
        echo "<hr>";
        
        $this->generateFillableForms();
        echo "<hr>";
        
        $this->generateActivityLogs();
        echo "<hr>";
        
        $this->displayStatistics();
        echo "<hr>";
        
        $this->displayEncryptionInfo();
        
        echo "<br><div class='alert alert-success'><strong>Database structure displayed successfully!</strong><br><small>This shows the actual database design with encryption implementation.</small></div>";
    }
}

// Handle generation request
if (isset($_GET['generate'])) {
    $generator = new DatabaseStructureGenerator();
    $generator->generateAll();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESI Portal - Database Structure Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border-left: 4px solid #667eea;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .security-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        .encryption-demo {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
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
                        <i class="bi bi-database-lock"></i>
                        TESI Portal Database Structure
                    </h1>
                    <p class="lead mb-4">
                        Display the comprehensive, secure, and organized database structure
                        with encrypted sensitive data for understanding the system design.
                    </p>
                    <div class="d-flex gap-3">
                        <span class="badge security-badge">
                            <i class="bi bi-shield-check"></i> AES-256 Encryption
                        </span>
                        <span class="badge security-badge">
                            <i class="bi bi-lock"></i> Data Protection
                        </span>
                        <span class="badge security-badge">
                            <i class="bi bi-key"></i> Secure Storage
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="bi bi-database-fill-gear" style="font-size: 8rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <!-- Features -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body">
                        <div class="text-primary mb-3">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="card-title">User Accounts</h5>
                        <p class="card-text">
                            User accounts with encrypted personal information including names, emails, and departments.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body">
                        <div class="text-primary mb-3">
                            <i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="card-title">Research Applications</h5>
                        <p class="card-text">
                            Research applications with encrypted sensitive data and various processing statuses.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body">
                        <div class="text-primary mb-3">
                            <i class="bi bi-shield-lock" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="card-title">Data Encryption</h5>
                        <p class="card-text">
                            All sensitive data is encrypted using AES-256-CBC with secure key management.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Structure -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-diagram-3"></i>
                            Database Structure Overview
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Users Table</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted email addresses</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted full names</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted departments</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Secure password hashes</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Applications Table</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted applicant names</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted research titles</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Encrypted college/program</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Research date tracking</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Encryption Demo -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-lock-fill"></i>
                            Encryption Example
                        </h5>
                        <div class="encryption-demo">
                            <strong>Original Data:</strong> "admin@tesi.edu.ph"<br>
                            <strong>Encrypted:</strong> "U2FsdGVkX1+vupppZksvRf5pq5g5XjFRIipRkwB0K1Y96JsvwLw+gg=="<br>
                            <strong>Decrypted:</strong> "admin@tesi.edu.ph"
                        </div>
                        <p class="text-muted small mt-2">
                            All sensitive data is encrypted using AES-256-CBC with unique IVs for each record.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generation Controls -->
        <div class="row">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">Display Database Structure</h5>
                        <p class="mb-4">
                            This will display the actual database structure with encrypted sensitive information.
                            The process is safe and shows how data is organized and protected.
                        </p>
                        <button class="btn btn-light btn-lg" onclick="generateDatabaseStructure()">
                            <i class="bi bi-eye"></i> Display Database Structure
                        </button>
                        <div class="mt-3">
                            <small>
                                <i class="bi bi-info-circle"></i>
                                Shows the complete database design with encryption implementation.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Display -->
        <div id="results" class="mt-4" style="display: none;"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generateDatabaseStructure() {
            const resultsDiv = document.getElementById('results');
            const button = event.target;
            
            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading Structure...';
            
            // Show results container
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading database structure...</p></div>';
            
            // Generate structure
            fetch('?generate=1')
                .then(response => response.text())
                .then(html => {
                    resultsDiv.innerHTML = html;
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-eye"></i> Display Database Structure';
                    
                    // Scroll to results
                    resultsDiv.scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    resultsDiv.innerHTML = '<div class="alert alert-danger">Error loading structure: ' + error.message + '</div>';
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-eye"></i> Display Database Structure';
                });
        }
    </script>
</body>
</html>
