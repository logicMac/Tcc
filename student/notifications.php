<?php
/**
 * TCC RRS – Student: Notification Center
 * In-app feed of status updates for submissions linked to this account.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireStudentAuth();

$studentId = currentStudentId();
$pageTitle = 'Notifications';

// Mark all as read
if (isPost() && post('action') === 'mark_all_read') {
    abortCsrf();
    db()->execute(
        "UPDATE student_notifications SET is_read = 1 WHERE student_id = ?",
        [$studentId]
    );
    redirect(BASE_URL . '/student/notifications.php');
}

$page    = max(1, getInt('page', 1));
$perPage = 20;
$notifs  = db()->paginate(
    "SELECT * FROM student_notifications WHERE student_id = ? ORDER BY created_at DESC",
    [$studentId],
    $page,
    $perPage
);
$unread  = unreadStudentNotifCount($studentId);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/student_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/student_topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Notifications',
    $unread ? "{$unread} unread update" . ($unread === 1 ? '' : 's') : 'You are all caught up.',
    'bell',
    $unread ? '<form method="POST" class="d-inline">' . csrfField()
        . '<input type="hidden" name="action" value="mark_all_read">'
        . ui_btn('Mark all as read', ['type'=>'submit','size'=>'sm','variant'=>'outline-secondary','icon'=>'check2-all'])
        . '</form>' : ''
) ?>

<?php
$rows = [];
foreach ($notifs['data'] as $n) {
    $unreadDot = $n['is_read'] ? '' : '<span class="badge bg-primary ms-1">New</span>';
    $rows[] = [
        ['html' => '<div class="' . ($n['is_read'] ? '' : 'fw-semibold') . '">' . e($n['title']) . $unreadDot . '</div>'
                 . '<div class="row-sub">' . e(mb_strimwidth($n['message'], 0, 140, '…')) . '</div>'],
        ui_code($n['tracking_code']),
        '<span class="small text-muted">' . formatDateTime($n['created_at']) . '</span>'
            . '<div class="row-sub">' . timeAgo($n['created_at']) . '</div>',
        $n['link']
            ? ui_btn('View', ['size'=>'sm','variant'=>'outline-primary','icon'=>'box-arrow-up-right','href'=>$n['link']])
            : '',
    ];
}

echo ui_card_open('All notifications', 'list-ul');
echo ui_table(
    ['Update', 'Tracking Code', 'Received', ['label' => '', 'style' => 'width:90px']],
    $rows,
    ['empty_title' => 'No notifications yet',
     'empty_msg'   => 'Status updates for your submissions will appear here.',
     'pagination'  => ['page' => $page, 'pages' => $notifs['pages'], 'total' => $notifs['total'],
                       'per_page' => $perPage, 'base_url' => BASE_URL . '/student/notifications.php']]
);
echo ui_card_close();
?>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
