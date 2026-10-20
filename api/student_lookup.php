<?php
/**
 * TCC RRS – Returning-student autofill lookup
 *
 * GET api/student_lookup.php?first=<first>&last=<last>&email=<email>
 *
 * Returns previous submission fields (address, program, year, IDs, contact)
 * ONLY when name + exact email match an existing record — so a stranger
 * cannot enumerate students by name alone.
 *
 * Returns: { "found": true, "fields": { ... } } or { "found": false }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
startSession();

$first = trim($_GET['first'] ?? '');
$last  = trim($_GET['last']  ?? '');
$email = trim($_GET['email'] ?? '');

$empty = static function (): never {
    echo json_encode(['found' => false]);
    exit;
};

if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $empty();
}

// A signed-in student may look up their own record unconditionally.
$self = currentStudent();
$isSelf = $self && strcasecmp($self['email'] ?? '', $email) === 0;

// Latest matching document request (same fields as this form)
$row = db()->fetchOne(
    "SELECT first_name, middle_name, last_name, suffix, student_id, email,
            contact_number, address, program, year_graduated, last_year_attended
     FROM document_requests
     WHERE email = ? AND first_name = ? AND last_name = ?
     ORDER BY id DESC LIMIT 1",
    [$email, $first, $last]
);
if ($row) {
    echo json_encode([
        'found'  => true,
        'fields' => [
            'middle_name'    => $row['middle_name']        ?? '',
            'suffix'         => $row['suffix']             ?? '',
            'student_id'     => $row['student_id']         ?? '',
            'contact_number' => $row['contact_number']     ?? '',
            'address'        => $row['address']            ?? '',
            'program'        => $row['program']            ?? '',
            'year_graduated' => $row['year_graduated'] ?: ($row['last_year_attended'] ?? ''),
        ],
    ]);
    exit;
}

// Fall back to the student's latest pre-registration
$reg = db()->fetchOne(
    "SELECT pr.*, p.name AS prog_name
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.email = ? AND pr.first_name = ? AND pr.last_name = ?
     ORDER BY pr.id DESC LIMIT 1",
    [$email, $first, $last]
);
if ($reg) {
    echo json_encode([
        'found'  => true,
        'fields' => [
            'middle_name'    => $reg['middle_name']    ?? '',
            'suffix'         => $reg['suffix']         ?? '',
            'student_id'     => $reg['student_id']     ?? '',
            'contact_number' => $reg['contact_number'] ?? '',
            'address'        => implode(', ', array_filter([
                $reg['house_no_street'] ?? '', $reg['barangay'] ?? '',
                $reg['municipality_city'] ?? '', $reg['province'] ?? '',
            ])),
            'program'        => $reg['program_other'] ?: ($reg['prog_name'] ?? ''),
            'year_graduated' => $reg['shs_year_graduated'] ?: ($reg['last_year_attended'] ?? ''),
        ],
    ]);
    exit;
}

$empty();
