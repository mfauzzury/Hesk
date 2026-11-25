<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
$hesk_settings['db_failure_response'] = 'json';
hesk_isLoggedIn();

header('Content-Type: application/json; charset='.$hesklang['ENCODING']);
header('X-Content-Type-Options: nosniff');

if ( defined('HESK_DEMO') )
{
    http_response_code(400);
    print json_encode([
        'message' => $hesklang['ddemo']
    ]);
    exit();
}

// A security check
if ( ! hesk_token_check('POST', 0))
{
    http_response_code(403);
    print json_encode([
        'message' => $hesklang['eto']
    ]);
    exit();
}

//-- Grab search query params
$name = hesk_prepare_customer_name(hesk_input(hesk_POST('name')));
$email = hesk_input(hesk_POST('email'));
$password = hesk_input(hesk_POST('password'));
$import = hesk_POST('import') == 1;
$finish_import = hesk_POST('finish_import') == 1;
$strict_creation = $password !== '' || $import;
$elevated = hesk_SESSION('elevated') && hesk_SESSION('elevated') >= new DateTime();
$import_token = hesk_input(hesk_POST('import_token'));
$stored_import_token = hesk_SESSION('customer_import_token');
$import_token_expires = hesk_SESSION('customer_import_token_expires');
$import_token_matches = $import_token !== '' && is_string($stored_import_token) &&
    hash_equals($stored_import_token, $import_token);
$import_authorized = $import && $import_token_matches &&
    ((!empty($import_token_expires) && $import_token_expires >= time()) || (empty($import_token_expires) && $elevated));

if ($finish_import) {
    if ($import_token_matches) {
        unset($_SESSION['customer_import_token'], $_SESSION['customer_import_token_expires']);
    }
    print json_encode(['success' => 1]);
    exit();
}

if ($strict_creation && empty($hesk_settings['customer_accounts'])) {
    http_response_code(400);
    print json_encode([
        'code' => 'CUSTOMER_ACCOUNTS_DISABLED',
        'message' => $hesklang['customer_accounts_disabled']
    ]);
    exit();
}

if ($strict_creation && !hesk_checkPermission('can_man_customers', 0)) {
    http_response_code(403);
    print json_encode([
        'code' => 'PERMISSION_DENIED',
        'message' => $hesklang['no_permission']
    ]);
    exit();
}

if ($import && !$import_authorized) {
    http_response_code(403);
    print json_encode([
        'code' => 'IMPORT_AUTHORIZATION_EXPIRED',
        'message' => $hesklang['elevator_enter_password']
    ]);
    exit();
} elseif ($password !== '' && !$elevated) {
    http_response_code(403);
    print json_encode([
        'code' => 'ELEVATION_REQUIRED',
        'message' => $hesklang['no_permission']
    ]);
    exit();
}

$name = hesk_has_visible_customer_content($name) ? $name : '';
$email = hesk_has_visible_customer_content($email) ? $email : '';

if (($import && $name === '' && $email === '') || (!$import && $name === '')) {
    http_response_code(400);
    print json_encode([
        'message' => $import ? $hesklang['import_customer_name_or_email_required'] : $hesklang['enter_real_name']
    ]);
    exit();
}

if ($import) {
    if (empty($import_token_expires)) {
        $current_time = new DateTime();
        $maximum_expiration = clone $current_time;
        $maximum_expiration->add(new DateInterval('PT2H'));
        $interval_amount = $hesk_settings['elevator_duration'];
        if (in_array(substr($interval_amount, -1), array('M', 'H'))) {
            $interval_amount = 'T'.$interval_amount;
        }
        $import_expiration = $current_time->add(new DateInterval("P{$interval_amount}"));
        $_SESSION['customer_import_token_expires'] = min($import_expiration->getTimestamp(), $maximum_expiration->getTimestamp());
    }
}

if ($password !== '' && strlen($password) < 5) {
    http_response_code(400);
    print json_encode([
        'message' => $hesklang['password_not_valid']
    ]);
    exit();
}

if ($password !== '') {
    $password_policy = hesk_password_complexity($password, 0, 0);
    if (!empty($password_policy['errors'])) {
        http_response_code(400);
        print json_encode([
            'message' => implode(' ', $password_policy['hesk_array_error'])
        ]);
        exit();
    }
}

if (($hesk_settings['require_email'] || $email !== '') && !hesk_isValidEmail($email)) {
    http_response_code(400);
    print json_encode([
        'message' => $hesklang['enter_valid_email']
    ]);
    exit();
}
$customer_creation = hesk_get_customer_creation_action($name, $email, $strict_creation);

if ($customer_creation['action'] === 'BLOCK') {
    hesk_hook_customer_search_executed(array(intval($customer_creation['customer']['id'])), true);

    http_response_code(400);
    print json_encode([
        'message' => empty($email) ? $hesklang['customer_name_with_no_email_exists'] : $hesklang['customer_name_email_exists']
    ]);
    exit();
}

if ($customer_creation['action'] === 'REUSE') {
    $customer_id = $customer_creation['customer']['id'];
    $name = $customer_creation['customer']['name'];
    $email = $customer_creation['customer']['email'];
    $language = $customer_creation['customer']['language'];
    hesk_hook_customer_search_executed(array(intval($customer_id)), true);
    http_response_code(200);
} else {
    $language = null;
    if ($hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1) {
        $language = hesk_POST('customer_language', HESK_DEFAULT_LANGUAGE);
        if (!isset($hesk_settings['languages'][$language])) {
            $language = HESK_DEFAULT_LANGUAGE;
        }
    }
    $language_sql = $language === null ? 'NULL' : "'" . hesk_dbEscape($language) . "'";
    $hashed_password = 'NULL';
    $verified = 0;
    $password_changed_at = 'NULL';
    $password_expires_at = 'NULL';

    if ($password !== '') {
        $hashed_password = "'".hesk_dbEscape(hesk_password_hash($password))."'";
        $verified = 1;
        $password_changed_at = 'NOW()';
        if (!empty($hesk_settings['pass_customers_expire'])) {
            $exp_unit = $hesk_settings['pass_customers_expire_unit'] === 'months' ? 'MONTH' : 'DAY';
            $exp_value = intval($hesk_settings['pass_customers_expire_value']);
            $password_expires_at = "NOW() + INTERVAL {$exp_value} {$exp_unit}";
        }
    }

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`name`, `email`, `language`, `pass`, `verified`, `pass_changed_at`, `pass_expires_at`)
    VALUES ('".hesk_dbEscape($name)."', '".hesk_dbEscape($email)."', {$language_sql}, {$hashed_password}, ".intval($verified).", {$password_changed_at}, {$password_expires_at})");
    $customer_id = hesk_dbInsertID();
    if ($password !== '') {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."password_history` (`user_id`, `user_type`, `pass`, `dt`) VALUES (".intval($customer_id).", 0, {$hashed_password}, NOW())");
        hesk_prune_password_history($customer_id, 0);
    }
    hesk_hook_customer_created([
        'id' => $customer_id,
        'name' => $name,
        'email' => $email,
        'verified' => $verified,
        'language' => $language
    ], true);
    http_response_code(201);
}

$name = hesk_html_entity_decode(hesk_stripslashes($name));
print json_encode([
    'id' => intval($customer_id),
    'name' => $name,
    'email' => $email,
    'language' => $language,
    'displayName' => $email ? "{$name} <{$email}>" : $name
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit();
