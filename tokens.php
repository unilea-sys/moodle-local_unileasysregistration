<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
$context = context_system::instance();
require_capability('local/unileasysregistration:manage', $context);
\local_unileasysregistration\license::require_valid();
require_once(__DIR__ . '/classes/verification.php');
require_once(__DIR__ . '/classes/token_export.php');

use local_unileasysregistration\verification;
use local_unileasysregistration\token_export;
use local_unileasysregistration\course_license;

global $DB, $OUTPUT, $PAGE;
verification::ensure_schema();
$PAGE->set_url(new moodle_url('/local/unileasysregistration/tokens.php'));
$PAGE->set_context($context);
$PAGE->set_title('Verification Tokens');
$PAGE->set_heading('Verification Tokens');

$action = optional_param('action', '', PARAM_ALPHA);
$submittedgenerate = optional_param('generate', 0, PARAM_BOOL);
$tokenid = optional_param('tokenid', 0, PARAM_INT);
$reset = optional_param('reset', 0, PARAM_BOOL);
$clearbatch = optional_param('clearbatch', 0, PARAM_BOOL);
if ($clearbatch && confirm_sesskey()) {
    token_export::clear_batch();
    redirect($PAGE->url, 'The previous generated batch has been cleared. You can now generate the next batch.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($reset && confirm_sesskey()) {
    $DB->delete_records_select('unileasys_verify_token', 'usedby IS NULL');
    redirect($PAGE->url, 'All unused generated activation tokens have been permanently cleared. Used-token history and course activation history were not changed.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'delete' && $tokenid > 0 && confirm_sesskey()) {
    $token = $DB->get_record('unileasys_verify_token', ['id' => $tokenid]);
    if ($token) {
        $DB->delete_records('unileasys_verify_token', ['id' => $tokenid]);
        redirect($PAGE->url, 'Activation token record deleted. Any related verification/course history remains stored, but this token record can no longer be reused.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, 'The activation token record could not be found.', null, \core\output\notification::NOTIFY_WARNING);
}
if ($action === 'revoke' && $tokenid > 0 && confirm_sesskey()) {
    if (verification::revoke_token($tokenid)) {
        redirect($PAGE->url, 'Activation token revoked.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, 'The token could not be revoked. It may already be used or inactive.', null,
        \core\output\notification::NOTIFY_WARNING);
}

$exportaction = optional_param('export', '', PARAM_ALPHA);
if (in_array($exportaction, ['csv', 'excel', 'pdf'], true)) {
    require_sesskey();
    $batch = token_export::get_batch();
    if (empty($batch['rows'])) {
        throw new moodle_exception('No activation-code export is available in this session. Generate a new batch first.');
    }
    if ($exportaction === 'csv') {
        token_export::csv($batch);
    } else if ($exportaction === 'excel') {
        token_export::excel($batch);
    } else {
        token_export::pdf($batch);
    }
}

// Course activation tokens are generated from an existing Course License.
// The Course License is the single source of truth for course, school, class,
// account type, academic session and expiry. The generator deliberately does
// not ask the administrator to re-enter any of those fields.
$courselicenses = course_license::all(true);
$licenselabels = [];
$licensemap = [];
foreach ($courselicenses as $license) {
    if ($license->status !== course_license::ACTIVE || (int)$license->expiresat < time()) {
        continue;
    }
    $course = $DB->get_record('course', ['id' => (int)$license->courseid], 'id,fullname,shortname', IGNORE_MISSING);
    $school = $DB->get_record('unileasys_school', ['id' => (int)$license->schoolid], 'id,name', IGNORE_MISSING);
    $class = $DB->get_record('unileasys_class', ['id' => (int)$license->classid], 'id,name', IGNORE_MISSING);
    $session = $DB->get_record('unileasys_session', ['id' => (int)$license->sessionid], 'id,name', IGNORE_MISSING);
    if (!$course || !$school || !$class || !$session) {
        continue;
    }
    $licensemap[(int)$license->id] = [
        'license' => $license,
        'course' => $course,
        'school' => $school,
        'class' => $class,
        'session' => $session,
    ];
    $licenselabels[(int)$license->id] = $course->fullname . ' — ' . $school->name .
        ' — ' . $class->name . ' — ' . ucfirst($license->accounttype) .
        ' — ' . $session->name . ' — Expires ' .
        userdate((int)$license->expiresat, get_string('strftimedatefullshort', 'langconfig'));
}

if ($submittedgenerate && confirm_sesskey()) {
    $licenseid = optional_param('licenseid', 0, PARAM_INT);
    $requestedquantity = max(1, optional_param('quantity', 1, PARAM_INT));

    if ($licenseid <= 0 || !isset($licensemap[$licenseid])) {
        throw new moodle_exception('nocourselicensefortokens', 'local_unileasysregistration');
    }

    $selected = $licensemap[$licenseid];
    $license = $selected['license'];
    $now = time();
    course_license::refresh_status($license);
    if ($license->status !== course_license::ACTIVE || (int)$license->expiresat < $now) {
        throw new moodle_exception('course_license_not_available_for_tokens', 'local_unileasysregistration');
    }

    $issued = course_license::issued_token_count((int)$license->id);
    $available = (int)$license->seats > 0 ? max(0, (int)$license->seats - $issued) : 500;
    if ((int)$license->seats > 0 && $available <= 0) {
        throw new moodle_exception('courselicense_token_capacity_reached', 'local_unileasysregistration');
    }
    if ($requestedquantity > $available) {
        throw new moodle_exception('courselicense_token_quantity_exceeded', 'local_unileasysregistration', '', [
            'requested' => $requestedquantity, 'available' => $available, 'seats' => (int)$license->seats
        ]);
    }
    // Never generate more than the Course License capacity. The browser also
    // sets this as the quantity field's maximum, but the server is authoritative.
    if (\local_unileasysregistration\demo::enabled()) {
        \local_unileasysregistration\demo::require_capacity('tokens', $requestedquantity);
    }
    $quantity = min(500, $requestedquantity, $available);

    $rows = [];
    for ($i = 0; $i < $quantity; $i++) {
        $created = verification::create_token(
            (int)$license->schoolid,
            (int)$license->classid,
            (string)$license->accounttype,
            (int)$license->sessionid,
            1,
            (int)$license->courseid,
            (int)$license->expiresat,
            (int)$license->id
        );
        $rows[] = [
            'token' => $created['token'],
            'school' => $selected['school']->name,
            'class' => $selected['class']->name,
            'accounttype' => ucfirst((string)$license->accounttype),
            'session' => $selected['session']->name,
            'course' => $selected['course']->fullname,
            'expires' => userdate((int)$license->expiresat, get_string('strftimedatefullshort', 'langconfig')),
            'licenseid' => (string)$license->id,
        ];
    }

    $title = $selected['course']->fullname . ' - ' . $selected['school']->name .
        ' - ' . $selected['class']->name . ' Course Tokens';
    token_export::set_batch($rows, $title);

    echo $OUTPUT->header();
    local_unileasysregistration_admin_navigation('tokens');
    echo $OUTPUT->heading('Unileasys Token System');
    echo $OUTPUT->heading('Activation Code Batch Generated', 3);
    echo $OUTPUT->notification(
        'These tokens inherit their expiry date directly from the selected Course License. The token validity cannot be extended separately from the Course License.',
        'warning'
    );
    echo html_writer::tag('p', 'Generated: ' . count($rows) . ' codes. Each code is single-use and is valid only for the selected Course License scope.');
    echo html_writer::tag('p', '<strong>Course License:</strong> ' . s($licenselabels[$licenseid]), [], true);

    $exporturl = function(string $format): moodle_url {
        return new moodle_url('/local/unileasysregistration/tokens.php', [
            'export' => $format,
            'sesskey' => sesskey(),
        ]);
    };
    echo html_writer::start_div('mb-4');
    echo html_writer::tag('strong', 'Download distribution list:');
    echo html_writer::link($exporturl('excel'), 'Excel (.xls)', ['class' => 'btn btn-success m-1']);
    echo html_writer::link($exporturl('csv'), 'CSV (.csv)', ['class' => 'btn btn-secondary m-1']);
    echo html_writer::link($exporturl('pdf'), 'PDF (.pdf)', ['class' => 'btn btn-danger m-1']);
    echo html_writer::link(new moodle_url($PAGE->url, ['clearbatch'=>1, 'sesskey'=>sesskey()]), 'Clear Batch / Generate New', ['class' => 'btn btn-outline-primary m-1']);
    echo html_writer::end_div();

    $table = new html_table();
    $table->head = ['No.', 'Activation Code', 'School', 'Class', 'Type', 'Session', 'Course / Purpose', 'License', 'Expires'];
    foreach ($rows as $i => $row) {
        $table->data[] = [$i + 1, html_writer::tag('code', s($row['token'])), s($row['school']),
            s($row['class']), s($row['accounttype']), s($row['session']), s($row['course']), s($row['licenseid']), s($row['expires'])];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo html_writer::div(
        html_writer::link(new moodle_url($PAGE->url, ['clearbatch'=>1, 'sesskey'=>sesskey()]), 'Clear Batch / Generate New', ['class'=>'btn btn-secondary mt-3']),
        'mt-2'
    );
    echo $OUTPUT->footer();
    exit;
}

$filterstatus = optional_param('status', 'all', PARAM_ALPHA);
$where = '';
$params = [];
if ($filterstatus === 'unused') {
    $where = 'active = 1 AND usedby IS NULL AND expiresat >= :now';
    $params['now'] = time();
} else if ($filterstatus === 'used') {
    $where = 'usedby IS NOT NULL';
} else if ($filterstatus === 'expired') {
    $where = 'active = 1 AND usedby IS NULL AND expiresat < :now';
    $params['now'] = time();
} else if ($filterstatus === 'revoked') {
    $where = 'active = 0 AND usedby IS NULL';
}
$tokens = $DB->get_records_select('unileasys_verify_token', $where ?: '1=1', $params, 'id DESC', '*', 0, 500);

$users = [];
foreach ($tokens as $token) {
    if (!empty($token->usedby) && !isset($users[$token->usedby])) {
        $users[$token->usedby] = $DB->get_record('user', ['id' => $token->usedby], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email');
    }
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('tokens');
echo $OUTPUT->heading('Unileasys Token System');
    echo $OUTPUT->heading('Generate School Activation Codes', 3);
echo html_writer::tag('p', 'Select an existing Course License. All token details — course, school, class, account type, academic session and expiry date — are taken automatically from that Course License. The token expiry cannot be changed here.');

$formurl = new moodle_url('/local/unileasysregistration/tokens.php');
echo html_writer::start_tag('form', ['method'=>'post','action'=>$formurl->out(false), 'class'=>'mb-5']);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'generate','value'=>'1']);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
echo html_writer::label('Course License', 'activation-licenseid');
echo html_writer::start_tag('select', ['name'=>'licenseid', 'id'=>'activation-licenseid', 'class'=>'form-control mb-3', 'required'=>'required']);
echo html_writer::tag('option', '— Select Course License —', ['value'=>'0']);
foreach ($licenselabels as $id => $label) {
    echo html_writer::tag('option', s($label), ['value'=>(int)$id]);
}
echo html_writer::end_tag('select');
if (!$licenselabels) {
    echo $OUTPUT->notification('No active Course Licenses are currently available for token generation.', 'info');
}
echo html_writer::tag('div', 'The selected Course License controls the complete token scope and expiry date. If the Course License expires, newly generated tokens cannot be generated from it and existing tokens will also expire on the same date.', ['class'=>'small text-muted mb-3']);
echo html_writer::label('Number of activation codes', 'quantity');
$firstmax = 500;
if ($licenselabels) {
    $firstlicenseid = array_key_first($licenselabels);
    $firstlicense = $licensemap[$firstlicenseid]['license'];
    $firstissued = course_license::issued_token_count((int)$firstlicense->id);
    $firstmax = (int)$firstlicense->seats > 0 ? max(0, (int)$firstlicense->seats - $firstissued) : 500;
}
echo html_writer::empty_tag('input', ['type'=>'number','name'=>'quantity','id'=>'token-quantity','value'=>$firstmax > 0 ? 1 : 0,'min'=>1,'max'=>$firstmax,'class'=>'form-control mb-3', 'required'=>'required']);
echo html_writer::tag('div', 'Select a Course License to see the maximum number of codes available.', ['id'=>'token-capacity-help','class'=>'small text-muted mb-3']);
echo html_writer::empty_tag('input', ['type'=>'submit','id'=>'generate-activation-codes','value'=>'Generate Activation Codes','class'=>'btn btn-primary', 'disabled'=>empty($licenselabels) || $firstmax < 1]);
if ($licenselabels) {
    $capacitymap = [];
    foreach ($licensemap as $lid => $entry) {
        $lic = $entry['license'];
        $issued = course_license::issued_token_count((int)$lic->id);
        $capacitymap[(string)$lid] = (int)$lic->seats > 0 ? max(0, (int)$lic->seats - $issued) : 500;
    }
    echo html_writer::script('(function(){var s=document.getElementById("activation-licenseid"),b=document.getElementById("generate-activation-codes"),q=document.getElementById("token-quantity"),h=document.getElementById("token-capacity-help"),caps=' . json_encode($capacitymap) . '; if(!s||!b||!q)return; function sync(){var cap=Number(caps[s.value]||0); q.max=cap; if(cap<1){q.value=0;b.disabled=true;}else{if(Number(q.value)<1||Number(q.value)>cap)q.value=1;b.disabled=false;} if(h)h.textContent=cap<1?"No codes are currently available for this Course License.":"Maximum available codes for this Course License: "+cap+".";} s.addEventListener("change",sync); sync();})();');
}
echo html_writer::end_tag('form');

echo $OUTPUT->heading('Activation Code Management', 3);
echo html_writer::div(
    html_writer::link(new moodle_url($PAGE->url, ['status'=>'all']), 'All', ['class'=>'btn btn-sm btn-secondary mr-1']) .
    html_writer::link(new moodle_url($PAGE->url, ['status'=>'unused']), 'Unused', ['class'=>'btn btn-sm btn-secondary mr-1']) .
    html_writer::link(new moodle_url($PAGE->url, ['status'=>'used']), 'Used', ['class'=>'btn btn-sm btn-secondary mr-1']) .
    html_writer::link(new moodle_url($PAGE->url, ['status'=>'expired']), 'Expired', ['class'=>'btn btn-sm btn-secondary mr-1']) .
    html_writer::link(new moodle_url($PAGE->url, ['status'=>'revoked']), 'Revoked', ['class'=>'btn btn-sm btn-secondary']),
    'mb-3'
);
echo html_writer::div(
    html_writer::link(new moodle_url($PAGE->url, ['reset'=>1, 'sesskey'=>sesskey()]),
        'Reset / Clear Unused Tokens',
        ['class'=>'btn btn-outline-danger', 'onclick'=>"return confirm('PERMANENTLY DELETE all unused generated activation tokens? Used-token history and course activation history will not be changed.');"]),
    'mb-4'
);

$table = new html_table();
$table->head = ['Code', 'School', 'Class', 'Type', 'Session', 'Course / Purpose', 'Status', 'Used By', 'Used On', 'Action'];
foreach ($tokens as $token) {
    $status = 'Unused';
    if (!empty($token->usedby)) {
        $status = 'Used';
    } else if (!(int)$token->active) {
        $status = 'Revoked';
    } else if ((int)$token->expiresat < time()) {
        $status = 'Expired';
    }
    $usedby = !empty($token->usedby) && !empty($users[$token->usedby]) ? fullname($users[$token->usedby]) : '—';
    $usedon = !empty($token->usedtime) ? userdate($token->usedtime) : '—';
    $actions = html_writer::link(new moodle_url($PAGE->url, [
        'action'=>'delete','tokenid'=>$token->id,'sesskey'=>sesskey()
    ]), 'Delete', [
        'class'=>'btn btn-sm btn-outline-danger',
        'onclick'=>"return confirm('Permanently delete this token record? If it was used, its token-history record will be removed. Existing verification/course activation history will not be deleted.');"
    ]);
    if ($status === 'Unused') {
        $actions = html_writer::link(new moodle_url($PAGE->url, [
            'action'=>'revoke','tokenid'=>$token->id,'sesskey'=>sesskey()
        ]), 'Revoke', ['class'=>'btn btn-sm btn-outline-danger mr-1']) . ' ' . $actions;
    }
    $table->data[] = [
        html_writer::tag('code', s($token->tokenprefix) . '••••••••'),
        s($token->schoolid && isset($schoolmap[$token->schoolid]) ? $schoolmap[$token->schoolid] : 'All Schools'),
        s($token->classid && isset($classmap[$token->classid]) ? $classmap[$token->classid] : 'All Classes'),
        s(ucfirst($token->accounttype)),
        s($token->sessionid && isset($sessionmap[$token->sessionid]) ? $sessionmap[$token->sessionid] : 'Any active session'),
        s(!empty($token->courseid) && isset($coursemap[$token->courseid]) ? $coursemap[$token->courseid] : 'Account Verification'),
        s($status),
        s($usedby),
        s($usedon),
        $actions,
    ];
}
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('No activation codes match this filter.', 'info');
}

echo $OUTPUT->footer();
