<?php
namespace local_unileasysregistration\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Synchronize one Moodle user with Unileasys classification and active
 * course-enrolment rules after the user/profile transaction has completed.
 */
class sync_user extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task_sync_user', 'local_unileasysregistration');
    }

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $userid = !empty($data->userid) ? (int)$data->userid : 0;
        $attempt = isset($data->attempt) ? (int)$data->attempt : 0;

        if ($userid <= 1 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }

        $ready = \local_unileasysregistration\observer::sync_user($userid);
        if ($ready) {
            return;
        }

        // Give Moodle profile data time to be committed. Retry a few times,
        // then leave the user for the next profile update/manual sync rather
        // than creating an endless adhoc-task loop.
        if ($attempt < 3) {
            \local_unileasysregistration\observer::queue_sync($userid, $attempt + 1, 30);
        }
    }
}
