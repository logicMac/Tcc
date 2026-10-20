<?php
/**
 * TCC RRS – Global Helper Functions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/components.php';

// ══════════════════════════════════════════════════════════════════════════════
// SESSION MANAGEMENT
// ══════════════════════════════════════════════════════════════════════════════

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => SESSION_PATH,
            'secure'   => false,   // set true when using HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function isRegistrar(): bool
{
    return currentRole() === ROLE_REGISTRAR;
}

function isStaff(): bool
{
    return currentRole() === ROLE_STAFF;
}

/**
 * Require a logged-in user with specified role(s).
 * Redirects to login if not authenticated.
 * Redirects to change_password if must_change_password is set.
 */
function requireAuth(string ...$roles): void
{
    startSession();
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/login.php?reason=unauthenticated');
    }
    if (!empty($roles) && !in_array(currentRole(), $roles, true)) {
        redirect(BASE_URL . '/auth/unauthorized.php');
    }
    // Force password change intercept (skip if already on change_password page)
    $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
    if ($currentScript !== 'change_password.php') {
        $mustChange = db()->fetchValue(
            "SELECT must_change_password FROM users WHERE id = ?",
            [currentUserId()]
        );
        if ($mustChange) {
            redirect(BASE_URL . '/auth/change_password.php?force=1');
        }
    }
}

/**
 * Prevent logged-in users from accessing guest-only pages.
 */
function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        $role = currentRole();
        if ($role === ROLE_REGISTRAR) {
            redirect(BASE_URL . '/registrar/dashboard.php');
        } else {
            redirect(BASE_URL . '/staff/dashboard.php');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// STUDENT SESSION (separate from staff — uses `student_*` session keys so a
// browser may be signed in as both staff and student at once)
// ══════════════════════════════════════════════════════════════════════════════

function isStudentLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['student_id']);
}

function currentStudent(): ?array
{
    if (!isStudentLoggedIn()) return null;
    return $_SESSION['student'] ?? null;
}

function currentStudentId(): ?int
{
    return $_SESSION['student_id'] ?? null;
}

/**
 * Require a signed-in student; redirects to the unified login page otherwise,
 * preserving the intended destination in ?return=.
 */
function requireStudentAuth(): void
{
    startSession();
    if (!isStudentLoggedIn()) {
        $return = $_SERVER['REQUEST_URI'] ?? '';
        redirect(BASE_URL . '/auth/login.php?return=' . urlencode($return));
    }
    // Force password change intercept (skip if already on the change page)
    $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
    if ($currentScript !== 'change_password.php') {
        try {
            $mustChange = db()->fetchValue(
                "SELECT must_change_password FROM students WHERE id = ?",
                [currentStudentId()]
            );
        } catch (Throwable) {
            $mustChange = 0;
        }
        if ($mustChange) {
            redirect(BASE_URL . '/student/change_password.php?force=1');
        }
    }
}

/**
 * Log a student in: populates the `student_*` session keys.
 */
function loginStudentSession(array $student): void
{
    startSession();
    $_SESSION['student_id'] = (int) $student['id'];
    $_SESSION['student']    = [
        'id'             => (int) $student['id'],
        'student_no'     => $student['student_no'],
        'first_name'     => $student['first_name'],
        'middle_name'    => $student['middle_name'],
        'last_name'      => $student['last_name'],
        'suffix'         => $student['suffix'],
        'email'          => $student['email'],
        'contact_number' => $student['contact_number'],
    ];
}

/**
 * Link a submission row to a student account (called after INSERT).
 */
function linkSubmissionToStudent(string $table, int $id, ?int $studentId): void
{
    if (!$studentId) return;
    $allowed = ['pre_registrations', 'document_requests'];
    if (!in_array($table, $allowed, true)) return;
    db()->execute("UPDATE {$table} SET student_account_id = ? WHERE id = ?", [$studentId, $id]);
}

/**
 * Link all guest submissions sharing the student's email to their account.
 * Called at registration and login so past submissions appear in the portal.
 */
function claimSubmissionsForStudent(int $studentId, string $email): void
{
    db()->execute(
        "UPDATE pre_registrations SET student_account_id = ? WHERE student_account_id IS NULL AND email = ?",
        [$studentId, $email]
    );
    db()->execute(
        "UPDATE document_requests SET student_account_id = ? WHERE student_account_id IS NULL AND email = ?",
        [$studentId, $email]
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// REDIRECTS & OUTPUT
// ══════════════════════════════════════════════════════════════════════════════

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function redirectBack(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
    redirect($ref);
}

// ══════════════════════════════════════════════════════════════════════════════
// FLASH MESSAGES
// ══════════════════════════════════════════════════════════════════════════════

function flash(string $type, string $message): void
{
    startSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array
{
    startSession();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function renderFlash(): string
{
    $html     = '';
    $messages = getFlash();
    foreach ($messages as $msg) {
        $type    = htmlspecialchars($msg['type']);
        $message = htmlspecialchars($msg['message']);
        $icon    = match ($type) {
            'success' => 'check-circle-fill',
            'danger'  => 'x-circle-fill',
            'warning' => 'exclamation-triangle-fill',
            default   => 'info-circle-fill',
        };
        $html .= <<<HTML
        <div class="alert alert-{$type} alert-dismissible d-flex align-items-center fade show" role="alert">
            <i class="bi bi-{$icon} me-2 flex-shrink-0"></i>
            <div>{$message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
    }
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// TRACKING CODE GENERATION
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Generate a unique tracking code using atomic DB sequence.
 * Format: TCC-REG-2026-000001 or TCC-DOC-2026-000001
 */
function generateTrackingCode(string $prefix): string
{
    $year = (int) date('Y');
    $db   = db();

    // Atomic increment
    $db->execute(
        "INSERT INTO tracking_sequences (prefix, year, last_seq)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
        [$prefix, $year]
    );

    $seq = (int) $db->fetchValue(
        "SELECT last_seq FROM tracking_sequences WHERE prefix = ? AND year = ?",
        [$prefix, $year]
    );

    return $prefix . '-' . $year . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
}

// ══════════════════════════════════════════════════════════════════════════════
// SANITIZATION & VALIDATION
// ══════════════════════════════════════════════════════════════════════════════

function sanitize(mixed $value): string
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function sanitizeAll(array $data): array
{
    return array_map('sanitize', $data);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool
{
    return (bool) preg_match('/^[\+\d\s\-\(\)]{7,20}$/', $phone);
}

function validateDate(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function csrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    startSession();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function abortCsrf(): void
{
    if (!verifyCsrf()) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:20px;color:red;">
            403 – Invalid security token. Please go back and try again.</div>');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTIVITY LOGGING
// ══════════════════════════════════════════════════════════════════════════════

function logActivity(
    string  $action,
    string  $module,
    string  $description = '',
    string  $reference   = '',
    ?int    $userId      = null,
    ?string $userName    = null,
    ?string $userRole    = null
): void {
    $userId   = $userId   ?? currentUserId();
    $user     = currentUser();
    $userName = $userName ?? ($user['full_name'] ?? 'System');
    $userRole = $userRole ?? currentRole();

    db()->execute(
        "INSERT INTO activity_logs
            (user_id, user_name, user_role, action, module, description, reference, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $userName,
            $userRole,
            $action,
            $module,
            $description,
            $reference,
            getClientIp(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// STATUS HISTORY
// ══════════════════════════════════════════════════════════════════════════════

function addStatusHistory(
    string  $refType,
    int     $refId,
    string  $trackingCode,
    ?string $oldStatus,
    string  $newStatus,
    ?string $remarks  = null,
    ?int    $changedBy = null
): void {
    $changedBy = $changedBy ?? currentUserId();
    db()->execute(
        "INSERT INTO status_history
            (reference_type, reference_id, tracking_code, old_status, new_status, remarks, changed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$refType, $refId, $trackingCode, $oldStatus, $newStatus, $remarks, $changedBy]
    );

    // In-app notification for the linked student account (status changes only)
    if ($oldStatus !== null && $oldStatus !== $newStatus) {
        $table = $refType === 'registration' ? 'pre_registrations' : 'document_requests';
        $studentId = (int) (db()->fetchValue(
            "SELECT student_account_id FROM {$table} WHERE id = ?", [$refId]
        ) ?? 0);
        if ($studentId) {
            $kind = $refType === 'registration' ? 'pre-registration' : 'document request';
            $msg  = "Your {$kind} {$trackingCode} is now {$newStatus}."
                  . ($remarks ? ' Remarks: ' . $remarks : '');
            notifyStudentAccount(
                $studentId, $trackingCode,
                ($kind === 'pre-registration' ? 'Pre-registration' : 'Document request') . ' — ' . $newStatus,
                $msg,
                BASE_URL . '/student/track.php?code=' . urlencode($trackingCode)
            );
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// STUDENT NOTIFICATIONS (in-app feed)
// ══════════════════════════════════════════════════════════════════════════════

function notifyStudentAccount(
    int     $studentId,
    string  $trackingCode,
    string  $title,
    string  $message,
    ?string $link = null
): void {
    try {
        db()->execute(
            "INSERT INTO student_notifications (student_id, tracking_code, title, message, link)
             VALUES (?, ?, ?, ?, ?)",
            [$studentId, $trackingCode, $title, $message, $link]
        );
    } catch (Throwable) {
        // Table may not exist yet on un-migrated installs — never block the action.
    }
}

function unreadStudentNotifCount(?int $studentId = null): int
{
    $studentId = $studentId ?? currentStudentId();
    if (!$studentId) return 0;
    try {
        return (int) db()->fetchValue(
            "SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = 0",
            [$studentId]
        );
    } catch (Throwable) {
        return 0;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// PICKUP APPOINTMENTS
// ══════════════════════════════════════════════════════════════════════════════

function pickupSlotOptions(): array
{
    $raw = getSetting('pickup_slots',
        '8:00 AM – 10:00 AM|10:00 AM – 12:00 PM|1:00 PM – 3:00 PM|3:00 PM – 5:00 PM');
    return array_values(array_filter(array_map('trim', explode('|', $raw))));
}

function pickupSlotCapacity(): int
{
    return max(1, (int) getSetting('pickup_slot_capacity', '10'));
}

function pickupSlotBookedCount(string $date, string $slot, ?int $excludeId = null): int
{
    $sql = "SELECT COUNT(*) FROM document_requests
            WHERE pickup_date = ? AND pickup_slot = ?
              AND status NOT IN ('Released','Declined')";
    $params = [$date, $slot];
    if ($excludeId) { $sql .= " AND id <> ?"; $params[] = $excludeId; }
    return (int) db()->fetchValue($sql, $params);
}

// ══════════════════════════════════════════════════════════════════════════════
// RECORD DIFF (audit trail for staff edits)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Describe field-level changes between a stored row and submitted data.
 * Returns e.g. "Email: 'old@x.com' → 'new@x.com'; Copies: 1 → 2".
 */
function describeRecordChanges(array $before, array $after, array $ignore = ['edit_note']): string
{
    $labels = [
        'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name',
        'suffix' => 'Suffix', 'student_id' => 'Student ID', 'email' => 'Email',
        'contact_number' => 'Contact number', 'telephone_number' => 'Telephone',
        'address' => 'Address', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender',
        'civil_status' => 'Civil status', 'nationality' => 'Nationality', 'religion' => 'Religion',
        'birth_place' => 'Birthplace', 'document_type_id' => 'Document type',
        'document_type_other' => 'Other document', 'copies' => 'Copies', 'purpose' => 'Purpose',
        'date_needed' => 'Date needed', 'delivery_method' => 'Delivery method',
        'program' => 'Program', 'program_id' => 'Program', 'program_other' => 'Other program',
        'year_graduated' => 'Year graduated', 'year_level' => 'Year level',
        'last_year_attended' => 'Last year attended', 'enrollment_type' => 'Enrollment type',
        'school_year' => 'School year', 'semester' => 'Semester', 'lrn' => 'LRN',
        'previous_school' => 'Previous school', 'previous_program' => 'Previous program',
    ];
    $changes = [];
    foreach ($after as $key => $newVal) {
        if (in_array($key, $ignore, true) || !array_key_exists($key, $before)) continue;
        $oldVal = $before[$key];
        if (is_array($newVal)) $newVal = json_encode($newVal);
        if ((string)($oldVal ?? '') === (string)($newVal ?? '')) continue;
        $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
        $old   = mb_strimwidth((string)($oldVal ?? '—'), 0, 40, '…');
        $new   = mb_strimwidth((string)($newVal === '' || $newVal === null ? '—' : $newVal), 0, 40, '…');
        $changes[] = "{$label}: '{$old}' → '{$new}'";
    }
    return implode('; ', $changes);
}

function getStatusHistory(string $trackingCode): array
{
    return db()->fetchAll(
        "SELECT sh.*, u.full_name AS changed_by_name
         FROM status_history sh
         LEFT JOIN users u ON u.id = sh.changed_by
         WHERE sh.tracking_code = ?
         ORDER BY sh.changed_at ASC",
        [$trackingCode]
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// SYSTEM SETTINGS
// ══════════════════════════════════════════════════════════════════════════════

function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $val = db()->fetchValue(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        $cache[$key] = ($val !== null) ? (string)$val : $default;
    }
    return $cache[$key];
}

function getAllSettings(): array
{
    $rows = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings");
    $out  = [];
    foreach ($rows as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}

// ══════════════════════════════════════════════════════════════════════════════
// FORMATTING HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function formatDate(string $date, string $format = 'M d, Y'): string
{
    if (empty($date) || $date === '0000-00-00') return '—';
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception) {
        return '—';
    }
}

function formatDateTime(string $datetime, string $format = 'M d, Y h:i A'): string
{
    return formatDate($datetime, $format);
}

function timeAgo(string $datetime): string
{
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . ' year'  . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min'   . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

function formatCurrency(float $amount, string $symbol = '₱'): string
{
    return $symbol . number_format($amount, 2);
}

function fullName(array $row, bool $lastFirst = false): string
{
    $first  = trim($row['first_name']  ?? '');
    $middle = trim($row['middle_name'] ?? '');
    $last   = trim($row['last_name']   ?? '');
    $suffix = trim($row['suffix']      ?? '');
    $mi     = $middle ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : '';

    if ($lastFirst) {
        return trim("{$last}, {$first} {$mi}" . ($suffix ? ", {$suffix}" : ''));
    }
    return trim("{$first} {$mi} {$last}" . ($suffix ? " {$suffix}" : ''));
}

function statusBadge(string $status, string $type = 'registration'): string
{
    $statuses = $type === 'registration' ? REG_STATUSES : DOC_STATUSES;
    $info     = $statuses[$status] ?? ['label' => $status, 'color' => 'secondary', 'icon' => 'circle'];
    return '<span class="badge badge-soft badge-soft-' . e($info['color']) . '">'
         . '<i class="bi bi-' . e($info['icon']) . ' me-1"></i>'
         . e($info['label']) . '</span>';
}

function statusTimeline(array $history, string $statusType = 'registration'): string
{
    if (empty($history)) return '<p class="text-muted">No status history yet.</p>';
    $html = '<div class="status-timeline">';
    foreach ($history as $h) {
        $date      = formatDateTime($h['changed_at']);
        $newStatus = e($h['new_status']);
        $by        = e($h['changed_by_name'] ?? 'System');
        $remarks   = $h['remarks'] ? '<div class="timeline-remarks text-muted small">' . e($h['remarks']) . '</div>' : '';
        $html .= <<<HTML
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <div class="d-flex justify-content-between align-items-start">
                    <strong>{$newStatus}</strong>
                    <small class="text-muted ms-3 text-nowrap">{$date}</small>
                </div>
                <small class="text-muted">by {$by}</small>
                {$remarks}
            </div>
        </div>
        HTML;
    }
    $html .= '</div>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ══════════════════════════════════════════════════════════════════════════════

function paginationLinks(int $currentPage, int $totalPages, string $baseUrl): string
{
    if ($totalPages <= 1) return '';
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';

    // Previous
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $prevLink     = $currentPage > 1  ? $baseUrl . '&page=' . ($currentPage - 1) : '#';
    $html .= "<li class=\"page-item {$prevDisabled}\">
                <a class=\"page-link\" href=\"{$prevLink}\"><i class=\"bi bi-chevron-left\"></i></a>
              </li>";

    // Pages
    $start = max(1, $currentPage - 2);
    $end   = min($totalPages, $currentPage + 2);
    if ($start > 1)          $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html  .= "<li class=\"page-item {$active}\">
                     <a class=\"page-link\" href=\"{$baseUrl}&page={$i}\">{$i}</a>
                   </li>";
    }
    if ($end < $totalPages) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';

    // Next
    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $nextLink     = $currentPage < $totalPages  ? $baseUrl . '&page=' . ($currentPage + 1) : '#';
    $html .= "<li class=\"page-item {$nextDisabled}\">
                <a class=\"page-link\" href=\"{$nextLink}\"><i class=\"bi bi-chevron-right\"></i></a>
              </li>";

    $html .= '</ul></nav>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// NETWORK / REQUEST HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function getClientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function isPost(): bool  { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function isGet(): bool   { return $_SERVER['REQUEST_METHOD'] === 'GET'; }
function isAjax(): bool  { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }

function post(string $key, mixed $default = ''): string
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get(string $key, mixed $default = ''): string
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function postInt(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function getInt(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $default);
}

// ══════════════════════════════════════════════════════════════════════════════
// JSON RESPONSE
// ══════════════════════════════════════════════════════════════════════════════

function jsonResponse(bool $success, string $message = '', array $data = [], int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// QR CODE URL HELPER
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Returns the public tracking URL for a given tracking code.
 * This URL is embedded in every QR code.
 */
function trackingUrl(string $trackingCode): string
{
    return BASE_URL . '/student/track.php?code=' . urlencode($trackingCode);
}

/**
 * Generate a unique issued-document verification code.
 * Format: TCC-VRF-2026-000001
 */
function generateVerificationCode(): string
{
    return generateTrackingCode(TRACKING_VRF_PREFIX);
}

/**
 * Public URL that verifies an issued document's authenticity.
 * Embedded in the QR code printed on release slips / issued documents.
 */
function verificationUrl(string $verificationCode): string
{
    return BASE_URL . '/verify.php?code=' . urlencode($verificationCode);
}

/**
 * Look up an issued document by its verification code.
 */
function getIssuedDocument(string $code): ?array
{
    return db()->fetchOne(
        "SELECT i.*, u.full_name AS issued_by_name
         FROM issued_documents i
         LEFT JOIN users u ON u.id = i.issued_by
         WHERE i.verification_code = ?",
        [$code]
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// PROGRAMS & DOCUMENT TYPES LOOKUPS
// ══════════════════════════════════════════════════════════════════════════════

function getActivePrograms(): array
{
    return db()->fetchAll("SELECT * FROM programs WHERE is_active = 1 ORDER BY name ASC");
}

function getActiveDocumentTypes(): array
{
    return db()->fetchAll("SELECT * FROM document_types WHERE is_active = 1 ORDER BY name ASC");
}

function getDocumentTypeName(int $id): string
{
    $row = db()->fetchOne("SELECT name FROM document_types WHERE id = ?", [$id]);
    return $row['name'] ?? 'Unknown';
}

function getProgramName(int $id): string
{
    $row = db()->fetchOne("SELECT name FROM programs WHERE id = ?", [$id]);
    return $row['name'] ?? 'Unknown';
}

// ══════════════════════════════════════════════════════════════════════════════
// FILE HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function ensureDir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

/**
 * Cache-busting query string for a local asset, based on file mtime.
 * Usage: href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer('assets/css/main.css') ?>"
 */
function assetVer(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return is_file($file) ? '?v=' . filemtime($file) : '';
}

/**
 * School credentials checklist — mirrors TCC-RRMO-FORM-001 "Credentials
 * Presented" section. Shared by the student form (uploads), staff view and
 * staff edit (original-presented ticks).
 */
function credentialCatalog(): array
{
    return [
        'psa_birth'    => ['label' => 'PSA Birth Certificate',                                'note' => 'Original'],
        'psa_marriage' => ['label' => 'PSA Marriage Certificate',                             'note' => 'Original · for married women'],
        'good_moral'   => ['label' => 'Certificate of Good Moral Character',                  'note' => 'Original'],
        'indigency'    => ['label' => 'Certificate of Indigency',                             'note' => 'Original'],
        'report_card'  => ['label' => 'Report Card',                                          'note' => 'Original'],
        'photos_2x2'   => ['label' => '2 pcs. 2×2 ID Photos',                                 'note' => 'White background'],
        'tor'          => ['label' => 'TOR / Transfer Credential / Honorable Dismissal',      'note' => 'Original', 'transferee' => true],
    ];
}
