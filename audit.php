<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Record one Unileasys synchronization event.
 */
function local_unileasysregistration_log_sync(int $userid, ?int $ruleid, ?int $courseid,
        string $action, string $result, string $message = ''): void {
    global $DB;
    if (!$DB->get_manager()->table_exists('unileasys_sync_log')) {
        return;
    }
    $record = (object)[
        'userid' => $userid,
        'ruleid' => $ruleid ?: null,
        'courseid' => $courseid ?: null,
        'action' => $action,
        'result' => $result,
        'message' => $message,
        'timecreated' => time(),
    ];
    $DB->insert_record('unileasys_sync_log', $record);
}
