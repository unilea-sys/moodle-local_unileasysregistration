<?php
namespace local_unileasysregistration;
defined('MOODLE_INTERNAL') || die();

class verification {
    const PENDING = 'pending';
    const VERIFIED = 'verified';
    const REJECTED = 'rejected';
    const SUSPENDED = 'suspended';

    public static function ensure_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new \xmldb_table('unileasys_verification');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, self::PENDING);
            $table->add_field('method', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'none');
            $table->add_field('schoolid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('accounttype', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('tokenid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('verifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timeverified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('useridunique', XMLDB_KEY_UNIQUE, ['userid']);
            $dbman->create_table($table);
        } else {
            // v1.4.1 binds a verified account to the classification that was
            // actually verified. If the user later changes school/class/type,
            // the account must be re-verified.
            $fields = [
                ['schoolid', XMLDB_TYPE_INTEGER, '10'],
                ['classid', XMLDB_TYPE_INTEGER, '10'],
                ['accounttype', XMLDB_TYPE_CHAR, '20'],
                ['sessionid', XMLDB_TYPE_INTEGER, '10'],
            ];
            foreach ($fields as [$name, $type, $length]) {
                $field = new \xmldb_field($name, $type, $length, null, null, null, null);
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }
        }

        $table = new \xmldb_table('unileasys_verify_token');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tokenprefix', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, null);
            $table->add_field('schoolid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('accounttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'student');
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('courselicenseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('usedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usedtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('tokenhash', XMLDB_INDEX_UNIQUE, ['tokenhash']);
            $table->add_index('schoolclass', XMLDB_INDEX_NOTUNIQUE, ['schoolid', 'classid']);
            $dbman->create_table($table);
        } else {
            $field = new \xmldb_field('courselicenseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $index = new \xmldb_index('courselicenseid', XMLDB_INDEX_NOTUNIQUE, ['courselicenseid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }

    public static function get_status(int $userid): string {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        return $record ? (string)$record->status : self::PENDING;
    }

    public static function ensure_user(int $userid, string $status = self::PENDING): void {
        global $DB;
        self::ensure_schema();
        if ($DB->record_exists('unileasys_verification', ['userid' => $userid])) {
            return;
        }
        $DB->insert_record('unileasys_verification', (object)[
            'userid' => $userid, 'status' => $status,
            'method' => $status === self::VERIFIED ? 'legacy' : 'none',
            'timemodified' => time(),
            'timeverified' => $status === self::VERIFIED ? time() : null,
        ]);
    }

    public static function bind_verified_classification(int $userid, int $schoolid, int $classid,
            string $accounttype, int $sessionid): void {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        $data = (object)[
            'userid' => $userid,
            'schoolid' => $schoolid,
            'classid' => $classid,
            'accounttype' => strtolower($accounttype),
            'sessionid' => $sessionid,
            'timemodified' => time(),
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('unileasys_verification', $data);
        }
    }

    public static function invalidate_on_classification_change(int $userid, int $schoolid, int $classid,
            string $accounttype, int $sessionid): bool {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        if (!$record || $record->status !== self::VERIFIED) {
            return false;
        }
        if (!empty($record->schoolid) && (int)$record->schoolid !== $schoolid ||
                !empty($record->classid) && (int)$record->classid !== $classid ||
                !empty($record->accounttype) && strtolower($record->accounttype) !== strtolower($accounttype) ||
                !empty($record->sessionid) && (int)$record->sessionid !== $sessionid) {
            $DB->update_record('unileasys_verification', (object)[
                'id' => $record->id,
                'status' => self::PENDING,
                'method' => 'none',
                'tokenid' => null,
                'verifiedby' => null,
                'timeverified' => null,
                'timemodified' => time(),
            ]);
            return true;
        }
        return false;
    }

    /** Mark a verified user as pending; caller then removes course access. */
    public static function invalidate_verified(int $userid): void {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        if (!$record || $record->status !== self::VERIFIED) {
            return;
        }
        $DB->update_record('unileasys_verification', (object)[
            'id' => $record->id,
            'status' => self::PENDING,
            'method' => 'none',
            'tokenid' => null,
            'verifiedby' => null,
            'timeverified' => null,
            'timemodified' => time(),
        ]);
    }

    public static function migrate_existing_users(): void {
        global $DB;
        self::ensure_schema();
        $records = $DB->get_records('unileasys_user', null, 'id ASC', 'userid');
        foreach ($records as $record) {
            self::ensure_user((int)$record->userid, self::VERIFIED);
            $existing = $DB->get_record('unileasys_verification', ['userid' => $record->userid]);
            if ($existing && $existing->method === 'none') {
                $DB->update_record('unileasys_verification', (object)[
                    'id' => $existing->id,
                    'status' => self::VERIFIED,
                    'method' => 'legacy',
                    'schoolid' => $record->schoolid,
                    'classid' => $record->classid,
                    'accounttype' => $record->accounttype,
                    'sessionid' => $record->sessionid,
                    'timeverified' => time(),
                    'timemodified' => time(),
                ]);
            } else if ($existing && $existing->status === self::VERIFIED &&
                    (empty($existing->schoolid) || empty($existing->classid) || empty($existing->accounttype) || empty($existing->sessionid))) {
                // Bind older v1.4.0 verified users to their current classification
                // so a later profile change cannot retain verified access.
                self::bind_verified_classification((int)$record->userid, (int)$record->schoolid,
                    (int)$record->classid, (string)$record->accounttype, (int)$record->sessionid);
            }
        }
    }

    public static function is_verified(int $userid): bool {
        return self::get_status($userid) === self::VERIFIED;
    }

    public static function approve(int $userid, int $adminid): void {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        $data = (object)['userid'=>$userid, 'status'=>self::VERIFIED, 'method'=>'manual',
            'verifiedby'=>$adminid, 'timeverified'=>time(), 'timemodified'=>time()];
        if ($record) { $data->id = $record->id; $DB->update_record('unileasys_verification', $data); }
        else { self::demo_guard_verified_user($userid); $DB->insert_record('unileasys_verification', $data); }
        $mapping = $DB->get_record('unileasys_user', ['userid' => $userid]);
        if ($mapping) {
            self::bind_verified_classification($userid, (int)$mapping->schoolid, (int)$mapping->classid,
                (string)$mapping->accounttype, (int)$mapping->sessionid);
        }
    }

    public static function reject(int $userid, int $adminid): void {
        global $DB;
        self::ensure_schema();
        $record = $DB->get_record('unileasys_verification', ['userid' => $userid]);
        $data = (object)['userid'=>$userid, 'status'=>self::REJECTED, 'method'=>'manual',
            'verifiedby'=>$adminid, 'timemodified'=>time()];
        if ($record) { $data->id = $record->id; $DB->update_record('unileasys_verification', $data); }
        else { $DB->insert_record('unileasys_verification', $data); }
    }

    private static function demo_guard_verified_user(int $userid): void {
        if (\local_unileasysregistration\demo::enabled()) {
            $existing = self::get_status($userid);
            if ($existing !== self::VERIFIED) {
                \local_unileasysregistration\demo::require_capacity('verified');
            }
        }
    }

    public static function create_token(?int $schoolid, ?int $classid, string $accounttype,
            int $sessionid, int $days, ?int $courseid = null, ?int $expiresat = null, ?int $courselicenseid = null): array {
        global $DB;
        self::ensure_schema();
        if (!$schoolid || !$classid || !$sessionid || !in_array(strtolower($accounttype), ['student', 'teacher'], true)) {
            throw new \moodle_exception('invalidactivationcodeparameters', 'local_unileasysregistration');
        }
        if (!$DB->record_exists('unileasys_school', ['id' => $schoolid, 'active' => 1]) ||
                !$DB->record_exists('unileasys_class', ['id' => $classid, 'active' => 1]) ||
                !$DB->record_exists('unileasys_session', ['id' => $sessionid])) {
            throw new \moodle_exception('invalidactivationcodeparameters', 'local_unileasysregistration');
        }
        $raw = strtoupper(substr(bin2hex(random_bytes(16)), 0, 16));
        if ($courseid !== null && $courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
            throw new \moodle_exception('invalidactivationcodeparameters', 'local_unileasysregistration');
        }
        if ($expiresat !== null && (int)$expiresat <= time()) {
            throw new \moodle_exception('course_license_not_available_for_tokens', 'local_unileasysregistration');
        }
        $record = (object)[
            'tokenhash'=>hash('sha256',$raw), 'tokenprefix'=>substr($raw,0,8),
            'schoolid'=>$schoolid ?: null, 'classid'=>$classid ?: null,
            'accounttype'=>strtolower($accounttype), 'sessionid'=>$sessionid ?: null,
            'courseid'=>$courseid ?: null,
            'courselicenseid'=>$courselicenseid ?: null,
            'expiresat'=>($expiresat !== null ? (int)$expiresat : time()+max(1,$days)*DAYSECS),
            'active'=>1, 'timecreated'=>time()
        ];
        $record->id=$DB->insert_record('unileasys_verify_token',$record);
        return ['token'=>$raw,'id'=>$record->id];
    }

    public static function revoke_token(int $tokenid): bool {
        global $DB;
        self::ensure_schema();
        $token = $DB->get_record('unileasys_verify_token', ['id' => $tokenid]);
        if (!$token || !(int)$token->active || (int)$token->usedby) {
            return false;
        }
        $DB->update_record('unileasys_verify_token', (object)[
            'id' => $tokenid,
            'active' => 0,
        ]);
        return true;
    }

    public static function redeem_token(string $raw, int $userid, int $schoolid, int $classid,
            string $accounttype, int $sessionid): bool {
        global $DB;
        self::ensure_schema();
        $token=$DB->get_record('unileasys_verify_token',['tokenhash'=>hash('sha256',strtoupper(trim($raw)))]);
        if (!$token || !(int)$token->active || (int)$token->usedby || (int)$token->expiresat < time()) return false;
        if ($token->schoolid && (int)$token->schoolid !== $schoolid) return false;
        if ($token->classid && (int)$token->classid !== $classid) return false;
        if (strtolower($token->accounttype) !== strtolower($accounttype)) return false;
        if ($token->sessionid && (int)$token->sessionid !== $sessionid) return false;
        // Course tokens are redeemed from My School Courses, not account activation.
        if (!empty($token->courseid)) return false;
        $DB->update_record('unileasys_verify_token',(object)[
            'id'=>$token->id,'active'=>0,'usedby'=>$userid,'usedtime'=>time()]);
        $existing=$DB->get_record('unileasys_verification',['userid'=>$userid]);
        $data=(object)['userid'=>$userid,'status'=>self::VERIFIED,'method'=>'token',
            'schoolid'=>$schoolid,'classid'=>$classid,'accounttype'=>strtolower($accounttype),
            'sessionid'=>$sessionid,'tokenid'=>$token->id,'timeverified'=>time(),'timemodified'=>time()];
        if ($existing) {$data->id=$existing->id;$DB->update_record('unileasys_verification',$data);}
        else { self::demo_guard_verified_user($userid); $DB->insert_record('unileasys_verification',$data);}
        return true;
    }

    /** Redeem a course-specific token for an already verified school user. */
    public static function redeem_course_token(string $raw, int $userid, int $courseid, int $schoolid,
            int $classid, string $accounttype, int $sessionid): bool {
        global $DB;
        self::ensure_schema();
        schema::ensure_course_activation();
        if (!self::is_verified($userid) || $courseid <= 0) {
            return false;
        }
        $hash = hash('sha256', strtoupper(trim($raw)));
        $token = $DB->get_record('unileasys_verify_token', ['tokenhash' => $hash]);
        if (!$token || !(int)$token->active || (int)$token->usedby || (int)$token->expiresat < time()) {
            return false;
        }
        if (empty($token->courseid) || (int)$token->courseid !== $courseid) return false;
        if ($token->schoolid && (int)$token->schoolid !== $schoolid) return false;
        if ($token->classid && (int)$token->classid !== $classid) return false;
        if (strtolower($token->accounttype) !== strtolower($accounttype)) return false;
        if ($token->sessionid) {
            $sessionok = ((int)$token->sessionid === $sessionid);
            if (!$sessionok) {
                $active = $DB->get_record('unileasys_session', ['active'=>1], 'id', IGNORE_MULTIPLE);
                $sessionok = $active && (int)$token->sessionid === (int)$active->id;
            }
            if (!$sessionok) return false;
        }
        $licenseid = 0;
        $courselicense = \local_unileasysregistration\course_license::get_for_user($userid, $courseid);
        if (!empty($token->courselicenseid)) {
            $linkedlicense = $DB->get_record('unileasys_course_license', ['id' => (int)$token->courselicenseid], '*', IGNORE_MISSING);
            if (!$linkedlicense || (int)$linkedlicense->courseid !== $courseid ||
                    (int)$linkedlicense->schoolid !== $schoolid || (int)$linkedlicense->classid !== $classid ||
                    strtolower((string)$linkedlicense->accounttype) !== strtolower($accounttype) ||
                    (int)$linkedlicense->sessionid !== $sessionid || (string)$linkedlicense->status !== \local_unileasysregistration\course_license::ACTIVE ||
                    (int)$linkedlicense->expiresat < time()) {
                return false;
            }
            $licenseid = (int)$linkedlicense->id;
        }
        if ($courselicense) {
            if (!\local_unileasysregistration\course_license::can_user_access($userid, $courseid)) {
                return false;
            }
            $licenseid = (int)$courselicense->id;
        } else if (\local_unileasysregistration\course_license::course_has_licenses($courseid)) {
            return false;
        }

        // Do not consume a fresh school-issued course token unless the
        // corresponding Moodle enrolment can actually be restored. This is
        // important when a license was previously suspended/revoked.
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->update_record('unileasys_verify_token', (object)[
                'id' => $token->id, 'active' => 0, 'usedby' => $userid, 'usedtime' => time()
            ]);
            $existing = $DB->get_record('unileasys_course_activation', ['userid' => $userid, 'courseid' => $courseid]);
            $data = (object)[
                'userid' => $userid, 'courseid' => $courseid, 'tokenid' => $token->id,
                'licenseid' => $licenseid, 'status' => 1, 'timeactivated' => time(), 'timemodified' => time()
            ];
            if ($existing) { $data->id = $existing->id; $DB->update_record('unileasys_course_activation', $data); }
            else { $DB->insert_record('unileasys_course_activation', $data); }

            if (!\local_unileasysregistration\enrolment_manager::activate_course($userid, $courseid)) {
                $transaction->rollback(new \moodle_exception('courseactivationinvalid', 'local_unileasysregistration'));
            }
            $transaction->allow_commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return false;
        }
    }
}
