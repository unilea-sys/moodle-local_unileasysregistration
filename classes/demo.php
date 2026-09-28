<?php
namespace local_unileasysregistration;
defined('MOODLE_INTERNAL') || die();

/** Restricted free demonstration edition limits. */
class demo {
    public const MAX_SCHOOLS = 1;
    public const MAX_CLASSES = 2;
    public const MAX_SESSIONS = 1;
    public const MAX_RULES = 2;
    public const MAX_COURSE_LICENSES = 1;
    public const MAX_SEATS = 10;
    public const MAX_TOKENS = 10;
    public const MAX_VERIFIED_USERS = 10;

    public static function enabled(): bool {
        return defined('UNILEASYS_DEMO_EDITION') && UNILEASYS_DEMO_EDITION === true;
    }

    public static function remaining(string $type): int {
        global $DB;
        if (!self::enabled()) return PHP_INT_MAX;
        $map = [
            'schools' => [self::MAX_SCHOOLS, 'unileasys_school'],
            'classes' => [self::MAX_CLASSES, 'unileasys_class'],
            'sessions' => [self::MAX_SESSIONS, 'unileasys_session'],
            'rules' => [self::MAX_RULES, 'unileasys_enrol_rule'],
            'courselicenses' => [self::MAX_COURSE_LICENSES, 'unileasys_course_license'],
            'tokens' => [self::MAX_TOKENS, 'unileasys_verify_token'],
            'verified' => [self::MAX_VERIFIED_USERS, 'unileasys_verification'],
        ];
        if (!isset($map[$type])) return PHP_INT_MAX;
        [$max, $table] = $map[$type];
        $count = (int)$DB->count_records($table);
        return max(0, $max - $count);
    }

    /**
     * Enforce a demo capacity limit without sending the administrator to
     * Moodle's exception/debug page.  The demo is a customer-facing trial,
     * so an exceeded limit should be a normal notification and return to the
     * page the administrator was using.
     */
    public static function require_capacity(string $type, int $requested = 1): bool {
        if (!self::enabled()) return true;
        $remaining = self::remaining($type);
        if ($requested <= $remaining) return true;

        $limits = [
            'schools' => self::MAX_SCHOOLS,
            'classes' => self::MAX_CLASSES,
            'sessions' => self::MAX_SESSIONS,
            'rules' => self::MAX_RULES,
            'courselicenses' => self::MAX_COURSE_LICENSES,
            'tokens' => self::MAX_TOKENS,
            'verified' => self::MAX_VERIFIED_USERS,
        ];
        $labels = [
            'schools' => [1 => 'school', 2 => 'schools'],
            'classes' => [1 => 'class', 2 => 'classes'],
            'sessions' => [1 => 'academic session', 2 => 'academic sessions'],
            'rules' => [1 => 'enrolment rule', 2 => 'enrolment rules'],
            'courselicenses' => [1 => 'Course License', 2 => 'Course Licenses'],
            'tokens' => [1 => 'verification token', 2 => 'verification tokens'],
            'verified' => [1 => 'verified user', 2 => 'verified users'],
        ];
        $max = $limits[$type] ?? 0;
        $labelset = $labels[$type] ?? [1 => $type, 2 => $type];
        $label = ($max === 1) ? $labelset[1] : $labelset[2];
        $used = max(0, $max - $remaining);

        $message = get_string('demolimitreached', 'local_unileasysregistration', (object)[
            'label' => $label,
            'max' => $max,
            'used' => $used,
            'remaining' => $remaining,
            'requested' => $requested,
        ]);

        if (!CLI_SCRIPT) {
            global $PAGE;
            redirect($PAGE->url, $message, 0, \core\output\notification::NOTIFY_ERROR);
        }

        // Demo limits are web-facing, but keep CLI behaviour safe if a caller
        // ever reaches this guard from a scheduled task.
        throw new \moodle_exception('demolimitreached', 'local_unileasysregistration', '', (object)[
            'label' => $label, 'max' => $max, 'used' => $used,
            'remaining' => $remaining, 'requested' => $requested,
        ]);
    }

    public static function cap_seats(int $seats): int {
        if (!self::enabled()) return $seats;
        if ($seats < 0 || $seats > self::MAX_SEATS) {
            $message = get_string('demoseatlimitreached', 'local_unileasysregistration', (object)[
                'max' => self::MAX_SEATS,
                'requested' => $seats,
            ]);
            if (!CLI_SCRIPT) {
                global $PAGE;
                redirect($PAGE->url, $message, 0, \core\output\notification::NOTIFY_ERROR);
            }
            throw new \moodle_exception('demoseatlimitreached', 'local_unileasysregistration', '', (object)[
                'max' => self::MAX_SEATS, 'requested' => $seats,
            ]);
        }
        return $seats;
    }

    public static function banner(): string {
        if (!self::enabled()) return '';
        $detailsurl = new \moodle_url('/local/unileasysregistration/demo.php');
        $purchaseurl = 'https://apps.unileasys.com/product/unileasys-course-distribution-plugin-pro/';
        $buttons = \html_writer::div(
            \html_writer::link($detailsurl, 'Demo Details', ['class' => 'btn btn-sm btn-outline-dark mr-2']) .
            \html_writer::link($purchaseurl, 'Purchase Full Version', ['class' => 'btn btn-sm btn-primary', 'target' => '_blank', 'rel' => 'noopener']),
            'mt-2'
        );
        return \html_writer::div(
            '<strong>Unileasys Registration — Demo Edition</strong><br>' .
            'This is a restricted demonstration version. Limits apply to schools, classes, rules, Course Licenses, seats, tokens and verified users. A commercial license is required for unrestricted use.' .
            $buttons,
            'alert alert-warning', [], true
        );
    }
}
