<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

class enrolment_manager {
    private static function ensure_schema(): void {
        schema::ensure_enrolment_tables();
        schema::ensure_course_activation();
    }
    public static function sync_user(int $userid): void {
        self::ensure_schema();
        global $DB;
        if ($userid <= 1 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        $rules = $DB->get_records('unileasys_enrol_rule', ['active' => 1], 'id ASC');
        $matched = [];
        foreach ($rules as $rule) {
            if (self::rule_matches_user($rule, $userid) && !isset($matched[(int)$rule->courseid])) {
                $matched[(int)$rule->courseid] = $rule;
            }
        }
        foreach ($DB->get_records('unileasys_enrolment', ['userid' => $userid]) as $mapping) {
            $courseid = (int)$mapping->courseid;
            if (isset($matched[$courseid])) {
                self::ensure_enrolment_active($mapping, $matched[$courseid]);
                unset($matched[$courseid]);
            } else {
                self::suspend_mapping($mapping);
            }
        }
        foreach ($matched as $rule) {
            self::enrol_for_rule($userid, $rule);
        }
        // Course tokens grant access independently of the rule matcher, but
        // still require a verified Unileasys account.
        self::sync_course_activations($userid);
    }

    public static function activate_course(int $userid, int $courseid): bool {
        if (!\local_unileasysregistration\license::is_valid()) return false;
        if (!\local_unileasysregistration\verification::is_verified($userid)) return false;
        if (!\local_unileasysregistration\course_license::can_user_access($userid, $courseid)) return false;
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        if (!$course || (int)$course->id === SITEID || !enrol_is_enabled('manual')) return false;
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) return false;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instanceid = $plugin->add_default_instance($course);
            if (!$instanceid) return false;
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }
        $roleid = (int)$DB->get_field_sql('SELECT roleid FROM {unileasys_enrol_rule} WHERE courseid = ? AND active = 1 ORDER BY id ASC', [$courseid]);
        if (!$roleid) {
            $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        }
        $plugin->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
        $mapping = $DB->get_record('unileasys_enrolment', ['userid'=>$userid, 'courseid'=>$courseid]);
        $record = (object)[
            'userid'=>$userid, 'courseid'=>$courseid, 'ruleid'=>$mapping ? $mapping->ruleid : 0,
            'enrolid'=>$instance->id, 'roleid'=>$roleid, 'status'=>ENROL_USER_ACTIVE,
            'timecreated'=>$mapping ? $mapping->timecreated : time(), 'timemodified'=>time()
        ];
        if ($mapping) { $record->id=$mapping->id; $DB->update_record('unileasys_enrolment',$record); }
        else { $DB->insert_record('unileasys_enrolment',$record); }
        return true;
    }

    private static function sync_course_activations(int $userid): void {
        global $DB;
        if (!\local_unileasysregistration\verification::is_verified($userid)) return;
        self::ensure_schema();
        foreach ($DB->get_records('unileasys_course_activation', ['userid'=>$userid, 'status'=>1]) as $activation) {
            self::activate_course($userid, (int)$activation->courseid);
        }
    }

    public static function sync_rule(int $ruleid): array {
        if (!\local_unileasysregistration\license::is_valid()) {
            return ['enrolled' => 0, 'suspended' => 0, 'errors' => 0];
        }
        self::ensure_schema();
        global $DB;
        $rule = $DB->get_record('unileasys_enrol_rule', ['id' => $ruleid], '*', MUST_EXIST);
        $enrolled = 0;
        $suspended = 0;
        foreach ($DB->get_records('user', ['deleted' => 0], 'id ASC', 'id') as $user) {
            $uid = (int)$user->id;
            if ($uid === 1) {
                continue;
            }
            if ((int)$rule->active === 1 && self::rule_matches_user($rule, $uid)) {
                self::enrol_for_rule($uid, $rule);
                $enrolled++;
            }
        }
        foreach ($DB->get_records('unileasys_enrolment', ['ruleid' => $ruleid]) as $mapping) {
            if ((int)$rule->active !== 1 || !self::rule_matches_user($rule, (int)$mapping->userid)) {
                self::suspend_mapping($mapping);
                $suspended++;
            }
        }
        return ['enrolled' => $enrolled, 'suspended' => $suspended];
    }

    public static function sync_all_rules(): array {
        if (!\local_unileasysregistration\license::is_valid()) {
            return ['enrolled' => 0, 'suspended' => 0, 'errors' => 0];
        }
        self::ensure_schema();
        global $DB;
        $total = ['enrolled' => 0, 'suspended' => 0];
        foreach ($DB->get_records('unileasys_enrol_rule', ['active' => 1], 'id ASC') as $rule) {
            $result = self::sync_rule((int)$rule->id);
            $total['enrolled'] += $result['enrolled'];
            $total['suspended'] += $result['suspended'];
        }
        return $total;
    }

    /**
     * Synchronize every non-deleted Moodle user through the current Unileasys
     * classification and active enrolment rules.
     *
     * Returns dashboard-friendly aggregate counts. This intentionally uses the
     * same sync path as registration/profile updates, so manual repair and
     * automatic synchronization cannot drift apart.
     */
    public static function sync_all_users(): array {
        if (!\local_unileasysregistration\license::is_valid()) {
            return ['processed' => 0, 'classified' => 0, 'enrolled' => 0, 'suspended' => 0, 'errors' => 0];
        }
        self::ensure_schema();
        global $DB;
        $result = [
            'processed' => 0,
            'classified' => 0,
            'enrolled' => 0,
            'suspended' => 0,
            'errors' => 0,
        ];
        $rs = $DB->get_recordset_select('user', 'deleted = 0 AND id > 1', null, 'id ASC', 'id');
        foreach ($rs as $user) {
            $result['processed']++;
            try {
                $before = $DB->get_records('unileasys_enrolment', ['userid' => $user->id], '', 'id,status');
                \local_unileasysregistration\observer::sync_user((int)$user->id);
                $mapping = $DB->get_record('unileasys_user', ['userid' => $user->id]);
                if ($mapping) {
                    $result['classified']++;
                }
                $after = $DB->get_records('unileasys_enrolment', ['userid' => $user->id], '', 'id,status');
                foreach ($after as $row) {
                    if ((int)$row->status === ENROL_USER_ACTIVE) {
                        $result['enrolled']++;
                    } else if ((int)$row->status === ENROL_USER_SUSPENDED) {
                        $result['suspended']++;
                    }
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                debugging('Unileasys user synchronization failed for user ' . $user->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $rs->close();

        // Counts above represent current mapping state. Return them as a
        // dashboard snapshot rather than event-level deltas.
        $result['enrolled'] = $DB->count_records('unileasys_enrolment', ['status' => ENROL_USER_ACTIVE]);
        $result['suspended'] = $DB->count_records('unileasys_enrolment', ['status' => ENROL_USER_SUSPENDED]);
        return $result;
    }

    public static function rule_matches_user($rule, int $userid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($userid, false);
        $schoolvalue = trim((string)($profile->unileasys_school ?? ''));
        $classvalue = trim((string)($profile->unileasys_class ?? ''));
        $accounttype = strtolower(trim((string)($profile->unileasys_account_type ?? '')));
        if ($schoolvalue === '' || $classvalue === '' || !in_array($accounttype, ['student', 'teacher'], true)) {
            return false;
        }
        $school = $DB->get_record('unileasys_school', ['name' => $schoolvalue]);
        if (!$school) {
            $school = $DB->get_record('unileasys_school', ['code' => $schoolvalue]);
        }
        $class = $DB->get_record('unileasys_class', ['name' => $classvalue]);
        if (!$class) {
            $class = $DB->get_record('unileasys_class', ['shortname' => $classvalue]);
        }
        if (!$school || !$class) {
            return false;
        }

        // School/Class/Account Type are eligibility claims only. Automatic
        // course enrolment is allowed only after the student's Unileasys
        // account has been verified (activation token or manual approval).
        if (!\local_unileasysregistration\verification::is_verified($userid)) {
            return false;
        }

        $schoolids = self::decode_ids($rule->schoolids);
        $classids = self::decode_ids($rule->classids);
        $types = self::decode_strings($rule->accounttypes);
        if ($schoolids && !in_array((int)$school->id, $schoolids, true)) {
            return false;
        }
        if ($classids && !in_array((int)$class->id, $classids, true)) {
            return false;
        }
        if ($types && !in_array($accounttype, $types, true)) {
            return false;
        }
        $mapping = $DB->get_record('unileasys_user', ['userid' => $userid]);
        if (!$mapping || (!empty($rule->sessionid) && (int)$mapping->sessionid !== (int)$rule->sessionid)) {
            return false;
        }
        return true;
    }

    private static function enrol_for_rule(int $userid, $rule): void {
        global $DB, $CFG;
        if (!\local_unileasysregistration\course_license::can_user_access($userid, (int)$rule->courseid)) {
            return;
        }
        require_once($CFG->libdir . '/enrollib.php');
        $course = $DB->get_record('course', ['id' => $rule->courseid], '*', IGNORE_MISSING);
        if (!$course || (int)$course->id === SITEID || !enrol_is_enabled('manual')) {
            return;
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return;
        }
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instanceid = $plugin->add_default_instance($course);
            if (!$instanceid) {
                return;
            }
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }
        $existing = $DB->get_record('unileasys_enrolment', ['userid' => $userid, 'courseid' => $course->id]);
        $plugin->enrol_user($instance, $userid, (int)$rule->roleid, 0, 0, ENROL_USER_ACTIVE);
        $record = (object)[
            'userid' => $userid, 'courseid' => $course->id, 'ruleid' => $rule->id,
            'enrolid' => $instance->id, 'roleid' => $rule->roleid, 'status' => ENROL_USER_ACTIVE,
            'timecreated' => $existing ? $existing->timecreated : time(), 'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('unileasys_enrolment', $record);
        } else {
            $DB->insert_record('unileasys_enrolment', $record);
        }
    }

    private static function ensure_enrolment_active($mapping, $rule): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $instance = $DB->get_record('enrol', ['id' => $mapping->enrolid], '*', IGNORE_MISSING);
        if (!$instance) {
            self::enrol_for_rule((int)$mapping->userid, $rule);
            return;
        }
        $plugin = enrol_get_plugin($instance->enrol);
        if (!$plugin) {
            return;
        }
        $plugin->enrol_user($instance, (int)$mapping->userid, (int)$rule->roleid, 0, 0, ENROL_USER_ACTIVE);
        $DB->update_record('unileasys_enrolment', (object)[
            'id' => $mapping->id, 'ruleid' => $rule->id, 'enrolid' => $instance->id,
            'roleid' => $rule->roleid, 'status' => ENROL_USER_ACTIVE, 'timemodified' => time(),
        ]);
    }

    /**
     * Suspend one Unileasys-managed enrolment.
     *
     * Moodle's enrol_plugin::update_user_enrol() expects the enrolment status
     * as its third argument. Passing it as the fifth argument changes the
     * enrolment end time instead of suspending the user, which could leave an
     * old course active after a school/class change.
     */
    private static function suspend_mapping($mapping): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $instance = $DB->get_record('enrol', ['id' => $mapping->enrolid], '*', IGNORE_MISSING);
        if ($instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            if ($plugin && $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $mapping->userid])) {
                $plugin->update_user_enrol($instance, (int)$mapping->userid, ENROL_USER_SUSPENDED, 0, 0);
            }
        }
        $DB->update_record('unileasys_enrolment', (object)[
            'id' => $mapping->id, 'status' => ENROL_USER_SUSPENDED, 'timemodified' => time(),
        ]);
    }

    /**
     * Immediately suspend every course enrolment managed by Unileasys for a
     * user. Used when their verified school/class/type/session changes.
     */
    public static function suspend_course(int $userid, int $courseid): void {
        self::ensure_schema();
        global $DB;
        $mapping = $DB->get_record('unileasys_enrolment', ['userid' => $userid, 'courseid' => $courseid], '*', IGNORE_MISSING);
        if ($mapping && (int)$mapping->status !== ENROL_USER_SUSPENDED) {
            self::suspend_mapping($mapping);
        }
    }

    public static function suspend_all_managed_enrolments(int $userid): void {
        self::ensure_schema();
        global $DB;
        foreach ($DB->get_records('unileasys_enrolment', ['userid' => $userid]) as $mapping) {
            if ((int)$mapping->status !== ENROL_USER_SUSPENDED) {
                self::suspend_mapping($mapping);
            }
        }
    }

    private static function decode_ids(string $value): array {
        $data = json_decode($value, true);
        return is_array($data) ? array_values(array_filter(array_map('intval', $data))) : [];
    }
    private static function decode_strings(string $value): array {
        $data = json_decode($value, true);
        if (!is_array($data)) { return []; }
        return array_values(array_filter(array_map(function($v) { return strtolower(trim((string)$v)); }, $data)));
    }
}
