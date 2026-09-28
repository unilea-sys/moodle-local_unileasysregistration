<?php
defined('MOODLE_INTERNAL') || die();

// This package is the restricted free Demo Edition. The commercial build does not define this constant.
define('UNILEASYS_DEMO_EDITION', true);

/**
 * Extend Moodle's public signup form.
 *
 * School, Class and Account Type are real Moodle custom user profile fields.
 * Moodle therefore also shows them on admin Add User, user Edit Profile,
 * and the user profile, not just on public signup.
 */
function local_unileasysregistration_extend_signup_form($mform): void {
    global $DB;
    if (!\local_unileasysregistration\license::is_valid(false)) {
        return;
    }

    // Moodle may build/extend the signup form more than once during a request.
    // This callback must be idempotent; otherwise duplicate fieldsets can be
    // rendered and the same field names can collide.
    static $added = false;
    if ($added) {
        return;
    }
    $added = true;

    $schooloptions = ['' => get_string('selectschool', 'local_unileasysregistration')];
    $schools = $DB->get_records_select('unileasys_school', 'active = 1', null,
        'name ASC', 'id,name');
    foreach ($schools as $school) {
        $schooloptions[$school->name] = $school->name;
    }

    $classoptions = ['' => get_string('selectclass', 'local_unileasysregistration')];
    $classes = $DB->get_records_select('unileasys_class', 'active = 1', null,
        'sortorder ASC, name ASC', 'id,name');
    foreach ($classes as $class) {
        $classoptions[$class->name] = $class->name;
    }

    $accountoptions = [
        '' => get_string('selectaccounttype', 'local_unileasysregistration'),
        'Student' => 'Student',
        'Teacher' => 'Teacher',
    ];

    // Insert directly before the email field. Do not add these fields first
    // and then remove/reinsert them; that can leave orphaned elements in
    // MoodleQuickForm on repeated form construction.
    $before = $mform->elementExists('email') ? 'email' : null;

    $intro = $mform->createElement(
        'html',
        '<div class="unileasys-registration-intro" style="margin: 1rem 0 0.5rem;">' .
        '<strong>' . s(get_string('registrationfieldset', 'local_unileasysregistration')) . '</strong>' .
        '<div class="small text-muted">' .
        s(get_string('registrationoptionalhelp', 'local_unileasysregistration')) .
        '</div></div>'
    );

    $school = $mform->createElement(
        'select', 'profile_field_unileasys_school',
        get_string('school', 'local_unileasysregistration'), $schooloptions
    );
    $class = $mform->createElement(
        'select', 'profile_field_unileasys_class',
        get_string('class', 'local_unileasysregistration'), $classoptions
    );
    $accounttype = $mform->createElement(
        'select', 'profile_field_unileasys_account_type',
        get_string('accounttype', 'local_unileasysregistration'), $accountoptions
    );


    if ($before !== null) {
        $mform->insertElementBefore($intro, $before);
        $mform->insertElementBefore($school, $before);
        $mform->insertElementBefore($class, $before);
        $mform->insertElementBefore($accounttype, $before);
    } else {
        $mform->addElement($intro);
        $mform->addElement($school);
        $mform->addElement($class);
        $mform->addElement($accounttype);
    }

    $mform->setType('profile_field_unileasys_school', PARAM_TEXT);
    $mform->setType('profile_field_unileasys_class', PARAM_TEXT);
    $mform->setType('profile_field_unileasys_account_type', PARAM_TEXT);
    $mform->setDefault('profile_field_unileasys_school', '');
    $mform->setDefault('profile_field_unileasys_class', '');
    $mform->setDefault('profile_field_unileasys_account_type', '');

    local_unileasysregistration_extend_signup_form_extra($mform);

    if ($mform->elementExists('unileasys_teacher_code')) {
        $element = $mform->removeElement('unileasys_teacher_code', false);
        if ($element) {
            $before = $mform->elementExists('email') ? 'email' : null;
            if ($before !== null) {
                $mform->insertElementBefore($element, $before);
            } else {
                $mform->addElement($element);
            }
        }
    }
}

/**
 * Validate the extra registration rules.
 *
 * The actual custom profile fields are validated/saved by Moodle's profile API.
 * This callback additionally protects the public Teacher registration code.
 */
function local_unileasysregistration_validate_extend_signup_form($data): array {
    global $DB;

    $errors = [];

    $school = trim((string)($data['profile_field_unileasys_school'] ?? ''));
    $class = trim((string)($data['profile_field_unileasys_class'] ?? ''));
    $accounttype = strtolower(trim((string)($data['profile_field_unileasys_account_type'] ?? '')));

    // All three fields blank means this is a normal/core Moodle account.
    // Do not require school data and do not create a Unileasys cohort.
    if ($school === '' && $class === '' && $accounttype === '') {
        return $errors;
    }

    // Once a user chooses to participate in the school system, all three
    // classification fields must be supplied together.
    if ($school === '') {
        $errors['profile_field_unileasys_school'] = get_string('schoolrequired', 'local_unileasysregistration');
    }
    if ($class === '') {
        $errors['profile_field_unileasys_class'] = get_string('classrequired', 'local_unileasysregistration');
    }
    if ($accounttype === '') {
        $errors['profile_field_unileasys_account_type'] = get_string('accounttyperequired', 'local_unileasysregistration');
    }

    if ($school !== '') {
        $schoolvalid =
            $DB->record_exists('unileasys_school', ['name' => $school, 'active' => 1]) ||
            $DB->record_exists('unileasys_school', ['code' => $school, 'active' => 1]);
        if (!$schoolvalid) {
            $errors['profile_field_unileasys_school'] = get_string('invalidschool', 'local_unileasysregistration');
        }
    }

    if ($class !== '') {
        $classvalid =
            $DB->record_exists('unileasys_class', ['name' => $class, 'active' => 1]) ||
            $DB->record_exists('unileasys_class', ['shortname' => $class, 'active' => 1]);
        if (!$classvalid) {
            $errors['profile_field_unileasys_class'] = get_string('invalidclass', 'local_unileasysregistration');
        }
    }

    if ($accounttype !== '' && !in_array($accounttype, ['student', 'teacher'], true)) {
        $errors['profile_field_unileasys_account_type'] = get_string('accounttyperequired', 'local_unileasysregistration');
    }

    if ($accounttype === 'teacher') {
        $enabled = get_config('local_unileasysregistration', 'enableteacherregistration');
        if (!$enabled) {
            $errors['profile_field_unileasys_account_type'] = get_string('teacherdisabled', 'local_unileasysregistration');
        }

        $code = (string)get_config('local_unileasysregistration', 'teacherregistrationcode');
        if ($code !== '') {
            $submitted = (string)($data['unileasys_teacher_code'] ?? '');
            if (!hash_equals($code, $submitted)) {
                $errors['unileasys_teacher_code'] = get_string('wrongteachercode', 'local_unileasysregistration');
            }
        }
    }

    if (!$DB->record_exists('unileasys_session', ['active' => 1])) {
        $errors['profile_field_unileasys_school'] = get_string('invalidsession', 'local_unileasysregistration');
    }

    return $errors;
}

/**
 * Add the teacher registration code to public signup only.
 *
 * @param moodleform $mform
 */
function local_unileasysregistration_extend_signup_form_extra($mform): void {
    $enabled = get_config('local_unileasysregistration', 'enableteacherregistration');
    if (!$enabled) {
        return;
    }

    $code = (string)get_config('local_unileasysregistration', 'teacherregistrationcode');
    if ($code !== '') {
        $mform->addElement('passwordunmask', 'unileasys_teacher_code',
            get_string('teacher_code', 'local_unileasysregistration'));
        $mform->setType('unileasys_teacher_code', PARAM_TEXT);
        $mform->hideIf('unileasys_teacher_code', 'profile_field_unileasys_account_type', 'neq', 'teacher');
    }
}

/**
 * Kept for compatibility with the previous plugin version.
 *
 * The authoritative post-registration processing is now done by
 * core user_created/user_updated observers because that also covers
 * Site administration > Users > Add a new user and profile editing.
 */
function local_unileasysregistration_post_signup_requests($data): void {
    global $DB, $CFG;

    // Moodle documents this callback as receiving the submitted signup data.
    // Do not assume it is already persisted in user_info_data: explicitly save
    // the three Unileasys profile values first, then perform cohort sync.
    $userid = 0;
    if (is_object($data)) {
        $userid = !empty($data->id) ? (int)$data->id : (!empty($data->userid) ? (int)$data->userid : 0);
    } else if (is_array($data)) {
        $userid = !empty($data['id']) ? (int)$data['id'] : (!empty($data['userid']) ? (int)$data['userid'] : 0);
    }

    if ($userid <= 1 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        return;
    }

    require_once($CFG->dirroot . '/user/profile/lib.php');

    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        return;
    }

    $keys = [
        'profile_field_unileasys_school',
        'profile_field_unileasys_class',
        'profile_field_unileasys_account_type',
    ];

    foreach ($keys as $key) {
        $value = null;
        if (is_object($data) && property_exists($data, $key)) {
            $value = $data->{$key};
        } else if (is_array($data) && array_key_exists($key, $data)) {
            $value = $data[$key];
        }
        if ($value !== null) {
            $user->{$key} = is_scalar($value) ? (string)$value : '';
        }
    }

    profile_save_data($user);


    if (!\local_unileasysregistration\observer::sync_user($userid)) {
        // Keep the delayed fallback for unusual signup/authentication flows.
        \local_unileasysregistration\observer::queue_sync($userid);
    }
}

/**
 * Add the student-facing account activation page to the global navigation.
 * This is intentionally separate from the public registration form.
 */
function local_unileasysregistration_extend_navigation_global($navigation): void {
    global $USER;

    if (empty($USER->id) || $USER->id <= 1 || isguestuser($USER->id)) {
        return;
    }

    // Give every logged-in Unileasys user a clear home page. Pending users also
    // retain the dedicated activation link below.
    $home = $navigation->add(
        get_string('myunileasys', 'local_unileasysregistration'),
        new \moodle_url('/local/unileasysregistration/studentdashboard.php'),
        navigation_node::TYPE_CUSTOM,
        'local_unileasysregistration',
        'unileasys_home',
        new \pix_icon('i/home', '', 'moodle')
    );
    if ($home) {
        $home->showinflatnavigation = true;
    }

    $status = \local_unileasysregistration\verification::get_status((int)$USER->id);
    if ($status === \local_unileasysregistration\verification::VERIFIED) {
        return;
    }

    $node = $navigation->add(
        get_string('activateaccount', 'local_unileasysregistration'),
        new \moodle_url('/local/unileasysregistration/activate.php'),
        navigation_node::TYPE_CUSTOM,
        'local_unileasysregistration',
        'unileasys_activate',
        new \pix_icon('t/check', '', 'moodle')
    );
    if ($node) {
        $node->showinflatnavigation = true;
    }
}

/**
 * Add the activation link to the user's profile navigation.
 * This is the user profile navigation callback and is separate from the
 * user settings/preferences navigation. It is intentionally implemented
 * because Moodle themes may render these navigation trees differently.
 */
function local_unileasysregistration_extend_navigation_user(
        $parentnode, $user, $context, $course, $coursecontext): void {
    global $USER, $DB;
    if (empty($user->id) || (int)$user->id <= 1 || isguestuser((int)$user->id)) {
        return;
    }
    // My School Courses is a self-service page and must never appear when an
    // administrator is viewing somebody else's profile.
    if ((int)$USER->id !== (int)$user->id) {
        return;
    }
    if (!$DB->record_exists('unileasys_user', ['userid' => (int)$user->id])) {
        return;
    }

    $parentnode->add(
        get_string('myschoolcourses', 'local_unileasysregistration'),
        new \moodle_url('/local/unileasysregistration/mycourses.php'),
        navigation_node::TYPE_SETTING, 'local_unileasysregistration',
        'unileasys_myschoolcourses', new \pix_icon('i/course', '', 'moodle')
    );

    $status = \local_unileasysregistration\verification::get_status((int)$user->id);
    if ($status === \local_unileasysregistration\verification::VERIFIED) {
        return;
    }

    $parentnode->add(
        get_string('activateaccount', 'local_unileasysregistration'),
        new \moodle_url('/local/unileasysregistration/activate.php'),
        navigation_node::TYPE_SETTING,
        'local_unileasysregistration',
        'unileasys_activate_profile',
        new \pix_icon('t/check', '', 'moodle')
    );
}

/**
 * Add the activation link directly to Moodle's My Profile page.
 * This is the profile-page navigation API and is separate from the settings
 * navigation callbacks above.
 */
function local_unileasysregistration_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course): void {
    global $DB;
    if (empty($user->id) || (int)$user->id <= 1 || !$iscurrentuser) {
        return;
    }
    if (!$DB->record_exists('unileasys_user', ['userid' => (int)$user->id])) {
        return;
    }

    // Put My School Courses directly into Moodle's standard profile
    // 'Miscellaneous' section, alongside items such as grades, calendar,
    // private files and other profile links. Do not create a separate
    // Unileasys category; the user should see one simple direct link.
    $tree->add_node(new \core_user\output\myprofile\node(
        'miscellaneous',
        'myschoolcourses',
        get_string('myschoolcourses', 'local_unileasysregistration'),
        null,
        new \moodle_url('/local/unileasysregistration/mycourses.php'),
        null,
        new \pix_icon('i/course', '', 'moodle')
    ));

    $status = \local_unileasysregistration\verification::get_status((int)$user->id);
    if ($status !== \local_unileasysregistration\verification::VERIFIED) {
        $tree->add_node(new \core_user\output\myprofile\node(
            'miscellaneous',
            'unileasysactivation',
            get_string('activateaccount', 'local_unileasysregistration'),
            'myschoolcourses',
            new \moodle_url('/local/unileasysregistration/activate.php'),
            null,
            new \pix_icon('t/check', '', 'moodle')
        ));
    }
}

/**
 * Add the activation link to the user's settings/profile navigation.
 * This gives students a reliable entry point even when the site's theme does
 * not display custom global-navigation nodes.
 */
function local_unileasysregistration_extend_navigation_user_settings(
        $parentnode, $user, $context, $course, $coursecontext): void {
    if (empty($user->id) || (int)$user->id <= 1 || isguestuser((int)$user->id)) {
        return;
    }

    $status = \local_unileasysregistration\verification::get_status((int)$user->id);
    if ($status === \local_unileasysregistration\verification::VERIFIED) {
        return;
    }

    $parentnode->add(
        get_string('activateaccount', 'local_unileasysregistration'),
        new \moodle_url('/local/unileasysregistration/activate.php'),
        navigation_node::TYPE_SETTING,
        'local_unileasysregistration',
        'unileasys_activate_user_settings',
        new \pix_icon('t/check', '', 'moodle')
    );
}

/**
 * Safety net for logged-in users when cron has not yet processed an adhoc task.
 * This is deliberately cheap: it only attempts synchronization when the plugin
 * has no local mapping for the current user.
 */
function local_unileasysregistration_after_require_login(): void {
    global $USER, $DB;

    if (empty($USER->id) || $USER->id <= 1 || isguestuser($USER->id)) {
        return;
    }

    if (!$DB->record_exists('unileasys_user', ['userid' => $USER->id])) {
        \local_unileasysregistration\observer::sync_user((int)$USER->id);
    }
    // v1.8.0: course-license expiry is enforced before normal Moodle page
    // output. Sites without course licenses retain v1.7.9 behaviour.
    \local_unileasysregistration\course_license::enforce_user((int)$USER->id);
}


/**
 * Render the direct administration navigation for all Unileasys feature pages.
 *
 * This keeps every feature one click away instead of forcing administrators
 * back through the plugin landing page.
 */
function local_unileasysregistration_admin_navigation(string $active = ''): void {
    global $OUTPUT;
    echo \local_unileasysregistration\demo::banner();
    $items = [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'schools' => ['Schools', 'schools.php'],
        'classes' => ['Classes', 'classes.php'],
        'sessions' => ['Academic Sessions', 'sessions.php'],
        'rules' => ['Enrolment Rules', 'enrolrules.php'],
        'courselicenses' => ['Course Licenses', 'courselicenses.php'],
        'tokens' => ['Verification Tokens', 'tokens.php'],
        'verification' => ['User Verification', 'verification.php'],
        'synclog' => ['Sync Log', 'synclog.php'],
        'license' => ['Plugin License', 'license.php'],
        'settings' => ['Settings', '/admin/settings.php?section=local_unileasysregistration_settings'],
    ];

    $links = [];
    foreach ($items as $key => [$label, $file]) {
        $classes = 'btn btn-sm mr-1 mb-1 ' . ($key === $active ? 'btn-primary' : 'btn-outline-secondary');
        $links[] = html_writer::link(
            new moodle_url($file[0] === '/' ? $file : '/local/unileasysregistration/' . $file),
            s($label),
            ['class' => $classes]
        );
    }

    echo html_writer::div(implode('', $links), 'unileasys-admin-navigation mb-4', [
        'style' => 'display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;'
    ]);
}
