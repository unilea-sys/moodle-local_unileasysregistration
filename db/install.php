<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_unileasysregistration_install(): void {
    \local_unileasysregistration\schema::ensure_all_tables();
    \local_unileasysregistration\course_license::ensure_schema();
    \local_unileasysregistration\schema::ensure_master_data();
    \local_unileasysregistration\profile_fields::ensure_all();
}
