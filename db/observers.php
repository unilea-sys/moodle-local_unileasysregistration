<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\user_created',
        'callback' => '\\local_unileasysregistration\\observer::user_created',
        'priority' => 1000,
    ],
    [
        'eventname' => '\\core\\event\\user_updated',
        'callback' => '\\local_unileasysregistration\\observer::user_updated',
        'priority' => 1000,
    ],
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\local_unileasysregistration\\observer::user_deleted',
        'priority' => 1000,
    ],
];
