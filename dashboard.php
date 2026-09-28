<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
require_capability('local/unileasysregistration:manage', context_system::instance());
\local_unileasysregistration\license::require_valid();

use local_unileasysregistration\enrolment_manager;
use local_unileasysregistration\schema;

global $DB, $PAGE, $OUTPUT;
$context = context_system::instance();
schema::ensure_enrolment_tables();
schema::ensure_master_data();

$PAGE->set_url(new moodle_url('/local/unileasysregistration/dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('dashboard', 'local_unileasysregistration'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'syncallusers' && confirm_sesskey()) {
    $result = enrolment_manager::sync_all_users();
    redirect($PAGE->url, get_string('syncallusersresult', 'local_unileasysregistration', (object)$result));
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('dashboard');
echo $OUTPUT->heading(get_string('dashboard', 'local_unileasysregistration'));
echo html_writer::div(
    html_writer::link(new moodle_url('/local/unileasysregistration/verification.php'),
        'User Verification', ['class'=>'btn btn-secondary mr-2']) .
    html_writer::link(new moodle_url('/local/unileasysregistration/tokens.php'),
        'Verification Tokens', ['class'=>'btn btn-secondary']),
    'mb-3'
);

echo html_writer::div(get_string('dashboardintro', 'local_unileasysregistration'), 'alert alert-info');

$totalusers = $DB->count_records_select('user', 'deleted = 0');
$unileasysusers = $DB->count_records('unileasys_user');
$ordinaryusers = max(0, $totalusers - 1 - $unileasysusers);
$activeenrolments = $DB->count_records('unileasys_enrolment', ['status' => ENROL_USER_ACTIVE]);
$suspendedenrolments = $DB->count_records('unileasys_enrolment', ['status' => ENROL_USER_SUSPENDED]);
$activerules = $DB->count_records('unileasys_enrol_rule', ['active' => 1]);

// Identify incomplete classifications without changing them.
$incomplete = 0;
$rs = $DB->get_recordset_select('user', 'deleted = 0 AND id > 1', null, 'id ASC', 'id');
foreach ($rs as $u) {
    require_once($CFG->dirroot . '/user/profile/lib.php');
    $p = profile_user_record($u->id, false);
    $school = trim((string)($p->unileasys_school ?? ''));
    $class = trim((string)($p->unileasys_class ?? ''));
    $type = strtolower(trim((string)($p->unileasys_account_type ?? '')));
    $any = ($school !== '' || $class !== '' || $type !== '');
    $complete = ($school !== '' && $class !== '' && in_array($type, ['student', 'teacher'], true));
    if ($any && !$complete) {
        $incomplete++;
    }
}
$rs->close();

$cards = [
    [get_string('dashboardtotalusers', 'local_unileasysregistration'), $totalusers],
    [get_string('dashboardunileasysusers', 'local_unileasysregistration'), $unileasysusers],
    [get_string('dashboardordinaryusers', 'local_unileasysregistration'), $ordinaryusers],
    [get_string('dashboardactiveenrolments', 'local_unileasysregistration'), $activeenrolments],
    [get_string('dashboardsuspendedenrolments', 'local_unileasysregistration'), $suspendedenrolments],
    [get_string('dashboardactiveRules', 'local_unileasysregistration'), $activerules],
    [get_string('dashboardincomplete', 'local_unileasysregistration'), $incomplete],
];
echo html_writer::start_div('row');
foreach ($cards as [$label, $value]) {
    echo html_writer::start_div('col-md-3 mb-3');
    echo html_writer::div(
        html_writer::tag('div', (string)$value, ['class' => 'h3 mb-1']) .
        html_writer::tag('div', $label, ['class' => 'small text-muted']),
        'card card-body h-100'
    );
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::div(
    html_writer::link(new moodle_url($PAGE->url, ['action' => 'syncallusers', 'sesskey' => sesskey()]),
        get_string('syncallusers', 'local_unileasysregistration'),
        ['class' => 'btn btn-primary mr-2']) .
    html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php'),
        get_string('dashboardviewrules', 'local_unileasysregistration'),
        ['class' => 'btn btn-secondary']),
    'mb-4'
);

if ($incomplete > 0) {
    echo $OUTPUT->notification(get_string('dashboardattention', 'local_unileasysregistration'), 'warning');
} else {
    echo $OUTPUT->notification(get_string('dashboardnoissues', 'local_unileasysregistration'), 'success');
}

echo $OUTPUT->heading(get_string('dashboardrulesummary', 'local_unileasysregistration'), 3);
$rules = $DB->get_records('unileasys_enrol_rule', null, 'id ASC');
if (!$rules) {
    echo $OUTPUT->notification(get_string('norulesyet', 'local_unileasysregistration'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('dashboardcoursename', 'local_unileasysregistration'),
        get_string('dashboardrulecount', 'local_unileasysregistration'),
        get_string('dashboardmatched', 'local_unileasysregistration'),
        get_string('dashboardactive', 'local_unileasysregistration'),
        get_string('dashboardpending', 'local_unileasysregistration'),
    ];
    $grouped = [];
    foreach ($rules as $rule) {
        $course = $DB->get_record('course', ['id' => $rule->courseid], 'id,fullname');
        $key = (int)$rule->courseid;
        if (!isset($grouped[$key])) {
            $grouped[$key] = ['course' => $course, 'rules' => 0, 'matched' => 0, 'active' => 0, 'pending' => 0];
        }
        $grouped[$key]['rules']++;
        $rsu = $DB->get_recordset_select('user', 'deleted = 0 AND id > 1', null, 'id ASC', 'id');
        foreach ($rsu as $u) {
            if ((int)$rule->active === 1 && enrolment_manager::rule_matches_user($rule, (int)$u->id)) {
                $grouped[$key]['matched']++;
            }
        }
        $rsu->close();
    }
    foreach ($grouped as $g) {
        $active = $DB->count_records_select('unileasys_enrolment', 'courseid = :courseid AND status = :status',
            ['courseid' => $g['course'] ? $g['course']->id : 0, 'status' => ENROL_USER_ACTIVE]);
        $pending = $DB->count_records_select('unileasys_enrolment', 'courseid = :courseid AND status = :status',
            ['courseid' => $g['course'] ? $g['course']->id : 0, 'status' => ENROL_USER_SUSPENDED]);
        $table->data[] = [
            $g['course'] ? format_string($g['course']->fullname) : '—',
            $g['rules'],
            $g['matched'],
            $active,
            $pending,
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
