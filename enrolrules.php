<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
require_capability('local/unileasysregistration:manage', context_system::instance());
\local_unileasysregistration\license::require_valid();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_unileasysregistration\enrolment_manager;
use local_unileasysregistration\schema;

global $DB, $PAGE, $OUTPUT;
$context = context_system::instance();
schema::ensure_enrolment_tables();
schema::ensure_master_data();

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

class local_unileasysregistration_rule_form extends moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // Build the course list directly from the course table.
        // Do not use the legacy get_courses() helper here: its category/field
        // handling differs across Moodle versions and can trigger a database
        // exception on Moodle 5.2 when used from a custom form.
        $courses = [];
        $courserecords = $DB->get_records_select(
            'course',
            'id <> :siteid',
            ['siteid' => SITEID],
            'fullname ASC',
            'id, fullname, shortname'
        );
        foreach ($courserecords as $course) {
            $courses[(int)$course->id] = format_string($course->fullname) . ' (' . s($course->shortname) . ')';
        }
        if (!$courses) {
            $mform->addElement('static', 'nocourses', '', get_string('norulecourses', 'local_unileasysregistration'));
        } else {
            $mform->addElement('select', 'courseid', get_string('rulecourse', 'local_unileasysregistration'), $courses);
            $mform->addRule('courseid', null, 'required', null, 'client');
        }

        $schools = ['' => get_string('allschools', 'local_unileasysregistration')];
        foreach ($DB->get_records('unileasys_school', ['active' => 1], 'name ASC') as $school) {
            $schools[$school->id] = $school->name;
        }
        $mform->addElement('select', 'schoolids', get_string('ruleschools', 'local_unileasysregistration'), $schools,
            ['multiple' => true, 'size' => min(7, max(2, count($schools)))]);
        $mform->addHelpButton('schoolids', 'ruleschools', 'local_unileasysregistration');

        $classes = ['' => get_string('allclasses', 'local_unileasysregistration')];
        foreach ($DB->get_records('unileasys_class', ['active' => 1], 'sortorder ASC, name ASC') as $class) {
            $classes[$class->id] = $class->name;
        }
        $mform->addElement('select', 'classids', get_string('ruleclasses', 'local_unileasysregistration'), $classes,
            ['multiple' => true, 'size' => min(7, max(2, count($classes)))]);
        $mform->addHelpButton('classids', 'ruleclasses', 'local_unileasysregistration');

        $types = [
            'student' => get_string('student', 'local_unileasysregistration'),
            'teacher' => get_string('teacher', 'local_unileasysregistration'),
        ];
        // Account type is deliberately a single choice. A rule must resolve to one
        // Moodle role, so allowing Student + Teacher together would create an
        // ambiguous role assignment.
        $mform->addElement('select', 'accounttype', get_string('ruletypes', 'local_unileasysregistration'), $types);
        $mform->addRule('accounttype', null, 'required', null, 'client');
        $mform->setDefault('accounttype', 'student');
        $mform->addHelpButton('accounttype', 'ruletypes', 'local_unileasysregistration');

        $sessions = [];
        foreach ($DB->get_records('unileasys_session', null, 'name DESC') as $session) {
            $sessions[$session->id] = $session->name . ($session->active ? ' — active' : '');
        }
        if ($sessions) {
            $mform->addElement('select', 'sessionid', get_string('rulesession', 'local_unileasysregistration'), $sessions);
            $mform->addRule('sessionid', null, 'required', null, 'client');
        } else {
            $mform->addElement('static', 'nosessions', '', get_string('norulesessions', 'local_unileasysregistration'));
        }

        // Only the two roles represented by Unileasys Account Type are exposed.
        // This prevents Manager/Teacher/Student role conflicts in an automatic rule.
        $roles = [];
        $studentrole = null;
        $teacherrole = null;
        foreach ($DB->get_records('role', null, 'sortorder ASC') as $role) {
            if ($role->archetype === 'student' && $studentrole === null) {
                $studentrole = $role;
                $roles[$role->id] = role_get_name($role, context_system::instance());
            } else if ($role->archetype === 'teacher' && $teacherrole === null) {
                $teacherrole = $role;
                $roles[$role->id] = role_get_name($role, context_system::instance());
            }
        }
        if (!$studentrole) {
            $studentrole = $DB->get_record('role', ['shortname' => 'student']);
            if ($studentrole) {
                $roles[$studentrole->id] = role_get_name($studentrole, context_system::instance());
            }
        }
        if (!$teacherrole) {
            $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
            if ($teacherrole) {
                $roles[$teacherrole->id] = role_get_name($teacherrole, context_system::instance());
            }
        }
        $mform->addElement('select', 'roleid', get_string('rulerole', 'local_unileasysregistration'), $roles);
        $mform->addRule('roleid', null, 'required', null, 'client');
        if ($studentrole) {
            $mform->setDefault('roleid', $studentrole->id);
        }

        $mform->addElement('select', 'active', get_string('status', 'local_unileasysregistration'), [
            1 => get_string('active', 'local_unileasysregistration'),
            0 => get_string('inactive', 'local_unileasysregistration'),
        ]);
        $mform->setDefault('active', 1);
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        global $DB;
        $accounttype = strtolower(trim((string)($data['accounttype'] ?? '')));
        $roleid = (int)($data['roleid'] ?? 0);
        if ($accounttype && $roleid) {
            $role = $DB->get_record('role', ['id' => $roleid], 'id,archetype,shortname', IGNORE_MISSING);
            if (!$role) {
                $errors['roleid'] = get_string('ruleroleinvalid', 'local_unileasysregistration');
            } else if ($accounttype === 'student' && $role->archetype !== 'student' && $role->shortname !== 'student') {
                $errors['roleid'] = get_string('rulerolestudentrequired', 'local_unileasysregistration');
            } else if ($accounttype === 'teacher' && $role->archetype !== 'teacher' && $role->shortname !== 'teacher') {
                $errors['roleid'] = get_string('ruleroleteacherrequired', 'local_unileasysregistration');
            }
        }
        return $errors;
    }
}

$PAGE->set_url(new moodle_url('/local/unileasysregistration/enrolrules.php', ['id' => $id, 'action' => $action]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('enrolmentrules', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('enrolmentrules', 'local_unileasysregistration'));

// Only build the form when it is actually needed. This keeps the list page safe even
// when there are no courses, schools, sessions, or rules yet.
$editing = ($id > 0);
$adding = ($action === 'add');
$showform = $editing || $adding;
$existing = null;
$form = null;

if ($showform) {
    if ($editing) {
        $existing = $DB->get_record('unileasys_enrol_rule', ['id' => $id], '*', MUST_EXIST);
    }
    // Keep the action and id in the form POST target. Moodle's Forms API strips
    // the current query string when no explicit action is supplied, which would
    // otherwise turn ?action=add into a plain POST back to this page. In that case
    // the submitted form is not processed and the rule appears not to save.
    $formaction = new moodle_url('/local/unileasysregistration/enrolrules.php', [
        'action' => $adding ? 'add' : 'edit',
    ]);
    if ($editing) {
        $formaction->param('id', $id);
    }
    $form = new local_unileasysregistration_rule_form($formaction);
    if ($existing && !$form->is_cancelled()) {
        $data = clone $existing;
        $data->schoolids = json_decode($data->schoolids, true) ?: [];
        $data->classids = json_decode($data->classids, true) ?: [];
        $oldtypes = json_decode($data->accounttypes, true) ?: [];
        $data->accounttype = !empty($oldtypes) ? reset($oldtypes) : 'student';
        $form->set_data($data);
    }
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'));
    }
    if ($data = $form->get_data()) {
        if (empty($data->courseid) || empty($data->sessionid) || empty($data->roleid)) {
            redirect(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => 'add']),
                get_string('rulemissingmasterdata', 'local_unileasysregistration'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $record = (object)[
            'courseid' => (int)$data->courseid,
            'schoolids' => json_encode(array_values(array_filter(array_map('intval', (array)($data->schoolids ?? []))))),
            'classids' => json_encode(array_values(array_filter(array_map('intval', (array)($data->classids ?? []))))),
            // Keep the database field as JSON for backward compatibility, but store
            // exactly one account type for each rule.
            'accounttypes' => json_encode([strtolower((string)$data->accounttype)]),
            'sessionid' => (int)$data->sessionid,
            'roleid' => (int)$data->roleid,
            'active' => !empty($data->active) ? 1 : 0,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('unileasys_enrol_rule', $record);
            $ruleid = $existing->id;
        } else {
            \local_unileasysregistration\demo::require_capacity('rules');
            $record->timecreated = time();
            $ruleid = $DB->insert_record('unileasys_enrol_rule', $record);
        }
        enrolment_manager::sync_rule($ruleid);
        redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'),
            get_string('rulesaved', 'local_unileasysregistration'));
    }
}

if ($action === 'delete' && $id && confirm_sesskey()) {
    // Deleting a rule must first suspend its Unileasys-managed enrolments,
    // then remove the rule and clear the rule reference from mapping history.
    $existingrule = $DB->get_record('unileasys_enrol_rule', ['id' => $id]);
    if ($existingrule) {
        enrolment_manager::sync_rule($id);
        $DB->set_field('unileasys_enrolment', 'ruleid', 0, ['ruleid' => $id]);
        $DB->delete_records('unileasys_enrol_rule', ['id' => $id]);
        redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'),
            'Enrolment rule deleted. Existing Unileasys-managed enrolments were suspended; manual enrolments were not changed.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'), 'The enrolment rule could not be found.', null, \core\output\notification::NOTIFY_WARNING);
}

if (($action === 'disable' || $action === 'enable') && $id && confirm_sesskey()) {
    $newactive = ($action === 'enable') ? 1 : 0;
    $DB->update_record('unileasys_enrol_rule', (object)[
        'id' => $id,
        'active' => $newactive,
        'timemodified' => time(),
    ]);
    // Re-evaluate the rule immediately. Enabling reactivates matching automatic
    // enrolments; disabling suspends them without deleting their history.
    enrolment_manager::sync_rule($id);
    $message = $newactive ? get_string('ruleenabled', 'local_unileasysregistration')
                           : get_string('ruledisabled', 'local_unileasysregistration');
    redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'), $message);
}
if ($action === 'sync' && $id && confirm_sesskey()) {
    $result = enrolment_manager::sync_rule($id);
    redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'), get_string('rulesynced', 'local_unileasysregistration', $result));
}
if ($action === 'syncall' && confirm_sesskey()) {
    $result = enrolment_manager::sync_all_rules();
    redirect(new moodle_url('/local/unileasysregistration/enrolrules.php'), get_string('allrulesynced', 'local_unileasysregistration', $result));
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('rules');
echo $OUTPUT->heading(get_string('enrolmentrules', 'local_unileasysregistration'));
echo html_writer::div(get_string('ruleintro', 'local_unileasysregistration'), 'alert alert-info');

// Lightweight status panel: empty master data is valid and is displayed explicitly.
$status = new html_table();
$status->attributes['class'] = 'table table-sm table-bordered mb-3';
$status->head = [get_string('statusitem', 'local_unileasysregistration'), get_string('statusvalue', 'local_unileasysregistration')];
$status->data[] = [get_string('statusschools', 'local_unileasysregistration'), (string)$DB->count_records('unileasys_school')];
$status->data[] = [get_string('statusclasses', 'local_unileasysregistration'), (string)$DB->count_records('unileasys_class')];
$status->data[] = [get_string('statussessions', 'local_unileasysregistration'), (string)$DB->count_records('unileasys_session')];
$status->data[] = [get_string('statuscourses', 'local_unileasysregistration'), (string)max(0, $DB->count_records('course') - 1)];
$status->data[] = [get_string('statusrules', 'local_unileasysregistration'), (string)$DB->count_records('unileasys_enrol_rule')];
echo html_writer::tag('details', html_writer::tag('summary', get_string('systemstatus', 'local_unileasysregistration')) . html_writer::table($status), ['class' => 'mb-3']);

if (!$showform) {
    echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'syncall', 'sesskey' => sesskey()]),
        get_string('synchallrules', 'local_unileasysregistration'), ['class' => 'btn btn-secondary mb-3 mr-2']);
    echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'add']),
        get_string('addrule', 'local_unileasysregistration'), ['class' => 'btn btn-primary mb-3']);

    $rules = $DB->get_records('unileasys_enrol_rule', null, 'id DESC');
    if (!$rules) {
        echo $OUTPUT->notification(get_string('norulesyet', 'local_unileasysregistration'), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('rulecourse', 'local_unileasysregistration'), get_string('ruleschools', 'local_unileasysregistration'),
            get_string('ruleclasses', 'local_unileasysregistration'), get_string('ruletypes', 'local_unileasysregistration'),
            get_string('rulesession', 'local_unileasysregistration'), get_string('rulerole', 'local_unileasysregistration'),
            get_string('active', 'local_unileasysregistration'), ''];
        foreach ($rules as $rule) {
            $course = $DB->get_record('course', ['id' => $rule->courseid]);
            $schools = json_decode($rule->schoolids, true) ?: [];
            $classes = json_decode($rule->classids, true) ?: [];
            $types = json_decode($rule->accounttypes, true) ?: [];
            $schoolnames = [];
            foreach ($schools as $sid) {
                if ($s = $DB->get_record('unileasys_school', ['id' => $sid])) { $schoolnames[] = $s->name; }
            }
            $classnames = [];
            foreach ($classes as $cid) {
                if ($c = $DB->get_record('unileasys_class', ['id' => $cid])) { $classnames[] = $c->name; }
            }
            $session = $DB->get_record('unileasys_session', ['id' => $rule->sessionid]);
            $role = $DB->get_record('role', ['id' => $rule->roleid]);
            $toggleaction = $rule->active ? 'disable' : 'enable';
            $togglelabel = $rule->active ? get_string('disable', 'local_unileasysregistration') : get_string('enable', 'local_unileasysregistration');
            $actions = html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => 'edit', 'id' => $rule->id]), get_string('edit')) . ' ' .
                html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => 'sync', 'id' => $rule->id, 'sesskey' => sesskey()]), get_string('syncnow', 'local_unileasysregistration')) . ' ' .
                html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => $toggleaction, 'id' => $rule->id, 'sesskey' => sesskey()]), $togglelabel) . ' ' .
                html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php', ['action' => 'delete', 'id' => $rule->id, 'sesskey' => sesskey()]), 'Delete', [
                    'class' => 'btn btn-sm btn-outline-danger',
                    'onclick' => "return confirm('Delete this enrolment rule? Unileasys-managed enrolments from this rule will be suspended. Manual enrolments will not be changed.');"
                ]);
            $table->data[] = [
                $course ? format_string($course->fullname) : '—',
                $schoolnames ? s(implode(', ', $schoolnames)) : get_string('allschools', 'local_unileasysregistration'),
                $classnames ? s(implode(', ', $classnames)) : get_string('allclasses', 'local_unileasysregistration'),
                $types ? s(implode(', ', array_map('ucfirst', $types))) : get_string('alltypes', 'local_unileasysregistration'),
                $session ? s($session->name) : '—',
                $role ? s(role_get_name($role, context_course::instance($rule->courseid))) : '—',
                $rule->active ? get_string('active', 'local_unileasysregistration') : get_string('inactive', 'local_unileasysregistration'),
                $actions,
            ];
        }
        echo html_writer::table($table);
    }
} else {
    echo html_writer::link(new moodle_url('/local/unileasysregistration/enrolrules.php'), '← ' . get_string('back'));
    $form->display();
}
echo $OUTPUT->footer();
