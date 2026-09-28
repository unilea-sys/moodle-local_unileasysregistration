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

class local_unileasysregistration_session_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('sessionname', 'local_unileasysregistration'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_unileasysregistration'));
        $mform->setDefault('active', 0);
        $this->add_action_buttons();
    }
}

$PAGE->set_url(new moodle_url('/local/unileasysregistration/sessions.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('sessions', 'local_unileasysregistration'));
$PAGE->set_heading(get_string('sessions', 'local_unileasysregistration'));

if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->delete_records('unileasys_session', ['id' => $id]);
    redirect($PAGE->url);
}

if ($action === 'activate' && $id && confirm_sesskey()) {
    $DB->set_field('unileasys_session', 'active', 0);
    $DB->set_field('unileasys_session', 'active', 1, ['id' => $id]);
    redirect($PAGE->url);
}

$form = new local_unileasysregistration_session_form();
if ($action === 'edit' && $id) {
    $form->set_data($DB->get_record('unileasys_session', ['id' => $id], '*', MUST_EXIST));
}
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    $data->timemodified = time();
    if (!empty($data->active)) {
        $DB->set_field('unileasys_session', 'active', 0);
    }
    if (!empty($data->id)) {
        $DB->update_record('unileasys_session', $data);
    } else {
        $data->timecreated = time();
        \local_unileasysregistration\demo::require_capacity('sessions');
        $DB->insert_record('unileasys_session', $data);
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('sessions');
echo $OUTPUT->heading(get_string('sessions', 'local_unileasysregistration'));
echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'edit']), '+ ' . get_string('addsession', 'local_unileasysregistration'), ['class' => 'btn btn-primary mb-3']);

if ($action === 'edit') {
    $form->display();
} else {
    $table = new html_table();
    $table->head = [get_string('sessionname', 'local_unileasysregistration'), get_string('active', 'local_unileasysregistration'), ''];
    foreach ($DB->get_records('unileasys_session', null, 'name DESC') as $row) {
        $edit = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $row->id]);
        $delete = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]);
        $activate = new moodle_url($PAGE->url, ['action' => 'activate', 'id' => $row->id, 'sesskey' => sesskey()]);
        $table->data[] = [
            s($row->name),
            $row->active ? get_string('active', 'local_unileasysregistration') : get_string('inactive', 'local_unileasysregistration'),
            html_writer::link($edit, get_string('edit')) . ' ' .
            html_writer::link($delete, get_string('delete')) . ' ' .
            ($row->active ? '' : html_writer::link($activate, get_string('active', 'local_unileasysregistration'))),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
