<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_unileasysregistration\hook_callbacks::class, 'before_standard_head_html_generation'],
        'priority' => 1000,
    ],
    [
        'hook' => \core\hook\output\before_standard_footer_html_generation::class,
        'callback' => [\local_unileasysregistration\hook_callbacks::class, 'before_standard_footer_html_generation'],
        'priority' => 900,
    ],
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_unileasysregistration\hook_callbacks::class, 'extend_primary_navigation'],
        'priority' => 1000,
    ],
    [
        'hook' => \core_user\hook\extend_user_menu::class,
        'callback' => [\local_unileasysregistration\hook_callbacks::class, 'extend_user_menu'],
        'priority' => 1000,
    ],
];
