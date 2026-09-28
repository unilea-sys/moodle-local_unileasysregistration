<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('local/unileasysregistration:manage', context_system::instance());
\local_unileasysregistration\license::require_valid();

global $DB;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

require_once($CFG->libdir . '/formslib.php');

class local_unileasysregistration_school_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('schoolname', 'local_unileasysregistration'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'code', get_string('schoolcode', 'local_unileasysregistration'));
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_unileasysregistration'));
        $mform->setDefault('active', 1);
        $this->add_action_buttons();
    }
}

$PAGE->set_url(new moodle_url('/local/unileasysregistration/schools.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('schools', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('schools', 'local_unileasysregistration'));

if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->delete_records('unileasys_school', ['id' => $id]);
    \local_unileasysregistration\profile_fields::sync_option_lists();
    redirect($PAGE->url);
}

$form = new local_unileasysregistration_school_form();
if ($action === 'edit' && $id) {
    $form->set_data($DB->get_record('unileasys_school', ['id' => $id], '*', MUST_EXIST));
}
if ($form->is_cancelled()) {
    \local_unileasysregistration\profile_fields::sync_option_lists();
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    $data->code = strtoupper($data->code);
    $data->timemodified = time();
    if (!empty($data->id)) {
        $DB->update_record('unileasys_school', $data);
    } else {
        $data->timecreated = time();
        \local_unileasysregistration\demo::require_capacity('schools');
        $DB->insert_record('unileasys_school', $data);
    }
    \local_unileasysregistration\profile_fields::sync_option_lists();
    redirect($PAGE->url);
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('schools');
echo $OUTPUT->heading(get_string('schools', 'local_unileasysregistration'));
echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'edit']), '+ ' . get_string('addschool', 'local_unileasysregistration'), ['class' => 'btn btn-primary mb-3']);

if ($action === 'edit') {
    $form->display();
} else {
    $table = new html_table();
    $table->head = [get_string('schoolname', 'local_unileasysregistration'), get_string('schoolcode', 'local_unileasysregistration'), get_string('active', 'local_unileasysregistration'), ''];
    foreach ($DB->get_records('unileasys_school', null, 'name ASC') as $row) {
        $edit = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $row->id]);
        $delete = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]);
        $table->data[] = [
            s($row->name), s($row->code),
            $row->active ? get_string('active', 'local_unileasysregistration') : get_string('inactive', 'local_unileasysregistration'),
            html_writer::link($edit, get_string('edit')) . ' ' . html_writer::link($delete, get_string('delete')),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
