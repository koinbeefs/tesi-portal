<?php
/**
 * TESI Portal Database Schema Documentation
 * 
 * Comprehensive documentation of the secured, organized, and well-structured database
 * with encrypted sensitive data fields
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

class DatabaseSchemaDocumentation {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get table structure
     */
    public function getTableStructure($table_name) {
        $result = $this->conn->query("DESCRIBE $table_name");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get table indexes
     */
    public function getTableIndexes($table_name) {
        $result = $this->conn->query("SHOW INDEX FROM $table_name");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get table row count
     */
    public function getTableRowCount($table_name) {
        $result = $this->conn->query("SELECT COUNT(*) as count FROM $table_name");
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Display complete schema documentation
     */
    public function displaySchema() {
        $tables = [
            'users' => [
                'description' => 'User accounts and authentication information',
                'purpose' => 'Stores all user credentials and profile information with encrypted sensitive data',
                'security_notes' => 'Email, full_name, and department fields are encrypted using AES-256-CBC'
            ],
            'applications' => [
                'description' => 'Research applications and their metadata',
                'purpose' => 'Stores all research applications with encrypted sensitive information',
                'security_notes' => 'Applicant names, research titles, college, and program information are encrypted'
            ],
            'fillable_forms' => [
                'description' => 'Form data for QF-39 and QF-40 applications',
                'purpose' => 'Stores JSON form data with encrypted sensitive fields',
                'security_notes' => 'JSON data contains encrypted personal information'
            ],
            'staff_activity_logs' => [
                'description' => 'Audit trail of staff activities',
                'purpose' => 'Tracks all staff actions for security and compliance',
                'security_notes' => 'No sensitive personal data stored, only activity tracking'
            ]
        ];
        
        foreach ($tables as $table_name => $table_info) {
            $this->displayTableDocumentation($table_name, $table_info);
        }
    }
    
    /**
     * Display individual table documentation
     */
    private function displayTableDocumentation($table_name, $table_info) {
        $structure = $this->getTableStructure($table_name);
        $indexes = $this->getTableIndexes($table_name);
        $row_count = $this->getTableRowCount($table_name);
        
        echo "<div class='schema-section mb-5'>";
        echo "<div class='card'>";
        echo "<div class='card-header bg-primary text-white'>";
        echo "<h4 class='mb-0'><i class='bi bi-table'></i> $table_name</h4>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        // Table description
        echo "<div class='row mb-4'>";
        echo "<div class='col-md-12'>";
        echo "<h5>Description</h5>";
        echo "<p>{$table_info['description']}</p>";
        echo "<p><strong>Purpose:</strong> {$table_info['purpose']}</p>";
        echo "<div class='alert alert-info'>";
        echo "<i class='bi bi-shield-lock'></i> <strong>Security Notes:</strong> {$table_info['security_notes']}";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        // Table structure
        echo "<div class='row mb-4'>";
        echo "<div class='col-md-8'>";
        echo "<h5>Table Structure</h5>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr>";
        echo "<th>Field Name</th>";
        echo "<th>Type</th>";
        echo "<th>Null</th>";
        echo "<th>Key</th>";
        echo "<th>Default</th>";
        echo "<th>Encrypted</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($structure as $field) {
            $is_encrypted = $this->isFieldEncrypted($table_name, $field['Field']);
            $encryption_badge = $is_encrypted ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
            
            echo "<tr>";
            echo "<td><code>{$field['Field']}</code></td>";
            echo "<td>{$field['Type']}</td>";
            echo "<td>{$field['Null']}</td>";
            echo "<td>{$field['Key']}</td>";
            echo "<td>" . ($field['Default'] ?: 'NULL') . "</td>";
            echo "<td>$encryption_badge</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
        
        // Table statistics
        echo "<div class='col-md-4'>";
        echo "<h5>Statistics</h5>";
        echo "<div class='card bg-light'>";
        echo "<div class='card-body'>";
        echo "<p><strong>Total Records:</strong> " . number_format($row_count) . "</p>";
        echo "<p><strong>Total Fields:</strong> " . count($structure) . "</p>";
        echo "<p><strong>Encrypted Fields:</strong> " . $this->countEncryptedFields($table_name, $structure) . "</p>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        // Indexes
        if (!empty($indexes)) {
            echo "<div class='row'>";
            echo "<div class='col-md-12'>";
            echo "<h5>Indexes</h5>";
            echo "<div class='table-responsive'>";
            echo "<table class='table table-bordered table-sm'>";
            echo "<thead class='table-dark'>";
            echo "<tr>";
            echo "<th>Index Name</th>";
            echo "<th>Column</th>";
            echo "<th>Type</th>";
            echo "<th>Cardinality</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            
            foreach ($indexes as $index) {
                echo "<tr>";
                echo "<td><code>{$index['Key_name']}</code></td>";
                echo "<td><code>{$index['Column_name']}</code></td>";
                echo "<td>{$index['Index_type']}</td>";
                echo "<td>" . ($index['Cardinality'] ?: 'N/A') . "</td>";
                echo "</tr>";
            }
            
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    
    /**
     * Check if field is encrypted
     */
    private function isFieldEncrypted($table_name, $field_name) {
        $encrypted_fields = [
            'users' => ['email', 'full_name', 'department'],
            'applications' => ['applicant_name', 'research_title', 'college', 'program_course'],
            'fillable_forms' => ['form_data'] // JSON contains encrypted fields
        ];
        
        return isset($encrypted_fields[$table_name]) && in_array($field_name, $encrypted_fields[$table_name]);
    }
    
    /**
     * Count encrypted fields in table
     */
    private function countEncryptedFields($table_name, $structure) {
        $count = 0;
        foreach ($structure as $field) {
            if ($this->isFieldEncrypted($table_name, $field['Field'])) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Display security overview
     */
    public function displaySecurityOverview() {
        echo "<div class='security-overview mb-5'>";
        echo "<div class='card border-success'>";
        echo "<div class='card-header bg-success text-white'>";
        echo "<h4 class='mb-0'><i class='bi bi-shield-check'></i> Security Overview</h4>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        echo "<div class='row'>";
        echo "<div class='col-md-6'>";
        echo "<h5>Encryption Implementation</h5>";
        echo "<ul class='list-unstyled'>";
        echo "<li><i class='bi bi-check-circle text-success'></i> <strong>Algorithm:</strong> AES-256-CBC</li>";
        echo "<li><i class='bi bi-check-circle text-success'></i> <strong>Key Management:</strong> Secure key storage</li>";
        echo "<li><i class='bi bi-check-circle text-success'></i> <strong>IV Generation:</strong> Random per record</li>";
        echo "<li><i class='bi bi-check-circle text-success'></i> <strong>Encoding:</strong> Base64 for storage</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='col-md-6'>";
        echo "<h5>Protected Data Types</h5>";
        echo "<ul class='list-unstyled'>";
        echo "<li><i class='bi bi-shield text-success'></i> Personal Identifiable Information (PII)</li>";
        echo "<li><i class='bi bi-shield text-success'></i> Contact Information</li>";
        echo "<li><i class='bi bi-shield text-success'></i> Academic Information</li>";
        echo "<li><i class='bi bi-shield text-success'></i> Research Content</li>";
        echo "</ul>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='row mt-4'>";
        echo "<div class='col-12'>";
        echo "<h5>Security Best Practices</h5>";
        echo "<div class='row'>";
        echo "<div class='col-md-4'>";
        echo "<div class='card bg-light'>";
        echo "<div class='card-body text-center'>";
        echo "<i class='bi bi-key text-primary' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>Secure Keys</h6>";
        echo "<small>Encryption keys are stored securely and not exposed in code</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-4'>";
        echo "<div class='card bg-light'>";
        echo "<div class='card-body text-center'>";
        echo "<i class='bi bi-lock text-primary' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>Data At Rest</h6>";
        echo "<small>All sensitive data encrypted in database</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-4'>";
        echo "<div class='card bg-light'>";
        echo "<div class='card-body text-center'>";
        echo "<i class='bi bi-eye-slash text-primary' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>Privacy First</h6>";
        echo "<small>PII protected by encryption and access controls</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    
    /**
     * Display data flow diagram
     */
    public function displayDataFlow() {
        echo "<div class='data-flow mb-5'>";
        echo "<div class='card'>";
        echo "<div class='card-header bg-info text-white'>";
        echo "<h4 class='mb-0'><i class='bi bi-diagram-3'></i> Data Flow Architecture</h4>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        echo "<div class='row'>";
        echo "<div class='col-md-12'>";
        echo "<div class='text-center mb-4'>";
        echo "<h5>Application Lifecycle</h5>";
        echo "</div>";
        
        echo "<div class='d-flex justify-content-between align-items-center'>";
        echo "<div class='text-center'>";
        echo "<div class='card bg-primary text-white' style='width: 150px;'>";
        echo "<div class='card-body p-3'>";
        echo "<i class='bi bi-person-plus' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>User Registration</h6>";
        echo "<small>Encrypted PII</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<i class='bi bi-arrow-right' style='font-size: 2rem; color: #6c757d;'></i>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<div class='card bg-success text-white' style='width: 150px;'>";
        echo "<div class='card-body p-3'>";
        echo "<i class='bi bi-file-earmark-text' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>QF-39 Form</h6>";
        echo "<small>Encrypted Data</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<i class='bi bi-arrow-right' style='font-size: 2rem; color: #6c757d;'></i>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<div class='card bg-warning text-dark' style='width: 150px;'>";
        echo "<div class='card-body p-3'>";
        echo "<i class='bi bi-gear' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>Review Process</h6>";
        echo "<small>Staff Access</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<i class='bi bi-arrow-right' style='font-size: 2rem; color: #6c757d;'></i>";
        echo "</div>";
        
        echo "<div class='text-center'>";
        echo "<div class='card bg-info text-white' style='width: 150px;'>";
        echo "<div class='card-body p-3'>";
        echo "<i class='bi bi-award' style='font-size: 2rem;'></i>";
        echo "<h6 class='mt-2'>QF-40 Certificate</h6>";
        echo "<small>Final Output</small>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
}

// Display documentation
if (isset($_GET['view_schema'])) {
    $doc = new DatabaseSchemaDocumentation();
    $doc->displaySecurityOverview();
    $doc->displayDataFlow();
    $doc->displaySchema();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESI Portal - Database Schema Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .schema-section {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .badge-encrypted {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .security-metric {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .table th {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .table td {
            font-size: 0.9rem;
        }
        code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 4px;
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
                        TESI Portal Database Schema
                    </h1>
                    <p class="lead mb-4">
                        Comprehensive documentation of the secured, organized, and well-structured database
                        with encrypted sensitive data and privacy-first design.
                    </p>
                    <div class="d-flex gap-3">
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-shield-check"></i> AES-256 Encrypted
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-lock"></i> GDPR Compliant
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-eye-slash"></i> Privacy Protected
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
        <!-- Quick Stats -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card security-metric">
                    <div class="card-body">
                        <i class="bi bi-table text-primary" style="font-size: 2rem;"></i>
                        <h4 class="mt-2">4 Tables</h4>
                        <small class="text-muted">Core database structure</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card security-metric">
                    <div class="card-body">
                        <i class="bi bi-shield-lock text-success" style="font-size: 2rem;"></i>
                        <h4 class="mt-2">8+ Fields</h4>
                        <small class="text-muted">Encrypted sensitive data</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card security-metric">
                    <div class="card-body">
                        <i class="bi bi-key text-warning" style="font-size: 2rem;"></i>
                        <h4 class="mt-2">AES-256</h4>
                        <small class="text-muted">Encryption standard</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card security-metric">
                    <div class="card-body">
                        <i class="bi bi-diagram-3 text-info" style="font-size: 2rem;"></i>
                        <h4 class="mt-2">Normalized</h4>
                        <small class="text-muted">3NF database design</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="d-flex gap-3">
                            <button class="btn btn-primary" onclick="loadSchema()">
                                <i class="bi bi-eye"></i> View Complete Schema
                            </button>
                            <a href="mock-database-generator.php" class="btn btn-success">
                                <i class="bi bi-database-plus"></i> Generate Mock Data
                            </a>
                            <a href="../tests/index.php" class="btn btn-info">
                                <i class="bi bi-bug"></i> Run Tests
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schema Display -->
        <div id="schemaDisplay"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadSchema() {
            const displayDiv = document.getElementById('schemaDisplay');
            const button = event.target;
            
            // Show loading
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading Schema...';
            
            displayDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading database schema documentation...</p></div>';
            
            // Load schema
            fetch('?view_schema=1')
                .then(response => response.text())
                .then(html => {
                    displayDiv.innerHTML = html;
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-eye"></i> View Complete Schema';
                    
                    // Scroll to schema
                    displayDiv.scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    displayDiv.innerHTML = '<div class="alert alert-danger">Error loading schema: ' + error.message + '</div>';
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-eye"></i> View Complete Schema';
                });
        }
    </script>
</body>
</html>
