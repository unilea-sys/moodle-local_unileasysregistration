<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

/**
 * Remote license activation client.
 *
 * The license service is deliberately separate from Moodle. A copied plugin
 * package cannot activate a second Moodle site with the same one-site key.
 * The client caches a successful activation and allows a short offline grace
 * period so a temporary outage does not immediately disable a school site.
 */
class license {
    public const ACTIVE = 'active';
    public const GRACE_DAYS = 7;
    public const CACHE_SECONDS = 86400;

    public static function ensure_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('unileasys_license');
        if ($dbman->table_exists($table)) {
            $field = new \xmldb_field('customeremail', XMLDB_TYPE_CHAR, '320', null, XMLDB_NOTNULL, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            return;
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('licensekey', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('keylast4', XMLDB_TYPE_CHAR, '4', null, XMLDB_NOTNULL, null, '');
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'unactivated');
        $table->add_field('sitehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('siteurl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '');
        $table->add_field('customername', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('customeremail', XMLDB_TYPE_CHAR, '320', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('lastcheck', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('activatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('lastmessage', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $dbman->create_table($table);
    }

    public static function endpoint(): string {
        $endpoint = trim((string)get_config('local_unileasysregistration', 'licenseendpoint'));
        return $endpoint !== '' ? rtrim($endpoint, '/') : 'https://apps.unileasys.com/wp-json/unileasys-license/v1';
    }

    public static function site_url(): string {
        global $CFG;
        return rtrim((string)$CFG->wwwroot, '/');
    }

    public static function site_hash(): string {
        return hash('sha256', strtolower(self::site_url()));
    }

    public static function current() {
        global $DB;
        self::ensure_schema();
        return $DB->get_record('unileasys_license', [], '*', IGNORE_MULTIPLE);
    }

    public static function status(): string {
        $record = self::current();
        return $record ? (string)$record->status : 'unactivated';
    }

    public static function is_valid(bool $refresh = true): bool {
        $record = self::current();
        if (!$record || empty($record->licensekey)) {
            return false;
        }
        $now = time();
        if ((int)$record->expiresat > 0 && $now > (int)$record->expiresat) {
            return false;
        }
        if ($record->status === self::ACTIVE && !$refresh) {
            return true;
        }
        if ($record->status === self::ACTIVE && $now - (int)$record->lastcheck <= self::CACHE_SECONDS) {
            return true;
        }
        $result = self::request('heartbeat', ['license_key' => $record->licensekey]);
        if (!empty($result['success']) && ($result['status'] ?? '') === self::ACTIVE) {
            self::save_response($result, true);
            return true;
        }
        // Short offline grace period after the last successful server check.
        return $record->status === self::ACTIVE &&
            $record->lastcheck > 0 && $now <= ((int)$record->lastcheck + (self::GRACE_DAYS * DAYSECS));
    }

    public static function activate(string $key): array {
        $key = trim($key);
        if ($key === '') {
            return ['success' => false, 'message' => 'Please enter a license key.'];
        }
        self::ensure_schema();
        $result = self::request('activate', ['license_key' => $key]);
        if (empty($result['success'])) {
            self::save_response($result, false, $key);
            return ['success' => false, 'message' => (string)($result['message'] ?? 'License activation failed.')];
        }
        self::save_response($result, true, $key);
        return ['success' => true, 'message' => (string)($result['message'] ?? 'License activated successfully.')];
    }


    public static function deactivate(): array {
        $record = self::current();
        if (!$record || empty($record->licensekey)) {
            return ['success' => false, 'message' => 'No active license is configured.'];
        }
        $result = self::request('deactivate', ['license_key' => $record->licensekey]);
        if (!empty($result['success'])) {
            global $DB;
            $record->status = 'unactivated';
            $record->sitehash = '';
            $record->siteurl = '';
            $record->customername = '';
            $record->customeremail = '';
            $record->expiresat = 0;
            $record->lastcheck = time();
            $record->lastmessage = (string)($result['message'] ?? 'License deactivated.');
            $record->timemodified = time();
            $DB->update_record('unileasys_license', $record);
        }
        return ['success' => !empty($result['success']), 'message' => (string)($result['message'] ?? 'License deactivation failed.')];
    }

    public static function require_valid(): void {
        if (defined('UNILEASYS_DEMO_EDITION') && UNILEASYS_DEMO_EDITION === true) {
            return;
        }
        if (!self::is_valid()) {
            global $CFG;
            redirect(new \moodle_url('/local/unileasysregistration/license.php'),
                'A valid Unileasys Registration license is required to use this feature.');
        }
    }

    private static function request(string $action, array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $data = array_merge($payload, [
            'site_url' => self::site_url(),
            'site_hash' => self::site_hash(),
            'product' => 'unileasysregistration',
            'plugin_version' => '1.8.17',
        ]);
        try {
            $curl = new \curl(['proxy' => true]);
            $options = [
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_TIMEOUT' => 20,
                'CURLOPT_HTTPHEADER' => ['Content-Type: application/json', 'Accept: application/json'],
            ];
            $response = $curl->post(self::endpoint() . '/' . rawurlencode($action), json_encode($data), $options);
            $info = $curl->get_info();
            $decoded = json_decode((string)$response, true);
            if (!is_array($decoded)) {
                return ['success' => false, 'message' => 'License server returned an invalid response.', 'httpcode' => (int)($info['http_code'] ?? 0)];
            }
            return $decoded;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to contact the Unileasys license server.'];
        }
    }

    private static function save_response(array $result, bool $success, string $key = ''): void {
        global $DB;
        self::ensure_schema();
        $record = self::current();
        $now = time();
        if (!$record) {
            $record = (object)['timecreated' => $now];
        }
        if ($key !== '') {
            $record->licensekey = $key;
            $record->keylast4 = substr(preg_replace('/[^A-Za-z0-9]/', '', $key), -4);
        }
        $record->status = $success ? (string)($result['status'] ?? self::ACTIVE) : (string)($result['status'] ?? 'invalid');
        $record->sitehash = (string)($result['site_hash'] ?? self::site_hash());
        $record->siteurl = (string)($result['site_url'] ?? self::site_url());
        $record->customername = (string)($result['customer_name'] ?? ($record->customername ?? ''));
        $record->customeremail = (string)($result['customer_email'] ?? ($record->customeremail ?? ''));
        $record->expiresat = (int)($result['expires_at'] ?? ($record->expiresat ?? 0));
        $record->lastcheck = $now;
        $record->activatedat = $success ? (int)($record->activatedat ?: $now) : (int)($record->activatedat ?? 0);
        $record->lastmessage = (string)($result['message'] ?? '');
        $record->timemodified = $now;
        if (!empty($record->id)) {
            $DB->update_record('unileasys_license', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('unileasys_license', $record);
        }
    }
}
