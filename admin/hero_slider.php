<?php
$pageTitle = 'Hero Slider Management';
require_once 'includes/auth.php';
require_once 'includes/layout_top.php';

// Image optimization function
function optimizeSliderImage($imagePath, $extension) {
    if (!extension_loaded('gd')) {
        return false; // GD extension not available
    }
    
    $maxWidth = 1400;
    $maxHeight = 600;
    $quality = 85;
    
    // Get image dimensions
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) return false;
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Skip optimization if image is already small enough
    if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
        return true;
    }
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // Create image resources
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($imagePath);
            break;
        case 'png':
            $source = imagecreatefrompng($imagePath);
            break;
        case 'webp':
            $source = imagecreatefromwebp($imagePath);
            break;
        default:
            return false;
    }
    
    if (!$source) return false;
    
    // Create new image
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($extension === 'png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Save optimized image
    $result = false;
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($resized, $imagePath, $quality);
            break;
        case 'png':
            $result = imagepng($resized, $imagePath, (int)(9 - ($quality / 10)));
            break;
        case 'webp':
            $result = imagewebp($resized, $imagePath, $quality);
            break;
    }
    
    // Clean up memory
    imagedestroy($source);
    imagedestroy($resized);
    
    return $result;
}

// Generate CSRF token for form protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please try again.";
    } else {
        // Regenerate token after successful submission to prevent reuse
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        if (isset($_POST['add_slider'])) {
        // Add new slider
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $description = trim($_POST['description']);
        $button_text_1 = trim($_POST['button_text_1']);
        $button_link_1 = trim($_POST['button_link_1']);
        $button_text_2 = trim($_POST['button_text_2']);
        $button_link_2 = trim($_POST['button_link_2']);
        $badge_text = trim($_POST['badge_text']);
        $status = $_POST['status'];
        $display_order = (int)$_POST['display_order'];
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/sliders/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['slider_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $error = "Invalid file format. Please upload JPG, PNG, or WebP images.";
            } else {
                $fileName = 'slider_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['slider_image']['tmp_name'], $uploadPath)) {
                    // Optimize image for web (resize and compress)
                    if (optimizeSliderImage($uploadPath, $fileExtension)) {
                        $image_path = $fileName;
                    } else {
                        $image_path = $fileName; // Use original if optimization fails
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
        
        // Use default image if no image uploaded
        if (!$image_path) {
            $image_path = 'default-slider.jpg'; // We'll create a default later
        }
        
        $stmt = $conn->prepare("INSERT INTO hero_sliders (title, subtitle, description, badge_text, button_text_1, button_link_1, button_text_2, button_link_2, image_path, status, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssi", $title, $subtitle, $description, $badge_text, $button_text_1, $button_link_1, $button_text_2, $button_link_2, $image_path, $status, $display_order);
        
        if ($stmt->execute()) {
            $success = "Hero slider added successfully!";
        } else {
            $error = "Failed to add slider: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_slider'])) {
        // Update existing slider
        $slider_id = (int)$_POST['slider_id'];
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $description = trim($_POST['description']);
        $button_text_1 = trim($_POST['button_text_1']);
        $button_link_1 = trim($_POST['button_link_1']);
        $button_text_2 = trim($_POST['button_text_2']);
        $button_link_2 = trim($_POST['button_link_2']);
        $badge_text = trim($_POST['badge_text']);
        $status = $_POST['status'];
        $display_order = (int)$_POST['display_order'];
        
        // Handle image upload
        $current_slider = $conn->query("SELECT image_path FROM hero_sliders WHERE id = $slider_id")->fetch_assoc();
        $image_path = $current_slider['image_path'];
        
        if (isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/sliders/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['slider_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $error = "Invalid file format. Please upload JPG, PNG, or WebP images.";
            } else {
                $fileName = 'slider_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['slider_image']['tmp_name'], $uploadPath)) {
                    // Delete old image if it exists and is not default
                    if ($current_slider['image_path'] && $current_slider['image_path'] !== 'default-slider.jpg' && file_exists($uploadDir . $current_slider['image_path'])) {
                        unlink($uploadDir . $current_slider['image_path']);
                    }
                    
                    // Optimize new image
                    if (optimizeSliderImage($uploadPath, $fileExtension)) {
                        $image_path = $fileName;
                    } else {
                        $image_path = $fileName; // Use original if optimization fails
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
        
        $stmt = $conn->prepare("UPDATE hero_sliders SET title=?, subtitle=?, description=?, badge_text=?, button_text_1=?, button_link_1=?, button_text_2=?, button_link_2=?, image_path=?, status=?, display_order=? WHERE id=?");
        $stmt->bind_param("ssssssssssii", $title, $subtitle, $description, $badge_text, $button_text_1, $button_link_1, $button_text_2, $button_link_2, $image_path, $status, $display_order, $slider_id);
        
        if ($stmt->execute()) {
            $success = "Hero slider updated successfully!";
        } else {
            $error = "Failed to update slider: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_slider'])) {
        $slider_id = (int)$_POST['slider_id'];
        
        // Get image path before deletion
        $slider = $conn->query("SELECT image_path FROM hero_sliders WHERE id = $slider_id")->fetch_assoc();
        
        if ($conn->query("DELETE FROM hero_sliders WHERE id = $slider_id")) {
            // Delete image file if it exists and is not default
            if ($slider['image_path'] && $slider['image_path'] !== 'default-slider.jpg') {
                $imagePath = '../uploads/sliders/' . $slider['image_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $success = "Hero slider deleted successfully!";
        } else {
            $error = "Failed to delete slider: " . $conn->error;
        }
    }
    
    // Redirect to prevent form resubmission (PRG pattern)
    if (isset($success) || isset($error)) {
        $message = isset($success) ? $success : $error;
        $type = isset($success) ? 'success' : 'error';
        
        // Preserve current page state
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $currentSearch = isset($_GET['search']) ? $_GET['search'] : '';
        $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'display_order';
        $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'asc';
        
        // Build redirect URL with current state
        $redirectUrl = 'hero_slider.php?';
        $params = [];
        
        if ($currentPage > 1) $params[] = "page=$currentPage";
        if (!empty($currentSearch)) $params[] = "search=" . urlencode($currentSearch);
        if ($currentSort !== 'display_order') $params[] = "sort=$currentSort";
        if ($currentOrder !== 'asc') $params[] = "order=$currentOrder";
        
        $redirectUrl .= implode('&', $params);
        
        echo "<script>
            sessionStorage.setItem('sliderMessage', '" . addslashes($message) . "');
            sessionStorage.setItem('sliderMessageType', '$type');
            window.location.href = '$redirectUrl';
        </script>";
        exit;
    }
}
}

// Get all sliders with pagination, search, and sorting
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'display_order';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Valid sort columns
$validSortColumns = ['display_order', 'title', 'status', 'created_at', 'updated_at'];
if (!in_array($sortBy, $validSortColumns)) {
    $sortBy = 'display_order';
}

// Build query
$whereCondition = '';
$params = [];
$types = '';

if (!empty($search)) {
    $whereCondition = " WHERE (title LIKE ? OR subtitle LIKE ? OR description LIKE ? OR badge_text LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    $types = 'ssss';
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM hero_sliders" . $whereCondition;
if (!empty($params)) {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalSliders = $countStmt->get_result()->fetch_assoc()['total'];
} else {
    $totalSliders = $conn->query($countSql)->fetch_assoc()['total'];
}

$totalPages = ceil($totalSliders / $limit);

// Get sliders with pagination
$sql = "SELECT * FROM hero_sliders" . $whereCondition . " ORDER BY $sortBy $sortOrder LIMIT ? OFFSET ?";
if (!empty($params)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sliders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $sliders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get slider for editing
$editing_slider = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editing_slider = $conn->query("SELECT * FROM hero_sliders WHERE id = $edit_id")->fetch_assoc();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-images me-2"></i>Hero Slider Management</h1>
                    <p class="mb-0">
                        Manage the hero slider on your school's homepage
                        <?php if ($totalSliders > 0): ?>
                            <span class="text-muted">• <?= $totalSliders ?> slide<?= $totalSliders !== 1 ? 's' : '' ?> total</span>
                        <?php endif; ?>
                    </p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSliderModal">
                    <i class="fas fa-plus me-2"></i>Add New Slide
                </button>
            </div>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search slides by title, subtitle, or description...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="display_order" <?= $sortBy === 'display_order' ? 'selected' : '' ?>>Sort by Order</option>
                                <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Sort by Title</option>
                                <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Sort by Status</option>
                                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Sort by Date Created</option>
                                <option value="updated_at" <?= $sortBy === 'updated_at' ? 'selected' : '' ?>>Sort by Last Updated</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="order" onchange="this.form.submit()">
                                <option value="asc" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>
                                    <?= $sortBy === 'display_order' ? 'Low to High' : ($sortBy === 'title' ? 'A to Z' : 'Oldest First') ?>
                                </option>
                                <option value="desc" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>
                                    <?= $sortBy === 'display_order' ? 'High to Low' : ($sortBy === 'title' ? 'Z to A' : 'Newest First') ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <?php if (!empty($search) || $sortBy !== 'display_order' || $sortOrder !== 'ASC'): ?>
                                <a href="hero_slider.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Reset
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <div id="alertContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Current Slides</h5>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-info">
                            <i class="fas fa-search me-1"></i>
                            Search: "<?= htmlspecialchars($search) ?>" (<?= $totalSliders ?> result<?= $totalSliders !== 1 ? 's' : '' ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($totalSliders === 0): ?>
                        <?php if (!empty($search)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search text-muted" style="font-size: 3rem;"></i>
                            <h4 class="text-muted mt-3">No Results Found</h4>
                            <p class="text-muted">No slides match your search criteria.</p>
                            <a href="hero_slider.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>View All Slides
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-images text-muted" style="font-size: 4rem;"></i>
                            <h4 class="text-muted mt-3">No Slides Added Yet</h4>
                            <p class="text-muted">Add your first hero slide to get started</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSliderModal">
                                <i class="fas fa-plus me-2"></i>Add First Slide
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="60">
                                        <a href="?search=<?= urlencode($search) ?>&sort=display_order&order=<?= $sortBy === 'display_order' && $sortOrder === 'ASC' ? 'desc' : 'asc' ?>" 
                                           class="text-decoration-none text-dark d-flex align-items-center">
                                            Order
                                            <?php if ($sortBy === 'display_order'): ?>
                                                <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ms-1 text-muted"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th width="100">Image</th>
                                    <th>
                                        <a href="?search=<?= urlencode($search) ?>&sort=title&order=<?= $sortBy === 'title' && $sortOrder === 'ASC' ? 'desc' : 'asc' ?>" 
                                           class="text-decoration-none text-dark d-flex align-items-center">
                                            Title & Content
                                            <?php if ($sortBy === 'title'): ?>
                                                <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ms-1 text-muted"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th width="100">
                                        <a href="?search=<?= urlencode($search) ?>&sort=status&order=<?= $sortBy === 'status' && $sortOrder === 'ASC' ? 'desc' : 'asc' ?>" 
                                           class="text-decoration-none text-dark d-flex align-items-center">
                                            Status
                                            <?php if ($sortBy === 'status'): ?>
                                                <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ms-1 text-muted"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th width="120">
                                        <a href="?search=<?= urlencode($search) ?>&sort=updated_at&order=<?= $sortBy === 'updated_at' && $sortOrder === 'ASC' ? 'desc' : 'asc' ?>" 
                                           class="text-decoration-none text-dark d-flex align-items-center">
                                            Last Updated
                                            <?php if ($sortBy === 'updated_at'): ?>
                                                <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ms-1 text-muted"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sliders as $slider): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= $slider['display_order'] ?></span>
                                    </td>
                                    <td>
                                        <div class="slider-image-preview" style="width: 80px; height: 50px; position: relative; background: #f8f9fa; border-radius: 4px; overflow: hidden;">
                                            <img src="../uploads/sliders/<?= $slider['image_path'] ?>" 
                                                 alt="Slider" 
                                                 class="img-thumbnail slider-preview-img" 
                                                 style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s ease;"
                                                 loading="lazy"
                                                 onload="this.style.opacity='1';"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="image-fallback" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none; align-items: center; justify-content: center; background: #e9ecef; color: #6c757d; font-size: 10px; text-align: center;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($slider['title']) ?></strong>
                                            <?php if ($slider['badge_text']): ?>
                                                <span class="badge bg-info ms-2"><?= htmlspecialchars($slider['badge_text']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($slider['subtitle']): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($slider['subtitle']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($slider['description']): ?>
                                            <div class="text-muted small mt-1"><?= htmlspecialchars(substr($slider['description'], 0, 100)) ?><?= strlen($slider['description']) > 100 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $slider['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($slider['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('M j, Y', strtotime($slider['updated_at'])) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= date('g:i A', strtotime($slider['updated_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="hero_slider.php?edit=<?= $slider['id'] ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>&page=<?= $page ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSlider(<?= $slider['id'] ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalSliders) ?> of <?= $totalSliders ?> slides
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <?php if ($i == $page): ?>
                                            <span class="page-link"><?= $i ?></span>
                                        <?php else: ?>
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>"><?= $totalPages ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Next Page -->
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= strtolower($sortOrder) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Slider Modal -->
<div class="modal fade" id="addSliderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-<?= $editing_slider ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editing_slider ? 'Edit' : 'Add New' ?> Hero Slide
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php if ($editing_slider): ?>
                        <input type="hidden" name="slider_id" value="<?= $editing_slider['id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Slide Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?= htmlspecialchars($editing_slider['title'] ?? '') ?>" 
                                   placeholder="e.g. Welcome to Hilal Public School" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" 
                                   value="<?= $editing_slider['display_order'] ?? 1 ?>" 
                                   min="1" max="10">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Subtitle</label>
                            <input type="text" class="form-control" name="subtitle" 
                                   value="<?= htmlspecialchars($editing_slider['subtitle'] ?? '') ?>" 
                                   placeholder="e.g. Quality Education For Every Child">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge Text</label>
                            <input type="text" class="form-control" name="badge_text" 
                                   value="<?= htmlspecialchars($editing_slider['badge_text'] ?? '') ?>" 
                                   placeholder="e.g. Est. 2052 BS">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Brief description for the slide"><?= htmlspecialchars($editing_slider['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Button 1 Text</label>
                            <input type="text" class="form-control" name="button_text_1" 
                                   value="<?= htmlspecialchars($editing_slider['button_text_1'] ?? '') ?>" 
                                   placeholder="e.g. Learn More">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button 1 Link</label>
                            <input type="text" class="form-control" name="button_link_1" 
                                   value="<?= htmlspecialchars($editing_slider['button_link_1'] ?? '') ?>" 
                                   placeholder="e.g. about or https://example.com">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Button 2 Text</label>
                            <input type="text" class="form-control" name="button_text_2" 
                                   value="<?= htmlspecialchars($editing_slider['button_text_2'] ?? '') ?>" 
                                   placeholder="e.g. Apply Now">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button 2 Link</label>
                            <input type="text" class="form-control" name="button_link_2" 
                                   value="<?= htmlspecialchars($editing_slider['button_link_2'] ?? '') ?>" 
                                   placeholder="e.g. admissions">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Slider Image</label>
                            <input type="file" class="form-control" name="slider_image" 
                                   accept="image/jpeg,image/jpg,image/png,image/webp" 
                                   <?= !$editing_slider ? 'required' : '' ?>
                                   onchange="previewSliderImage(this)">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Recommended:</strong> 1400x600px or larger, JPG/PNG/WebP, max 5MB
                                <br><small class="text-muted">Images will be automatically optimized for web performance</small>
                            </div>
                            <div id="imagePreview" class="mt-2" style="display: none;">
                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                    <img id="previewImg" class="me-2" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <div>
                                        <div id="fileName" class="small fw-semibold"></div>
                                        <div id="fileSize" class="small text-muted"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active" <?= ($editing_slider['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($editing_slider['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <?php if ($editing_slider && $editing_slider['image_path']): ?>
                        <div class="col-12">
                            <label class="form-label">Current Image</label>
                            <div class="current-image-preview" style="position: relative;">
                                <div class="image-loading" style="display: flex; align-items: center; justify-content: center; height: 200px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                                <img src="../uploads/sliders/<?= $editing_slider['image_path'] ?>" 
                                     alt="Current slider" 
                                     class="img-thumbnail current-slider-img" 
                                     style="max-height: 200px; display: none; transition: opacity 0.3s ease;"
                                     loading="lazy"
                                     onload="this.style.display='block'; this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                     onerror="this.style.display='none'; this.previousElementSibling.innerHTML='<i class=\'fas fa-exclamation-triangle text-warning\'></i><div class=\'mt-2 text-muted small\'>Image not found</div>';">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="<?= $editing_slider ? 'update_slider' : 'add_slider' ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?= $editing_slider ? 'Update' : 'Add' ?> Slide
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-trash-alt text-danger" style="font-size: 3rem;"></i>
                </div>
                <h6 class="text-center mb-3">Are you sure you want to delete this hero slide?</h6>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action is permanent and cannot be undone. The slide image will also be deleted from the server.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirmFinalDelete();">
                    <input type="hidden" name="slider_id" id="deleteSliderIdInput">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="delete_slider" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Yes, Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmFinalDelete() {
    // Final confirmation before actual deletion
    const confirmed = confirm('FINAL CONFIRMATION: This will permanently delete the hero slide. Are you absolutely sure?');
    if (confirmed) {
        // Show loading state
        const submitBtn = event.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
            submitBtn.disabled = true;
        }
    }
    return confirmed;
}
</script>

<script>
// Show alert messages from session storage
document.addEventListener('DOMContentLoaded', function() {
    const message = sessionStorage.getItem('sliderMessage');
    const type = sessionStorage.getItem('sliderMessageType');
    
    if (message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertIcon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        document.getElementById('alertContainer').innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show">
                <i class="fas ${alertIcon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Clear the messages
        sessionStorage.removeItem('sliderMessage');
        sessionStorage.removeItem('sliderMessageType');
    }
    
    // Show edit modal if editing
    <?php if ($editing_slider): ?>
        new bootstrap.Modal(document.getElementById('addSliderModal')).show();
    <?php endif; ?>
    
    // Initialize image loading optimization
    initImageLoading();
});

function deleteSlider(sliderId) {
    // Double confirmation to prevent accidental deletions
    if (confirm('Are you sure you want to delete this hero slide? This action cannot be undone.')) {
        document.getElementById('deleteSliderIdInput').value = sliderId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
}

// Prevent form resubmission on page reload
document.addEventListener('DOMContentLoaded', function() {
    // Disable form resubmission warning
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Page loaded from cache (back/forward button)
            window.location.reload();
        }
    });
    
    // Prevent accidental form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        let submitted = false;
        
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
            
            // Re-enable after 3 seconds as safety measure
            setTimeout(() => {
                submitted = false;
            }, 3000);
        });
    });
    
    // Add loading state to delete button
    const deleteForm = document.querySelector('#deleteModal form');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
                submitBtn.disabled = true;
            }
        });
    }
});

// Optimize image loading to prevent flickering
function initImageLoading() {
    // Set initial opacity to 0 for smooth fade-in
    const previewImages = document.querySelectorAll('.slider-preview-img');
    previewImages.forEach(img => {
        img.style.opacity = '0';
        
        // If image is already loaded, show it immediately
        if (img.complete && img.naturalHeight !== 0) {
            img.style.opacity = '1';
        }
    });
    
    // Handle file input preview
    const fileInput = document.querySelector('input[name="slider_image"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show file name and size
                const fileInfo = document.createElement('div');
                fileInfo.className = 'mt-2 small text-muted';
                fileInfo.innerHTML = `
                    <i class="fas fa-file-image me-1"></i>
                    ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
                `;
                
                // Remove existing file info
                const existingInfo = fileInput.parentNode.querySelector('.mt-2.small.text-muted');
                if (existingInfo) existingInfo.remove();
                
                // Add new file info
                fileInput.parentNode.appendChild(fileInfo);
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    fileInfo.innerHTML = `
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                        File too large (${(file.size / 1024 / 1024).toFixed(2)} MB). Max 5MB recommended.
                    `;
                    fileInfo.className = 'mt-2 small text-warning';
                }
            }
        });
    }
}

// Lazy load images when they come into view
function lazyLoadImages() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
}

// Call lazy loading on page load
document.addEventListener('DOMContentLoaded', lazyLoadImages);

// Enhanced search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const searchForm = searchInput?.closest('form');
    let searchTimeout;
    
    if (searchInput && searchForm) {
        // Auto-search with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchForm.submit();
            }, 1000); // Search after 1 second of no typing
        });
        
        // Clear search on Escape key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                searchForm.submit();
            }
        });
        
        // Focus search input with Ctrl+F
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    }
    
    // Table row hover effects
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Show loading state for pagination/sorting links
    const paginationLinks = document.querySelectorAll('.pagination a, th a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            const spinner = '<span class="spinner-border spinner-border-sm me-1"></span>';
            if (!this.innerHTML.includes('spinner-border')) {
                this.innerHTML = spinner + this.innerHTML;
            }
        });
    });
});

// Keyboard shortcuts help
function showKeyboardShortcuts() {
    alert(`Keyboard Shortcuts:
    
Ctrl + F: Focus search box
Escape: Clear search
Enter: Submit search/filter
    
Navigation:
Arrow keys: Navigate table
Tab: Move between controls`);
}

// Add keyboard shortcuts help button
document.addEventListener('DOMContentLoaded', function() {
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader) {
        const helpBtn = document.createElement('button');
        helpBtn.className = 'btn btn-outline-secondary btn-sm ms-2';
        helpBtn.innerHTML = '<i class="fas fa-keyboard"></i>';
        helpBtn.title = 'Keyboard Shortcuts';
        helpBtn.onclick = showKeyboardShortcuts;
        pageHeader.querySelector('div:last-child').appendChild(helpBtn);
    }
});

// Preview image function for file input
function previewSliderImage(input) {
    const file = input.files[0];
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    if (file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, or WebP)');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size too large. Please select an image smaller than 5MB.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            fileName.textContent = file.name;
            fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}
</script>

<style>
/* Optimize image loading styles */
.slider-image-preview {
    background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%),
                linear-gradient(-45deg, #f8f9fa 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f8f9fa 75%),
                linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
    background-size: 10px 10px;
    background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
}

.slider-preview-img {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.current-image-preview .img-thumbnail {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-loading {
    transition: opacity 0.3s ease;
}

/* Prevent layout shift during image loading */
.table img {
    width: 80px !important;
    height: 50px !important;
    object-fit: cover;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

/* Smooth hover effects */
.table tbody tr:hover .slider-image-preview {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}

.table tbody tr .slider-image-preview {
    transition: transform 0.2s ease;
}

/* File upload styles */
input[type="file"] {
    transition: border-color 0.3s ease;
}

input[type="file"]:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

/* Loading spinner optimization */
.spinner-border {
    width: 2rem;
    height: 2rem;
}

/* Image placeholder improvements */
.image-fallback {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px dashed #dee2e6;
}

/* Pagination and Search Enhancements */
.pagination .page-link {
    color: var(--primary);
    border: 1px solid #dee2e6;
    padding: 0.375rem 0.75rem;
    transition: all 0.2s ease;
}

.pagination .page-link:hover {
    color: white;
    background-color: var(--primary);
    border-color: var(--primary);
    transform: translateY(-1px);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary);
    border-color: var(--primary);
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    background-color: #fff;
    border-color: #dee2e6;
}

/* Search and Filter Styles */
.input-group .input-group-text {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

.form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

/* Table Sorting Links */
th a {
    color: inherit !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

th a:hover {
    color: var(--primary) !important;
}

th a i.text-primary {
    color: var(--primary) !important;
}

/* Search Results Badge */
.badge.bg-info {
    background-color: #0dcaf0 !important;
}

/* Loading States */
.spinner-border-sm {
    width: 0.875rem;
    height: 0.875rem;
}

/* Mobile Pagination */
@media (max-width: 768px) {
    .pagination {
        justify-content: center;
    }
    
    .pagination .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    /* Mobile search controls */
    .row.g-3.align-items-center {
        row-gap: 0.75rem;
    }
    
    .col-md-4, .col-md-3, .col-md-2 {
        width: 100%;
    }
}

/* Enhanced table styles */
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Status badges */
.badge.bg-success {
    background-color: var(--primary) !important;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .table img,
    .slider-image-preview {
        width: 60px !important;
        height: 40px !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
}
</style>

<?php require_once 'includes/layout_bottom.php'; ?>