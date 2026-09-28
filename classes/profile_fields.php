<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

class profile_fields {

    public static function ensure_all(): void {
        global $DB;

        require_once($GLOBALS['CFG']->dirroot . '/user/profile/lib.php');

        $categoryid = self::ensure_category('Unileasys');

        $schools = $DB->get_records_select(
            'unileasys_school', 'active = 1', null, 'name ASC', 'id,name,code'
        );
        $schooloptions = [''];
        foreach ($schools as $school) {
            // Moodle's standard menu profile field displays the stored option
            // itself. Therefore the user-facing value must be the school title,
            // while the plugin resolves the title back to the internal school
            // record/code when it processes the user.
            $schooloptions[] = $school->name;
        }

        $classes = $DB->get_records_select(
            'unileasys_class', 'active = 1', null, 'sortorder ASC, name ASC', 'id,name,shortname'
        );
        $classoptions = [''];
        foreach ($classes as $class) {
            $classoptions[] = $class->name;
        }

        self::ensure_menu(
            $categoryid,
            'unileasys_school',
            'School',
            $schooloptions,
            'Select your school',
            false
        );

        self::ensure_menu(
            $categoryid,
            'unileasys_class',
            'Class',
            $classoptions,
            'Select your class',
            false
        );

        self::ensure_menu(
            $categoryid,
            'unileasys_account_type',
            'Account Type',
            ['', 'Student', 'Teacher'],
            'Select account type',
            false
        );
    }

    public static function sync_option_lists(): void {
        self::ensure_all();
    }

    private static function ensure_category(string $name): int {
        global $DB;

        $category = $DB->get_record('user_info_category', ['name' => $name]);
        if ($category) {
            return (int)$category->id;
        }

        return (int)$DB->insert_record('user_info_category', (object)[
            'name' => $name,
            'sortorder' => 1000,
        ]);
    }

    private static function ensure_menu(
        int $categoryid,
        string $shortname,
        string $name,
        array $options,
        string $description,
        bool $required
    ): int {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);

        // Moodle's menu profile field reads param1 as newline-separated options.
        $param1 = implode("\n", array_values(array_unique($options)));

        if ($field) {
            $changed = false;
            if ((int)$field->categoryid !== $categoryid) {
                $field->categoryid = $categoryid;
                $changed = true;
            }
            if ($field->datatype !== 'menu') {
                $field->datatype = 'menu';
                $changed = true;
            }
            if ($field->name !== $name) {
                $field->name = $name;
                $changed = true;
            }
            if ($field->param1 !== $param1) {
                $field->param1 = $param1;
                $changed = true;
            }
            if ((int)$field->required !== ($required ? 1 : 0)) {
                $field->required = $required ? 1 : 0;
                $changed = true;
            }

            // Visible to all and editable by user/admin. The public signup form is
            // rendered explicitly by the plugin so we can control its position.
            // Keeping signup=0 prevents Moodle from auto-inserting a second copy.
            $desired = [
                'visible' => 2,
                'signup' => 0,
                'locked' => 0,
                'forceunique' => 0,
            ];
            foreach ($desired as $key => $value) {
                if ((int)$field->{$key} !== $value) {
                    $field->{$key} = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $DB->update_record('user_info_field', $field);
            }
            return (int)$field->id;
        }

        return (int)$DB->insert_record('user_info_field', (object)[
            'shortname' => $shortname,
            'name' => $name,
            'datatype' => 'menu',
            'description' => $description,
            'descriptionformat' => FORMAT_MOODLE,
            'categoryid' => $categoryid,
            'sortorder' => 1,
            'required' => $required ? 1 : 0,
            'locked' => 0,
            'visible' => 2,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => FORMAT_MOODLE,
            'param1' => $param1,
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
        ]);
    }
}
