<?php
/**
 * Folder Explorer - QF39 and QF40 Document Browser
 * TAU-TeSI Portal - Staff Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Require staff login
requireStaffLogin();

$base_path = __DIR__ . '/../uploads/';
$current_year = date('Y');
$selected_year = $_GET['year'] ?? $current_year;
$selected_folder = $_GET['folder'] ?? 'all'; // 'all', 'qf39', 'qf40'
$search_term = $_GET['search'] ?? '';

// Get available years
$available_years = [];
$years_scan = scandir($base_path);
foreach ($years_scan as $item) {
    if ($item !== '.' && $item !== '..' && is_dir($base_path . $item)) {
        if (is_numeric($item) && strlen($item) === 4) {
            $available_years[] = $item;
        }
    }
}
rsort($available_years);

// Get folder structure
function getFolderStructure($base_path, $year, $folder_filter = 'all', $search = '') {
    $structure = [];
    
    $folders_to_scan = [];
    if ($folder_filter === 'all' || $folder_filter === 'qf39') {
        $qf39_path = $base_path . 'QF39/';
        if (is_dir($qf39_path)) {
            $folders_to_scan['QF39'] = $qf39_path;
        }
    }
    
    if ($folder_filter === 'all' || $folder_filter === 'qf40') {
        $qf40_path = $base_path . 'QF40/';
        if (is_dir($qf40_path)) {
            $folders_to_scan['QF40'] = $qf40_path;
        }
    }
    
    foreach ($folders_to_scan as $folder_type => $folder_path) {
        if (is_dir($folder_path)) {
            $queue_folders = scandir($folder_path);
            foreach ($queue_folders as $queue_folder) {
                if ($queue_folder !== '.' && $queue_folder !== '..' && is_dir($folder_path . $queue_folder)) {
                    // Check if queue number matches search
                    if (!empty($search) && stripos($queue_folder, $search) === false) {
                        continue;
                    }
                    
                    $queue_path = $folder_path . $queue_folder . '/';
                    $files = scandir($queue_path);
                    
                    $file_info = [];
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && is_file($queue_path . $file)) {
                            $file_path = $queue_path . $file;
                            $file_info[] = [
                                'name' => $file,
                                'size' => filesize($file_path),
                                'modified' => filemtime($file_path),
                                'path' => $file_path,
                                'type' => pathinfo($file, PATHINFO_EXTENSION)
                            ];
                        }
                    }
                    
                    if (!empty($file_info)) {
                        $structure[$folder_type][$queue_folder] = $file_info;
                    }
                }
            }
        }
    }
    
    return $structure;
}

$folder_structure = getFolderStructure($base_path, $selected_year, $selected_folder, $search_term);

// Get statistics
function getStatistics($structure) {
    $stats = [
        'total_queues' => 0,
        'total_files' => 0,
        'total_size' => 0,
        'qf39_queues' => 0,
        'qf40_queues' => 0,
        'qf39_files' => 0,
        'qf40_files' => 0
    ];
    
    foreach ($structure as $folder_type => $queues) {
        foreach ($queues as $queue => $files) {
            $stats['total_queues']++;
            $stats['total_files'] += count($files);
            
            if ($folder_type === 'QF39') {
                $stats['qf39_queues']++;
                $stats['qf39_files'] += count($files);
            } elseif ($folder_type === 'QF40') {
                $stats['qf40_queues']++;
                $stats['qf40_files'] += count($files);
            }
            
            foreach ($files as $file) {
                $stats['total_size'] += $file['size'];
            }
        }
    }
    
    return $stats;
}

$statistics = getStatistics($folder_structure);

$page_title = 'Folder Explorer';
$active_page = 'explorer';
include 'auth_header.php';
?>

<style>
.explorer-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.stats-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
}

.folder-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.folder-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f0f0f0;
}

.folder-icon {
    font-size: 1.5rem;
    margin-right: 0.5rem;
}

.queue-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #667eea;
    transition: all 0.2s;
}

.queue-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.file-list {
    margin-top: 0.5rem;
}

.file-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 0.5rem;
    background: white;
    border-radius: 5px;
    margin-bottom: 0.25rem;
    border: 1px solid #dee2e6;
}

.file-name {
    flex: 1;
    font-weight: 500;
}

.file-info {
    font-size: 0.875rem;
    color: #6c757d;
}

.btn-download {
    background: #28a745;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    border-radius: 5px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-download:hover {
    background: #218838;
}

.filter-section {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.year-badge {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="explorer-header">
    <div class="container">
        <h1><i class="bi bi-folder2-open me-3"></i>Folder Explorer</h1>
        <p class="mb-0">Browse and manage QF-39 and QF-40 documents organized by year and queue number</p>
    </div>
</div>

<div class="container">
    <!-- Statistics Section -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['total_queues']; ?></div>
                <div class="text-muted">Total Queues</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['total_files']; ?></div>
                <div class="text-muted">Total Files</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo number_format($statistics['total_size'] / 1024 / 1024, 2); ?> MB</div>
                <div class="text-muted">Total Size</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['qf39_files'] + $statistics['qf40_files']; ?></div>
                <div class="text-muted">Documents</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Folder Type</label>
                <select name="folder" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $selected_folder == 'all' ? 'selected' : ''; ?>>All Folders</option>
                    <option value="qf39" <?php echo $selected_folder == 'qf39' ? 'selected' : ''; ?>>QF-39 Only</option>
                    <option value="qf40" <?php echo $selected_folder == 'qf40' ? 'selected' : ''; ?>>QF-40 Only</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search Queue Number</label>
                <input type="text" name="search" class="form-control" placeholder="e.g., PLA-0023" value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- Folder Structure -->
    <?php if (empty($folder_structure)): ?>
        <div class="empty-state">
            <i class="bi bi-folder-x"></i>
            <h4>No Documents Found</h4>
            <p>No QF-39 or QF-40 documents found for the selected criteria.</p>
        </div>
    <?php else: ?>
        <?php foreach ($folder_structure as $folder_type => $queues): ?>
            <div class="folder-card">
                <div class="folder-header">
                    <div class="folder-icon">
                        <?php if ($folder_type === 'QF39'): ?>
                            <i class="bi bi-file-earmark-text text-primary"></i>
                        <?php else: ?>
                            <i class="bi bi-award text-success"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo $folder_type; ?> Documents</h4>
                        <small class="text-muted"><?php echo count($queues); ?> queue folders</small>
                    </div>
                    <div class="ms-auto">
                        <span class="year-badge"><?php echo $selected_year; ?></span>
                    </div>
                </div>
                
                <?php foreach ($queues as $queue_number => $files): ?>
                    <div class="queue-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">
                                <i class="bi bi-folder-fill me-2"></i>
                                <?php echo htmlspecialchars($queue_number); ?>
                            </h5>
                            <small class="text-muted"><?php echo count($files); ?> files</small>
                        </div>
                        
                        <div class="file-list">
                            <?php foreach ($files as $file): ?>
                                <div class="file-item">
                                    <div class="file-name">
                                        <?php
                                        $icon = 'bi-file-earmark';
                                        if ($file['type'] === 'pdf') $icon = 'bi-file-earmark-pdf';
                                        elseif ($file['type'] === 'docx') $icon = 'bi-file-earmark-word';
                                        ?>
                                        <i class="bi <?php echo $icon; ?> me-2"></i>
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </div>
                                    <div class="file-info me-3">
                                        <?php echo number_format($file['size'] / 1024, 2); ?> KB | 
                                        <?php echo date('M d, Y H:i', $file['modified']); ?>
                                    </div>
                                    <button class="btn-download" onclick="downloadFile('<?php echo $folder_type; ?>', '<?php echo $queue_number; ?>', '<?php echo htmlspecialchars($file['name']); ?>')">
                                        <i class="bi bi-download"></i> Download
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function downloadFile(folderType, queueNumber, fileName) {
    // Use secure download handler
    const downloadUrl = `download-explorer-file.php?folder=${folderType}&queue=${queueNumber}&file=${encodeURIComponent(fileName)}`;
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.style.display = 'none';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto-refresh every 30 seconds to show new files
setInterval(() => {
    if (confirm('New files may have been added. Refresh the page?')) {
        location.reload();
    }
}, 30000);
</script>

<?php include 'auth_footer.php'; ?>
