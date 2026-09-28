<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_unileasysregistration_manage',
        get_string('manage', 'local_unileasysregistration')
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_dashboard',
        get_string('dashboard', 'local_unileasysregistration'),
        new moodle_url('/local/unileasysregistration/dashboard.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_schools',
        get_string('schools', 'local_unileasysregistration'),
        new moodle_url('/local/unileasysregistration/schools.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_classes',
        get_string('classes', 'local_unileasysregistration'),
        new moodle_url('/local/unileasysregistration/classes.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_sessions',
        get_string('sessions', 'local_unileasysregistration'),
        new moodle_url('/local/unileasysregistration/sessions.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_enrolrules',
        get_string('enrolmentrules', 'local_unileasysregistration'),
        new moodle_url('/local/unileasysregistration/enrolrules.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_verification',
        'User Verification',
        new moodle_url('/local/unileasysregistration/verification.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_tokens',
        'Verification Tokens',
        new moodle_url('/local/unileasysregistration/tokens.php'),
        'local/unileasysregistration:manage'
    ));
    $ADMIN->add('local_unileasysregistration_manage', new admin_externalpage(
        'local_unileasysregistration_courselicenses',
        'Course Licenses',
        new moodle_url('/local/unileasysregistration/courselicenses.php'),
        'local/unileasysregistration:manage'
    ));
}

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_unileasysregistration_settings',
        get_string('settings', 'local_unileasysregistration')
    );
    $settings->add(new admin_setting_configtext(
        'local_unileasysregistration/cohortprefix',
        get_string('cohortprefix', 'local_unileasysregistration'),
        'Prefix used for automatically created cohorts.',
        'UNI',
        PARAM_ALPHANUMEXT,
        20
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_unileasysregistration/enableteacherregistration',
        get_string('enableteacherregistration', 'local_unileasysregistration'),
        '',
        1
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'local_unileasysregistration/teacherregistrationcode',
        get_string('teacherregistrationcode', 'local_unileasysregistration'),
        'Optional code required when Teacher is selected during public registration.',
        '',
        PARAM_TEXT
    ));
    $ADMIN->add('localplugins', $settings);
}

// Synchronization log is also available from the Unileasys administration navigation.
