<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/unileasysregistration/demo.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Unileasys Registration Demo');
$PAGE->set_heading('Unileasys Registration — Demo Edition');

$purchaseurl = 'https://apps.unileasys.com/product/unileasys-course-distribution-plugin-pro/';

echo $OUTPUT->header();
echo html_writer::start_div('container-fluid');
echo html_writer::div(
    '<h3>Unileasys Registration — Demo Edition</h3>' .
    '<p>This free Demo Edition lets you evaluate the core Unileasys workflow before purchasing the full commercial version.</p>' .
    '<p><strong>Workflow:</strong> Enrolment Rules → Course Licenses → Verification Tokens → Student Enrollment</p>',
    'alert alert-info'
);

$table = new html_table();
$table->head = ['Feature', 'Demo limit'];
$table->data = [
    ['Schools', (string)\local_unileasysregistration\demo::MAX_SCHOOLS],
    ['Classes', (string)\local_unileasysregistration\demo::MAX_CLASSES],
    ['Academic Sessions', (string)\local_unileasysregistration\demo::MAX_SESSIONS],
    ['Enrolment Rules', (string)\local_unileasysregistration\demo::MAX_RULES],
    ['Course Licenses', (string)\local_unileasysregistration\demo::MAX_COURSE_LICENSES],
    ['Seats per Course License', (string)\local_unileasysregistration\demo::MAX_SEATS],
    ['Verification Tokens', (string)\local_unileasysregistration\demo::MAX_TOKENS],
    ['Verified Users', (string)\local_unileasysregistration\demo::MAX_VERIFIED_USERS],
];
echo html_writer::table($table);

echo html_writer::div(
    'The commercial version removes these demo limits and provides unrestricted use according to the purchased license.',
    'alert alert-warning mt-3'
);

echo html_writer::div(
    html_writer::link(new moodle_url($purchaseurl), 'Purchase Full Version', ['class' => 'btn btn-primary', 'target' => '_blank', 'rel' => 'noopener']),
    'mt-3'
);
echo html_writer::end_div();
echo $OUTPUT->footer();
