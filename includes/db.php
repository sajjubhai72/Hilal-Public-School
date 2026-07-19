<?php
/* =====================================================
   DATABASE CONNECTION
   Hilal Public Secondary School
   ===================================================== */

require_once __DIR__ . '/config.php';

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hilal_school');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

$conn->set_charset("utf8mb4");

/* =====================================================
   HELPER FUNCTIONS
   ===================================================== */

// Get school setting value
function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM school_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : '';
}

// Calculate Grade from marks
function calculateGrade($obtained, $full) {
    if ($full == 0) return ['grade' => 'NG', 'gpa' => 0.0];
    $percent = ($obtained / $full) * 100;

    if ($percent >= 90) return ['grade' => 'A+', 'gpa' => 4.0];
    if ($percent >= 80) return ['grade' => 'A',  'gpa' => 3.6];
    if ($percent >= 70) return ['grade' => 'B+', 'gpa' => 3.2];
    if ($percent >= 60) return ['grade' => 'B',  'gpa' => 2.8];
    if ($percent >= 50) return ['grade' => 'C+', 'gpa' => 2.4];
    if ($percent >= 40) return ['grade' => 'C',  'gpa' => 2.0];
    if ($percent >= 30) return ['grade' => 'D',  'gpa' => 1.6];
    return ['grade' => 'NG', 'gpa' => 0.0];
}

// Sanitize input
function sanitize($conn, $input) {
    return $conn->real_escape_string(htmlspecialchars(trim($input)));
}

// ── Secure File Upload ────────────────────────────────
/**
 * Securely handle file upload
 * @param array  $file        $_FILES['field']
 * @param string $uploadDir   Absolute path to upload dir (with trailing slash)
 * @param array  $allowedExt  e.g. ['jpg','jpeg','png','webp']
 * @param int    $maxBytes    Max file size in bytes
 * @param string $prefix      Filename prefix e.g. 'teacher_'
 * @return array ['success'=>bool, 'filename'=>string, 'error'=>string]
 */
function secureUpload($file, $uploadDir, $allowedExt = ['jpg','jpeg','png','webp'], $maxBytes = 5242880, $prefix = 'upload_') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'filename' => '', 'error' => 'No file uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => '', 'error' => 'Upload error code: ' . $file['error']];
    }
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'filename' => '', 'error' => 'File too large. Max ' . round($maxBytes/1048576,1) . 'MB allowed.'];
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'filename' => '', 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExt)];
    }

    // Validate MIME type via finfo (prevents extension spoofing)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'webp' => 'image/webp',
        'gif'  => 'image/gif',  'pdf'  => 'application/pdf',
    ];
    if (isset($allowedMimes[$ext]) && $mimeType !== $allowedMimes[$ext]) {
        return ['success' => false, 'filename' => '', 'error' => 'File content does not match extension.'];
    }

    // Sanitize filename — use random name only
    $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Ensure upload dir exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => false, 'filename' => '', 'error' => 'Failed to save file.'];
    }

    return ['success' => true, 'filename' => $filename, 'error' => ''];
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// ── CSRF Token helpers ────────────────────────────────
function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token validation failed. Please go back and try again.');
    }
}

// Normalize date — accepts YYYY-MM-DD, YYYY/MM/DD, DD-MM-YYYY, DD/MM/YYYY
// Returns MySQL format YYYY-MM-DD or null if invalid
function normalizeDate($input) {
    $input = trim($input);
    if (!$input) return null;

    // Already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
        return $input;
    }
    // YYYY/MM/DD → YYYY-MM-DD
    if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $input)) {
        return str_replace('/', '-', $input);
    }
    // DD-MM-YYYY → YYYY-MM-DD
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $input, $m)) {
        return "$m[3]-$m[2]-$m[1]";
    }
    // DD/MM/YYYY → YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $input, $m)) {
        return "$m[3]-$m[2]-$m[1]";
    }
    // Fallback — try strtotime
    $ts = strtotime($input);
    if ($ts) return date('Y-m-d', $ts);

    return null;
}
?>
