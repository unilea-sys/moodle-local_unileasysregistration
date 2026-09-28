<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

/**
 * Course entitlement/license management.
 *
 * This is separate from the commercial plugin license in classes/license.php.
 * The commercial license controls use of the Unileasys Registration plugin.
 * Course licenses control a school's entitlement to a particular Moodle course.
 */
class course_license {
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const EXPIRED = 'expired';
    public const REVOKED = 'revoked';

    public static function ensure_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('unileasys_course_license');
        if ($dbman->table_exists($table)) {
            return;
        }

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('licensekey', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('schoolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('accounttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'student');
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('seats', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, self::ACTIVE);
        $table->add_field('startsat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('licensekeyunique', XMLDB_KEY_UNIQUE, ['licensekey']);
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('ruleid', XMLDB_INDEX_NOTUNIQUE, ['ruleid']);
        $table->add_index('scope', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'schoolid', 'classid', 'accounttype', 'sessionid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('expiry', XMLDB_INDEX_NOTUNIQUE, ['expiresat']);
        $dbman->create_table($table);
    }

    public static function all($includeexpired = true): array {
        global $DB;
        self::ensure_schema();
        $records = $DB->get_records('unileasys_course_license', null, 'id DESC');
        foreach ($records as $record) {
            self::refresh_status($record);
        }
        return $includeexpired ? $DB->get_records('unileasys_course_license', null, 'id DESC') :
            $DB->get_records_select('unileasys_course_license', 'status IN (?, ?)', [self::ACTIVE, self::SUSPENDED], 'id DESC');
    }

    /**
     * Return true when a course is already registered for the same
     * Course + School + Class + Academic Session. A course registration is
     * unique at this level; account type does not create a second course.
     */
    public static function registration_exists(int $courseid, int $schoolid, int $classid,
            int $sessionid, int $excludeid = 0): bool {
        global $DB;
        self::ensure_schema();
        $params = [
            'courseid' => $courseid,
            'ruleid' => !empty($data['ruleid']) ? (int)$data['ruleid'] : null,
            'schoolid' => $schoolid,
            'classid' => $classid,
            'sessionid' => $sessionid,
        ];
        $sql = 'courseid = :courseid AND schoolid = :schoolid AND classid = :classid
                AND sessionid = :sessionid';
        if ($excludeid > 0) {
            $sql .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludeid;
        }
        return $DB->record_exists_select('unileasys_course_license', $sql, $params);
    }

    public static function create(array $data): int {
        global $DB;
        self::ensure_schema();

        $courseid = (int)$data['courseid'];
        $schoolid = (int)$data['schoolid'];
        $classid = (int)$data['classid'];
        $sessionid = (int)$data['sessionid'];
        $accounttype = strtolower(trim((string)$data['accounttype']));
        $seats = max(0, (int)$data['seats']);
        $startsat = (int)$data['startsat'];
        $expiresat = (int)$data['expiresat'];

        if (!$DB->record_exists('course', ['id' => $courseid]) ||
            !$DB->record_exists('unileasys_school', ['id' => $schoolid, 'active' => 1]) ||
            !$DB->record_exists('unileasys_class', ['id' => $classid, 'active' => 1]) ||
            !$DB->record_exists('unileasys_session', ['id' => $sessionid]) ||
            !in_array($accounttype, ['student', 'teacher'], true) ||
            $startsat <= 0 || $expiresat <= $startsat) {
            throw new \moodle_exception('invalidcourselicense', 'local_unileasysregistration');
        }

        if (self::registration_exists($courseid, $schoolid, $classid, $sessionid)) {
            throw new \moodle_exception('courselicensealreadyexists', 'local_unileasysregistration');
        }

        $now = time();
        $record = (object)[
            'licensekey' => self::new_key(),
            'courseid' => $courseid,
            'schoolid' => $schoolid,
            'classid' => $classid,
            'accounttype' => $accounttype,
            'sessionid' => $sessionid,
            'seats' => $seats,
            'status' => ($expiresat < $now ? self::EXPIRED : self::ACTIVE),
            'startsat' => $startsat,
            'expiresat' => $expiresat,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('unileasys_course_license', $record);
        $created = $DB->get_record('unileasys_course_license', ['id' => $id], '*', MUST_EXIST);
        self::reactivate_users($created);
        return $id;
    }

    /**
     * Create Course License record(s) from an existing enrolment rule.
     *
     * The rule is the source of truth for course, school, class, account type
     * and academic session. A separate Course License is created for each
     * concrete school/class combination represented by the rule.
     */
    public static function create_from_rule(int $ruleid, int $seats, int $startsat, int $expiresat): array {
        global $DB;
        self::ensure_schema();

        $rule = $DB->get_record('unileasys_enrol_rule', ['id' => $ruleid], '*', IGNORE_MISSING);
        if (!$rule) {
            throw new \moodle_exception('enrolmentrulenotfound', 'local_unileasysregistration');
        }
        if (empty($rule->active)) {
            throw new \moodle_exception('enrolmentruleinactive', 'local_unileasysregistration');
        }

        $schools = array_values(array_filter(array_map('intval', json_decode((string)$rule->schoolids, true) ?: [])));
        $classes = array_values(array_filter(array_map('intval', json_decode((string)$rule->classids, true) ?: [])));
        $types = array_values(array_filter(array_map('strtolower', json_decode((string)$rule->accounttypes, true) ?: [])));

        if (!$schools || !$classes) {
            throw new \moodle_exception('rulemustspecifyschoolclass', 'local_unileasysregistration');
        }
        if (count($types) !== 1 || !in_array($types[0], ['student', 'teacher'], true)) {
            throw new \moodle_exception('rulemusthaveonetype', 'local_unileasysregistration');
        }
        if ($startsat <= 0 || $expiresat <= $startsat) {
            throw new \moodle_exception('invalidcourselicense', 'local_unileasysregistration');
        }
        if (\local_unileasysregistration\demo::enabled()) {
            \local_unileasysregistration\demo::require_capacity('courselicenses');
            \local_unileasysregistration\demo::cap_seats($seats);
        }
        if (!$DB->record_exists('course', ['id' => (int)$rule->courseid]) ||
                !$DB->record_exists('unileasys_session', ['id' => (int)$rule->sessionid])) {
            throw new \moodle_exception('invalidcourselicense', 'local_unileasysregistration');
        }

        if (\local_unileasysregistration\demo::enabled()) {
            $potential = count($schools) * count($classes);
            \local_unileasysregistration\demo::require_capacity('courselicenses', $potential);
            \local_unileasysregistration\demo::cap_seats($seats);
        }

        $created = [];
        $skipped = [];
        foreach ($schools as $schoolid) {
            foreach ($classes as $classid) {
                if (!$DB->record_exists('unileasys_school', ['id' => $schoolid, 'active' => 1]) ||
                        !$DB->record_exists('unileasys_class', ['id' => $classid, 'active' => 1])) {
                    $skipped[] = [$schoolid, $classid, 'School or class is inactive/not found.'];
                    continue;
                }
                if (self::registration_exists((int)$rule->courseid, $schoolid, $classid, (int)$rule->sessionid)) {
                    $skipped[] = [$schoolid, $classid, 'Course is already registered for this school, class and academic session.'];
                    continue;
                }
                try {
                    $created[] = self::create([
                        'courseid' => (int)$rule->courseid,
                        'ruleid' => $ruleid,
                        'schoolid' => $schoolid,
                        'classid' => $classid,
                        'accounttype' => $types[0],
                        'sessionid' => (int)$rule->sessionid,
                        'seats' => $seats,
                        'startsat' => $startsat,
                        'expiresat' => $expiresat,
                    ]);
                } catch (\Throwable $e) {
                    $skipped[] = [$schoolid, $classid, $e->getMessage()];
                }
            }
        }

        if (!$created && !$skipped) {
            throw new \moodle_exception('courselicensecreationfailed', 'local_unileasysregistration');
        }
        return ['created' => $created, 'skipped' => $skipped, 'ruleid' => $ruleid];
    }

    /**
     * Update the editable entitlement fields of an existing course license.
     * The generated license key and historical creation timestamp are immutable.
     */
    public static function update(int $id, array $data): bool {
        global $DB;
        self::ensure_schema();

        $record = $DB->get_record('unileasys_course_license', ['id' => $id], '*', IGNORE_MISSING);
        if (!$record) {
            return false;
        }

        $courseid = (int)($data['courseid'] ?? $record->courseid);
        $schoolid = (int)($data['schoolid'] ?? $record->schoolid);
        $classid = (int)($data['classid'] ?? $record->classid);
        $sessionid = (int)($data['sessionid'] ?? $record->sessionid);
        $accounttype = strtolower(trim((string)($data['accounttype'] ?? $record->accounttype)));
        $seats = max(0, (int)($data['seats'] ?? $record->seats));
        $startsat = (int)($data['startsat'] ?? $record->startsat);
        $expiresat = (int)($data['expiresat'] ?? $record->expiresat);

        if (!$DB->record_exists('course', ['id' => $courseid]) ||
            !$DB->record_exists('unileasys_school', ['id' => $schoolid, 'active' => 1]) ||
            !$DB->record_exists('unileasys_class', ['id' => $classid, 'active' => 1]) ||
            !$DB->record_exists('unileasys_session', ['id' => $sessionid]) ||
            !in_array($accounttype, ['student', 'teacher'], true) ||
            $startsat <= 0 || $expiresat <= $startsat) {
            throw new \moodle_exception('invalidcourselicense', 'local_unileasysregistration');
        }

        if (self::registration_exists($courseid, $schoolid, $classid, $sessionid, $id)) {
            throw new \moodle_exception('courselicensealreadyexists', 'local_unileasysregistration');
        }

        $used = self::used_seats($id);
        if ($seats > 0 && $used > $seats) {
            throw new \moodle_exception('courselicenseusedseatsexceed', 'local_unileasysregistration');
        }

        $record->courseid = $courseid;
        $record->schoolid = $schoolid;
        $record->classid = $classid;
        $record->accounttype = $accounttype;
        $record->sessionid = $sessionid;
        $record->seats = $seats;
        $record->startsat = $startsat;
        $record->expiresat = $expiresat;

        // An expired license becomes active again when its dates are explicitly
        // edited into a valid future/current period. Manual suspension/revocation
        // is otherwise preserved.
        if ($record->status === self::EXPIRED && $expiresat >= time()) {
            $record->status = self::ACTIVE;
        }
        $record->timemodified = time();
        $DB->update_record('unileasys_course_license', $record);
        if ($record->status === self::ACTIVE) {
            self::reactivate_users($record);
        }
        return true;
    }

    /**
     * Delete an unused course license. Active users or linked activation history
     * are protected so deleting a license cannot silently break access records.
     */
    public static function delete(int $id): bool {
        global $DB;
        self::ensure_schema();

        $record = $DB->get_record('unileasys_course_license', ['id' => $id], 'id', IGNORE_MISSING);
        if (!$record) {
            return false;
        }

        if (self::used_seats($id) > 0) {
            throw new \moodle_exception('courselicensecannotdeleteused', 'local_unileasysregistration');
        }

        // Keep activation history intact. A license that has been referenced by
        // an activation record must be revoked/expired rather than hard-deleted.
        if ($DB->record_exists('unileasys_course_activation', ['licenseid' => $id])) {
            throw new \moodle_exception('courselicensecannotdeletehistory', 'local_unileasysregistration');
        }

        return $DB->delete_records('unileasys_course_license', ['id' => $id]);
    }

    public static function renew(int $id, int $expiresat): bool {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_course_license', ['id' => $id], '*', IGNORE_MISSING);
        if (!$record || $expiresat <= (int)$record->startsat) {
            return false;
        }
        $record->expiresat = $expiresat;
        $record->status = self::ACTIVE;
        $record->timemodified = time();
        $DB->update_record('unileasys_course_license', $record);
        self::reactivate_users($record);
        return true;
    }

    public static function set_status(int $id, string $status): bool {
        global $DB;
        self::ensure_schema();
        if (!in_array($status, [self::ACTIVE, self::SUSPENDED, self::REVOKED], true)) {
            return false;
        }
        $record = $DB->get_record('unileasys_course_license', ['id' => $id], '*', IGNORE_MISSING);
        if (!$record) {
            return false;
        }
        $record->status = $status;
        $record->timemodified = time();
        $DB->update_record('unileasys_course_license', $record);
        if ($status === self::ACTIVE) {
            self::reactivate_users($record);
        }
        return true;
    }

    /**
     * Re-activate access immediately when a course license becomes active.
     * Rule-based access is restored through the rule engine; token-based access
     * is restored only for users who already have a valid course activation.
     */
    public static function reactivate_users($license): int {
        global $DB;
        if (!$license || (int)$license->id <= 0 || $license->status !== self::ACTIVE) {
            return 0;
        }
        if (time() < (int)$license->startsat || time() > (int)$license->expiresat) {
            return 0;
        }

        $restored = 0;
        $rules = $DB->get_records('unileasys_enrol_rule', ['courseid' => (int)$license->courseid, 'active' => 1]);
        foreach ($rules as $rule) {
            // sync_rule applies the rule's own school/class/type/session scope
            // and the license seat limit.
            $result = enrolment_manager::sync_rule((int)$rule->id);
            $restored += (int)$result['enrolled'];
        }

        $sql = "SELECT ca.*
                  FROM {unileasys_course_activation} ca
                 WHERE ca.courseid = :courseid
                   AND ca.status = 1
                   AND (ca.licenseid = :licenseid OR ca.licenseid = 0)";
        $activations = $DB->get_records_sql($sql, [
            'courseid' => (int)$license->courseid,
            'licenseid' => (int)$license->id,
        ]);
        foreach ($activations as $activation) {
            $userlicense = self::get_for_user((int)$activation->userid, (int)$license->courseid);
            if (!$userlicense || (int)$userlicense->id !== (int)$license->id) {
                continue;
            }
            if (self::can_user_access((int)$activation->userid, (int)$license->courseid) &&
                    enrolment_manager::activate_course((int)$activation->userid, (int)$license->courseid)) {
                if ((int)$activation->licenseid === 0) {
                    $DB->set_field('unileasys_course_activation', 'licenseid', (int)$license->id, ['id' => $activation->id]);
                }
                $restored++;
            }
        }
        return $restored;
    }

    public static function refresh_status($record): void {
        global $DB;
        if (!$record || empty($record->id)) {
            return;
        }
        if ((int)$record->expiresat > 0 && time() > (int)$record->expiresat &&
                $record->status !== self::REVOKED && $record->status !== self::EXPIRED) {
            $DB->update_record('unileasys_course_license', (object)[
                'id' => $record->id, 'status' => self::EXPIRED, 'timemodified' => time()
            ]);
            $record->status = self::EXPIRED;
        }
    }

    /**
     * Returns the applicable active/suspended license for a user's verified
     * classification and course. If no license has been created for this
     * course, legacy v1.7.9 behaviour is retained.
     */
    public static function get_for_user(int $userid, int $courseid) {
        global $DB;
        self::ensure_schema();

        $verification = $DB->get_record('unileasys_verification', ['userid' => $userid], '*', IGNORE_MISSING);
        if (!$verification || empty($verification->schoolid) || empty($verification->classid) ||
                empty($verification->accounttype) || empty($verification->sessionid)) {
            return null;
        }

        $records = $DB->get_records('unileasys_course_license', [
            'courseid' => $courseid,
            'schoolid' => (int)$verification->schoolid,
            'classid' => (int)$verification->classid,
            'accounttype' => strtolower((string)$verification->accounttype),
            'sessionid' => (int)$verification->sessionid,
        ], 'id DESC');

        // Allow a license issued for the current active academic session when
        // the stored verified session is from the previous session.
        if (!$records) {
            $active = $DB->get_record('unileasys_session', ['active' => 1], 'id', IGNORE_MULTIPLE);
            if ($active && (int)$active->id !== (int)$verification->sessionid) {
                $records = $DB->get_records('unileasys_course_license', [
                    'courseid' => $courseid,
                    'schoolid' => (int)$verification->schoolid,
                    'classid' => (int)$verification->classid,
                    'accounttype' => strtolower((string)$verification->accounttype),
                    'sessionid' => (int)$active->id,
                ], 'id DESC');
            }
        }

        $record = reset($records);
        if ($record) {
            self::refresh_status($record);
        }
        return $record ?: null;
    }

    public static function course_has_licenses(int $courseid): bool {
        global $DB;
        self::ensure_schema();
        return $DB->record_exists('unileasys_course_license', ['courseid' => $courseid]);
    }

    public static function can_user_access(int $userid, int $courseid): bool {
        global $DB;
        $license = self::get_for_user($userid, $courseid);

        // No course license means v1.7.9 compatibility mode.
        if (!$license) {
            return !self::course_has_licenses($courseid);
        }

        if ($license->status !== self::ACTIVE || time() < (int)$license->startsat ||
                time() > (int)$license->expiresat) {
            return false;
        }

        if ((int)$license->seats > 0) {
            $used = self::used_seats((int)$license->id);
            $already = self::user_has_activation_for_license($userid, (int)$license->id);
            if ($used >= (int)$license->seats && !$already) {
                return false;
            }
        }
        return true;
    }

    public static function user_has_activation_for_license(int $userid, int $licenseid): bool {
        global $DB;
        $license = $DB->get_record('unileasys_course_license', ['id' => $licenseid], 'courseid', IGNORE_MISSING);
        if (!$license) {
            return false;
        }
        return $DB->record_exists('unileasys_course_activation', [
            'userid' => $userid,
            'courseid' => (int)$license->courseid,
            'status' => 1,
        ]);
    }

    public static function used_seats(int $licenseid): int {
        global $DB;
        $license = $DB->get_record('unileasys_course_license', ['id' => $licenseid], '*', IGNORE_MISSING);
        if (!$license) {
            return 0;
        }

        // Count active Unileasys-managed enrolments belonging to this exact
        // license scope. This covers both token activations and rule-based
        // course enrolments.
        $sql = "SELECT COUNT(ue.id)
                  FROM {unileasys_enrolment} ue
                  JOIN {unileasys_verification} v ON v.userid = ue.userid
                 WHERE ue.courseid = :courseid
                   AND ue.status = :active
                   AND v.status = :verified
                   AND v.schoolid = :schoolid
                   AND v.classid = :classid
                   AND LOWER(v.accounttype) = :accounttype
                   AND v.sessionid = :sessionid";
        return (int)$DB->count_records_sql($sql, [
            'courseid' => (int)$license->courseid,
            'active' => ENROL_USER_ACTIVE,
            'verified' => \local_unileasysregistration\verification::VERIFIED,
            'schoolid' => (int)$license->schoolid,
            'classid' => (int)$license->classid,
            'accounttype' => strtolower((string)$license->accounttype),
            'sessionid' => (int)$license->sessionid,
        ]);
    }

    public static function enforce_user(int $userid): void {
        global $DB;
        self::ensure_schema();
        if ($userid <= 1) {
            return;
        }
        $mappings = $DB->get_records('unileasys_enrolment', ['userid' => $userid]);
        foreach ($mappings as $mapping) {
            $courseid = (int)$mapping->courseid;

            // Once a course has at least one license, every managed enrolment
            // must have a matching license. This closes the v1.8.0 gap where
            // users enrolled before a license was created could keep access.
            if (self::course_has_licenses($courseid)) {
                $license = self::get_for_user($userid, $courseid);
                $valid = $license && $license->status === self::ACTIVE &&
                    time() >= (int)$license->startsat && time() <= (int)$license->expiresat;
                if (!$valid && (int)$mapping->status === ENROL_USER_ACTIVE) {
                    enrolment_manager::suspend_course($userid, $courseid);
                }
                continue;
            }

            // No license exists for this course: preserve v1.7.9 behaviour.
        }
    }

    /**
     * Enforce course licensing for all Unileasys-managed enrolments.
     *
     * This is intended for Moodle cron and is deliberately limited to the
     * plugin's own enrolment table. Ordinary Moodle/manual enrolments are not
     * changed by this task.
     */
    public static function enforce_all(): int {
        global $DB;
        self::ensure_schema();

        $suspended = 0;
        $now = time();

        // First mark time-expired licenses. Revoked licenses remain revoked.
        $DB->execute(
            "UPDATE {unileasys_course_license}
                SET status = :expired, timemodified = :modified
              WHERE expiresat > 0
                AND expiresat < :now
                AND status NOT IN (:revoked, :alreadyexpired)",
            [
                'expired' => self::EXPIRED,
                'modified' => $now,
                'now' => $now,
                'revoked' => self::REVOKED,
                'alreadyexpired' => self::EXPIRED,
            ]
        );

        $mappings = $DB->get_records('unileasys_enrolment', null, 'id ASC');
        foreach ($mappings as $mapping) {
            if ((int)$mapping->status !== ENROL_USER_ACTIVE) {
                continue;
            }
            $courseid = (int)$mapping->courseid;
            if (!self::course_has_licenses($courseid)) {
                continue;
            }

            $license = self::get_for_user((int)$mapping->userid, $courseid);
            $valid = $license && $license->status === self::ACTIVE &&
                $now >= (int)$license->startsat && $now <= (int)$license->expiresat;
            if (!$valid) {
                enrolment_manager::suspend_course((int)$mapping->userid, $courseid);
                $suspended++;
            }
        }

        return $suspended;
    }

    private static function new_key(): string {
        return 'ULS-CL-' . strtoupper(substr(bin2hex(random_bytes(12)), 0, 20));
    }
    /** Return the number of non-revoked activation tokens already issued for a Course License. */
    public static function issued_token_count(int $licenseid): int {
        global $DB;
        $table = new \xmldb_table('unileasys_verify_token');
        if (!$DB->get_manager()->table_exists($table)) {
            return 0;
        }
        $field = new \xmldb_field('courselicenseid', XMLDB_TYPE_INTEGER, '10');
        if (!$DB->get_manager()->field_exists($table, $field)) {
            return 0;
        }
        return (int)$DB->count_records_select('unileasys_verify_token', 'courselicenseid = :licenseid AND (active = 1 OR usedby IS NOT NULL)', ['licenseid' => $licenseid]);
    }

}
