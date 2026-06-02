<?php
/**
 * Review Queue
 * TAU-TeSI Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$staff_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get applications requiring review
$apps_stmt = $conn->prepare("
    SELECT a.*,
           u.full_name as assigned_staff_name,
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count,
           (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') as unread_messages,
           DATEDIFF(NOW(), a.submission_timestamp) as days_pending
    FROM applications a
    LEFT JOIN users u ON a.assigned_staff_id = u.user_id
    WHERE a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED')
    ORDER BY a.submission_timestamp ASC
");
$apps_stmt->execute();
$applications = $apps_stmt->get_result();

// Get stats
$temp_apps = $applications->fetch_all(MYSQLI_ASSOC);
$urgent_count = 0;
$total_days = 0;
foreach ($temp_apps as $app) {
    if ($app['days_pending'] > 7)
        $urgent_count++;
    $total_days += $app['days_pending'];
}
$avg_days = count($temp_apps) > 0 ? round($total_days / count($temp_apps), 1) : 0;

closeDBConnection($conn);

$page_title = 'Review Queue';
$base_url = '../';
$active_menu = 'review';
include '../includes/auth_header.php';
?>

<style>
/* Section Card Design */
.section-card {
    border: none;
    border-radius: 16px;
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.section-card .card-header {
    background: white;
    border-bottom: 2px solid #f8f9fa;
    padding: 1.5rem 2rem;
    font-weight: 700;
    color: var(--tau-green-dark);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.1rem;
}

.info-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-weight: 600;
    color: #333;
    margin-bottom: 0;
}

.clickable-card {
    cursor: pointer;
    transition: all 0.2s ease;
}

.clickable-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 35px rgba(0,0,0,0.15);
}

/* Layout Toggle Styles */
.layout-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.layout-toggle .btn {
    border: 2px solid #e9ecef;
    background: white;
    color: #6c757d;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.layout-toggle .btn:hover {
    border-color: var(--tau-green-primary, #006400);
    color: var(--tau-green-primary, #006400);
}

.layout-toggle .btn.active {
    background: var(--tau-green-primary, #006400);
    border-color: var(--tau-green-primary, #006400);
    color: white;
    box-shadow: 0 2px 8px rgba(0, 100, 0, 0.2);
}

/* List View Styles */
.review-queue-list .section-card {
    display: none;
}

.review-queue-list .list-view-item {
    display: block !important;
}

.list-view-item {
    border: none;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.list-view-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.list-view-item .row {
    align-items: center;
}

.list-view-item .queue-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.list-view-item .queue-info i {
    color: var(--tau-green-primary);
    font-size: 1.1rem;
}

.list-view-item .queue-details strong {
    color: #333;
    font-size: 0.9rem;
}

.list-view-item .queue-details small {
    color: #666;
    font-size: 0.8rem;
}

.list-view-item .research-title {
    color: #555;
    font-size: 0.85rem;
    line-height: 1.3;
}

.list-view-item .status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.list-view-item .actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

.list-view-item .action-buttons {
    display: flex;
    gap: 0.5rem;
}

.list-view-item .assignment-info {
    display: flex;
    gap: 0.5rem;
}

.list-view-item .time-info {
    font-size: 0.8rem;
    color: #888;
    white-space: nowrap;
}

.list-view-item .unread-indicator {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}

.review-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.review-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.review-card.urgent {
    border-left: 4px solid #DC3545 !important;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
}

.review-card.high-priority {
    border-left: 4px solid #FFC107 !important;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 193, 7, 0.02));
}

.review-card .card-header {
    border-radius: 0.375rem 0.375rem 0 0 !important;
}

.review-card .card-footer {
    border-radius: 0 0 0.375rem 0.375rem !important;
}

.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.priority-badge {
    position: absolute;
    top: -8px;
    right: 10px;
    z-index: 10;
}

@media (max-width: 768px) {
    .section-card {
        margin-bottom: 1rem;
    }

    .section-card .card-header {
        padding: 0.5rem !important;
    }

    .section-card .card-body {
        padding: 0.5rem !important;
    }

    .list-view-item .col-md-3,
    .list-view-item .col-md-4,
    .list-view-item .col-md-2,
    .list-view-item .col-md-3 {
        padding: 0.25rem;
    }

    .list-view-item .queue-info {
        gap: 0.5rem;
    }

    .list-view-item .queue-info i {
        font-size: 1rem;
    }

    .list-view-item .research-title {
        font-size: 0.8rem;
        line-height: 1.2;
    }

    .list-view-item .actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .list-view-item .action-buttons {
        width: 100%;
        justify-content: space-between;
    }

    .list-view-item .time-info {
        font-size: 0.75rem;
        align-self: flex-end;
    }

    .review-card {
        margin-bottom: 1rem;
    }

    .review-card .card-header {
        padding: 0.5rem !important;
    }

    .review-card .card-body {
        padding: 0.5rem !important;
    }

    .priority-badge {
        top: -6px;
        right: 8px;
        font-size: 0.65rem;
    }
}
</style>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-list-check"></i> Review Queue
    </h2>

    <!-- Queue Statistics -->
    <div class="section-card mb-4" style="color: #006400; font-weight: 700;">
        <div class="card-header">
            <i class="bi bi-bar-chart"></i> Queue Statistics
        </div>
        <div class="card-body p-4">
            <div class="alert alert-info border-0 shadow-sm mb-3">
                <i class="bi bi-info-circle"></i> Applications are sorted by submission date (oldest first). Priority is given to applications with longer wait times.
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-hourglass-split text-primary"></i>
                                <h5 class="text-primary"><?php echo count($temp_apps); ?></h5>
                                <small class="text-muted fw-medium">Total in Queue</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                                <h5 class="text-danger"><?php echo $urgent_count; ?></h5>
                                <small class="text-muted fw-medium">Urgent (>7 days)</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-clock-history text-info"></i>
                                <h5 class="text-info"><?php echo $avg_days; ?> days</h5>
                                <small class="text-muted fw-medium">Avg. Wait Time</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Queue -->
    <div class="section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2" style="color: #006400; font-weight: 700;">
                <i class="bi bi-list-check"></i>
                <span class="fw-bold">Review Queue</span>
                <span class="badge bg-primary"><?php echo count($temp_apps); ?> applications</span>
            </div>
            <!-- Layout Toggle -->
            <div class="layout-toggle">
                <button type="button" class="btn layout-btn active" data-layout="cards">
                    <i class="bi bi-grid"></i> Cards
                </button>
                <button type="button" class="btn layout-btn" data-layout="list">
                    <i class="bi bi-list"></i> List
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="review-queue-container">
                <div class="row g-4" id="review-queue-grid">
                    <?php if (count($temp_apps) > 0): ?>
                        <?php foreach ($temp_apps as $index => $app):
        $priority_class = '';
        $priority_badge = '';
        if ($app['days_pending'] > 7) {
            $priority_class = 'urgent';
            $priority_badge = '<span class="badge bg-danger priority-badge">URGENT</span>';
        }
        elseif ($app['days_pending'] > 3) {
            $priority_class = 'high-priority';
            $priority_badge = '<span class="badge bg-warning priority-badge">HIGH</span>';
        }
        else {
            $priority_badge = '<span class="badge bg-secondary priority-badge">NORMAL</span>';
        }
?>
                            <div class="col-xl-4 col-lg-6 col-md-6">
                                <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="text-decoration-none">
                                    <div class="section-card h-100 clickable-card review-card <?php echo $priority_class; ?> position-relative">
                                        <?php echo $priority_badge; ?>

                                        <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none;">
                                            <div class="row g-2 align-items-start">
                                                <div class="col-7">
                                                    <div class="info-label opacity-75 mb-1" style="color: rgba(255,255,255,0.8); font-size: 1.2rem;">
                                                        <i class="bi bi-ticket-detailed me-1"></i><?php echo htmlspecialchars($app['queue_number']); ?>
                                                    </div>
                                                    <small class="opacity-75" style="font-size: 0.90rem;">
                                                        <i class="bi bi-calendar-event me-1"></i><?php echo formatDate($app['submission_timestamp']); ?>
                                                    </small>
                                                </div>
                                                <div class="col-5 text-end">
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> mb-1 small text-truncate d-block" style="max-width: 100%; font-size: 0.7rem;" title="<?php echo getStatusDisplayName($app['current_status']); ?>">
                                                        <?php
        $short_status = [
            'INTENT_RECEIVED' => 'Intent Rcvd',
            'REQUIREMENTS_SENT' => 'Req. Sent',
            'REQUIREMENTS_PENDING' => 'Req. Pending',
            'UNDER_AUTO_REVIEW' => 'Auto Review',
            'STAFF_REVIEW_REQUIRED' => 'Staff Review',
            'REQUIREMENTS_INCOMPLETE' => 'Incomplete',
            'REGISTERED' => 'Registered',
            'UNDER_STAFF_REVIEW' => 'Under Review',
            'REVISIONS_REQUIRED' => 'Revisions',
            'CATEGORIZED' => 'Categorized',
            'FORWARDED_FOR_TESTING' => 'To Testing',
            'UNDER_SIMILARITY_TESTING' => 'Testing',
            'COMPLIANCE_PENDING' => 'Compliance',
            'COMPLIANCE_REVIEW' => 'Compliance Review',
            'APPROVED' => 'Approved',
            'CERTIFICATE_ISSUED' => 'Certified',
            'REJECTED' => 'Rejected'
        ];
        echo $short_status[$app['current_status']] ?? substr(getStatusDisplayName($app['current_status']), 0, 12) . '...';
?>
                                                    </span>
                                                    <?php if ($app['category']): ?>
                                                        <small class="badge bg-light text-dark small d-block" style="font-size: 0.65rem;"><?php echo ucfirst($app['category']); ?> Review</small>
                                                    <?php
        endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card-body p-3">
                                            <!-- Applicant Info -->
                                            <div class="mb-3">
                                                <div class="info-label">Applicant</div>
                                                <div class="info-value small mb-1"><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($app['applicant_email']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-person me-1"></i><?php echo ucfirst($app['applicant_type']); ?>
                                                </div>
                                            </div>

                                            <!-- Research Title -->
                                            <div class="mb-3">
                                                <div class="info-label">Research Title</div>
                                                <div class="info-value small text-truncate" title="<?php echo htmlspecialchars($app['research_title']); ?>" style="max-width: 100%;">
                                                    <?php echo htmlspecialchars(substr($app['research_title'], 0, 50) . (strlen($app['research_title']) > 50 ? '...' : '')); ?>
                                                </div>
                                            </div>

                                            <!-- Status & Assignment -->
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="text-center p-2 bg-light rounded small">
                                                        <i class="bi bi-person-check d-block text-primary mb-1" style="font-size: 1rem;"></i>
                                                        <div class="info-label text-center mb-1" style="font-size: 0.65rem;">Assigned</div>
                                                        <?php if ($app['assigned_staff_name']): ?>
                                                            <div class="fw-bold text-truncate small" style="max-width: 100px; margin: 0 auto;" title="<?php echo htmlspecialchars($app['assigned_staff_name']); ?>"><?php echo htmlspecialchars(substr($app['assigned_staff_name'], 0, 10) . (strlen($app['assigned_staff_name']) > 10 ? '...' : '')); ?></div>
                                                        <?php
        else: ?>
                                                            <div class="text-warning small">Unassigned</div>
                                                        <?php
        endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center p-2 bg-light rounded small">
                                                        <i class="bi bi-file-earmark d-block text-info mb-1" style="font-size: 1rem;"></i>
                                                        <div class="info-label text-center mb-1" style="font-size: 0.65rem;">Documents</div>
                                                        <span class="badge bg-info small"><?php echo $app['doc_count']; ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Messages & Updates -->
                                            <div class="d-flex justify-content-between align-items-center small mb-3">
                                                <div>
                                                    <?php if ($app['unread_messages'] > 0): ?>
                                                        <small class="text-danger"><i class="bi bi-envelope-exclamation me-1"></i><?php echo $app['unread_messages']; ?> unread messages</small>
                                                    <?php
        else: ?>
                                                        <small class="text-muted"><i class="bi bi-envelope me-1"></i>No new messages</small>
                                                    <?php
        endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i><?php echo $app['days_pending']; ?> days pending
                                                </small>
                                            </div>

                                            <!-- Additional Info -->
                                            <?php if ($app['has_additional_requirements'] || $app['completion_attempts'] > 0): ?>
                                                <div class="mb-3">
                                                    <?php if ($app['has_additional_requirements']): ?>
                                                        <span class="badge bg-warning text-dark me-1 small"><i class="bi bi-exclamation-triangle me-1"></i>Additional Req.</span>
                                                    <?php
            endif; ?>
                                                    <?php if ($app['completion_attempts'] > 0): ?>
                                                        <span class="badge bg-secondary small"><i class="bi bi-arrow-repeat me-1"></i><?php echo $app['completion_attempts']; ?> attempts</span>
                                                    <?php
            endif; ?>
                                                </div>
                                            <?php
        endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- List View Item -->
                            <div class="list-view-item d-none">
                                <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="text-decoration-none text-dark">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="queue-info">
                                                <i class="bi bi-ticket-detailed"></i>
                                                <div class="queue-details">
                                                    <strong><?php echo htmlspecialchars($app['queue_number']); ?></strong>
                                                    <br>
                                                    <small><?php echo htmlspecialchars($app['applicant_name']); ?></small>
                                                    <?php if ($app['unread_messages'] > 0): ?>
                                                        <br><span class="unread-indicator"><?php echo $app['unread_messages']; ?> new</span>
                                                    <?php
        endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="research-title text-truncate" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                                <?php echo htmlspecialchars(substr($app['research_title'], 0, 60) . (strlen($app['research_title']) > 60 ? '...' : '')); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="badge status-badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?>">
                                                <?php echo $short_status[$app['current_status']] ?? substr(getStatusDisplayName($app['current_status']), 0, 12) . '...'; ?>
                                            </span>
                                            <?php if ($app['category']): ?>
                                                <br><small class="text-muted"><?php echo ucfirst($app['category']); ?> Review</small>
                                            <?php
        endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="actions">
                                                <div class="assignment-info">
                                                    <?php if ($app['assigned_staff_name']): ?>
                                                        <small class="text-muted"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars(substr($app['assigned_staff_name'], 0, 8) . (strlen($app['assigned_staff_name']) > 8 ? '...' : '')); ?></small>
                                                    <?php
        else: ?>
                                                        <small class="text-warning"><i class="bi bi-person-dash"></i> Unassigned</small>
                                                    <?php
        endif; ?>
                                                </div>
                                                <div class="time-info">
                                                    <i class="bi bi-clock"></i> <?php echo $app['days_pending']; ?> days
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php
    endforeach; ?>
                    <?php
else: ?>
                        <div class="col-12">
                            <div class="text-center text-muted py-5" id="no-applications">
                                <i class="bi bi-check-circle display-4 text-success"></i>
                                <p class="mt-3 h5">All applications have been reviewed!</p>
                                <p class="text-muted">No applications are currently waiting for review.</p>
                            </div>
                        </div>
                    <?php
endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Layout Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize layout from localStorage or default to 'cards'
    const savedLayout = localStorage.getItem('review_queue_layout') || 'cards';
    setLayout(savedLayout);

    // Handle layout toggle button clicks
    document.querySelectorAll('.layout-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const layout = this.getAttribute('data-layout');
            setLayout(layout);
            localStorage.setItem('review_queue_layout', layout);
        });
    });
});

function setLayout(layout) {
    const container = document.getElementById('review-queue-container');

    // Update button states
    document.querySelectorAll('.layout-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-layout="${layout}"]`).classList.add('active');

    // Apply layout class and show/hide appropriate items
    if (layout === 'list') {
        container.classList.add('review-queue-list');
    } else {
        container.classList.remove('review-queue-list');
    }

    // Save preference
    localStorage.setItem('review_queue_layout', layout);
}
</script>

<?php include '../includes/auth_footer.php'; ?>
