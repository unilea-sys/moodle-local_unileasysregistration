<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_unileasysregistration_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026092512) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('unileasys_license');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('customeremail', XMLDB_TYPE_CHAR, '320', null, XMLDB_NOTNULL, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026092512, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091902) {
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026091902, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091903) {
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026091903, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091905) {
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026091905, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091906) {
        upgrade_plugin_savepoint(true, 2026091906, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091907) {
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026091907, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091908) {
        upgrade_plugin_savepoint(true, 2026091908, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091909) {
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026091909, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091910) {
        upgrade_plugin_savepoint(true, 2026091910, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026091920) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('unileasys_enrol_rule');
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

        $table = new xmldb_table('unileasys_enrolment');
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

        // Existing 1.0.x users/cohorts are deliberately left intact. From 1.1
        // onward, course enrolment is rule-based; legacy cohort automation is
        // opt-in through the setting created below.
        set_config('legacycohorts', 0, 'local_unileasysregistration');
        upgrade_plugin_savepoint(true, 2026091920, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091922) {
        // Final safety net: ensure the rule-engine tables exist even if a prior
        // upgrade was interrupted or the database migration was incomplete.
        \local_unileasysregistration\schema::ensure_enrolment_tables();
        upgrade_plugin_savepoint(true, 2026091922, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091923) {
        \local_unileasysregistration\schema::ensure_enrolment_tables();
        \local_unileasysregistration\schema::ensure_master_data();
        upgrade_plugin_savepoint(true, 2026091923, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091924) {
        // The rule form now uses a Moodle 5.2-safe direct course query.
        upgrade_plugin_savepoint(true, 2026091924, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026091926) {
        // Fix rule-form submission: preserve action/id in the form POST target.
        upgrade_plugin_savepoint(true, 2026091926, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026091927) {
        // Improve rule status editing and add an explicit enable/disable toggle.
        upgrade_plugin_savepoint(true, 2026091927, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026091928) {
        // Add the adhoc synchronization task used to process profile data after
        // user creation/profile-save transactions. No database schema change is
        // required for the task itself.
        upgrade_plugin_savepoint(true, 2026091928, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092002) {
        upgrade_plugin_savepoint(true, 2026092002, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092003) {
        // Add secure school-user verification and one-time invitation tokens.
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\verification::migrate_existing_users();
        upgrade_plugin_savepoint(true, 2026092003, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092004) {
        // Move activation-code redemption out of public registration and into
        // the authenticated student-facing Activate Account page.
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\verification::migrate_existing_users();
        upgrade_plugin_savepoint(true, 2026092004, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092005) {
        // Add school activation-code distribution exports (Excel, CSV and PDF).
        // No database schema change is required; plaintext codes remain available
        // only in the generating administrator's current Moodle session.
        upgrade_plugin_savepoint(true, 2026092005, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092006) {
        // Activation-code generation is now strictly scoped to one school, class,
        // account type and academic session. Wildcard generation is not permitted.
        upgrade_plugin_savepoint(true, 2026092006, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092008) {
        upgrade_plugin_savepoint(true, 2026092008, 'local', 'unileasysregistration');
    }


    if ($oldversion < 2026092011) {
        // v1.5.0: add direct My Profile navigation entry for student activation.
        upgrade_plugin_savepoint(true, 2026092011, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092012) {
        // v1.5.1: correctly suspend managed Moodle enrolments when a verified
        // user's school/class/account type/session changes.
        \local_unileasysregistration\schema::ensure_enrolment_tables();
        upgrade_plugin_savepoint(true, 2026092012, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092013) {
        // v1.5.2: fail-closed classification-change handling. Compare the
        // stored Unileasys mapping with the current profile classification so
        // managed enrolments cannot remain active after a school/class/type or
        // academic-session change.
        \local_unileasysregistration\schema::ensure_enrolment_tables();
        upgrade_plugin_savepoint(true, 2026092013, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092015) {
        // v1.5.4: branded Unileasys Token System export formats.
        upgrade_plugin_savepoint(true, 2026092015, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092016) {
        // v1.5.5: add the student-facing My Unileasys dashboard.
        upgrade_plugin_savepoint(true, 2026092016, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092017) {
        // v1.5.6: production packaging cleanup only; no database changes.
        upgrade_plugin_savepoint(true, 2026092017, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092401) {
        // v1.6.0: remote one-site licensing.
        \local_unileasysregistration\license::ensure_schema();
        upgrade_plugin_savepoint(true, 2026092401, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092402) {
        // v1.6.1: idempotent schema repair. This is safe for normal upgrades
        // and also ensures every table exists if an older migration was partial.
        \local_unileasysregistration\schema::ensure_all_tables();
        \local_unileasysregistration\profile_fields::ensure_all();
        upgrade_plugin_savepoint(true, 2026092402, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092403) {
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\schema::ensure_course_activation();
        upgrade_plugin_savepoint(true, 2026092403, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092404) {
        // v1.6.4: navigation-only improvement. No database changes.
        upgrade_plugin_savepoint(true, 2026092404, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092405) {
        // v1.6.5: use Moodle 5.2 primary/user-menu hooks. No database changes.
        upgrade_plugin_savepoint(true, 2026092405, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092407) {
        // v1.6.7: fix course activation modal rendering for Moodle 5.2.
        // No database changes.
        upgrade_plugin_savepoint(true, 2026092407, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092411) {
        // v1.7.1: UI cleanup and missing course action language string.
        upgrade_plugin_savepoint(true, 2026092411, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092412) {
        // v1.7.2: course-token redemption now uses the stored verified
        // classification/session as the authoritative token binding.
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\schema::ensure_course_activation();
        upgrade_plugin_savepoint(true, 2026092412, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092413) {
        // v1.7.3: restore robust course-token validation while keeping the
        // verified school/class/account binding authoritative. Course tokens
        // may match either the stored verified session or the currently active
        // academic session, which avoids rejecting valid newly-issued tokens
        // after an academic-session rollover.
        \local_unileasysregistration\verification::ensure_schema();
        \local_unileasysregistration\schema::ensure_course_activation();
        upgrade_plugin_savepoint(true, 2026092413, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092415) {
        // v1.7.5: token-management reset permanently removes all unused
        // activation-token records, including previously revoked unused codes.
        // Used-token history remains intact.
        upgrade_plugin_savepoint(true, 2026092415, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092420) {
        // v1.7.6: administrative deletion controls for enrolment rules and
        // individual token records, plus tabular license details. No schema changes.
        upgrade_plugin_savepoint(true, 2026092420, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092422) {
        // v1.7.8: remove the redundant standalone deactivation button below the
        // license table; the row-level action remains the single deactivation control.
        upgrade_plugin_savepoint(true, 2026092422, 'local', 'unileasysregistration');
    }


    if ($oldversion < 2026092424) {
        // v1.8.0: add standalone course-license/entitlement management.
        // The existing commercial plugin license in unileasys_license remains
        // unchanged and continues to control use of this plugin.
        \local_unileasysregistration\course_license::ensure_schema();
        \local_unileasysregistration\schema::ensure_course_activation();
        upgrade_plugin_savepoint(true, 2026092424, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092501) {
        // v1.8.1: strict course-license enforcement and a 15-minute scheduled
        // task. No database schema changes are required.
        \local_unileasysregistration\course_license::ensure_schema();
        upgrade_plugin_savepoint(true, 2026092501, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092502) {
        // v1.8.2: add Course License edit/delete controls. No database schema
        // changes are required; deletion is protected when the license is in use
        // or has activation history.
        \local_unileasysregistration\course_license::ensure_schema();
        upgrade_plugin_savepoint(true, 2026092502, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092503) {
        // v1.8.3: remove the legacy automatic cohort creation setting from the
        // administrator UI. The legacy behavior remains internally available
        // only for backward compatibility with existing data.
        upgrade_plugin_savepoint(true, 2026092503, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092504) {
        // v1.8.4: add the Course License details/user view. No database schema
        // changes are required.
        upgrade_plugin_savepoint(true, 2026092504, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092505) {
        // v1.8.5: fix namespaced verification class references on the Course License details page.
        upgrade_plugin_savepoint(true, 2026092505, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092506) {
        // v1.8.6: prevent duplicate course registrations, restore access immediately
        // when a Course License is activated/renewed, make fresh course tokens
        // transactional, and hide seller-side plugin license activation UI.
        \local_unileasysregistration\course_license::ensure_schema();
        upgrade_plugin_savepoint(true, 2026092506, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092507) {
        // v1.8.7: remove the remaining customer-facing commercial-license entry
        // point. Commercial licensing remains an internal client/server concern;
        // customer administration exposes only Unileasys course functionality.
        upgrade_plugin_savepoint(true, 2026092507, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092508) {
        // v1.8.8: include all Moodle user name fields in Course License details
        // so fullname() does not emit missing-field debugging notices.
        upgrade_plugin_savepoint(true, 2026092508, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092509) {
        // v1.8.9: include all Moodle user name fields in the verification-token
        // management page so fullname() does not emit missing-field debugging notices.
        upgrade_plugin_savepoint(true, 2026092509, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092601) {
        // v1.8.13: allow administrators to change the commercial license from Moodle
        // without exposing seller-side license management controls.
        upgrade_plugin_savepoint(true, 2026092601, 'local', 'unileasysregistration');
    }


    if ($oldversion < 2026092608) {
        // v1.8.20: link course activation tokens to their Course License so
        // token issuance is capped by the license seat count and redemption
        // can verify the exact entitlement.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('unileasys_verify_token');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('courselicenseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $index = new xmldb_index('courselicenseid', XMLDB_INDEX_NOTUNIQUE, ['courselicenseid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026092608, 'local', 'unileasysregistration');
    }

    if ($oldversion < 2026092607) {
        // Link Course Licenses to the enrolment rule that created them.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('unileasys_course_license');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'courseid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $index = new xmldb_index('ruleid', XMLDB_INDEX_NOTUNIQUE, ['ruleid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026092607, 'local', 'unileasysregistration');
    }
    if ($oldversion < 2026092609) {
        // v1.8.21: fix Course License token schema upgrade for installations
        // where unileasys_verify_token does not contain courseid. The field
        // must not be positioned AFTER a non-existent column.
        upgrade_plugin_savepoint(true, 2026092609, 'local', 'unileasysregistration');
    }
    return true;
}

