<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function user_created(\core\event\user_created $event): void {
        $userid = (int)$event->objectid;
        // User-created can fire before Moodle has persisted custom profile
        // fields. Queue a post-transaction synchronization so registration
        // and admin-created users are both handled reliably.
        self::queue_sync($userid);
    }

    public static function user_updated(\core\event\user_updated $event): void {
        $userid = (int)$event->objectid;
        if (!self::sync_user($userid)) {
            self::queue_sync($userid);
        }
    }

    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('unileasys_enrolment', ['userid' => $event->objectid]);
        $DB->delete_records('unileasys_user', ['userid' => $event->objectid]);
        $DB->delete_records('unileasys_verification', ['userid' => $event->objectid]);
        $DB->set_field('unileasys_verify_token', 'usedby', null, ['usedby' => $event->objectid]);
    }

    public static function queue_sync(int $userid, int $attempt = 0, int $delay = 10): void {
        if ($userid <= 1) {
            return;
        }
        $task = new \local_unileasysregistration\task\sync_user();
        $task->set_custom_data(['userid' => $userid, 'attempt' => $attempt]);
        $task->set_next_run_time(time() + max(1, $delay));
        \core\task\manager::queue_adhoc_task($task);
    }

    /** Fail-closed verification/profile consistency backstop. */
    public static function enforce_profile_consistency(int $userid): void {
        global $DB, $CFG;
        if (!\local_unileasysregistration\license::is_valid()) {
            \local_unileasysregistration\enrolment_manager::suspend_all_managed_enrolments($userid);
            return;
        }
        if ($userid <= 1 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($userid, false);
        $schoolvalue = trim((string)($profile->unileasys_school ?? ''));
        $classvalue = trim((string)($profile->unileasys_class ?? ''));
        $accounttype = strtolower(trim((string)($profile->unileasys_account_type ?? '')));
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\verification::ensure_user($userid);
        $verification = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        $mapping = $DB->get_record('unileasys_user', ['userid' => $userid]);

        // Pending/rejected/suspended users must never retain managed access.
        if (!$verification || $verification->status !== \local_unileasysregistration\verification::VERIFIED) {
            \local_unileasysregistration\enrolment_manager::suspend_all_managed_enrolments($userid);
            return;
        }

        $school = $DB->get_record('unileasys_school', ['name' => $schoolvalue, 'active' => 1]);
        if (!$school && $schoolvalue !== '') {
            $school = $DB->get_record('unileasys_school', ['code' => $schoolvalue, 'active' => 1]);
        }
        $class = $DB->get_record('unileasys_class', ['name' => $classvalue, 'active' => 1]);
        if (!$class && $classvalue !== '') {
            $class = $DB->get_record('unileasys_class', ['shortname' => $classvalue, 'active' => 1]);
        }
        $session = $DB->get_record('unileasys_session', ['active' => 1], '*', IGNORE_MULTIPLE);

        $mismatch = !$school || !$class || !$session ||
            empty($verification->schoolid) || empty($verification->classid) ||
            empty($verification->accounttype) || empty($verification->sessionid) ||
            (int)$verification->schoolid !== (int)$school->id ||
            (int)$verification->classid !== (int)$class->id ||
            strtolower((string)$verification->accounttype) !== $accounttype ||
            (int)$verification->sessionid !== (int)$session->id;

        if ($mismatch) {
            \local_unileasysregistration\verification::invalidate_verified($userid);
            \local_unileasysregistration\enrolment_manager::suspend_all_managed_enrolments($userid);
            if ($mapping && !empty($mapping->cohortid)) {
                self::remove_legacy_cohort_membership($userid, (int)$mapping->cohortid);
            }
        }
    }

    private static function remove_legacy_cohort_membership(int $userid, int $cohortid): void {
        global $CFG, $DB;
        if ($cohortid <= 0) {
            return;
        }
        require_once($CFG->dirroot . '/cohort/lib.php');
        if ($DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
            cohort_remove_member($cohortid, $userid);
        }
    }

    public static function sync_user(int $userid): bool {
        global $DB, $CFG;
        if (!\local_unileasysregistration\license::is_valid()) {
            return true;
        }
        if ($userid <= 0 || $userid === 1) {
            return true;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return false;
        }
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($userid, false);
        $schoolvalue = trim((string)($profile->unileasys_school ?? ''));
        $classvalue = trim((string)($profile->unileasys_class ?? ''));
        $accounttype = strtolower(trim((string)($profile->unileasys_account_type ?? '')));

        // Blank in all three fields means an ordinary Moodle account. Do not
        // create a Unileasys mapping or course enrolment for such users.
        $allblank = ($schoolvalue === '' && $classvalue === '' && $accounttype === '');
        if ($allblank) {
            \local_unileasysregistration\enrolment_manager::sync_user($userid);
            $DB->delete_records('unileasys_user', ['userid' => $userid]);
            return true;
        }

        // Partial data, or an invalid account type, is not ready to synchronize.
        // Returning false causes the adhoc task to retry after profile data has
        // been committed. This is important for public signup and admin-created
        // users where profile fields may be saved after user_created fires.
        $classificationcomplete = ($schoolvalue !== '' && $classvalue !== '' &&
            in_array($accounttype, ['student', 'teacher'], true));
        if (!$classificationcomplete) {
            return false;
        }

        $school = $DB->get_record('unileasys_school', ['name' => $schoolvalue, 'active' => 1]);
        if (!$school) {
            $school = $DB->get_record('unileasys_school', ['code' => $schoolvalue, 'active' => 1]);
        }
        $class = $DB->get_record('unileasys_class', ['name' => $classvalue, 'active' => 1]);
        if (!$class) {
            $class = $DB->get_record('unileasys_class', ['shortname' => $classvalue, 'active' => 1]);
        }
        $session = $DB->get_record('unileasys_session', ['active' => 1], '*', IGNORE_MULTIPLE);
        if (!$school || !$class || !$session) {
            return false;
        }

        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\verification::ensure_user($userid);

        // Compare the stored Unileasys classification as well as the
        // verification binding. This is deliberately fail-closed: even if a
        // profile-update event/task is delivered after the profile field cache
        // has changed, an old managed enrolment must never survive a school,
        // class, account-type or session change.
        $old = $DB->get_record('unileasys_user', ['userid' => $userid]);
        $classificationchanged = $old && (
            (int)$old->schoolid !== (int)$school->id ||
            (int)$old->classid !== (int)$class->id ||
            strtolower((string)$old->accounttype) !== $accounttype ||
            (int)$old->sessionid !== (int)$session->id
        );

        $verificationinvalidated = \local_unileasysregistration\verification::invalidate_on_classification_change(
            $userid, (int)$school->id, (int)$class->id, $accounttype, (int)$session->id
        );
        if ($classificationchanged || $verificationinvalidated) {
            // A verified user who changes school/class/type/session must lose
            // all Unileasys-managed course access immediately. The user must
            // activate again for the new classification.
            \local_unileasysregistration\enrolment_manager::suspend_all_managed_enrolments($userid);
            if ($old && !empty($old->cohortid)) {
                self::remove_legacy_cohort_membership($userid, (int)$old->cohortid);
            }
        }

        $record = (object)[
            'userid' => $userid,
            'schoolid' => $school->id,
            'classid' => $class->id,
            'accounttype' => $accounttype,
            'sessionid' => $session->id,
            // Cohort is now legacy-only. Existing cohort records are retained,
            // but new users are not placed into a cohort by default.
            'cohortid' => $old ? (int)$old->cohortid : 0,
            'timemodified' => time(),
        ];
        if ($old) {
            $record->id = $old->id;
            $DB->update_record('unileasys_user', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('unileasys_user', $record);
        }

        // Optional legacy mode preserves the pre-1.1 cohort behaviour.
        if (get_config('local_unileasysregistration', 'legacycohorts') && \local_unileasysregistration\verification::is_verified($userid)) {
            self::sync_legacy_cohort($userid, $school, $class, $accounttype, $session);
        }

        \local_unileasysregistration\enrolment_manager::sync_user($userid);
        return true;
    }

    private static function sync_legacy_cohort(int $userid, $school, $class, string $accounttype, $session): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $prefix = get_config('local_unileasysregistration', 'cohortprefix') ?: 'UNI';
        $rolecode = ($accounttype === 'teacher') ? 'T' : 'S';
        $idnumber = $prefix . '-' . clean_param($school->code, PARAM_ALPHANUMEXT) . '-' .
            clean_param($class->shortname, PARAM_ALPHANUMEXT) . '-' . $rolecode . '-' .
            clean_param($session->name, PARAM_ALPHANUMEXT);
        $contextid = \context_system::instance()->id;
        $cohort = $DB->get_record('cohort', ['contextid' => $contextid, 'idnumber' => $idnumber]);
        if (!$cohort) {
            $cohort = (object)[
                'contextid' => $contextid,
                'name' => $school->name . ' - ' . $class->name . ' - ' . ucfirst($accounttype) . ' - ' . $session->name,
                'idnumber' => $idnumber,
                'description' => 'Legacy cohort created by Unileasys Registration.',
                'descriptionformat' => FORMAT_HTML,
            ];
            $cohort->id = cohort_add_cohort($cohort);
        }
        if (!cohort_is_member($cohort->id, $userid)) {
            cohort_add_member($cohort->id, $userid);
        }
        $DB->set_field('unileasys_user', 'cohortid', $cohort->id, ['userid' => $userid]);
    }
}
