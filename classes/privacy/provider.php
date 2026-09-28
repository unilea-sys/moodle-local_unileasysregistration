<?php
namespace local_unileasysregistration\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table(
            'unileasys_user',
            [
                'userid' => 'privacy:metadata:unileasys_user:userid',
                'schoolid' => 'privacy:metadata:unileasys_user:schoolid',
                'classid' => 'privacy:metadata:unileasys_user:classid',
                'accounttype' => 'privacy:metadata:unileasys_user:accounttype',
                'sessionid' => 'privacy:metadata:unileasys_user:sessionid',
                'cohortid' => 'privacy:metadata:unileasys_user:cohortid',
                'timecreated' => 'privacy:metadata:unileasys_user:timecreated',
                'timemodified' => 'privacy:metadata:unileasys_user:timemodified',
            ],
            'privacy:metadata:unileasys_user'
        );

        $collection->add_database_table(
            'unileasys_course_activation',
            [
                'userid' => 'privacy:metadata:unileasys_course_activation:userid',
                'courseid' => 'privacy:metadata:unileasys_course_activation:courseid',
                'tokenid' => 'privacy:metadata:unileasys_course_activation:tokenid',
                'licenseid' => 'privacy:metadata:unileasys_course_activation:licenseid',
                'status' => 'privacy:metadata:unileasys_course_activation:status',
                'timeactivated' => 'privacy:metadata:unileasys_course_activation:timeactivated',
                'timemodified' => 'privacy:metadata:unileasys_course_activation:timemodified',
            ],
            'privacy:metadata:unileasys_course_activation'
        );

        $collection->add_database_table(
            'unileasys_verification',
            [
                'userid' => 'privacy:metadata:unileasys_verification:userid',
                'status' => 'privacy:metadata:unileasys_verification:status',
                'method' => 'privacy:metadata:unileasys_verification:method',
                'tokenid' => 'privacy:metadata:unileasys_verification:tokenid',
                'verifiedby' => 'privacy:metadata:unileasys_verification:verifiedby',
                'timeverified' => 'privacy:metadata:unileasys_verification:timeverified',
                'timemodified' => 'privacy:metadata:unileasys_verification:timemodified',
            ],
            'privacy:metadata:unileasys_verification'
        );
        return $collection;
    }

    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $userlist->get_user()->id;
        if ($DB->record_exists('unileasys_user', ['userid' => $userid]) ||
                $DB->record_exists('unileasys_verification', ['userid' => $userid]) ||
                $DB->record_exists('unileasys_verify_token', ['usedby' => $userid]) ||
                $DB->record_exists('unileasys_course_activation', ['userid' => $userid])) {
            $userlist->add_user($userid);
        }
    }

    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist as $context) {
            $userid = $context->instanceid;
            $record = $DB->get_record('unileasys_user', ['userid' => $userid]);
            if ($record) {
                \core_privacy\local\request\writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_unileasysregistration')],
                    $record
                );
            }
            $activations = $DB->get_records('unileasys_course_activation', ['userid' => $userid]);
            if ($activations) {
                \core_privacy\local\request\writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_unileasysregistration'), 'courseactivations'],
                    (object)['activations' => array_values($activations)]
                );
            }

            $verification = $DB->get_record('unileasys_verification', ['userid' => $userid]);
            if ($verification) {
                \core_privacy\local\request\writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_unileasysregistration'), 'verification'],
                    $verification
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_USER) {
            $DB->delete_records('unileasys_user', ['userid' => $context->instanceid]);
            $DB->delete_records('unileasys_verification', ['userid' => $context->instanceid]);
            $DB->delete_records('unileasys_course_activation', ['userid' => $context->instanceid]);
            $DB->set_field('unileasys_verify_token', 'usedby', null, ['usedby' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist as $context) {
            $DB->delete_records('unileasys_user', ['userid' => $context->instanceid]);
            $DB->delete_records('unileasys_verification', ['userid' => $context->instanceid]);
            $DB->delete_records('unileasys_course_activation', ['userid' => $context->instanceid]);
            $DB->set_field('unileasys_verify_token', 'usedby', null, ['usedby' => $context->instanceid]);
        }
    }
}
