<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// ── Sanitize all fields ──────────────────────────────
$studentName      = sanitize($conn, $_POST['student_name']        ?? '');
$dob              = normalizeDate($_POST['date_of_birth']          ?? '');
$dobType          = in_array(strtoupper($_POST['dob_type'] ?? 'AD'), ['BS','AD']) ? strtoupper($_POST['dob_type']) : 'AD';
$gender           = sanitize($conn, $_POST['gender']               ?? '');
$applyingForClass = sanitize($conn, $_POST['applying_for_class']   ?? '');
$academicYear     = sanitize($conn, $_POST['academic_year']        ?? '');
$religion         = sanitize($conn, $_POST['religion']             ?? '');
$nationality      = sanitize($conn, $_POST['nationality']          ?? 'Nepali');
$bloodGroup       = sanitize($conn, $_POST['blood_group']          ?? '');
$fatherName       = sanitize($conn, $_POST['father_name']          ?? '');
$motherName       = sanitize($conn, $_POST['mother_name']          ?? '');
$grandfatherName  = sanitize($conn, $_POST['grandfather_name']     ?? '');
$guardianPhone    = sanitize($conn, $_POST['guardian_phone']       ?? '');
$whatsappNo       = sanitize($conn, $_POST['whatsapp_no']          ?? '');
$guardianEmail    = sanitize($conn, $_POST['guardian_email']       ?? '');
$address          = sanitize($conn, $_POST['address']              ?? '');
$previousSchool   = sanitize($conn, $_POST['previous_school']      ?? '');
$previousClass    = sanitize($conn, $_POST['previous_class']       ?? '');

// ── Required field validation ───────────────────────
if (!$studentName || !$dob || !$gender || !$applyingForClass || !$fatherName || !$guardianPhone || !$address) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit();
}
if (!in_array($gender, ['male', 'female', 'other'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid gender value.']);
    exit();
}
if ($guardianEmail && !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

// ── Document upload ──────────────────────────────────
$documentFile = '';
if (isset($_FILES['documents']) && $_FILES['documents']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
    if (!in_array($_FILES['documents']['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Documents: only PDF, JPG, PNG allowed.']);
        exit();
    }
    if ($_FILES['documents']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Document file must be under 5MB.']);
        exit();
    }
    $ext          = pathinfo($_FILES['documents']['name'], PATHINFO_EXTENSION);
    $documentFile = 'admission_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['documents']['tmp_name'], '../uploads/admissions/' . $documentFile)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload document.']);
        exit();
    }
} else {
    // Document is required
    echo json_encode(['success' => false, 'message' => 'Documents upload is required.']);
    exit();
}

// ── Student photo upload ─────────────────────────────
$studentPhoto = '';
if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg','image/jpg','image/png'];
    if (!in_array($_FILES['student_photo']['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Student photo: only JPG/PNG allowed.']);
        exit();
    }
    if ($_FILES['student_photo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Student photo must be under 2MB.']);
        exit();
    }
    $ext          = pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION);
    $studentPhoto = 'student_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['student_photo']['tmp_name'], '../uploads/admissions/' . $studentPhoto)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload student photo.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Student passport photo is required.']);
    exit();
}

// ── Insert ───────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO admissions
        (student_name, date_of_birth, dob_type, gender, applying_for_class, academic_year,
         religion, nationality, blood_group,
         father_name, mother_name, grandfather_name,
         guardian_phone, whatsapp_no, guardian_email,
         address, previous_school, previous_class,
         documents, student_photo)
    VALUES (?,?,?,?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?)
");
$stmt->bind_param(
    "ssssssssssssssssssss",
    $studentName, $dob, $dobType, $gender, $applyingForClass, $academicYear,
    $religion, $nationality, $bloodGroup,
    $fatherName, $motherName, $grandfatherName,
    $guardianPhone, $whatsappNo, $guardianEmail,
    $address, $previousSchool, $previousClass,
    $documentFile, $studentPhoto
);

if ($stmt->execute()) {
    // Use current BS year for reference number
    require_once '../includes/nepali_date.php';
    $bsYear = getCurrentBS()['year'];
    $refNo  = 'ADM-' . $bsYear . '-' . str_pad($stmt->insert_id, 4, '0', STR_PAD_LEFT);
    echo json_encode(['success' => true, 'ref_no' => $refNo]);
} else {
    echo json_encode(['success' => false, 'message' => 'Submission failed. Please try again.']);
}
$stmt->close();
