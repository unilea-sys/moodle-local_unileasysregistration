<?php
require_once(__DIR__ . '/../../config.php');
require_login();
\local_unileasysregistration\license::require_valid();
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/verification.php');
require_once(__DIR__ . '/classes/enrolment_manager.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_unileasysregistration\verification;

global $DB, $USER, $PAGE, $OUTPUT;
$context = context_system::instance();
$userid = (int)$USER->id;
verification::ensure_schema();
\local_unileasysregistration\schema::ensure_course_activation();

$PAGE->set_url(new moodle_url('/local/unileasysregistration/mycourses.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('myschoolcourses', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('myschoolcourses', 'local_unileasysregistration'));

$message = '';
$messagetype = 'info';
$ajaxactivation = optional_param('ajax', 0, PARAM_BOOL);
$activationok = false;

$profile = profile_user_record($userid, false);
$schoolvalue = trim((string)($profile->unileasys_school ?? ''));
$classvalue = trim((string)($profile->unileasys_class ?? ''));
$accounttype = strtolower(trim((string)($profile->unileasys_account_type ?? '')));
$school = $DB->get_record('unileasys_school', ['name'=>$schoolvalue]);
if (!$school && $schoolvalue !== '') $school = $DB->get_record('unileasys_school', ['code'=>$schoolvalue]);
$class = $DB->get_record('unileasys_class', ['name'=>$classvalue]);
if (!$class && $classvalue !== '') $class = $DB->get_record('unileasys_class', ['shortname'=>$classvalue]);
$session = $DB->get_record('unileasys_session', ['active'=>1], '*', IGNORE_MULTIPLE);

// The verified classification is the authoritative identity for course-token
// redemption. This avoids rejecting a valid course token because the current
// active academic session or profile display value differs from the values that
// were actually verified. Profile changes are still fail-closed by v1.5.3+.
$verifiedrecord = $DB->get_record('unileasys_verification', ['userid'=>$userid]);
if ($verifiedrecord && $verifiedrecord->status === verification::VERIFIED) {
    if (!empty($verifiedrecord->schoolid)) {
        $school = $DB->get_record('unileasys_school', ['id'=>(int)$verifiedrecord->schoolid], '*', IGNORE_MISSING);
    }
    if (!empty($verifiedrecord->classid)) {
        $class = $DB->get_record('unileasys_class', ['id'=>(int)$verifiedrecord->classid], '*', IGNORE_MISSING);
    }
    if (!empty($verifiedrecord->accounttype)) {
        $accounttype = strtolower((string)$verifiedrecord->accounttype);
    }
    // Keep the verified school/class/account binding, but use the current
    // active academic session for newly issued course tokens. This prevents a
    // valid course token from being rejected after the academic session rolls
    // over while the user's verified school classification remains unchanged.
    $activesession = $DB->get_record('unileasys_session', ['active'=>1], '*', IGNORE_MULTIPLE);
    if ($activesession) {
        $session = $activesession;
    } else if (!empty($verifiedrecord->sessionid)) {
        $session = $DB->get_record('unileasys_session', ['id'=>(int)$verifiedrecord->sessionid], '*', IGNORE_MISSING);
    }
}

if (optional_param('activatecourse', 0, PARAM_BOOL) && confirm_sesskey()) {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $rawtoken = trim((string)optional_param('activationtoken', '', PARAM_ALPHANUMEXT));
    if (!$courseid || $rawtoken === '') {
        $message = get_string('courseactivationrequired', 'local_unileasysregistration');
        $messagetype = 'warning';
    } else if (!$school || !$class || !$session || !verification::is_verified($userid)) {
        $message = get_string('courseactivationinvalid', 'local_unileasysregistration');
        $messagetype = 'warning';
    } else if (verification::redeem_course_token($rawtoken, $userid, $courseid, (int)$school->id,
            (int)$class->id, $accounttype, (int)$session->id)) {
        \local_unileasysregistration\enrolment_manager::activate_course($userid, $courseid);
        $activationok = true;
        $message = get_string('courseactivated', 'local_unileasysregistration');
        $messagetype = 'success';
    } else {
        $message = get_string('courseactivationinvalid', 'local_unileasysregistration');
        $messagetype = 'warning';
    }
}


if ($ajaxactivation) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $activationok,
        'message' => $message,
    ]);
    exit;
}

$eligible = [];
if ($school && $class && $session && verification::is_verified($userid)) {
    foreach ($DB->get_records('unileasys_enrol_rule', ['active'=>1], 'id ASC') as $rule) {
        if ((int)$rule->sessionid !== (int)$session->id) continue;
        if (\local_unileasysregistration\enrolment_manager::rule_matches_user($rule, $userid)) {
            $course = $DB->get_record('course', ['id'=>$rule->courseid], 'id,fullname,shortname', IGNORE_MISSING);
            if ($course && (int)$course->id !== SITEID) $eligible[(int)$course->id] = $course;
        }
    }
}

$mappings = $DB->get_records('unileasys_enrolment', ['userid'=>$userid], 'id DESC');
$history = [];
foreach ($mappings as $mapping) {
    $course = $DB->get_record('course', ['id'=>$mapping->courseid], 'id,fullname,shortname', IGNORE_MISSING);
    if ($course && (int)$course->id !== SITEID) $history[(int)$course->id] = ['course'=>$course, 'mapping'=>$mapping];
}
foreach ($eligible as $cid=>$course) {
    if (!isset($history[$cid])) $history[$cid] = ['course'=>$course, 'mapping'=>null];
}

// Process any course token before rendering and refresh status from DB.
if ($message !== '') {
    $mappings = $DB->get_records('unileasys_enrolment', ['userid'=>$userid], 'id DESC');
    foreach ($mappings as $mapping) {
        $course = $DB->get_record('course', ['id'=>$mapping->courseid], 'id,fullname,shortname', IGNORE_MISSING);
        if ($course && (int)$course->id !== SITEID) $history[(int)$course->id] = ['course'=>$course, 'mapping'=>$mapping];
    }
}

echo $OUTPUT->header();

if ($message !== '') echo $OUTPUT->notification($message, $messagetype);

echo html_writer::div(
    html_writer::tag('strong', get_string('coursehistory', 'local_unileasysregistration')) .
    html_writer::tag('div', $school ? format_string($school->name) . ' · ' . format_string($class->name) : '—', ['class'=>'small text-muted']),
    'alert alert-info mb-4'
);

if (!$history) {
    echo $OUTPUT->notification(get_string('noeligiblecourses', 'local_unileasysregistration'), 'info');
} else {
    echo html_writer::start_div('table-responsive');
    $table = new html_table();
    $table->attributes['class'] = 'table table-striped align-middle';
    $table->head = ['Course', 'Status', 'Activated', 'Action'];
    foreach ($history as $item) {
        $course = $item['course'];
        $mapping = $item['mapping'];
        $active = $mapping && (int)$mapping->status === ENROL_USER_ACTIVE;
        $statushtml = $active
            ? html_writer::span(get_string('courseactive', 'local_unileasysregistration'), 'badge badge-success')
            : html_writer::span(get_string('courseinactive', 'local_unileasysregistration'), 'badge badge-secondary');
        $activated = $mapping && !empty($mapping->timecreated) ? userdate($mapping->timecreated) : '—';
        $courselabel = format_string($course->fullname) . ($course->shortname ? html_writer::div(s($course->shortname),'small text-muted') : '');
        if ($active) {
            $coursecell = html_writer::link(
                new moodle_url('/course/view.php', ['id'=>$course->id]),
                $courselabel,
                ['class'=>'unileasys-course-link', 'title'=>get_string('opencourse', 'local_unileasysregistration')]
            );
            $action = html_writer::link(
                new moodle_url('/course/view.php', ['id'=>$course->id]),
                get_string('opencourse', 'local_unileasysregistration'),
                ['class'=>'btn btn-sm btn-primary']
            );
        } else {
            $coursecell = html_writer::link(
                '#',
                $courselabel,
                [
                    'class'=>'unileasys-course-activate-link',
                    'data-courseid'=>(int)$course->id,
                    'data-coursename'=>format_string($course->fullname),
                    'title'=>get_string('activatecourse', 'local_unileasysregistration'),
                    'aria-label'=>get_string('activatecourse', 'local_unileasysregistration') . ': ' . format_string($course->fullname),
                ]
            );
            $action = html_writer::tag(
                'button',
                get_string('activatecourse', 'local_unileasysregistration'),
                [
                    'type'=>'button',
                    'class'=>'btn btn-sm btn-primary unileasys-course-activate-link',
                    'data-courseid'=>(int)$course->id,
                    'data-coursename'=>format_string($course->fullname),
                ]
            );
        }
        $table->data[] = [$coursecell, $statushtml, $activated, $action];
    }
    echo html_writer::table($table);
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(new moodle_url('/local/unileasysregistration/studentdashboard.php'), get_string('myunileasys','local_unileasysregistration'), ['class'=>'btn btn-secondary']),
    'mt-4'
);

// Course activation popup. The dialog stays on this page so the user can
// activate a selected course without leaving the course history.
$modalform = html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'id' => 'unileasys-course-activation-form',
]);
$modalform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
$modalform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'activatecourse','value'=>'1']);
$modalform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'courseid','id'=>'unileasys-modal-courseid','value'=>'0']);
$modalform .= html_writer::tag('label', get_string('activationtoken','local_unileasysregistration'), ['for'=>'unileasys-modal-token','class'=>'font-weight-bold']);
$modalform .= html_writer::empty_tag('input', [
    'type'=>'text', 'name'=>'activationtoken', 'id'=>'unileasys-modal-token',
    'class'=>'form-control mt-2', 'placeholder'=>get_string('activationtoken','local_unileasysregistration'),
    'required'=>'required', 'autocomplete'=>'one-time-code',
]);
$modalform .= html_writer::div(get_string('courseactivationhelp','local_unileasysregistration'), 'small text-muted mt-2');
$modalform .= html_writer::div(
    html_writer::tag('button', get_string('activatecourse','local_unileasysregistration'), ['type'=>'submit','class'=>'btn btn-primary']) .
    html_writer::tag('button', get_string('back'), ['type'=>'button','class'=>'btn btn-secondary ml-2','id'=>'unileasys-course-modal-back']),
    'mt-4'
);
$modalform .= html_writer::end_tag('form');

$dialogcontent = html_writer::tag('h4', get_string('courseactivation','local_unileasysregistration'), [
    'id'=>'unileasys-course-modal-title', 'class'=>'mb-3'
]);
$dialogcontent .= html_writer::div('', 'unileasys-course-modal-course', ['id'=>'unileasys-course-modal-course']);
$dialogcontent .= $modalform;
$dialog = html_writer::tag('dialog',
    html_writer::div($dialogcontent, 'unileasys-course-modal-dialog'),
    [
        'id'=>'unileasys-course-activation-modal',
        'aria-labelledby'=>'unileasys-course-modal-title',
    ]
);
echo $dialog;

echo html_writer::script(
    "(function(){
        function init(){
            const modal=document.getElementById('unileasys-course-activation-modal');
            const form=document.getElementById('unileasys-course-activation-form');
            const courseid=document.getElementById('unileasys-modal-courseid');
            const token=document.getElementById('unileasys-modal-token');
            const coursebox=document.getElementById('unileasys-course-modal-course');
            const back=document.getElementById('unileasys-course-modal-back');
            if(!modal||!form||!courseid||!token||!coursebox||!back){return;}
            function closeModal(){
                if(typeof modal.close==='function' && modal.open){modal.close();}
                else {modal.removeAttribute('open');}
                document.body.classList.remove('unileasys-modal-open');
            }
            function openModal(id,name){
                courseid.value=id||'0';
                coursebox.textContent=name||'';
                token.value='';
                if(typeof modal.showModal==='function'){modal.showModal();}
                else {modal.setAttribute('open','open');}
                document.body.classList.add('unileasys-modal-open');
                window.setTimeout(function(){token.focus();},50);
            }
            document.querySelectorAll('.unileasys-course-activate-link').forEach(function(button){
                button.addEventListener('click',function(e){
                    e.preventDefault();
                    openModal(button.getAttribute('data-courseid'),button.getAttribute('data-coursename'));
                });
            });
            back.addEventListener('click',closeModal);
            modal.addEventListener('click',function(e){
                if(e.target===modal){closeModal();}
            });
            modal.addEventListener('cancel',function(e){
                e.preventDefault();
                closeModal();
            });
        }
        if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
    })();"
);

echo html_writer::tag('style',
    '#unileasys-course-activation-modal{border:0;border-radius:12px;padding:0;width:min(520px,calc(100% - 32px));max-width:520px;box-shadow:0 18px 55px rgba(0,0,0,.30);}' .
    '#unileasys-course-activation-modal::backdrop{background:rgba(0,0,0,.55);}' .
    '.unileasys-course-modal-dialog{background:#fff;border-radius:12px;padding:26px;}' .
    '.unileasys-course-modal-course{font-weight:600;font-size:1.05rem;padding:12px 14px;background:#f6f7f9;border-radius:6px;margin-bottom:18px;}' .
    'body.unileasys-modal-open{overflow:hidden;}' .
    '.unileasys-course-activate-link{font-weight:500;}'
);

echo $OUTPUT->footer();
