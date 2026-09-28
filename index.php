<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('local/unileasysregistration:manage', context_system::instance());

$PAGE->set_url(new moodle_url('/local/unileasysregistration/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('pluginname', 'local_unileasysregistration'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'syncprofiles' && confirm_sesskey()) {
    \local_unileasysregistration\profile_fields::sync_option_lists();
    redirect($PAGE->url, get_string('profilesynced', 'local_unileasysregistration'));
}

if ($action === 'repairusers' && confirm_sesskey()) {
    $count = 0;
    $rs = $DB->get_recordset('user', null, '', 'id');
    foreach ($rs as $user) {
        if (!empty($user->id)) {
            \local_unileasysregistration\observer::sync_user((int)$user->id);
            $count++;
        }
    }
    $rs->close();
    redirect($PAGE->url, get_string('usersrepaired', 'local_unileasysregistration', $count));
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('');
echo $OUTPUT->heading(get_string('pluginname', 'local_unileasysregistration'));

echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url($PAGE->url, ['action' => 'syncprofiles', 'sesskey' => sesskey()]),
    get_string('syncprofilefields', 'local_unileasysregistration'),
    ['class' => 'btn btn-primary mr-2']
);
echo html_writer::link(
    new moodle_url($PAGE->url, ['action' => 'repairusers', 'sesskey' => sesskey()]),
    get_string('repairusers', 'local_unileasysregistration'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo html_writer::start_div('row');
$items = [
    ['dashboard.php', 'dashboard'],
    ['schools.php', 'schools'],
    ['classes.php', 'classes'],
    ['sessions.php', 'sessions'],
    ['enrolrules.php', 'enrolmentrules'],
    ['settings.php', 'settings'],
];
foreach ($items as [$url, $key]) {
    $link = new moodle_url('/local/unileasysregistration/' . $url);
    echo html_writer::div(
        html_writer::link($link, get_string($key, 'local_unileasysregistration'), ['class' => 'btn btn-primary m-2']),
        'col-md-3'
    );
}
echo html_writer::end_div();

echo html_writer::start_div('mb-4');
echo html_writer::link(new moodle_url('/local/unileasysregistration/verification.php'),
    'User Verification', ['class' => 'btn btn-secondary mr-2']);
echo html_writer::link(new moodle_url('/local/unileasysregistration/tokens.php'),
    'Verification Tokens', ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
