<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
$context = context_system::instance();
require_capability('local/unileasysregistration:manage', $context);
\local_unileasysregistration\license::require_valid();
require_once(__DIR__ . '/classes/verification.php');

\local_unileasysregistration\verification::ensure_schema();
global $DB, $OUTPUT, $PAGE, $USER;
$PAGE->set_url(new moodle_url('/local/unileasysregistration/verification.php'));
$PAGE->set_context($context);
$PAGE->set_title('User Verification');
$PAGE->set_heading('Unileasys User Verification');

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
if ($userid > 1 && confirm_sesskey()) {
    if ($action === 'approve') {
        \local_unileasysregistration\verification::approve($userid, $USER->id);
        \local_unileasysregistration\observer::sync_user($userid);
        redirect($PAGE->url, 'User verified and synchronized.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'reject') {
        \local_unileasysregistration\verification::reject($userid, $USER->id);
        \local_unileasysregistration\observer::sync_user($userid);
        redirect($PAGE->url, 'User rejected.', null, \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('verification');
echo $OUTPUT->heading('School User Verification');
echo $OUTPUT->notification('Only verified users can receive automatic Unileasys course enrolments.', 'info');

require_once($CFG->dirroot . '/user/profile/lib.php');
$users = $DB->get_records_select('user', 'deleted = 0 AND id > 1', null, 'lastname ASC, firstname ASC');
$table = new html_table();
$table->head = ['User', 'School', 'Class', 'Type', 'Verification', 'Action'];
foreach ($users as $u) {
    $p = profile_user_record($u->id, false);
    $school = trim((string)($p->unileasys_school ?? ''));
    $class = trim((string)($p->unileasys_class ?? ''));
    $type = trim((string)($p->unileasys_account_type ?? ''));
    if ($school === '' && $class === '' && $type === '') continue;
    $status = \local_unileasysregistration\verification::get_status((int)$u->id);
    $actions = [];
    if ($status !== 'verified') {
        $actions[] = html_writer::link(new moodle_url($PAGE->url, [
            'action'=>'approve','userid'=>$u->id,'sesskey'=>sesskey()
        ]), 'Approve', ['class'=>'btn btn-sm btn-primary mr-1']);
    }
    if ($status !== 'rejected') {
        $actions[] = html_writer::link(new moodle_url($PAGE->url, [
            'action'=>'reject','userid'=>$u->id,'sesskey'=>sesskey()
        ]), 'Reject', ['class'=>'btn btn-sm btn-secondary']);
    }
    $table->data[] = [fullname($u), s($school), s($class), s($type), s(ucfirst($status)), implode(' ', $actions)];
}
if (empty($table->data)) {
    echo $OUTPUT->notification('No classified school users found.', 'info');
} else {
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
