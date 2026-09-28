<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/unileasysregistration:manage', $context);
\local_unileasysregistration\license::require_valid();

global $DB, $OUTPUT, $PAGE;

\local_unileasysregistration\course_license::ensure_schema();

$PAGE->set_url(new moodle_url('/local/unileasysregistration/courselicenses.php'));
$PAGE->set_context($context);
$PAGE->set_title('Course Licenses');
$PAGE->set_heading('Course Licenses');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action === 'status' && $id && confirm_sesskey()) {
    $status = optional_param('value', '', PARAM_ALPHA);
    if (\local_unileasysregistration\course_license::set_status($id, $status)) {
        redirect($PAGE->url, 'Course license status updated.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, 'The course license could not be updated.', null, \core\output\notification::NOTIFY_WARNING);
}

if ($action === 'renew' && $id && confirm_sesskey()) {
    $expiry = trim((string)optional_param('expiresat', '', PARAM_RAW_TRIMMED));
    $timestamp = $expiry !== '' ? strtotime($expiry . ' 23:59:59') : 0;
    if ($timestamp && \local_unileasysregistration\course_license::renew($id, $timestamp)) {
        redirect($PAGE->url, 'Course license renewed successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, 'Please provide a valid expiry date after the license start date.', null, \core\output\notification::NOTIFY_WARNING);
}

if ($action === 'delete' && $id && confirm_sesskey()) {
    try {
        if (\local_unileasysregistration\course_license::delete($id)) {
            redirect($PAGE->url, 'Course license deleted successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($PAGE->url, 'The course license could not be deleted because it was not found.', null, \core\output\notification::NOTIFY_WARNING);
    } catch (\Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'update' && $id && confirm_sesskey()) {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $schoolid = optional_param('schoolid', 0, PARAM_INT);
    $classid = optional_param('classid', 0, PARAM_INT);
    $accounttype = strtolower(trim((string)optional_param('accounttype', '', PARAM_ALPHANUMEXT)));
    $sessionid = optional_param('sessionid', 0, PARAM_INT);
    $seats = max(0, optional_param('seats', 0, PARAM_INT));
    $startdate = trim((string)optional_param('startdate', '', PARAM_RAW_TRIMMED));
    $expirydate = trim((string)optional_param('expirydate', '', PARAM_RAW_TRIMMED));

    $start = $startdate !== '' ? strtotime($startdate . ' 00:00:00') : 0;
    $expiry = $expirydate !== '' ? strtotime($expirydate . ' 23:59:59') : 0;

    try {
        \local_unileasysregistration\demo::cap_seats($seats);
        if (\local_unileasysregistration\course_license::update($id, [
            'courseid' => $courseid,
            'schoolid' => $schoolid,
            'classid' => $classid,
            'accounttype' => $accounttype,
            'sessionid' => $sessionid,
            'seats' => $seats,
            'startsat' => $start,
            'expiresat' => $expiry,
        ])) {
            redirect($PAGE->url, 'Course license updated successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($PAGE->url, 'The course license could not be updated.', null, \core\output\notification::NOTIFY_WARNING);
    } catch (\Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'createfromrule' && confirm_sesskey()) {
    $ruleid = optional_param('ruleid', 0, PARAM_INT);
    $seats = max(0, optional_param('seats', 0, PARAM_INT));
    $startdate = trim((string)optional_param('startdate', '', PARAM_RAW_TRIMMED));
    $expirydate = trim((string)optional_param('expirydate', '', PARAM_RAW_TRIMMED));
    $start = $startdate !== '' ? strtotime($startdate . ' 00:00:00') : 0;
    $expiry = $expirydate !== '' ? strtotime($expirydate . ' 23:59:59') : 0;

    try {
        \local_unileasysregistration\demo::cap_seats($seats);
        \local_unileasysregistration\demo::require_capacity('courselicenses');
        $result = \local_unileasysregistration\course_license::create_from_rule($ruleid, $seats, $start, $expiry);
        $message = count($result['created']) . ' Course License(s) created from the selected enrolment rule.';
        if (!empty($result['skipped'])) {
            $message .= ' ' . count($result['skipped']) . ' scope(s) were skipped because they were already registered or invalid.';
        }
        redirect($PAGE->url, $message, null,
            !empty($result['created']) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
    } catch (\Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$schools = $DB->get_records('unileasys_school', ['active' => 1], 'name ASC');
$classes = $DB->get_records('unileasys_class', ['active' => 1], 'sortorder ASC, name ASC');
$sessions = $DB->get_records('unileasys_session', [], 'name DESC');
$courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id,fullname,shortname');

$schooloptions = [];
foreach ($schools as $row) {
    $schooloptions[$row->id] = $row->name;
}
$classoptions = [];
foreach ($classes as $row) {
    $classoptions[$row->id] = $row->name;
}
$sessionoptions = [];
foreach ($sessions as $row) {
    $sessionoptions[$row->id] = $row->name;
}
$courseoptions = [];
foreach ($courses as $course) {
    $courseoptions[$course->id] = $course->fullname;
}

$editlicense = null;
if ($action === 'edit' && $id) {
    $editlicense = $DB->get_record('unileasys_course_license', ['id' => $id], '*', IGNORE_MISSING);
    if (!$editlicense) {
        redirect($PAGE->url, 'Course license not found.', null, \core\output\notification::NOTIFY_WARNING);
    }
    \local_unileasysregistration\course_license::refresh_status($editlicense);
}

$render_select = function($name, $label, $options, $selected = 0, $required = true) {
    echo html_writer::label($label, 'cl-' . $name, false, ['class' => 'font-weight-bold']);
    $attrs = ['name' => $name, 'id' => 'cl-' . $name, 'class' => 'form-control mb-3'];
    if ($required) {
        $attrs['required'] = 'required';
    }
    echo html_writer::start_tag('select', $attrs);
    echo html_writer::tag('option', '— Select —', ['value' => '0', 'selected' => ((int)$selected === 0 ? 'selected' : null)]);
    foreach ($options as $optionid => $text) {
        $attrs = ['value' => (int)$optionid];
        if ((int)$selected === (int)$optionid) {
            $attrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', s($text), $attrs);
    }
    echo html_writer::end_tag('select');
};

$render_license_form = function($editlicense = null) use ($PAGE, $render_select, $courseoptions, $schooloptions, $classoptions, $sessionoptions) {
    $editing = $editlicense !== null;
    $heading = $editing ? 'Edit Course License' : 'Create Course License';
    $formaction = $editing ? 'update' : 'create';

    echo html_writer::start_div('card p-4 mb-4');
    echo $GLOBALS['OUTPUT']->heading($heading, 3);

    if ($editing) {
        echo html_writer::div(
            'License key: ' . html_writer::tag('code', s($editlicense->licensekey)),
            'alert alert-secondary'
        );
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $formaction]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    if ($editing) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$editlicense->id]);
    }

    if ($editing && !empty($editlicense->ruleid)) {
        echo html_writer::div('This Course License was created from Enrollment Rule #' . (int)$editlicense->ruleid . '. Course, school, class, account type and academic session are inherited from that rule and cannot be changed here.', 'alert alert-secondary');
        foreach (['courseid','schoolid','classid','sessionid'] as $field) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $field, 'value' => (int)$editlicense->{$field}]);
        }
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'accounttype', 'value' => s($editlicense->accounttype)]);
    } else {
        $render_select('courseid', 'Course', $courseoptions, $editing ? $editlicense->courseid : 0);
        $render_select('schoolid', 'School', $schooloptions, $editing ? $editlicense->schoolid : 0);
        $render_select('classid', 'Class', $classoptions, $editing ? $editlicense->classid : 0);
        echo html_writer::label('Account Type', 'cl-accounttype', false, ['class' => 'font-weight-bold']);
        echo html_writer::start_tag('select', ['name' => 'accounttype', 'id' => 'cl-accounttype', 'class' => 'form-control mb-3', 'required' => 'required']);
        echo html_writer::tag('option', '— Select —', ['value' => '']);
        foreach (['student' => 'Student', 'teacher' => 'Teacher'] as $value => $label) {
            $attrs = ['value' => $value];
            if ($editing && strtolower((string)$editlicense->accounttype) === $value) { $attrs['selected'] = 'selected'; }
            echo html_writer::tag('option', $label, $attrs);
        }
        echo html_writer::end_tag('select');
        $render_select('sessionid', 'Academic Session', $sessionoptions, $editing ? $editlicense->sessionid : 0);
    }

    $seats = $editing ? (int)$editlicense->seats : 0;
    echo html_writer::label('Seats', 'cl-seats', false, ['class' => 'font-weight-bold']);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'seats', 'id' => 'cl-seats', 'value' => $seats, 'min' => '0', 'class' => 'form-control mb-1']);
    echo html_writer::div('Use 0 for unlimited seats.', 'small text-muted mb-3');

    $startvalue = $editing ? date('Y-m-d', (int)$editlicense->startsat) : date('Y-m-d');
    $expiryvalue = $editing ? date('Y-m-d', (int)$editlicense->expiresat) : date('Y-m-d', strtotime('+1 year'));
    echo html_writer::label('Start Date', 'cl-startdate', false, ['class' => 'font-weight-bold']);
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'startdate', 'id' => 'cl-startdate', 'value' => $startvalue, 'class' => 'form-control mb-3', 'required' => 'required']);
    echo html_writer::label('Expiry Date', 'cl-expirydate', false, ['class' => 'font-weight-bold']);
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'expirydate', 'id' => 'cl-expirydate', 'value' => $expiryvalue, 'class' => 'form-control mb-3', 'required' => 'required']);

    $buttonlabel = $editing ? 'Update Course License' : 'Create Course License';
    echo html_writer::tag('button', $buttonlabel, ['type' => 'submit', 'class' => 'btn btn-primary mr-2']);
    if ($editing) {
        echo html_writer::link($PAGE->url, 'Cancel', ['class' => 'btn btn-secondary']);
    }
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
};

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('courselicenses');
echo $OUTPUT->heading('Course Licenses');

// Detailed license view.
if ($action === 'view' && $id) {
    $license = $DB->get_record('unileasys_course_license', ['id' => $id], '*', IGNORE_MISSING);
    if (!$license) {
        redirect($PAGE->url, 'Course license not found.', null, \core\output\notification::NOTIFY_WARNING);
    }
    \local_unileasysregistration\course_license::refresh_status($license);

    $detailcourse = $DB->get_record('course', ['id' => $license->courseid], 'id,fullname,shortname', IGNORE_MISSING);
    $detailschool = $DB->get_record('unileasys_school', ['id' => $license->schoolid], 'id,name', IGNORE_MISSING);
    $detailclass = $DB->get_record('unileasys_class', ['id' => $license->classid], 'id,name', IGNORE_MISSING);
    $detailsession = $DB->get_record('unileasys_session', ['id' => $license->sessionid], 'id,name', IGNORE_MISSING);
    $used = \local_unileasysregistration\course_license::used_seats((int)$license->id);
    $seattext = (int)$license->seats > 0 ? $used . ' / ' . (int)$license->seats : $used . ' / Unlimited';
    $status = ucfirst((string)$license->status);

    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
    echo $OUTPUT->heading('Course License Details', 3, 'mb-0');
    echo html_writer::div(
        html_writer::link(new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $license->id]), 'Edit', ['class' => 'btn btn-primary mr-2']) .
        html_writer::link(new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $license->id, 'sesskey' => sesskey()]), 'Delete', [
            'class' => 'btn btn-danger',
            'onclick' => "return confirm('Delete this course license? This cannot be undone.');"
        ]),
        'd-flex align-items-center'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    $details = [
        'License Key' => html_writer::tag('code', s($license->licensekey)),
        'Enrollment Rule' => !empty($license->ruleid) ? html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => 'edit', 'id' => (int)$license->ruleid]), 'Rule #' . (int)$license->ruleid) : 'Legacy / manually created',
        'Course' => s($detailcourse ? $detailcourse->fullname : ('Course #' . $license->courseid)),
        'School' => s($detailschool ? $detailschool->name : '—'),
        'Class' => s($detailclass ? $detailclass->name : '—'),
        'Account Type' => s(ucfirst((string)$license->accounttype)),
        'Academic Session' => s($detailsession ? $detailsession->name : '—'),
        'Seats' => s($seattext),
        'Start Date' => !empty($license->startsat) ? userdate($license->startsat) : '—',
        'Expiry Date' => !empty($license->expiresat) ? userdate($license->expiresat) : '—',
        'Status' => s($status),
        'Created' => !empty($license->timecreated) ? userdate($license->timecreated) : '—',
        'Last Modified' => !empty($license->timemodified) ? userdate($license->timemodified) : '—',
    ];
    $detailtable = new html_table();
    $detailtable->attributes['class'] = 'table table-sm table-bordered mb-0';
    foreach ($details as $label => $value) {
        $detailtable->data[] = [html_writer::tag('strong', $label), $value];
    }
    echo html_writer::table($detailtable);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Current users consuming this license.
    $sql = "SELECT ue.id AS enrolmentid, ue.userid, ue.status AS enrolstatus, ue.timecreated AS enrolledat,
                   u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                   u.username, u.email,
                   v.schoolid, v.classid, v.accounttype, v.sessionid
              FROM {unileasys_enrolment} ue
              JOIN {user} u ON u.id = ue.userid
              JOIN {unileasys_verification} v ON v.userid = ue.userid
             WHERE ue.courseid = :courseid
               AND ue.status = :active
               AND v.status = :verified
               AND v.schoolid = :schoolid
               AND v.classid = :classid
               AND LOWER(v.accounttype) = :accounttype
               AND v.sessionid = :sessionid
          ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";
    $users = $DB->get_records_sql($sql, [
        'courseid' => (int)$license->courseid,
        'active' => ENROL_USER_ACTIVE,
        'verified' => \local_unileasysregistration\verification::VERIFIED,
        'schoolid' => (int)$license->schoolid,
        'classid' => (int)$license->classid,
        'accounttype' => strtolower((string)$license->accounttype),
        'sessionid' => (int)$license->sessionid,
    ]);

    echo $OUTPUT->heading('Users Using This License', 3);
    if (!$users) {
        echo $OUTPUT->notification('No active Unileasys-managed users are currently consuming seats from this license.', 'info');
    } else {
        $usertable = new html_table();
        $usertable->attributes['class'] = 'table table-bordered table-striped';
        $usertable->head = ['User', 'Username', 'Email', 'Account Type', 'Enrollment Date', 'Status'];
        foreach ($users as $user) {
            $usertable->data[] = [
                html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->userid]), fullname($user)),
                s($user->username),
                s($user->email),
                s(ucfirst((string)$user->accounttype)),
                !empty($user->enrolledat) ? userdate($user->enrolledat) : '—',
                'Active',
            ];
        }
        echo html_writer::div(html_writer::table($usertable), 'table-responsive');
    }

    $activationhistory = $DB->count_records('unileasys_course_activation', ['licenseid' => $license->id]);
    echo html_writer::start_div('card mt-4 mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('strong', 'Activation History: ') . html_writer::tag('span', (string)$activationhistory);
    echo html_writer::div('Activation history is retained for audit and protection against accidental deletion.', 'small text-muted mt-1');
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::link(new moodle_url($PAGE->url), '← Back to Course Licenses', ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    'Course licenses control access to a specific Moodle course for one school, class, account type and academic session. Expiry and seat limits are maintained inside Unileasys. The commercial plugin license shown under License remains separate.',
    'alert alert-info'
);

if ($editlicense) {
    $render_license_form($editlicense);
} else {
    $rules = $DB->get_records('unileasys_enrol_rule', ['active' => 1], 'id DESC');
    echo html_writer::start_div('card p-4 mb-4');
    echo $OUTPUT->heading('Create Course License from Enrollment Rule', 3);
    echo html_writer::div(
        'Enrollment Rule is the first layer. It defines the course, school, class, account type and academic session. Course License is the second layer and adds the entitlement period and seat limit. Verification Tokens are generated only after a Course License exists.',
        'alert alert-info'
    );
    if (!$rules) {
        echo $OUTPUT->notification('No active Enrollment Rules are available. Create an Enrollment Rule first.', 'warning');
    } else {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createfromrule']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::label('Enrollment Rule', 'cl-ruleid', false, ['class' => 'font-weight-bold']);
        echo html_writer::start_tag('select', ['name' => 'ruleid', 'id' => 'cl-ruleid', 'class' => 'form-control mb-3', 'required' => 'required']);
        echo html_writer::tag('option', '— Select Enrollment Rule —', ['value' => '0']);
        foreach ($rules as $rule) {
            $course = $DB->get_record('course', ['id' => $rule->courseid], 'id,fullname', IGNORE_MISSING);
            $schoolsrule = json_decode((string)$rule->schoolids, true) ?: [];
            $classesrule = json_decode((string)$rule->classids, true) ?: [];
            $typesrule = json_decode((string)$rule->accounttypes, true) ?: [];
            $schoolnames = [];
            foreach ($schoolsrule as $sid) {
                if ($sr = $DB->get_record('unileasys_school', ['id' => (int)$sid], 'id,name', IGNORE_MISSING)) { $schoolnames[] = $sr->name; }
            }
            $classnames = [];
            foreach ($classesrule as $cid) {
                if ($cr = $DB->get_record('unileasys_class', ['id' => (int)$cid], 'id,name', IGNORE_MISSING)) { $classnames[] = $cr->name; }
            }
            $sessionrule = $DB->get_record('unileasys_session', ['id' => (int)$rule->sessionid], 'id,name', IGNORE_MISSING);
            $label = ($course ? $course->fullname : 'Course #' . $rule->courseid) .
                ' — ' . ($schoolnames ? implode(', ', $schoolnames) : 'All Schools') .
                ' — ' . ($classnames ? implode(', ', $classnames) : 'All Classes') .
                ' — ' . ($typesrule ? ucfirst(implode(', ', $typesrule)) : 'Account type not set') .
                ' — ' . ($sessionrule ? $sessionrule->name : 'Session #' . $rule->sessionid);
            echo html_writer::tag('option', $label, ['value' => (int)$rule->id]);
        }
        echo html_writer::end_tag('select');
        echo html_writer::div('The selected rule supplies all course/school/class/account/session information. If a rule contains multiple schools or classes, one Course License will be created for each concrete combination. Rules using “all schools” or “all classes” must be made specific before a Course License can be created.', 'small text-muted mb-3');

        echo html_writer::label('Seats', 'cl-seats', false, ['class' => 'font-weight-bold']);
        echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'seats', 'id' => 'cl-seats', 'value' => 0, 'min' => 0, 'class' => 'form-control mb-1']);
        echo html_writer::div('Use 0 for unlimited seats.', 'small text-muted mb-3');
        echo html_writer::label('Start Date', 'cl-startdate', false, ['class' => 'font-weight-bold']);
        echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'startdate', 'id' => 'cl-startdate', 'value' => date('Y-m-d'), 'class' => 'form-control mb-3', 'required' => 'required']);
        echo html_writer::label('Expiry Date', 'cl-expirydate', false, ['class' => 'font-weight-bold']);
        echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'expirydate', 'id' => 'cl-expirydate', 'value' => date('Y-m-d', strtotime('+1 year')), 'class' => 'form-control mb-3', 'required' => 'required']);
        echo html_writer::tag('button', 'Create Course License(s)', ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
}

$licenses = \local_unileasysregistration\course_license::all(true);
$schoolmap = []; foreach ($schools as $r) $schoolmap[$r->id] = $r->name;
$classmap = []; foreach ($classes as $r) $classmap[$r->id] = $r->name;
$sessionmap = []; foreach ($sessions as $r) $sessionmap[$r->id] = $r->name;
$coursem = []; foreach ($courses as $r) $coursem[$r->id] = $r->fullname;

$table = new html_table();
$table->attributes['class'] = 'table table-bordered table-striped';
$table->head = ['License', 'Course', 'School', 'Class', 'Type', 'Session', 'Seats Used', 'Expiry', 'Status', 'Actions'];

foreach ($licenses as $lic) {
    $used = \local_unileasysregistration\course_license::used_seats((int)$lic->id);
    $seattext = (int)$lic->seats > 0 ? $used . ' / ' . (int)$lic->seats : $used . ' / Unlimited';
    $status = ucfirst((string)$lic->status);
    $actions = [];

    $actions[] = html_writer::link(new moodle_url($PAGE->url, [
        'action' => 'view', 'id' => $lic->id
    ]), 'View', ['class' => 'btn btn-sm btn-outline-info mr-1']);

    $actions[] = html_writer::link(new moodle_url($PAGE->url, [
        'action' => 'edit', 'id' => $lic->id
    ]), 'Edit', ['class' => 'btn btn-sm btn-outline-primary mr-1']);

    $actions[] = html_writer::link(new moodle_url($PAGE->url, [
        'action' => 'delete', 'id' => $lic->id, 'sesskey' => sesskey()
    ]), 'Delete', ['class' => 'btn btn-sm btn-outline-danger mr-1',
        'onclick' => "return confirm('Delete this course license? This cannot be undone.');"]);

    if (in_array($lic->status, [\local_unileasysregistration\course_license::ACTIVE, \local_unileasysregistration\course_license::SUSPENDED], true)) {
        $newstatus = $lic->status === \local_unileasysregistration\course_license::ACTIVE ? 'suspended' : 'active';
        $label = $newstatus === 'active' ? 'Activate' : 'Suspend';
        $actions[] = html_writer::link(new moodle_url($PAGE->url, [
            'action' => 'status', 'id' => $lic->id, 'value' => $newstatus, 'sesskey' => sesskey()
        ]), $label, ['class' => 'btn btn-sm btn-outline-secondary mr-1']);
        $actions[] = html_writer::link(new moodle_url($PAGE->url, [
            'action' => 'status', 'id' => $lic->id, 'value' => 'revoked', 'sesskey' => sesskey()
        ]), 'Revoke', ['class' => 'btn btn-sm btn-outline-danger mr-1',
            'onclick' => "return confirm('Revoke this course license? Existing access will be suspended.');"]);
    }

    $renewform = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'd-inline-flex align-items-center']);
    $renewform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'action','value'=>'renew']);
    $renewform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'id','value'=>(int)$lic->id]);
    $renewform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
    $renewform .= html_writer::empty_tag('input', ['type'=>'date','name'=>'expiresat','value'=>date('Y-m-d',(int)$lic->expiresat),'class'=>'form-control form-control-sm mr-1','required'=>'required']);
    $renewform .= html_writer::tag('button','Renew',['type'=>'submit','class'=>'btn btn-sm btn-outline-primary']);
    $renewform .= html_writer::end_tag('form');
    $actions[] = $renewform;

    $table->data[] = [
        html_writer::tag('code', s($lic->licensekey)),
        s($coursem[$lic->courseid] ?? ('Course #' . $lic->courseid)),
        s($schoolmap[$lic->schoolid] ?? '—'),
        s($classmap[$lic->classid] ?? '—'),
        s(ucfirst($lic->accounttype)),
        s($sessionmap[$lic->sessionid] ?? '—'),
        s($seattext),
        !empty($lic->expiresat) ? userdate($lic->expiresat) : '—',
        s($status),
        implode(' ', $actions),
    ];
}

if (!$table->data) {
    echo $OUTPUT->notification('No course licenses have been created yet.', 'info');
} else {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}
echo $OUTPUT->footer();
