<?php

$string['dashboard'] = 'Dashboard';
$string['syncallusers'] = 'Sync all users';
$string['syncallusersresult'] = 'User synchronization completed: {$a->processed} processed, {$a->classified} classified, {$a->enrolled} active automatic enrolments, {$a->suspended} suspended enrolments, {$a->errors} errors.';
$string['dashboardtotalusers'] = 'Total Moodle users';
$string['dashboardunileasysusers'] = 'Classified Unileasys users';
$string['dashboardordinaryusers'] = 'Ordinary Moodle users';
$string['dashboardactiveenrolments'] = 'Active automatic enrolments';
$string['dashboardsuspendedenrolments'] = 'Suspended automatic enrolments';
$string['dashboardactiveRules'] = 'Active enrolment rules';
$string['dashboardincomplete'] = 'Incomplete Unileasys classifications';
$string['dashboarderrors'] = 'Users needing attention';
$string['dashboardrulesummary'] = 'Rule summary';
$string['dashboardcoursename'] = 'Course';
$string['dashboardrulecount'] = 'Rules';
$string['dashboardmatched'] = 'Matching users';
$string['dashboardactive'] = 'Active enrolments';
$string['dashboardpending'] = 'Pending / suspended';
$string['dashboardviewrules'] = 'View rules';
$string['dashboardrun'] = 'Run synchronization';
$string['dashboardintro'] = 'Overview of Unileasys users, automatic course enrolment, and synchronization status.';
$string['dashboardattention'] = 'Some users have incomplete or invalid School, Class, or Account Type data and cannot be classified.';
$string['dashboardnoissues'] = 'No users currently require attention.';
$string['dashboardlastsync'] = 'Synchronization';
$string['pluginname'] = 'Unileasys Registration & School Cohorts';
$string['unileasysregistration'] = 'Unileasys Registration';
$string['manage'] = 'Manage';
$string['schools'] = 'Schools';
$string['classes'] = 'Classes';
$string['sessions'] = 'Academic sessions';
$string['settings'] = 'Registration settings';
$string['addschool'] = 'Add school';
$string['editschool'] = 'Edit school';
$string['schoolname'] = 'School name';
$string['schoolcode'] = 'School code';
$string['active'] = 'Active';
$string['inactive'] = 'Inactive';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['delete'] = 'Delete';
$string['addclass'] = 'Add class';
$string['editclass'] = 'Edit class';
$string['classname'] = 'Class name';
$string['shortname'] = 'Short name';
$string['sortorder'] = 'Sort order';
$string['addsession'] = 'Add academic session';
$string['editsession'] = 'Edit academic session';
$string['sessionname'] = 'Academic session';
$string['student'] = 'Student';
$string['teacher'] = 'Teacher';
$string['school'] = 'School';
$string['class'] = 'Class';
$string['accounttype'] = 'Account type';
$string['selectschool'] = 'Select school';
$string['selectclass'] = 'Select class';
$string['selectaccounttype'] = 'Select account type';
$string['registrationsettings'] = 'Registration settings';
$string['currentacademic'] = 'Current academic session';
$string['enableteacherregistration'] = 'Allow teacher registration';
$string['teacherregistrationcode'] = 'Teacher registration code';
$string['cohortprefix'] = 'Cohort prefix';
$string['schoolrequired'] = 'Please select your school.';
$string['classrequired'] = 'Please select your class.';
$string['accounttyperequired'] = 'Please select whether you are a student or teacher.';
$string['invalidschool'] = 'The selected school is not available.';
$string['invalidclass'] = 'The selected class is not available.';
$string['invalidschoolclass'] = 'The selected school/class combination is not valid.';
$string['invalidsession'] = 'No active academic session is configured.';
$string['teacherdisabled'] = 'Teacher registration is currently disabled.';
$string['wrongteachercode'] = 'The teacher registration code is incorrect.';
$string['teacher_code'] = 'Teacher registration code';
$string['cohortcreated'] = 'Cohort created';
$string['cohortname'] = 'Cohort';
$string['usersregistered'] = 'Registered users';
$string['privacy:metadata:unileasys_user'] = 'Stores the school, class, account type, academic session and cohort association selected during Unileasys registration.';
$string['privacy:metadata:unileasys_user:userid'] = 'Moodle user ID.';
$string['privacy:metadata:unileasys_user:schoolid'] = 'Selected school ID.';
$string['privacy:metadata:unileasys_user:classid'] = 'Selected class ID.';
$string['privacy:metadata:unileasys_user:accounttype'] = 'Selected account type.';
$string['privacy:metadata:unileasys_user:sessionid'] = 'Selected academic session ID.';
$string['privacy:metadata:unileasys_user:cohortid'] = 'Created/used Moodle cohort ID.';
$string['privacy:metadata:unileasys_user:timecreated'] = 'Time the registration record was created.';
$string['privacy:metadata:unileasys_user:timemodified'] = 'Time the registration record was modified.';

$string['unileasysregistration:manage'] = 'Manage Unileasys registration and school cohort data';

$string['syncprofilefields'] = 'Sync profile field options';
$string['profilesynced'] = 'Moodle user profile fields have been synchronised.';
$string['repairusers'] = 'Repair existing users and cohorts';
$string['usersrepaired'] = 'Existing users were checked: {$a}';

$string['task_sync_user'] = 'Synchronise Unileasys user and cohort';

$string['registrationfieldset'] = 'Unileasys Registration (optional)';
$string['selectschool'] = '— Select School —';
$string['selectclass'] = '— Select Class —';
$string['selectaccounttype'] = '— Select Account Type —';
$string['school'] = 'School';
$string['class'] = 'Class';
$string['accounttype'] = 'Account Type';

$string['registrationoptionalhelp'] = 'Leave all three fields blank if you are registering as a normal Moodle user.';

$string['enrolmentrules'] = 'Course enrolment rules';
$string['addrule'] = 'Add enrolment rule';
$string['rulecourse'] = 'Course';
$string['ruleschools'] = 'Schools';
$string['ruleschools_help'] = 'Leave all schools unselected to match every school. Multiple selected schools are treated as OR.';
$string['ruleclasses'] = 'Classes';
$string['ruleclasses_help'] = 'Leave all classes unselected to match every class. Multiple selected classes are treated as OR.';
$string['ruletypes'] = 'Account types';
$string['ruletypes_help'] = 'Select exactly one account type. Student and Teacher cannot be combined because each rule has one Moodle course role.';
$string['rulesession'] = 'Academic session';
$string['rulerole'] = 'Course role';
$string['allschools'] = 'All schools';
$string['allclasses'] = 'All classes';
$string['alltypes'] = 'All account types';
$string['ruleintro'] = 'Rules use AND between categories and OR within a category. Example: School A OR School B AND Class 6 OR Class 7 AND Student. Unileasys uses Moodle enrolment APIs and does not create a cohort for each school/class combination.';
$string['synchallrules'] = 'Synchronise all active rules';
$string['syncnow'] = 'Sync now';
$string['disable'] = 'Disable';
$string['enable'] = 'Enable';
$string['status'] = 'Status';
$string['ruleenabled'] = 'Rule enabled and matching automatic enrolments reactivated.';
$string['rulesaved'] = 'Enrolment rule saved and synchronised.';
$string['rulesynced'] = 'Rule synchronised: {$a->enrolled} users matched/enrolled and {$a->suspended} enrolments suspended.';
$string['allrulesynced'] = 'All active rules synchronised: {$a->enrolled} users processed and {$a->suspended} enrolments suspended.';
$string['ruledisabled'] = 'Rule disabled and matching automatic enrolments suspended.';
$string['legacycohorts'] = 'Legacy automatic cohort creation';
$string['legacycohorts_desc'] = 'Keep the pre-1.1 behaviour that creates a cohort for every School/Class/Account Type/Session combination. Leave disabled to use the scalable rule-based course enrolment system.';
$string['norulecourses'] = 'No Moodle courses are available yet. Create a course first, then add an enrolment rule.';
$string['norulesessions'] = 'No academic session is available. Create an academic session first.';
$string['rulemissingmasterdata'] = 'The rule cannot be saved until a course, academic session and course role are available.';
$string['norulesyet'] = 'No enrolment rules have been created yet. Click “Add enrolment rule” to create the first rule.';
$string['systemstatus'] = 'System status';
$string['statusitem'] = 'Item';
$string['statusvalue'] = 'Value';
$string['statusschools'] = 'Schools';
$string['statusclasses'] = 'Classes';
$string['statussessions'] = 'Academic sessions';
$string['statuscourses'] = 'Moodle courses';
$string['statusrules'] = 'Enrolment rules';

$string['ruleroleinvalid'] = 'Please select a valid course role.';
$string['rulerolestudentrequired'] = 'Student account type requires the Student course role.';
$string['ruleroleteacherrequired'] = 'Teacher account type requires the Teacher course role.';

$string['dashboard'] = 'Dashboard';


$string['verificationpending'] = 'Pending verification';

$string['verificationverified'] = 'Verified';


$string['myunileasys'] = 'My Unileasys';
$string['dashboardverifiedtitle'] = 'Your account is verified';
$string['dashboardverifiedmessage'] = 'Your Unileasys account is verified and your eligible courses are available below.';
$string['dashboardsuspendedtitle'] = 'Your account is suspended';
$string['dashboardsuspendedmessage'] = 'Your Unileasys account is suspended. Please contact your school or Unileasys administrator.';
$string['dashboardpendingtitle'] = 'Activate your Unileasys account';
$string['dashboardpendingmessage'] = 'Your account is registered, but you need the activation code provided by your school before your eligible courses can be activated.';
$string['dashboardprofiletitle'] = 'Your Unileasys Profile';
$string['dashboardactivationhelp'] = 'Use the one-time activation code supplied by your school. The code must match your registered school, class, account type and academic session.';
$string['dashboardcourses'] = 'Your Unileasys Courses';
$string['dashboardnocourses'] = 'Your account is verified, but no active course matches your current enrolment rules yet.';

$string['activateaccount'] = 'Activate Unileasys Account';
$string['activationcode'] = 'School Activation Code';
$string['activatebutton'] = 'Activate Account';
$string['activation_intro'] = 'Enter the one-time activation code provided by your school. Your code is checked against your registered school, class, account type and active academic session.';
$string['activationrequired'] = 'Please enter your school activation code.';
$string['activation_invalid'] = 'This activation code is invalid, expired, already used, or does not match your registered school/class/account type.';
$string['activation_success'] = 'Your Unileasys account has been verified successfully. Your eligible courses are now being activated.';
$string['alreadyverified'] = 'Your Unileasys account is already verified.';
$string['activation_suspended'] = 'Your Unileasys account is currently suspended. Please contact your school or Unileasys administrator.';
$string['activation_profile_incomplete'] = 'Your School, Class and Account Type information is incomplete or invalid. Please update your profile before activation.';

$string['privacy:metadata:unileasys_verification'] = 'Stores the verification status and verification method for Unileasys school users.';
$string['privacy:metadata:unileasys_verification:userid'] = 'Moodle user ID.';
$string['privacy:metadata:unileasys_verification:status'] = 'Verification status.';
$string['privacy:metadata:unileasys_verification:method'] = 'Verification method.';
$string['privacy:metadata:unileasys_verification:tokenid'] = 'Activation token record used for verification.';
$string['privacy:metadata:unileasys_verification:verifiedby'] = 'Moodle user ID of the administrator who manually verified the account, when applicable.';
$string['privacy:metadata:unileasys_verification:timeverified'] = 'Time the account was verified.';
$string['privacy:metadata:unileasys_verification:timemodified'] = 'Time the verification record was last changed.';

$string['invalidactivationcodeparameters'] = 'Please select one specific school, one specific class, one account type and one academic session. Activation codes cannot be generated for all schools, all classes or any session.';

$string['license'] = 'License';
$string['licenseendpoint'] = 'License server endpoint';

$string['unileasys'] = 'Unileasys';
$string['myschoolcourses'] = 'My School Courses';
$string['courseactivation'] = 'Course Activation';
$string['courseactivated'] = 'Course activated successfully.';
$string['courseactivationinvalid'] = 'The course activation token is invalid, expired, already used, or does not belong to this course or student profile.';
$string['courseactivationrequired'] = 'Enter the course activation token.';
$string['coursehistory'] = 'Course History';
$string['courseactive'] = 'Active';
$string['courseinactive'] = 'Inactive';
$string['activatecourse'] = 'Activate Course';
$string['opencourse'] = 'Open Course';
$string['activationtoken'] = 'Activation Token';
$string['noeligiblecourses'] = 'No school courses are currently available for your school, class and account type.';
$string['courseactivationhelp'] = 'Enter the activation token issued for this specific course.';

$string['courselicenses'] = 'Course Licenses';
$string['invalidcourselicense'] = 'The course license details are invalid. Please check the course, school, class, account type, session and dates.';
$string['courselicenseactive'] = 'Active';
$string['courselicenseexpired'] = 'Expired';
$string['courselicensesuspended'] = 'Suspended';
$string['courselicenserevoked'] = 'Revoked';

$string['privacy:metadata:unileasys_course_activation'] = 'Stores a user course activation and its license association.';
$string['privacy:metadata:unileasys_course_activation:userid'] = 'Moodle user ID.';
$string['privacy:metadata:unileasys_course_activation:courseid'] = 'Moodle course ID.';
$string['privacy:metadata:unileasys_course_activation:tokenid'] = 'Activation token ID.';
$string['privacy:metadata:unileasys_course_activation:licenseid'] = 'Course license ID.';
$string['privacy:metadata:unileasys_course_activation:status'] = 'Course activation status.';
$string['privacy:metadata:unileasys_course_activation:timeactivated'] = 'Time the course was activated.';
$string['privacy:metadata:unileasys_course_activation:timemodified'] = 'Time the activation record was last modified.';

$string['taskenforcecourselicenses'] = 'Enforce course licenses and expiry';
$string['courselicenseusedseatsexceed'] = 'The number of seats cannot be lower than the number of seats currently in use.';
$string['courselicensecannotdeleteused'] = 'This course license cannot be deleted because it currently has active users. Suspend or revoke it instead.';
$string['courselicensecannotdeletehistory'] = 'This course license cannot be deleted because it has activation history. Suspend or revoke it instead.';
$string['editcourselicense'] = 'Edit Course License';
$string['updatecourselicense'] = 'Update Course License';
$string['deletecourselicense'] = 'Delete';


$string['courselicensealreadyexists'] = 'Course already registered for this school, class and academic session. The same course cannot be registered twice.';

$string['nocourselicensefortokens'] = 'Please select an active Course License. Token details are taken from the Course License.';
$string['course_license_not_available_for_tokens'] = 'This Course License is not available for token generation. It may be expired, suspended, revoked or otherwise inactive.';
$string['courselicense_token_capacity_reached'] = 'All activation-code capacity for this Course License has already been issued.';
$string['courselicense_token_quantity_exceeded'] = 'You requested {$a->requested} codes, but only {$a->available} codes remain available from this Course License of {$a->seats} seats.';
$string['enrolmentrulenotfound'] = 'The selected Enrollment Rule could not be found.';
$string['enrolmentruleinactive'] = 'The selected Enrollment Rule is inactive. Enable it before creating a Course License.';
$string['rulemustspecifyschoolclass'] = 'The Enrollment Rule must specify at least one specific school and one specific class before a Course License can be created.';
$string['rulemusthaveonetype'] = 'The Enrollment Rule must contain exactly one valid account type before a Course License can be created.';
$string['courselicensecreationfailed'] = 'No Course License could be created from the selected Enrollment Rule.';

$string['demolimitreached'] = 'Demo limit exceeded: the free Demo Edition allows a maximum of {$a->max} {$a->label}. Current usage: {$a->used}/{$a->max}; remaining: {$a->remaining}. You requested {$a->requested}. Please purchase the full Unileasys Registration version for unrestricted functionality.';
$string['demoseatlimitreached'] = 'Demo limit exceeded: a Course License in the free Demo Edition allows a maximum of {$a->max} seats. You requested {$a->requested}. Please purchase the full Unileasys Registration version for unrestricted functionality.';
