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
define('HESK_PATH','./');
define('HESK_NO_ROBOTS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

hesk_load_database_functions();
hesk_session_start('CUSTOMER');
hesk_dbConnect();

$customerUserContext = hesk_isCustomerLoggedIn();
if (hesk_REQUEST('action', '') === '') {
    hesk_hook_personal_information_viewed(array(
        'subject_type' => 'customer',
        'subject_id' => intval($customerUserContext['id']),
        'page' => 'profile'
    ), true, $customerUserContext);
}
hesk_purge_expired_email_change_requests();

$hesk_error_buffer = array();

if (hesk_REQUEST('action') !== '') {
    // What are changing?
    $action = hesk_REQUEST('action');
    if ( ! empty($action) && defined('HESK_DEMO') ) {
        hesk_process_messages($hesklang['ddemo'], 'NOREDIRECT', 'NOTICE');
    } elseif ($action === 'profile') {
        hesk_token_check('POST');
        handle_profile_update($hesk_error_buffer, $customerUserContext);
    } elseif ($action === 'password') {
        hesk_token_check('POST');
        handle_password_change($hesk_error_buffer);
    } elseif ($action === 'email') {
        hesk_token_check('POST');
        handle_email_change($hesk_error_buffer, $customerUserContext);
    } elseif ($action === 'email-resend') {
        hesk_token_check();
        hesk_resend_email_change_notification($customerUserContext);
    }

    if (count($hesk_error_buffer)) {
        $tmp = '';
        foreach ($hesk_error_buffer as $error) {
            $tmp .= "<li>$error</li>\n";
        }
        hesk_process_messages($hesklang['pcer'] . '<br /><br /><ul>' . $tmp . '</ul>', 'NOREDIRECT');
    }
}
$messages = hesk_get_messages();

//-- Fetch a new user context in case name has changed
$customerUserContext = hesk_isCustomerLoggedIn(false);

$pendingEmailChange = hesk_get_pending_email_change_for_user($customerUserContext['id']);
$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/profile.php', array(
    'customerUserContext' => $customerUserContext,
    'pendingEmailChange' => $pendingEmailChange,
    'userCanChangeEmail' => $hesk_settings['customer_accounts_allow_email_changes'],
    'messages' => $messages,
    'serviceMessages' => hesk_get_service_messages('c-profile'),
    'validationFailures' => array_keys($hesk_error_buffer)
));

function handle_profile_update(&$hesk_error_buffer, $customerUserContext) {
    global $hesk_settings, $hesklang;

    $name = hesk_prepare_customer_name(hesk_input(hesk_POST('name')));
    if (!hesk_has_visible_customer_content($name)) {
        $hesk_error_buffer['name'] = $hesklang['enter_your_name'];
    }

    $language_sql = '';
    if ($hesk_settings['can_sel_lang']) {
        $language = hesk_input( hesk_POST('language') ) or $language = $hesk_settings['language'];
        if (isset($hesk_settings['languages'][$language])) {
            $language_sql = ", `language` = '".hesk_dbEscape($language)."' ";
            if ($language != $hesk_settings['language']) {
                hesk_setLanguage($language);
                hesk_setcookie('hesk_language', $language, time()+31536000, '/');
            }
        }
    }

    if (count($hesk_error_buffer) === 0) {
        $customer_for_hook = array(
            'id' => intval($_SESSION['customer']['id']),
            'name' => $name
        );
        if ($language_sql !== '') {
            $customer_for_hook['language'] = $language;
        }
        $customer_for_hook['changes'] = hesk_hooks_get_changed_fields($customerUserContext, $customer_for_hook, array('id'), array(), array('name'));
        if (in_array('name', $customer_for_hook['changes'], true)) {
            $customer_for_hook['old_identity'] = array(
                'name' => $customerUserContext['name'],
                'email' => strtolower(trim((string) $customerUserContext['email']))
            );
            $customer_for_hook['new_identity'] = array(
                'name' => $name,
                'email' => strtolower(trim((string) $customerUserContext['email']))
            );
        }
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
                SET
                    `name` = '".hesk_dbEscape($name)."'
                    $language_sql
                WHERE `id` = ".intval($_SESSION['customer']['id']));
        hesk_hook_customer_updated($customer_for_hook, true);
        $_SESSION['customer']['name'] = $name;
        $customerUserContext['name'] = $name;
        hesk_process_messages($hesklang['customer_profile_saved'], 'NOREDIRECT', 'SUCCESS');
    }
}

function handle_password_change(&$hesk_error_buffer) {
    global $hesk_settings, $hesklang;

    // Current password
    $current_password = hesk_input(hesk_POST('current-password'));
    if (!$current_password) {
        $hesk_error_buffer['current-password'] = $hesklang['enter_pass'];
    } else {
        hesk_limitInternalBfAttempts('CUSTOMER');

        // Get current password hash from DB
        $result = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($_SESSION['customer']['id'])." LIMIT 1");
        if (hesk_dbNumRows($result) != 1) {
            hesk_forceLogoutCustomer($hesklang['wrong_user']);
        }
        $user_row = hesk_dbFetchAssoc($result);

        // Validate current password
        if (hesk_password_verify($current_password, $user_row['pass'])) {
            hesk_cleanBfAttempts('CUSTOMER');
        } else {
            $hesk_error_buffer['current-password'] = $hesklang['wrong_pass'];
        }
    }

    // New password
    $new_password = hesk_input(hesk_POST('password'));
    if (!$new_password) {
        $hesk_error_buffer['password'] = $hesklang['e_new_pass'];
    } elseif (isset($user_row) && hesk_password_verify($new_password, $user_row['pass'])) {
        $hesk_error_buffer['password'] = $hesklang['customer_edit_pass_same'];
    }

    // Confirm password
    $confirm_password = hesk_input( hesk_POST('confirm-password') );
    if ($new_password !== $confirm_password) {
        $hesk_error_buffer['confirm-password'] = $hesklang['passwords_not_same'];
    }

    $result = hesk_password_complexity($new_password,intval($_SESSION['customer']['id']), '0');
    
    $a = [];
    if (!empty($result['errors'])) {
        foreach ($result['hesk_array_error'] as $key => $value) {
            $a[$key] = $value;
        }
    }

    $hesk_error_buffer = array_merge($hesk_error_buffer, $a);

    if (count($hesk_error_buffer) === 0 && empty($result['errors'])) {
        $newpass_hash = hesk_password_hash($new_password);

        $ext_sql = ", `pass_changed_at`= NOW()";

        if (isset($hesk_settings['pass_customers_expire']) && $hesk_settings['pass_customers_expire'] == '1') {
            $exp_unit  = $hesk_settings['pass_customers_expire_unit'] === 'months' ? 'MONTH' : 'DAY';
            $exp_value = intval($hesk_settings['pass_customers_expire_value']);
            $ext_sql .= ", `pass_expires_at`= NOW() + INTERVAL {$exp_value} {$exp_unit}";
        } else {
            $ext_sql .= ", `pass_expires_at`= NULL";
        }

        // Preserve the current password if history was cleared while protection was disabled.
        hesk_prune_password_history($_SESSION['customer']['id'], 0);
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` SET `pass` = '".hesk_dbEscape($newpass_hash)."' {$ext_sql} WHERE `id` = ".intval($_SESSION['customer']['id']));

        if (hesk_dbAffectedRows() === 1) {
            hesk_hook_customer_updated(array(
                'id' => intval($_SESSION['customer']['id']),
                'changes' => array('pass')
            ), true);
        }

        // Insert hash into the history table
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."password_history` (`user_id`, `user_type`, `pass`, `dt`) VALUES (".intval($_SESSION['customer']['id']).",'0','".hesk_dbEscape($newpass_hash)."', NOW()) ");
        hesk_prune_password_history($_SESSION['customer']['id'], 0);

        // Force login after password change
        hesk_forceLogoutCustomer($hesklang['pass_login'], 'profile.php', null, 'NOTICE');
    }
}

function handle_email_change(&$hesk_error_buffer, $customerUserContext) {
    global $hesk_settings, $hesklang;

    $email = hesk_validateEmail( hesk_POST('email'), 'ERR', 0);
    if (!$email) {
        $hesk_error_buffer['email'] = $hesklang['enter_valid_email'];
        return;
    }
    if (!$hesk_settings['customer_accounts_allow_email_changes']) {
        hesk_process_messages($hesklang['customer_change_email_disabled'], 'profile.php');
        return;
    }

    // Is the target email banned?
    if (hesk_isBannedEmail($email)) {
        $hesk_error_buffer['email'] = $hesklang['customer_change_email_banned'];
        return;
    }

    hesk_purge_expired_email_change_requests();

    if ($email === $customerUserContext['email']) {
        hesk_process_messages($hesklang['customer_profile_saved'], 'NOREDIRECT', 'SUCCESS');
        return;
    }

    // Make sure we don't have another active customer with this email, or someone else attempting to change their
    // email to this one
    if (hesk_get_customer_account_by_email($email, true) !== null ||
        hesk_get_pending_email_change_for_email($email, $customerUserContext['id'])) {
        $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists_no_reset_link'], $email);
        return;
    }

    // Has the user requested an email change too recently?
    $new_email = hesk_get_pending_email_change_for_user($customerUserContext['id']);
    if ($new_email !== null && $new_email['email_sent_too_recently']) {
        hesk_process_messages($hesklang['customer_login_resend_verification_email_too_early'], 'profile.php');
        return;
    }


    // All good; insert the change request and email them
    hesk_build_and_send_email($customerUserContext, $email);
    hesk_process_messages(sprintf($hesklang['customer_change_email_submitted'], $email), 'NOREDIRECT', 'SUCCESS');
}

function hesk_build_and_send_email($customerUserContext, $email) {
    global $hesklang, $hesk_settings;
    
    if (!function_exists('hesk_sendCustomerRegistrationEmail')) {
        require_once(HESK_PATH . 'inc/email_functions.inc.php');
    }

    hesk_purge_email_change_requests($customerUserContext['id']);
    $verification_token = hesk_insert_email_change_request($email, $customerUserContext['id']);

    // HACK: Email function uses the email on the customer object, so swap it out temporarily
    $original_email = $customerUserContext['email'];
    $customerUserContext['email'] = $email;
    hesk_sendCustomerRegistrationEmail($customerUserContext, $verification_token, 'customer_verify_new_email');
    $customerUserContext['email'] = $original_email;
}

function hesk_resend_email_change_notification($customerUserContext) {
    global $hesklang, $hesk_settings;

    if (!$hesk_settings['customer_accounts_allow_email_changes']) {
        hesk_process_messages($hesklang['customer_change_email_disabled'], 'profile.php');
        return;
    }

    $new_email = hesk_get_pending_email_change_for_user($customerUserContext['id']);

    if (is_null($new_email)) {
        hesk_process_messages($hesklang['customer_login_resend_verification_email_none'], 'profile.php');
        return;
    }

    if ($new_email['email_sent_too_recently']) {
        hesk_process_messages($hesklang['customer_login_resend_verification_email_too_early'], 'profile.php');
        return;
    }

    hesk_build_and_send_email($customerUserContext, $new_email['new_email']);
    hesk_process_messages(sprintf($hesklang['customer_change_email_submitted'], $new_email['new_email']), 'profile.php', 'SUCCESS');
}
