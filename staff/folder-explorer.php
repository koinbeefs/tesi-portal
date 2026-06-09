<?php
/**
 * Folder Explorer - QF39 and QF40 Document Browser
 * TAU-TeSI Portal - Staff Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Require staff login
requireLogin();

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
$base_url = '../';
$active_menu = 'explorer';
include '../includes/auth_header.php';
?>

<style>
:root {
    --tau-green: #006400;
    --tau-green-light: #228B22;
    --tau-green-pale: #e8f5e9;
    --primary-gradient: linear-gradient(135deg, #006400 0%, #228B22 100%);
    --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.explorer-header {
    background: var(--primary-gradient);
    color: white;
    padding: 2rem 2rem;
    margin-bottom: 1.5rem;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 4px 20px rgba(0, 100, 0, 0.1);
    position: relative;
}

.explorer-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
}

.explorer-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

.reload-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.reload-indicator.show {
    opacity: 1;
}

.reload-indicator i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.stats-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1rem;
    border: 1px solid #f1f3f5;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stats-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--tau-green);
    line-height: 1.2;
}

.stats-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 500;
}

.filter-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid #e9ecef;
}

.filter-section .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.folder-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid #e9ecef;
}

.folder-header {
    display: flex;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--tau-green-pale);
}

.folder-icon {
    font-size: 1.5rem;
    margin-right: 0.75rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--tau-green-pale);
}

.folder-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #212529;
}

.folder-header small {
    color: #6c757d;
    font-size: 0.85rem;
}

.queue-item {
    background: #fcfdfe;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid #f1f3f5;
    border-left: 4px solid var(--tau-green-light);
    transition: all 0.2s ease-in-out;
}

.queue-item:hover {
    background: #f8faf8;
    transform: translateX(3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.queue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.queue-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #212529;
}

.queue-header small {
    color: #6c757d;
    font-size: 0.8rem;
}

.file-list {
    margin-top: 0.75rem;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0.85rem;
    background: white;
    border-radius: 6px;
    margin-bottom: 0.4rem;
    border: 1px solid #e9ecef;
    transition: border-color 0.2s, background-color 0.2s;
}

.file-item:hover {
    border-color: var(--tau-green-light);
    background-color: #fafdfa;
}

.file-name {
    flex: 1;
    font-weight: 500;
    color: #495057;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.file-name i {
    font-size: 1.1rem;
}

.file-info {
    font-size: 0.8rem;
    color: #868e96;
    margin-right: 1rem;
}

.btn-download {
    background: var(--tau-green-light);
    color: white;
    border: none;
    padding: 0.35rem 1rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-download:hover {
    background: var(--tau-green);
    transform: translateY(-1px);
}

.btn-download i {
    font-size: 0.85rem;
}

.year-badge {
    background: var(--tau-green);
    color: white;
    padding: 0.3rem 0.85rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #868e96;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: var(--tau-green-pale);
}

.empty-state h4 {
    color: #495057;
    font-weight: 600;
}

@media (max-width: 768px) {
    .explorer-header {
        padding: 1.5rem 1rem;
    }
    
    .explorer-header h1 {
        font-size: 1.5rem;
    }
    
    .stats-card {
        padding: 1rem;
    }
    
    .stats-number {
        font-size: 1.5rem;
    }
    
    .filter-section {
        padding: 1rem;
    }
    
    .folder-card {
        padding: 1rem;
    }
    
    .file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .file-info {
        margin-right: 0;
        margin-bottom: 0.25rem;
    }
    
    .btn-download {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="explorer-header">
    <div class="container">
        <h1 style="color: white;"><i class="bi bi-folder2-open me-3" style="color: white;"></i>Folder Explorer</h1>
        <p class="mb-0">Browse and manage QF-39 and QF-40 documents organized by year and queue number</p>
        <div class="reload-indicator" id="reloadIndicator">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Checking for updates...</span>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Section -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['total_queues']; ?></div>
                <div class="stats-label">Total Queues</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['total_files']; ?></div>
                <div class="stats-label">Total Files</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo number_format($statistics['total_size'] / 1024 / 1024, 2); ?> MB</div>
                <div class="stats-label">Total Size</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-number"><?php echo $statistics['qf39_files'] + $statistics['qf40_files']; ?></div>
                <div class="stats-label">Documents</div>
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
                        <div class="queue-header">
                            <h5>
                                <i class="bi bi-folder-fill me-2"></i>
                                <?php echo htmlspecialchars($queue_number); ?>
                            </h5>
                            <small><?php echo count($files); ?> files</small>
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
                                        <i class="bi <?php echo $icon; ?>"></i>
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </div>
                                    <div class="file-info">
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

// Silent reload using fingerprint-based polling
let currentFingerprint = null;
const POLL_INTERVAL = 15000; // Check every 15 seconds

async function checkForUpdates() {
    try {
        const response = await fetch('../api/poll-updates.php?type=explorer');
        if (!response.ok) return;
        
        const data = await response.json();
        
        if (currentFingerprint === null) {
            // Initial load - store fingerprint
            currentFingerprint = data.fingerprint;
        } else if (data.fingerprint !== currentFingerprint) {
            // Fingerprint changed - silent reload
            showReloadIndicator();
            currentFingerprint = data.fingerprint;
            
            // Wait a moment for visual feedback, then reload
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    } catch (error) {
        console.error('Poll error:', error);
    }
}

function showReloadIndicator() {
    const indicator = document.getElementById('reloadIndicator');
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
}

// Start polling
setInterval(checkForUpdates, POLL_INTERVAL);
// Initial check after page load
setTimeout(checkForUpdates, 2000);
</script>

<?php include '../includes/auth_footer.php'; ?>
