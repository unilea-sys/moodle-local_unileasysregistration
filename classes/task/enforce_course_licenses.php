<?php
namespace local_unileasysregistration\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforce Course License expiry and entitlement rules.
 */
class enforce_course_licenses extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('taskenforcecourselicenses', 'local_unileasysregistration');
    }

    public function execute(): void {
        \local_unileasysregistration\course_license::enforce_all();
    }
}
