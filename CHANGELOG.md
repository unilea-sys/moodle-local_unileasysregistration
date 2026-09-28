# Changelog

## 1.8.18
- Changed school/course activation-code generation to use an existing Course License as the single source of truth.
- Token generator now asks only for the Course License and quantity; school, class, course, account type and academic session are derived automatically.
- Removed the separate token validity-days input for Course License tokens.
- Token expiry is set directly to the Course License expiry timestamp and cannot be extended by token generation.
- Token generation is blocked for expired, suspended, revoked or otherwise inactive Course Licenses.

## 1.8.17
- Stable production release after temporary license testing was completed.
- Removed developer/test activation tooling and customer-facing license replacement controls.
- Updated license API plugin version reporting to 1.8.17.

## v1.8.12
- Fixed XMLDB customeremail schema definition to use no default value and removed the empty-string CHAR default warning.

# Changelog

## 1.8.10
- Restored a customer-facing Plugin License activation page inside Moodle.
- Added an Activate Unileasys Plugin button for initial commercial license activation/testing.
- Active sites see masked license status and expiry information.
- Seller-side revoke, reset, delete and other management controls remain hidden from customers.
- Updated license API plugin version reporting to 1.8.10.

## 1.8.7
- Removed the remaining customer-facing commercial license link from the plugin landing page.
- Kept commercial license validation internal to the plugin client.
- Updated the provider-contact fallback page and reported plugin version.


## 1.8.4
- Added a dedicated Course License Details view.
- Added View action to the Course Licenses list.
- Details view shows entitlement scope, dates, status, seats and timestamps.
- Details view shows active Unileasys-managed users currently consuming seats.
- Added direct Edit and Delete actions on the details page.
- Preserved activation history and deletion protections.

## 1.8.2
- Added Edit and Delete controls to Course License management.
- Added safe editing of course, school, class, account type, session, seats and dates.
- License keys and creation timestamps remain immutable.
- Protected deletion of licenses with active users or activation history.
- Expired licenses can be reactivated by editing their dates into a valid period.


- Added a Moodle scheduled task that runs every 15 minutes.
- Expired course licenses are automatically marked Expired (revoked licenses are preserved).
- Existing Unileasys-managed enrolments are suspended when a course has licensing enabled but the user has no valid matching license.
- Login-time enforcement remains as an immediate safety net.
- Courses with no Course License continue to use the v1.7.9-compatible behaviour.

## 1.8.0
- Added a standalone **Course Licenses** management layer.
- Added course license keys, school/class/account-type/session binding, seat limits, start dates and expiry dates.
- Added activate/suspend/revoke/renew controls for course licenses.
- Existing v1.7.9 token, registration, verification, enrolment-rule and course-history functionality is preserved.
- Existing commercial plugin licensing remains separate and unchanged.
- Course-token activation now respects a matching active course license when a course has licensed entitlements configured.
- Rule-based enrolment respects course-license availability and seat limits when a course has licensed entitlements configured.
- Expired/suspended/revoked course licenses suspend Unileasys-managed enrolments when the user next authenticates.
- Added safe Moodle upgrade migration from v1.7.9 to v1.8.0.
- Fresh v1.8.0 installations create the new course-license table automatically.
- Added privacy metadata for course activation/license association.
- No v1.7.9 data is intentionally deleted during upgrade.

# Changelog

## 1.6.9
- Added **My School Courses** directly to Moodle 5.2 primary navigation for classified Unileasys users.
- Kept the existing user-menu/profile links and administrator Unileasys primary navigation entry.

## 1.6.8
- Added a course activation popup directly to Moodle's enrolment options page for inactive Unileasys courses.
- The Continue button is intercepted only for the current user's inactive Unileasys course.
- Course activation is submitted securely via the existing Unileasys course-token flow without leaving the enrolment page.
- Successful activation continues directly to the Moodle course.
- Added Moodle 5.2 before_standard_footer_html_generation hook integration.


## 1.6.5
- Added Unileasys directly to Moodle 5.2 primary navigation using the official primary navigation hook, immediately after Site administration.
- Added My School Courses directly to the standard user avatar/profile dropdown using Moodle 5.2's user-menu hook.
- Removed reliance on the legacy flat-navigation flag and legacy global-navigation injection for the primary menu.

# Changelog\n\n## 1.6.4\n- Added direct Unileasys entry to the Moodle primary/flat navigation for administrators and managers.\n- My School Courses now appears directly in Moodle's standard user profile Miscellaneous section alongside other profile links.\n- Removed the broken `[[mymoodle]]` label from the My Unileasys dashboard by using the standard Dashboard label.\n
Unileasys Registration — v1.6.1

- Built from the confirmed v1.5.6 baseline.
- Adds remote one-site licensing using the Unileasys Commerce license server.
- License server default: https://apps.unileasys.com/wp-json/unileasys-license/v1
- **Database recovery fix:** plugin installation no longer relies on `db/install.xml` to create Unileasys tables. All tables are created idempotently from `db/install.php`/`classes/schema.php`. Existing `mdl_unileasys_*` tables are preserved.
- Safe upgrade path from v1.5.6/v1.6.0.


## 1.6.3
- Added direct Unileasys Dashboard entry to the main Moodle navigation for administrators/managers.
- Added My School Courses to the Moodle user profile navigation.
- Added course history with Active/Inactive status and direct course links.
- Added course-specific activation tokens while preserving existing account-verification tokens.
- Added course-specific token generation in the Token System.
- Added safe database migration for course activation records.
\n## 1.7.2
- Fixed course activation-token validation to use the user's stored verified school, class, account type and academic session as the authoritative binding.
- Preserved fail-closed profile-change security.

## 1.7.4
- Removed the Account Verification Token option from the admin token-generation form.
- Token generation now requires selecting a specific Moodle course.
- Existing account-verification tokens already stored in the database remain valid for account activation; this change only removes their generation from the course-token form.
- Retained the admin Reset / Clear Unused Tokens function.

## 1.8.19
- Enrollment Rules are now the source layer for creating Course Licenses.
- Course Licenses created from a rule inherit course, school, class, account type and academic session.
- Course Licenses retain seats and expiry as the entitlement layer.
- Course Licenses store their originating Enrollment Rule ID.
- Verification Tokens remain downstream of Course Licenses and inherit Course License expiry.
- Fixed token-generation button state so it enables after selecting an active Course License.
