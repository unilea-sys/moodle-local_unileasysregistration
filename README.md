## v1.8.2 — Course License Edit/Delete Controls

Version 1.8.2 adds Edit and Delete controls to Course License management. License keys remain immutable. Editing can change the course, school, class, account type, academic session, seat limit and dates. Deletion is protected when a license has active users or activation history.

# Unileasys Registration Moodle Plugin

## v1.8.1 — Strict Course License Enforcement

Version 1.8.1 adds automatic entitlement enforcement. Moodle cron runs the enforcement task every 15 minutes, expires time-ended course licenses, and suspends Unileasys-managed enrolments that no longer have a valid matching course license. Login-time enforcement remains as a safety net. Courses without Course Licenses retain the v1.7.9-compatible behaviour.
 v1.8.0

Moodle 5.2 plugin for school-based registration, verification, activation tokens, rule-based course enrolment, My Unileasys dashboard and one-site commercial licensing.

## Important v1.6.1 installation behaviour

This release is intentionally idempotent. It does **not** use `db/install.xml` for table creation. The plugin creates only missing tables through `db/install.php` and `classes/schema.php`. Existing `mdl_unileasys_*` data is preserved. This specifically supports recovery when Moodle has lost the plugin's installed-version record but the database tables remain.

Do not delete existing Unileasys tables before installing this version.


## v1.8.0 Course Licenses

v1.8.0 adds a separate Course License layer without changing the commercial
plugin license. A course license can be assigned to one Moodle course, school,
class, account type and academic session with a seat limit and start/expiry
date.

If no Course License exists for a course, the existing v1.7.9 course access
behaviour is retained. Once a Course License exists for a course, matching
users must have an active, non-expired license entitlement. Expired, suspended
or revoked course licenses cause Unileasys-managed enrolments to be suspended
when the user next authenticates.

The existing v1.7.9 commercial plugin license remains separate and continues to
be validated by the remote Unileasys license server.


### Commercial plugin activation
Administrators activate the purchased commercial license from the **Plugin License** page inside Moodle. Seller-side license management remains on the remote licensing/WooCommerce system.
