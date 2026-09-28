<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
\local_unileasysregistration\license::require_valid();

global $DB, $OUTPUT, $PAGE;
$PAGE->set_url(new moodle_url('/local/unileasysregistration/synclog.php'));
$PAGE->set_context($context);
$PAGE->set_title('Synchronization Log');
$PAGE->set_heading('Unileasys Synchronization Log');

require_once(__DIR__ . '/audit.php');

$delete = optional_param('clear', 0, PARAM_BOOL);
if ($delete && confirm_sesskey()) {
    $DB->delete_records('unileasys_sync_log');
    redirect($PAGE->url, 'Synchronization log cleared.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('synclog');
echo $OUTPUT->heading('Synchronization Log');

if (!$DB->get_manager()->table_exists('unileasys_sync_log')) {
    echo $OUTPUT->notification('Synchronization log table is not available yet. Complete the plugin upgrade first.',
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$count = $DB->count_records('unileasys_sync_log');
echo html_writer::tag('p', 'Total log entries: ' . $count);

if ($count) {
    $url = new moodle_url($PAGE->url, ['clear' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($url, 'Clear log', 'post');

    $records = $DB->get_records('unileasys_sync_log', null, 'timecreated DESC', '*', 0, 500);
    $table = new html_table();
    $table->head = ['Date / Time', 'User ID', 'Rule ID', 'Course ID', 'Action', 'Result', 'Message'];
    foreach ($records as $r) {
        $table->data[] = [
            userdate($r->timecreated),
            $r->userid,
            $r->ruleid ?? '',
            $r->courseid ?? '',
            s($r->action),
            s($r->result),
            s($r->message),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('No synchronization events have been recorded yet.',
        \core\output\notification::NOTIFY_INFO);
}
echo $OUTPUT->footer();
