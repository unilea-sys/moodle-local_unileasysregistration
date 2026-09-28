<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
require_capability('local/unileasysregistration:manage', context_system::instance());

use local_unileasysregistration\license;

$PAGE->set_url(new moodle_url('/local/unileasysregistration/license.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Unileasys Plugin License');
$PAGE->set_heading('Unileasys Plugin License');

license::ensure_schema();
$message = '';
$messagetype = 'info';

if (optional_param('activate_license', 0, PARAM_BOOL) && confirm_sesskey()) {
    $key = trim((string)optional_param('license_key', '', PARAM_TEXT));
    $result = license::activate($key);
    $message = (string)$result['message'];
    $messagetype = !empty($result['success']) ? 'success' : 'error';
}

$current = license::current();

// Deliberately do not expose seller-side actions such as revoke, reset, or delete.
echo $OUTPUT->header();
local_unileasysregistration_admin_navigation('license');
echo $OUTPUT->heading('Unileasys Plugin License');

if ($message !== '') {
    echo $OUTPUT->notification($message, $messagetype);
}

if ($current && $current->status === license::ACTIVE) {
    $statuslabel = 'Active';
    $statusclass = 'alert-success';
    echo html_writer::div(
        html_writer::tag('strong', 'License Status: ') . s($statuslabel),
        'alert ' . $statusclass
    );
    echo html_writer::tag('p', 'This Moodle site is activated. Commercial license management is handled by your software provider.');
    echo html_writer::start_tag('dl', ['class' => 'row', 'style' => 'max-width:760px;']);
    $rows = [
        'License' => $current->keylast4 ? '••••' . $current->keylast4 : 'Activated',
        'Customer' => $current->customername ?: '—',
        'Customer email' => $current->customeremail ?: '—',
        'Site' => $current->siteurl ?: license::site_url(),
        'Expiry' => !empty($current->expiresat) ? userdate((int)$current->expiresat) : 'No expiry reported',
        'Last verification' => !empty($current->lastcheck) ? userdate((int)$current->lastcheck) : 'Not yet checked',
    ];
    foreach ($rows as $label => $value) {
        echo html_writer::tag('dt', s($label), ['class' => 'col-sm-4']);
        echo html_writer::tag('dd', s($value), ['class' => 'col-sm-8']);
    }
    echo html_writer::end_tag('dl');
} else {
    echo html_writer::div(
        'This Moodle site is not currently activated. Enter the commercial license key supplied with your Unileasys purchase.',
        'alert alert-warning'
    );
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/unileasysregistration/license.php'))->out(false),
        'style' => 'max-width:620px; margin:2rem auto;'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'activate_license', 'value' => '1']);
    echo html_writer::tag('label', 'Commercial License Key', ['for' => 'license_key', 'class' => 'font-weight-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'license_key', 'id' => 'license_key',
        'class' => 'form-control form-control-lg mb-3', 'maxlength' => '128',
        'autocomplete' => 'off', 'autocapitalize' => 'characters',
        'spellcheck' => 'false', 'required' => 'required'
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => 'Activate Unileasys Plugin',
        'class' => 'btn btn-primary btn-lg'
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::div(
        'Activation checks this Moodle site against the Unileasys license server. This page does not provide seller-side license management.',
        'alert alert-info', ['style' => 'max-width:620px; margin:1rem auto;']
    );
}

echo $OUTPUT->footer();
