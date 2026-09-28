<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    /**
     * Add the Unileasys management entry to Moodle 5.2 primary navigation.
     * The primary_extend hook runs immediately after Site administration, so
     * this places Unileasys directly beside it in the primary navigation.
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER, $DB;

        $primary = $hook->get_primaryview();

        // Administrators/managers get one direct Unileasys management entry.
        $context = \context_system::instance();
        if (has_capability('local/unileasysregistration:manage', $context)
                && !$primary->find('unileasys', \navigation_node::TYPE_CUSTOM)) {
            $primary->add(
                get_string('unileasys', 'local_unileasysregistration'),
                new \moodle_url('/local/unileasysregistration/dashboard.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'unileasys',
                new \pix_icon('i/settings', '', 'moodle')
            );
        }

        // Only classified Unileasys school users get the user-facing
        // My School Courses item. Ordinary Moodle users must not see it.
        if (empty($USER->id) || $USER->id <= 1 || isguestuser($USER->id)) {
            return;
        }
        if (!$DB->get_manager()->table_exists(new \xmldb_table('unileasys_user'))) {
            return;
        }
        if (!$DB->record_exists('unileasys_user', ['userid' => (int)$USER->id])) {
            return;
        }

        if (!$primary->find('unileasys_myschoolcourses', \navigation_node::TYPE_CUSTOM)) {
            $primary->add(
                get_string('myschoolcourses', 'local_unileasysregistration'),
                new \moodle_url('/local/unileasysregistration/mycourses.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'unileasys_myschoolcourses',
                new \pix_icon('i/course', '', 'moodle')
            );
        }
    }

    /**
     * Add My School Courses to Moodle's standard user profile dropdown.
     * Moodle 5.2 calls this hook while constructing the avatar/user menu,
     * alongside Profile, Grades, Calendar, Private files and Reports.
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        global $USER, $DB;

        if (empty($USER->id) || $USER->id <= 1 || isguestuser($USER->id)) {
            return;
        }

        if (!$DB->get_manager()->table_exists(new \xmldb_table('unileasys_user'))) {
            return;
        }

        if (!$DB->record_exists('unileasys_user', ['userid' => (int)$USER->id])) {
            return;
        }

        $item = new \stdClass();
        $item->itemtype = 'link';
        $item->url = new \moodle_url('/local/unileasysregistration/mycourses.php');
        $item->title = get_string('myschoolcourses', 'local_unileasysregistration');
        $item->titleidentifier = 'myschoolcourses,local_unileasysregistration';
        $hook->add_navitem($item);
    }

    /**
     * Add a course-activation dialog to Moodle's enrolment-options page.
     * This is deliberately limited to an inactive Unileasys course for the
     * current classified user, so normal Moodle enrolment pages are untouched.
     */
    public static function before_standard_footer_html_generation(
        \core\hook\output\before_standard_footer_html_generation $hook
    ): void {
        global $PAGE, $USER, $DB;

        if (empty($USER->id) || $USER->id <= 1 || CLI_SCRIPT || WS_SERVER) {
            return;
        }
        if (!$PAGE->url || $PAGE->url->get_path() !== '/enrol/index.php') {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        if (!$courseid || $courseid === SITEID) {
            return;
        }
        if (!$DB->get_manager()->table_exists(new \xmldb_table('unileasys_enrolment'))) {
            return;
        }

        $mapping = $DB->get_record('unileasys_enrolment', [
            'userid' => (int)$USER->id,
            'courseid' => $courseid,
        ], '*', IGNORE_MULTIPLE);
        if (!$mapping || (int)$mapping->status === ENROL_USER_ACTIVE) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname', IGNORE_MISSING);
        if (!$course) {
            return;
        }

        $courseName = addslashes(format_string($course->fullname, true, ['context' => \context_course::instance($courseid)]));
        $actionurl = (new \moodle_url('/local/unileasysregistration/mycourses.php'))->out(false);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        $sesskey = sesskey();
        $title = get_string('courseactivation', 'local_unileasysregistration');
        $tokenlabel = get_string('activationtoken', 'local_unileasysregistration');
        $activate = get_string('activatecourse', 'local_unileasysregistration');
        $back = get_string('back');
        $help = get_string('courseactivationhelp', 'local_unileasysregistration');

        $html = '<dialog id="unileasys-enrol-activation-modal" aria-labelledby="unileasys-enrol-activation-title">'
            . '<div class="unileasys-enrol-modal-card">'
            . '<h3 id="unileasys-enrol-activation-title">' . s($title) . '</h3>'
            . '<div class="unileasys-enrol-course-name" id="unileasys-enrol-course-name">' . $courseName . '</div>'
            . '<form id="unileasys-enrol-activation-form">'
            . '<input type="hidden" name="sesskey" value="' . s($sesskey) . '">'
            . '<input type="hidden" name="ajax" value="1">'
            . '<input type="hidden" name="activatecourse" value="1">'
            . '<input type="hidden" name="courseid" value="' . (int)$courseid . '">'
            . '<label for="unileasys-enrol-token">' . s($tokenlabel) . '</label>'
            . '<input id="unileasys-enrol-token" name="activationtoken" type="text" autocomplete="one-time-code" required>'
            . '<div class="unileasys-enrol-help">' . s($help) . '</div>'
            . '<div id="unileasys-enrol-error" class="alert alert-danger" hidden></div>'
            . '<div class="unileasys-enrol-actions">'
            . '<button type="submit" class="btn btn-primary">' . s($activate) . '</button>'
            . '<button type="button" id="unileasys-enrol-back" class="btn btn-secondary">' . s($back) . '</button>'
            . '</div></form></div></dialog>';

        $js = '<script>(function(){'
            . 'function init(){'
            . 'var modal=document.getElementById("unileasys-enrol-activation-modal");'
            . 'var form=document.getElementById("unileasys-enrol-activation-form");'
            . 'var token=document.getElementById("unileasys-enrol-token");'
            . 'var error=document.getElementById("unileasys-enrol-error");'
            . 'var back=document.getElementById("unileasys-enrol-back");'
            . 'if(!modal||!form||!token||!error||!back){return;}'
            . 'function close(){if(modal.open&&modal.close){modal.close();}else{modal.removeAttribute("open");}}'
            . 'function open(){error.hidden=true;error.textContent="";token.value="";if(modal.showModal){modal.showModal();}else{modal.setAttribute("open","open");}setTimeout(function(){token.focus();},50);}'
            . 'document.querySelectorAll("a,button,input[type=submit]").forEach(function(el){'
            . 'var label=(el.tagName==="INPUT"?(el.value||""):(el.textContent||"")).trim().replace(/\\s+/g," ").toLowerCase();'
            . 'if(label==="continue"||label==="continue to course"){el.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();open();},true);}'
            . '});'
            . 'back.addEventListener("click",close);'
            . 'modal.addEventListener("click",function(e){if(e.target===modal){close();}});'
            . 'modal.addEventListener("cancel",function(e){e.preventDefault();close();});'
            . 'form.addEventListener("submit",function(e){e.preventDefault();error.hidden=true;var data=new FormData(form);fetch(' . json_encode($actionurl) . ',{method:"POST",credentials:"same-origin",body:data,headers:{"X-Requested-With":"XMLHttpRequest"}}).then(function(r){return r.json();}).then(function(result){if(result.success){window.location.href=' . json_encode($courseurl) . ';return;}error.textContent=result.message||"Course activation failed.";error.hidden=false;token.focus();}).catch(function(){error.textContent="Unable to activate the course right now. Please try again.";error.hidden=false;});});'
            . '}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}'
            . '})();</script>';

        $css = '<style>'
            . '#unileasys-enrol-activation-modal{border:0;border-radius:14px;padding:0;width:min(520px,calc(100% - 32px));max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.3);}'
            . '#unileasys-enrol-activation-modal::backdrop{background:rgba(0,0,0,.58);}'
            . '.unileasys-enrol-modal-card{padding:28px;background:#fff;border-radius:14px;}'
            . '.unileasys-enrol-modal-card h3{margin:0 0 18px;}'
            . '.unileasys-enrol-course-name{font-weight:600;padding:12px 14px;background:#f5f6f8;border-radius:8px;margin-bottom:20px;}'
            . '.unileasys-enrol-modal-card label{display:block;font-weight:600;margin-bottom:7px;}'
            . '.unileasys-enrol-modal-card input[type=text]{width:100%;padding:10px 12px;border:1px solid #adb5bd;border-radius:6px;box-sizing:border-box;}'
            . '.unileasys-enrol-help{font-size:.875rem;color:#6c757d;margin-top:7px;}'
            . '.unileasys-enrol-actions{display:flex;gap:10px;margin-top:22px;}'
            . '</style>';
        $hook->add_html($html . $css . $js);
    }

    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $USER;
        if (empty($USER->id) || $USER->id <= 1 || CLI_SCRIPT || WS_SERVER) {
            return;
        }
        try {
            observer::enforce_profile_consistency((int)$USER->id);
        } catch (\Throwable $e) {
            debugging('Unileasys verification consistency check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
