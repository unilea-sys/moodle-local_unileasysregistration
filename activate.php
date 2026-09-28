<?php
require_once(__DIR__ . '/../../config.php');
require_login();
\local_unileasysregistration\license::require_valid();

require_once(__DIR__ . '/classes/verification.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_unileasysregistration\verification;

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/unileasysregistration/activate.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('activateaccount', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('activateaccount', 'local_unileasysregistration'));

verification::ensure_schema();
$userid = (int)$USER->id;
$status = verification::get_status($userid);
$message = '';
$messagetype = 'info';

if ($status === verification::VERIFIED) {
    $message = get_string('alreadyverified', 'local_unileasysregistration');
    $messagetype = 'success';
} else if ($status === verification::SUSPENDED) {
    $message = get_string('activation_suspended', 'local_unileasysregistration');
    $messagetype = 'warning';
} else if (optional_param('activate', 0, PARAM_BOOL) && confirm_sesskey()) {
    $rawtoken = trim((string)optional_param('activationcode', '', PARAM_ALPHANUMEXT));
    if ($rawtoken === '') {
        $message = get_string('activationrequired', 'local_unileasysregistration');
        $messagetype = 'warning';
    } else {
        $profile = profile_user_record($userid, false);
        $schoolvalue = trim((string)($profile->unileasys_school ?? ''));
        $classvalue = trim((string)($profile->unileasys_class ?? ''));
        $accounttype = strtolower(trim((string)($profile->unileasys_account_type ?? '')));
        $school = $DB->get_record('unileasys_school', ['name' => $schoolvalue, 'active' => 1]);
        if (!$school) {
            $school = $DB->get_record('unileasys_school', ['code' => $schoolvalue, 'active' => 1]);
        }
        $class = $DB->get_record('unileasys_class', ['name' => $classvalue, 'active' => 1]);
        if (!$class) {
            $class = $DB->get_record('unileasys_class', ['shortname' => $classvalue, 'active' => 1]);
        }
        $session = $DB->get_record('unileasys_session', ['active' => 1], '*', IGNORE_MULTIPLE);

        if (!$school || !$class || !$session || !in_array($accounttype, ['student', 'teacher'], true)) {
            $message = get_string('activation_profile_incomplete', 'local_unileasysregistration');
            $messagetype = 'warning';
        } else if (verification::redeem_token(
            $rawtoken, $userid, (int)$school->id, (int)$class->id, $accounttype, (int)$session->id
        )) {
            \local_unileasysregistration\observer::sync_user($userid);
            $message = get_string('activation_success', 'local_unileasysregistration');
            $messagetype = 'success';
            $status = verification::VERIFIED;
        } else {
            $message = get_string('activation_invalid', 'local_unileasysregistration');
            $messagetype = 'warning';
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activateaccount', 'local_unileasysregistration'));

echo html_writer::div(get_string('activation_intro', 'local_unileasysregistration'), 'alert alert-info');
if ($message !== '') {
    echo $OUTPUT->notification($message, $messagetype);
}

if ($status !== verification::VERIFIED && $status !== verification::SUSPENDED) {
    $formurl = new moodle_url('/local/unileasysregistration/activate.php');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl->out(false),
        'class' => 'unileasys-activation-form',
        'style' => 'max-width:520px; margin: 2rem auto;'
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden', 'name' => 'activate', 'value' => '1'
    ]);
    echo html_writer::label(get_string('activationcode', 'local_unileasysregistration'), 'activationcode', false,
        ['class' => 'font-weight-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'activationcode', 'id' => 'activationcode',
        'class' => 'form-control form-control-lg mb-3', 'maxlength' => '64',
        'autocomplete' => 'one-time-code', 'autocapitalize' => 'characters',
        'spellcheck' => 'false', 'required' => 'required'
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('activatebutton', 'local_unileasysregistration'),
        'class' => 'btn btn-primary btn-lg'
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
