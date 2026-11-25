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

// Are we in maintenance mode?
hesk_check_maintenance();

hesk_load_database_functions();
hesk_session_start('CUSTOMER');

hesk_dbConnect();

// Is the user already logged in? If so, they shouldn't be needing this page at all.
if (hesk_isCustomerLoggedIn(false) !== null) {
    header('Location: ' . $hesk_settings['hesk_url'] . '/index.php');
    exit();
}

// Tell header to load captcha API if needed
if ($hesk_settings['recaptcha_use'] == 3)
{
    define('TURNSTILE',1);
}
elseif ($hesk_settings['recaptcha_use'] == 4)
{
    define('HCAPTCHA',1);
}
elseif ($hesk_settings['recaptcha_use'])
{
    define('RECAPTCHA',1);
}

// Are we setting a new password?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_new_password();

    // If we've made it past this point, password reset was successful
    unset($_SESSION['a_iserror']);
    unset($_SESSION['userdata']);
    hesk_process_messages($hesklang['customer_password_reset_successful'], 'login.php', 'SUCCESS');
}

if ( ! isset($_SESSION['a_iserror'])) {
    $_SESSION['a_iserror'] = array();
}

// Verify the provided hash.  Don't even provide a reset form if the hash is invalid.
$hash = hesk_REQUEST('hash', '');
$hash_valid = hesk_verify_customer_password_reset_hash($hash);
if (!$hash_valid['success']) {
    hesk_process_messages($hash_valid['content'], 'NOREDIRECT');
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/reset-password.php', [
    'validationFailures' => hesk_SESSION_array('a_iserror'),
    'messages' => hesk_get_messages(),
    'validHash' => $hash_valid['success'],
    'resetPasswordHash' => $hash
]);
unset($_SESSION['a_iserror']);
unset($_SESSION['userdata']);

function handle_new_password() {
    global $hesk_settings, $hesklang;

    $hesk_error_buffer = [];

    // Check anti-SPAM question
    if ($hesk_settings['question_use'])
    {
        $question = hesk_input( hesk_POST('question') );

        if ( strlen($question) == 0)
        {
            $hesk_error_buffer[] = $hesklang['q_miss'];
        }
        elseif (hesk_mb_strtolower($question) != hesk_mb_strtolower($hesk_settings['question_ans']))
        {
            $hesk_error_buffer[] = $hesklang['q_wrng'];
        }
        else
        {
            $_SESSION['c_question'] = $question;
        }
    }

    // Check anti-SPAM image
    if ($hesk_settings['secimg_use'] && !isset($_SESSION['img_verified'])) {
        // Using Cloudflare Turnstile?
        if ($hesk_settings['recaptcha_use'] == 3) {
            $tsResponse = hesk_POST('cf-turnstile-response');
            $tsResult = hesk_verify_turnstile($tsResponse, $hesk_settings['turnstile_private_key']);
            if ($tsResult) {
                $_SESSION['img_verified']=true;
            } else {
                $hesk_error_buffer[]=$hesklang['turnstile_error'];
            }
        // Using hCaptcha?
        } elseif ($hesk_settings['recaptcha_use'] == 4) {
            $hcResponse = hesk_POST('h-captcha-response');
            $hcResult = hesk_verify_hcaptcha($hcResponse, $hesk_settings['hcaptcha_private_key']);
            if ($hcResult) {
                $_SESSION['img_verified']=true;
            } else {
                $hesk_error_buffer[]=$hesklang['hcaptcha_error'];
            }
        // Using reCAPTCHA?
        } elseif ($hesk_settings['recaptcha_use']) {
            require(HESK_PATH . 'inc/recaptcha/recaptchalib_v2.php');

            $resp = null;
            $reCaptcha = new ReCaptcha($hesk_settings['recaptcha_private_key']);

            // Was there a reCAPTCHA response?
            if (isset($_POST["g-recaptcha-response"])) {
                $resp = $reCaptcha->verifyResponse(hesk_getClientIP(), hesk_POST("g-recaptcha-response"));
            }

            if ($resp != null && $resp->success) {
                $_SESSION['img_verified']=true;
            } else {
                $hesk_error_buffer[]=$hesklang['recaptcha_error'];
            }
        } else {
            // Using PHP generated image
            $mysecnum = intval(hesk_POST('mysecnum', 0));

            if (empty($mysecnum)) {
                $hesk_error_buffer[]=$hesklang['sec_miss'];
            } else {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                    $_SESSION['img_verified']=true;
                    unset($_SESSION['checksum']);
                } else {
                    $hesk_error_buffer[]=$hesklang['sec_wrng'];
                }
            }
        }
    }

    $password = hesk_input(hesk_POST('password'));
    $password_length = strlen($password);

    if ($password_length < 5) {
        $hesk_error_buffer['password'] = $hesklang['password_not_valid'];
    } else {
        // TODO Should we care about passwords being "too long"?
        $confirm_password = hesk_input(hesk_POST('confirm-password'));
        if ($password !== $confirm_password) {
            $hesk_error_buffer['password'] = $hesklang['passwords_not_same'];
        }
    }

    $hash = hesk_input(hesk_POST('hash'));
    $hash_valid = hesk_verify_customer_password_reset_hash($hash);
    $customer_id = 0;
    if (!$hash_valid['success']) {
        $hesk_error_buffer['hash'] = $hash_valid['content'];

        $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);
        hesk_process_messages($hesklang['pcer'].'<br /><br /><ul><li>'.$hash_valid['content'].'</li></ul>', 'reset_password.php?hash='.$hash);
        return;
    }

    $customer_id = intval($hash_valid['content']);

    // Is this password the same as their old one?
    $existing_passsword_rs = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            WHERE `id` = ".$customer_id);
    $existing_password = hesk_dbFetchAssoc($existing_passsword_rs);
    if (hesk_password_verify($password, $existing_password['pass'])) {
        $hesk_error_buffer['password'] = $hesklang['customer_edit_pass_same'];
    }

    // Validation errors?
    $result = hesk_password_complexity($password,intval($hash_valid['content']), '0');

    if (count($hesk_error_buffer) || !empty($result['errors'])) {
        $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);
        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $tmp .= $result['hesk_error_buffer'];
        $hesk_error_buffer = $tmp;
        $hesk_buffer_string = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_buffer_string, 'reset_password.php?hash='.$hash);
        return;
    }
    unset($_SESSION['img_verified']);
    $hashed_password = hesk_password_hash($password);

    // Consume the reset hash only after validation, before changing the password.
    $reset_hash = hesk_hash_password_reset_token(hesk_clean_password_reset_token($hash));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` WHERE `hash` = '".hesk_dbEscape($reset_hash)."' AND `user` = {$customer_id} AND `user_type` = 'CUSTOMER' AND `dt` >= (NOW() - INTERVAL 1 HOUR)");
    if (hesk_dbAffectedRows() != 1) {
        hesk_limitBfAttempts(true, 'CUSTOMER');
        $_SESSION['a_iserror'] = array('hash');
        hesk_process_messages($hesklang['pcer'].'<br /><br /><ul><li>'.$hesklang['ehash'].'</li></ul>', 'reset_password.php?hash='.$hash);
        return;
    }

    $ext_sql = ", `pass_changed_at`= NOW()";

    if (isset($hesk_settings['pass_customers_expire']) && $hesk_settings['pass_customers_expire'] == '1') {
        $exp_unit  = $hesk_settings['pass_customers_expire_unit'] === 'months' ? 'MONTH' : 'DAY';
        $exp_value = intval($hesk_settings['pass_customers_expire_value']);
        $ext_sql .= ", `pass_expires_at`= NOW() + INTERVAL {$exp_value} {$exp_unit}";
    } else {
        $ext_sql .= ", `pass_expires_at`= NULL";
    }

    // Preserve the current password if history was cleared while protection was disabled.
    hesk_prune_password_history($customer_id, 0);

    // Update their password
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            SET `pass` = '".hesk_dbEscape($hashed_password)."',
                `verification_token` = NULL,
                `verified` = 1 {$ext_sql}
            WHERE `id` = ".$customer_id);

    // Insert hash into the history table
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."password_history` (`user_id`, `user_type`, `pass`, `dt`) VALUES (".intval($hash_valid['content']).",'0','".hesk_dbEscape($hashed_password)."', NOW()) ");
    hesk_prune_password_history($hash_valid['content'], 0);

    // Purge remaining reset hashes, remember-me tokens and pending MFA verification codes
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` WHERE `user` = {$customer_id} AND `user_type` = 'CUSTOMER'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id` = ".$customer_id." AND `user_type` = 'CUSTOMER'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id` = ".$customer_id." AND `user_type` = 'CUSTOMER'");

    hesk_hook_authentication_event(array(
        'event' => 'auth:customer_password_reset_completed',
        'data' => array('source' => 'password_reset_link'),
    ), true, array(
        'actor_type' => 'CUSTOMER',
        'user_id' => $customer_id,
        'auth_method' => 'NONE',
    ));
}
