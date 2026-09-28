<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

/**
 * Idempotent database/master-data safety helpers.
 *
 * v1.6.1 deliberately does not depend on db/install.xml for table creation.
 * This makes both clean installs and recovery installs safe when old
 * unileasys_* tables are present but Moodle has lost the plugin registration.
 */
class schema {
    public static function ensure_all_tables(): void {
        self::ensure_master_tables();
        self::ensure_enrolment_tables();
        self::ensure_sync_log();
        verification::ensure_schema();
        self::ensure_course_activation();
        license::ensure_schema();
    }

    private static function ensure_master_tables(): void {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new \xmldb_table('unileasys_school');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('code_unique', XMLDB_KEY_UNIQUE, ['code']);
            $dbman->create_table($table);
        }

        $table = new \xmldb_table('unileasys_class');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('shortname', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        $table = new \xmldb_table('unileasys_session');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        $table = new \xmldb_table('unileasys_user');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('schoolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('accounttype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_unique', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_index('schoolclass', XMLDB_INDEX_NOTUNIQUE, ['schoolid', 'classid']);
            $table->add_index('cohortidx', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
            $dbman->create_table($table);
        }
    }

    public static function ensure_enrolment_tables(): void {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new \xmldb_table('unileasys_enrol_rule');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('schoolids', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('classids', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('accounttypes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseactive', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'active']);
            $dbman->create_table($table);
        }

        $table = new \xmldb_table('unileasys_enrolment');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('usercourse', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
            $table->add_index('rule', XMLDB_INDEX_NOTUNIQUE, ['ruleid']);
            $dbman->create_table($table);
        }
    }

    private static function ensure_sync_log(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('unileasys_sync_log');
        if ($dbman->table_exists($table)) {
            return;
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'sync');
        $table->add_field('result', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'success');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('result', XMLDB_INDEX_NOTUNIQUE, ['result']);
        $dbman->create_table($table);
    }

    public static function ensure_verification_tables(): void {
        verification::ensure_schema();
    }

    public static function ensure_course_activation(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('unileasys_course_activation');
        if ($dbman->table_exists($table)) {
            $field = new \xmldb_field('licenseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $index = new \xmldb_index('licenseidx', XMLDB_INDEX_NOTUNIQUE, ['licenseid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            return;
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('tokenid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
        $table->add_field('timeactivated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('usercourse', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('token', XMLDB_INDEX_NOTUNIQUE, ['tokenid']);
        $table->add_index('licenseidx', XMLDB_INDEX_NOTUNIQUE, ['licenseid']);
        $dbman->create_table($table);
    }

    public static function ensure_master_data(): void {
        global $DB;
        $now = time();
        if (!$DB->record_exists('unileasys_class', [])) {
            $classes = [
                ['Class 3', 'C3', 3], ['Class 4', 'C4', 4], ['Class 5', 'C5', 5],
                ['Class 6', 'C6', 6], ['Class 7', 'C7', 7], ['Class 8', 'C8', 8],
                ['Class 9', 'C9', 9], ['Class 10', 'C10', 10], ['Class 11', 'C11', 11],
                ['Class 12', 'C12', 12],
            ];
            foreach ($classes as [$name, $shortname, $sortorder]) {
                $DB->insert_record('unileasys_class', (object)[
                    'name' => $name, 'shortname' => $shortname, 'sortorder' => $sortorder,
                    'active' => 1, 'timecreated' => $now, 'timemodified' => $now,
                ]);
            }
        }
        if (!$DB->record_exists('unileasys_session', [])) {
            $DB->insert_record('unileasys_session', (object)[
                'name' => '2026-27', 'active' => 1, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
    }
}
