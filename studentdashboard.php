<?php
require_once(__DIR__ . '/../../config.php');
require_login();
\local_unileasysregistration\license::require_valid();

require_once(__DIR__ . '/classes/verification.php');
require_once(__DIR__ . '/classes/schema.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_unileasysregistration\verification;
use local_unileasysregistration\schema;

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/unileasysregistration/studentdashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('myunileasys', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('myunileasys', 'local_unileasysregistration'));

schema::ensure_enrolment_tables();
verification::ensure_schema();

$userid = (int)$USER->id;
$status = verification::get_status($userid);
$profile = profile_user_record($userid, false);
$school = trim((string)($profile->unileasys_school ?? ''));
$class = trim((string)($profile->unileasys_class ?? ''));
$type = trim((string)($profile->unileasys_account_type ?? ''));

$homeurl = new moodle_url('/local/unileasysregistration/studentdashboard.php');
$activateurl = new moodle_url('/local/unileasysregistration/activate.php');

$activecourses = [];
if ($status === verification::VERIFIED) {
    $records = $DB->get_records('unileasys_enrolment', [
        'userid' => $userid,
        'status' => ENROL_USER_ACTIVE,
    ], 'id ASC');
    foreach ($records as $record) {
        $course = $DB->get_record('course', ['id' => $record->courseid], 'id,fullname,shortname');
        if ($course && (int)$course->id !== SITEID) {
            $activecourses[$course->id] = $course;
        }
    }
}

echo $OUTPUT->header();

// Hero / account status card.
if ($status === verification::VERIFIED) {
    $statusclass = 'alert alert-success';
    $statusicon = '✓';
    $statusheading = get_string('dashboardverifiedtitle', 'local_unileasysregistration');
    $statusmessage = get_string('dashboardverifiedmessage', 'local_unileasysregistration');
} else if ($status === verification::SUSPENDED) {
    $statusclass = 'alert alert-warning';
    $statusicon = '!';
    $statusheading = get_string('dashboardsuspendedtitle', 'local_unileasysregistration');
    $statusmessage = get_string('dashboardsuspendedmessage', 'local_unileasysregistration');
} else {
    $statusclass = 'alert alert-warning';
    $statusicon = '🔐';
    $statusheading = get_string('dashboardpendingtitle', 'local_unileasysregistration');
    $statusmessage = get_string('dashboardpendingmessage', 'local_unileasysregistration');
}

$hero = html_writer::div(
    html_writer::div($statusicon, 'unileasys-status-icon', ['style' => 'font-size:2rem;line-height:1;']) .
    html_writer::div(
        html_writer::tag('h2', $statusheading, ['class' => 'mb-1']) .
        html_writer::div($statusmessage, 'mb-3'),
        'flex-grow-1'
    ),
    $statusclass . ' d-flex align-items-center p-4 mb-4'
);
echo $hero;

// Identity summary.
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('dashboardprofiletitle', 'local_unileasysregistration'), 'card-header font-weight-bold');
echo html_writer::start_div('card-body');
$table = new html_table();
$table->attributes['class'] = 'table table-sm mb-0';
$table->data = [
    [get_string('school', 'local_unileasysregistration'), $school !== '' ? format_string($school) : '—'],
    [get_string('class', 'local_unileasysregistration'), $class !== '' ? format_string($class) : '—'],
    [get_string('accounttype', 'local_unileasysregistration'), $type !== '' ? format_string(ucfirst($type)) : '—'],
    [get_string('status', 'local_unileasysregistration'), $status === verification::VERIFIED
        ? get_string('verificationverified', 'local_unileasysregistration')
        : get_string('verificationpending', 'local_unileasysregistration')],
];
echo html_writer::table($table);
echo html_writer::end_div();
echo html_writer::end_div();

if ($status !== verification::VERIFIED) {
    echo html_writer::start_div('card mb-4 border-warning');
    echo html_writer::div(get_string('activateaccount', 'local_unileasysregistration'), 'card-header font-weight-bold');
    echo html_writer::div(
        get_string('dashboardactivationhelp', 'local_unileasysregistration') . '<br><br>' .
        html_writer::link($activateurl, get_string('activatebutton', 'local_unileasysregistration'), ['class' => 'btn btn-primary btn-lg']),
        'card-body'
    );
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('card mb-4');
    echo html_writer::div(get_string('dashboardcourses', 'local_unileasysregistration'), 'card-header font-weight-bold');
    if (!$activecourses) {
        echo html_writer::div(get_string('dashboardnocourses', 'local_unileasysregistration'), 'card-body alert alert-info mb-0');
    } else {
        echo html_writer::start_tag('ul', ['class' => 'list-group list-group-flush']);
        foreach ($activecourses as $course) {
            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            echo html_writer::tag('li',
                html_writer::link($url, format_string($course->fullname), ['class' => 'font-weight-bold']) .
                ($course->shortname !== '' ? html_writer::div(s($course->shortname), 'small text-muted') : ''),
                ['class' => 'list-group-item']
            );
        }
        echo html_writer::end_tag('ul');
    }
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]),
        get_string('editmyprofile', 'moodle'), ['class' => 'btn btn-secondary mr-2']) .
    html_writer::link(new moodle_url('/my/'),
        get_string('dashboard', 'local_unileasysregistration'), ['class' => 'btn btn-secondary']),
    'mb-4'
);

echo $OUTPUT->footer();
