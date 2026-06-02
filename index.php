<?php
/**
 * Homepage - Public Progress Tracking
 * TAU-TeSI Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$page_title = 'Home';
$active_page = 'home';
include 'includes/header.php';
?>

    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); position: relative; overflow: hidden; padding: 80px 0;">
        <div style="position: absolute; inset: 0; opacity: 0.1; background-image: radial-gradient(#fff 2px, transparent 2px); background-size: 30px 30px;"></div>
        <div class="container" style="max-width: 1200px; position: relative; z-index: 1;">
            <div class="row align-items-center">
                <div class="col-lg-6 text-white">
                    <h1 style="font-size: 56px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; color: white;">Turnitin Similarity Index (TeSI)</h1>
                    <p style="font-size: 20px; opacity: 0.9; margin-bottom: 30px;">Tarlac Agricultural University - DRD Portal. <br>Fast, accurate, and reliable similarity testing for academic excellence.</p>
                    <div class="d-flex gap-3">
                        <a href="submit-requirements.php" class="btn btn-light btn-lg px-4 fw-bold" style="color: #006400; border-radius: 10px;">
                            <i class="bi bi-file-earmark-plus"></i> Submit Requirements
                        </a>
                        <a href="track-application.php" class="btn btn-outline-light btn-lg px-4 fw-bold" style="border-radius: 10px;">
                            <i class="bi bi-search"></i> Track Progress
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1">
                    <div style="background: white; border-radius: 20px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                        <h3 style="color: #006400; font-weight: 700; margin-bottom: 20px;">Quick Search</h3>
                        <form action="track-application.php" method="POST">
                            <div class="mb-4">
                                <label class="form-label text-muted small fw-bold">Enter Queue Number</label>
                                <div class="input-group">
                                    <span class="input-group-text border-end-0 bg-light">PLA-</span>
                                    <input type="text" name="queue_number" class="form-control border-start-0 bg-light" placeholder="0001" required>
                                </div>
                            </div>
                            <button type="submit" name="track_application" class="btn btn-secondary w-100 py-3 fw-bold" style="border-radius: 10px; background: #006400;">
                                Track Application
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section style="padding: 100px 0; background: #fff;">
        <div class="container" style="max-width: 1200px;">
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="p-4 h-100 rounded-4" style="background: #f8f9fa;">
                        <div class="mb-3 text-success fs-1"><i class="bi bi-file-text"></i></div>
                        <h4 class="fw-bold">Fast Submission</h4>
                        <p class="text-muted">Simple multi-step form to get your research queued for testing in minutes.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 h-100 rounded-4" style="background: #f8f9fa;">
                        <div class="mb-3 text-success fs-1"><i class="bi bi-shield-check"></i></div>
                        <h4 class="fw-bold">Validated Data</h4>
                        <p class="text-muted">Automatic data checks ensure your requirements are complete before staff review.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 h-100 rounded-4" style="background: #f8f9fa;">
                        <div class="mb-3 text-success fs-1"><i class="bi bi-cpu"></i></div>
                        <h4 class="fw-bold">Auto Generation</h4>
                        <p class="text-muted">Forms like QF-39 are automatically filled from your submission, saving you time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
