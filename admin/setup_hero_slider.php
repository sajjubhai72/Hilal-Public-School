<?php
// Setup Hero Slider - Run this once to create the hero_sliders table
require_once 'includes/auth.php';

$setupMessage = '';
$setupStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    try {
        // Check if table already exists
        $tableExists = $conn->query("SHOW TABLES LIKE 'hero_sliders'")->num_rows > 0;
        
        if ($tableExists) {
            $setupMessage = "Hero sliders table already exists! You can now manage sliders from the Hero Slider menu.";
            $setupStatus = 'warning';
        } else {
            // Read and execute the SQL file
            $sqlFile = '../database/hero_sliders_table.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                
                // Split by semicolon and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !str_starts_with(trim($statement), '--')) {
                        if (!$conn->query($statement)) {
                            throw new Exception("Error executing SQL: " . $conn->error);
                        }
                    }
                }
                
                // Create uploads/sliders directory if it doesn't exist
                $uploadDir = '../uploads/sliders/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception("Failed to create uploads/sliders directory");
                    }
                }
                
                $setupMessage = "Hero slider system has been successfully installed! You can now manage sliders from the Hero Slider menu.";
                $setupStatus = 'success';
                
            } else {
                throw new Exception("SQL file not found: $sqlFile");
            }
        }
        
    } catch (Exception $e) {
        $setupMessage = "Setup failed: " . $e->getMessage();
        $setupStatus = 'error';
    }
}

// Check current status
$tableExists = $conn->query("SHOW TABLES LIKE 'hero_sliders'")->num_rows > 0;
$uploadDirExists = is_dir('../uploads/sliders/');

require_once 'includes/layout_top.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <h1><i class="fas fa-tools me-2"></i>Hero Slider Setup</h1>
                <p class="mb-0">One-time setup for the admin-controlled hero slider system</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <?php if ($setupMessage): ?>
            <div class="alert alert-<?= $setupStatus === 'success' ? 'success' : ($setupStatus === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
                <i class="fas fa-<?= $setupStatus === 'success' ? 'check-circle' : ($setupStatus === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> me-2"></i>
                <?= htmlspecialchars($setupMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Setup Status</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-database fa-2x text-<?= $tableExists ? 'success' : 'muted' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Database Table</h6>
                                    <div class="text-<?= $tableExists ? 'success' : 'muted' ?>">
                                        <?= $tableExists ? 'hero_sliders table exists' : 'Table not created yet' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-folder fa-2x text-<?= $uploadDirExists ? 'success' : 'muted' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Upload Directory</h6>
                                    <div class="text-<?= $uploadDirExists ? 'success' : 'muted' ?>">
                                        <?= $uploadDirExists ? 'uploads/sliders/ exists' : 'Directory not created yet' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($tableExists && $uploadDirExists): ?>
                    <div class="mt-4 p-3 bg-success-subtle border border-success-subtle rounded">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <div>
                                <strong>Setup Complete!</strong>
                                <div class="small">The hero slider system is ready to use.</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="hero_slider.php" class="btn btn-success">
                                <i class="fas fa-images me-2"></i>Manage Hero Sliders
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <h6>What this setup will do:</h6>
                        <ul class="mb-3">
                            <li>Create the <code>hero_sliders</code> database table</li>
                            <li>Insert 3 default sample slides</li>
                            <li>Create the <code>uploads/sliders/</code> directory</li>
                            <li>Enable admin control over homepage hero slider</li>
                        </ul>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to run the setup? This will create database tables and directories.')">
                            <button type="submit" name="run_setup" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>Run Setup
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>How it Works</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <p><strong>After setup:</strong></p>
                        <ol>
                            <li>Go to <strong>Content → Hero Slider</strong> in the admin menu</li>
                            <li>Add, edit, or delete hero slides</li>
                            <li>Upload custom images for each slide</li>
                            <li>Set titles, descriptions, and buttons</li>
                            <li>Control display order and status</li>
                            <li>Changes appear immediately on the homepage</li>
                        </ol>
                        
                        <hr>
                        
                        <p><strong>Features:</strong></p>
                        <ul>
                            <li>✓ Multiple slides with custom images</li>
                            <li>✓ Custom titles and descriptions</li>
                            <li>✓ Two action buttons per slide</li>
                            <li>✓ Badge text support</li>
                            <li>✓ Display order control</li>
                            <li>✓ Active/inactive status</li>
                            <li>✓ Responsive design</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_bottom.php'; ?>