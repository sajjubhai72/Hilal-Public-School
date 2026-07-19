<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// ── Scholarship Window Check ──────────────────────────
function getScholWindowStatus($conn) {
    $isOpen    = getSetting($conn, 'scholarship_is_open');
    $openFrom  = getSetting($conn, 'scholarship_open_from');
    $openUntil = getSetting($conn, 'scholarship_open_until');
    $today     = date('Y-m-d');
    if ($openFrom && $openUntil) {
        $auto = ($today >= $openFrom && $today <= $openUntil);
    } elseif ($openFrom) {
        $auto = ($today >= $openFrom);
    } elseif ($openUntil) {
        $auto = ($today <= $openUntil);
    } else {
        $auto = null;
    }
    return $auto !== null ? (bool)$auto : (bool)$isOpen;
}

if (!getScholWindowStatus($conn)) {
    $closedMsg = getSetting($conn, 'scholarship_closed_message') ?: 'Scholarship applications are currently closed.';
    echo json_encode(['success' => false, 'message' => $closedMsg, 'window_closed' => true]);
    exit();
}

// ── Collect inputs ────────────────────────────────────
$name                = sanitize($conn, $_POST['applicant_name']        ?? '');
$dob                 = normalizeDate($_POST['date_of_birth']            ?? '');
$dobType             = in_array(strtoupper($_POST['dob_type'] ?? 'AD'), ['BS','AD']) ? strtoupper($_POST['dob_type']) : 'AD';
$gender              = sanitize($conn, $_POST['gender']                 ?? '');
$currentClass        = sanitize($conn, $_POST['current_class']          ?? '');
$section             = sanitize($conn, $_POST['section']                ?? '');
$rollSymbolNo        = sanitize($conn, $_POST['roll_symbol_no']         ?? '');
$scholarshipType     = sanitize($conn, $_POST['scholarship_type']       ?? '');
$aliveParents        = sanitize($conn, $_POST['alive_parents']          ?? 'Father&Mother');
$studentType         = sanitize($conn, $_POST['student_type']           ?? 'old');
$fatherName          = sanitize($conn, $_POST['father_name']            ?? '');
$grandfatherName     = sanitize($conn, $_POST['grandfather_name']       ?? '');
$greatGrandfatherName= sanitize($conn, $_POST['great_grandfather_name'] ?? '');
$motherName          = sanitize($conn, $_POST['mother_name']            ?? '');
$fatherOccupation    = sanitize($conn, $_POST['father_occupation']      ?? '');
$guardianPhone       = sanitize($conn, $_POST['guardian_phone']         ?? '');
$guardianEmail       = sanitize($conn, $_POST['guardian_email']         ?? '');
$address             = sanitize($conn, $_POST['address']                ?? '');
$oldAddress          = sanitize($conn, $_POST['old_address']            ?? '');
$annualIncome        = sanitize($conn, $_POST['annual_income']          ?? '');
$siblingsCount       = (int)($_POST['siblings_count']                   ?? 0);
$familyMale          = (int)($_POST['family_male']                      ?? 0);
$familyFemale        = (int)($_POST['family_female']                    ?? 0);
$reason              = sanitize($conn, $_POST['reason']                 ?? '');
$siblingsData        = sanitize($conn, $_POST['siblings_data']          ?? '[]');

// ── Validate required ─────────────────────────────────
if (!$name || !$dob || !$gender || !$currentClass || !$scholarshipType || !$fatherName || !$guardianPhone || !$address || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit();
}
if (strlen($reason) > 500) {
    echo json_encode(['success' => false, 'message' => 'Reason too long (max 500 characters).']);
    exit();
}
if (!in_array($gender, ['male','female','other'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid gender.']);
    exit();
}

// ── Document upload ───────────────────────────────────
$documentFile = '';
if (isset($_FILES['documents']) && $_FILES['documents']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['application/pdf','image/jpeg','image/jpg','image/png'];
    if (!in_array($_FILES['documents']['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF, JPG, PNG files allowed.']);
        exit();
    }
    if ($_FILES['documents']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File must be under 5MB.']);
        exit();
    }
    $ext = pathinfo($_FILES['documents']['name'], PATHINFO_EXTENSION);
    $documentFile = 'scholarship_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['documents']['tmp_name'], '../uploads/scholarship/' . $documentFile)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload document.']);
        exit();
    }
}

// ── Student photo upload ──────────────────────────────
$studentPhoto = '';
if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
    $allowedImgTypes = ['image/jpeg','image/jpg','image/png'];
    if (!in_array($_FILES['student_photo']['type'], $allowedImgTypes)) {
        echo json_encode(['success' => false, 'message' => 'Student photo: only JPG/PNG allowed.']);
        exit();
    }
    if ($_FILES['student_photo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Student photo must be under 2MB.']);
        exit();
    }
    $ext = pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION);
    $studentPhoto = 'schol_photo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['student_photo']['tmp_name'], '../uploads/scholarship/' . $studentPhoto)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload student photo.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Student passport photo is required.']);
    exit();
}

// ── Combine class + section ───────────────────────────
$classSection = $section ? $currentClass . ' - ' . strtoupper($section) : $currentClass;

// ── Duplicate check: Father Name + DOB + Academic Year ─
$scholYear = getSetting($conn, 'scholarship_academic_year') ?: date('Y');
$chkStmt   = $conn->prepare("SELECT id FROM scholarship_applications
    WHERE LOWER(TRIM(father_name))=LOWER(TRIM(?))
    AND date_of_birth=?
    AND YEAR(submitted_at)=?
    LIMIT 1");
$chkStmt->bind_param("ssi", $fatherName, $dob, $scholYear);
$chkStmt->execute();
if ($chkStmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false,
        'message' => 'A scholarship application with the same Father\'s Name and Date of Birth has already been submitted for this academic year ('.$scholYear.'). Duplicate applications are not allowed.']);
    $chkStmt->close();
    exit();
}
$chkStmt->close();

// ── Insert ────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO scholarship_applications
    (applicant_name, date_of_birth, dob_type, gender,
     current_class, student_type, roll_no,
     scholarship_type, scholarship_type_detail, alive_parents,
     father_name, grandfather_name, great_grandfather_name, mother_name,
     guardian_phone, guardian_email,
     address, old_address, annual_income,
     siblings_count, father_occupation, family_male, family_female,
     reason, documents, student_photo)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");
$stmt->bind_param(
    "ssssssssssssssssssssisssss",
    $name, $dob, $dobType, $gender,
    $classSection, $studentType, $rollSymbolNo,
    $scholarshipType, $scholarshipType, $aliveParents,
    $fatherName, $grandfatherName, $greatGrandfatherName, $motherName,
    $guardianPhone, $guardianEmail,
    $address, $oldAddress, $annualIncome,
    $siblingsCount, $fatherOccupation, $familyMale, $familyFemale,
    $reason, $documentFile, $studentPhoto
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Scholarship application submitted successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Submission failed. Please try again.']);
}
$stmt->close();
