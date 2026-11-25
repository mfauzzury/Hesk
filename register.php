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

use RobThree\Auth\TwoFactorAuth;

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

// Are customer accounts enabled? If not, redirect
if (!$hesk_settings['customer_accounts']) {
    return hesk_process_messages($hesklang['customer_accounts_disabled'], 'index.php');
}

// Are customers allowed to create their own accounts? If not, redirect
if (!$hesk_settings['customer_accounts_customer_self_register']) {
    return hesk_process_messages($hesklang['customer_accounts_registration_disabled'], 'index.php');
}

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

// Are we creating a new account?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Demo mode
    if ( defined('HESK_DEMO') ) {
        hesk_process_messages($hesklang['ddemo'], 'register.php', 'NOTICE');
    }

    handle_registration();

    // If we've made it past this point, registration was successful
    hesk_process_messages($hesklang['customer_registration_successful'], 'NOREDIRECT', 'SUCCESS');
    $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/register-success.php', [
        'messages' => hesk_get_messages(),
        'serviceMessages' => hesk_get_service_messages('c-ok', $hesk_settings['customer_accounts_required'] == 2),
        'model' => hesk_SESSION_array('userdata')
    ]);
    unset($_SESSION['iserror']);
    unset($_SESSION['userdata']);
    return;
}

$model = hesk_SESSION_array('userdata');
$captcha_verified = $hesk_settings['secimg_use'] &&
    isset($_SESSION['register_captcha_retry']) && is_array($_SESSION['register_captcha_retry']) &&
    isset($_SESSION['register_captcha_retry']['email'], $_SESSION['register_captcha_retry']['expires'], $_SESSION['register_captcha_retry']['mode']) &&
    $_SESSION['register_captcha_retry']['expires'] > time() &&
    $_SESSION['register_captcha_retry']['mode'] == $hesk_settings['recaptcha_use'] &&
    ($_SESSION['register_captcha_retry']['email'] === '' ||
     $_SESSION['register_captcha_retry']['email'] === (isset($model['email']) ? $model['email'] : ''));
$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/register.php', [
    'validationFailures' => hesk_SESSION_array('iserror'),
    'messages' => hesk_get_messages(),
    'serviceMessages' => hesk_get_service_messages('c-register', $hesk_settings['customer_accounts_required'] == 2),
    'captchaVerified' => $captcha_verified,
    'model' => $model
]);
unset($_SESSION['iserror']);
unset($_SESSION['userdata']);

function handle_registration() {
    global $hesk_settings, $hesklang;

    $hesk_error_buffer = array();
    $captcha_email = hesk_validateEmail(hesk_POST('email'), 'ERR', 0);
    $captcha_retry = $hesk_settings['secimg_use'] &&
        isset($_SESSION['register_captcha_retry']) && is_array($_SESSION['register_captcha_retry']) &&
        isset($_SESSION['register_captcha_retry']['email'], $_SESSION['register_captcha_retry']['expires'], $_SESSION['register_captcha_retry']['mode']) &&
        $_SESSION['register_captcha_retry']['expires'] > time() &&
        $_SESSION['register_captcha_retry']['mode'] == $hesk_settings['recaptcha_use'] &&
        ($_SESSION['register_captcha_retry']['email'] === '' || $_SESSION['register_captcha_retry']['email'] === $captcha_email);
    if (!$captcha_retry) {
        unset($_SESSION['register_captcha_retry']);
    } elseif ($captcha_email !== '') {
        $_SESSION['register_captcha_retry']['email'] = $captcha_email;
    }

    $captcha_verified = $captcha_retry;

    // Check anti-SPAM question
    if ($hesk_settings['question_use'])
    {
        $question = hesk_input( hesk_POST('question') );

        if ( strlen($question) == 0)
        {
            $hesk_error_buffer['question'] = $hesklang['q_miss'];
        }
        elseif (hesk_mb_strtolower($question) != hesk_mb_strtolower($hesk_settings['question_ans']))
        {
            $hesk_error_buffer['question'] = $hesklang['q_wrng'];
        }
        else
        {
            $_SESSION['c_question'] = $question;
        }
    }

    // Check anti-SPAM image
    if ($hesk_settings['secimg_use'] && !$captcha_retry) {
        // Using Cloudflare Turnstile?
        if ($hesk_settings['recaptcha_use'] == 3) {
            $tsResponse = hesk_POST('cf-turnstile-response');
            $tsResult = hesk_verify_turnstile($tsResponse, $hesk_settings['turnstile_private_key']);
            if ($tsResult) {
                $captcha_verified = true;
            } else {
                $hesk_error_buffer['mysecnum']=$hesklang['turnstile_error'];
            }
        // Using hCaptcha?
        } elseif ($hesk_settings['recaptcha_use'] == 4) {
            $hcResponse = hesk_POST('h-captcha-response');
            $hcResult = hesk_verify_hcaptcha($hcResponse, $hesk_settings['hcaptcha_private_key']);
            if ($hcResult) {
                $captcha_verified = true;
            } else {
                $hesk_error_buffer['mysecnum']=$hesklang['hcaptcha_error'];
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
                $captcha_verified = true;
            } else {
                $hesk_error_buffer['mysecnum']=$hesklang['recaptcha_error'];
            }
        } else {
            // Using PHP generated image
            $mysecnum = intval(hesk_POST('mysecnum', 0));

            if (empty($mysecnum)) {
                $hesk_error_buffer['mysecnum']=$hesklang['sec_miss'];
            } else {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                    $captcha_verified = true;
                    unset($_SESSION['checksum']);
                } else {
                    $hesk_error_buffer['mysecnum']=$hesklang['sec_wrng'];
                }
            }
        }
    }

    $spam_check_failed = isset($hesk_error_buffer['question']) || isset($hesk_error_buffer['mysecnum']);

    $name = hesk_prepare_customer_name(hesk_input(hesk_POST('name')));
    if ($name && hesk_has_visible_customer_content($name)) {
        $myuser['name'] = $name;
    } else {
        $hesk_error_buffer['name'] = $hesklang['enter_real_name'];
    }

    $email = $captcha_email;
    if ($email) {
        $myuser['email'] = $email;
        if (!$spam_check_failed && !hesk_isAllowedEmailDomain($email)) {
            $hesk_error_buffer['email'] = $hesklang['customer_email_domain_not_allowed'];
        }
    } else {
        $hesk_error_buffer['email'] = $hesklang['enter_valid_email'];
    }

    // Are we banned?
    if ((!$spam_check_failed && hesk_isBannedEmail($email)) || hesk_isBannedIP(hesk_getClientIP())) {
        // Don't bother validating the rest at this point
        unset($_SESSION['register_captcha_retry']);
        hesk_process_messages($hesklang['customer_accounts_email_banned'], 'register.php');
        return;
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

    // Do we have an existing customer record (could be either a duplicate, or someone who lost their verification email
    $existing_account = null;
    if (isset($myuser['email']) && !$spam_check_failed) {
        $existing_account = hesk_get_customer_account_by_email($myuser['email'], true);
        if ($existing_account !== null) {
            if ($existing_account['verified'] == 0) {
                $resend_url = $hesk_settings['hesk_url'] . '/login.php?a=resend_verification_email&amp;email=' . rawurlencode(hesk_htmlspecialchars_decode($myuser['email']));
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists_pending_email_verification'], $myuser['email']) .
                    '<br /><a href="' . $resend_url . '">' . $hesklang['customer_login_resend_verification_email'] . '</a>';
            } elseif ($existing_account['verified'] == 1) {
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists'], $myuser['email']);
            } else {
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists_pending_approval'], $myuser['email']);
            }
        }
    }

    $_SESSION['userdata'] = $myuser;

    // Validation errors?
    $result = hesk_password_complexity($password,'0', '0');

    if (count($hesk_error_buffer) || !empty($result['errors'])) {
        if ($spam_check_failed) {
            unset($_SESSION['register_captcha_retry']);
        } elseif ($hesk_settings['secimg_use'] && $captcha_verified && !$captcha_retry) {
            $_SESSION['register_captcha_retry'] = array(
                'email' => $captcha_email,
                'expires' => time() + 600,
                'mode' => $hesk_settings['recaptcha_use']
            );
        }
        $_SESSION['iserror'] = array_keys($hesk_error_buffer);

        $tmp = '';
        foreach ($hesk_error_buffer as $error) {
            $tmp .= "<li>$error</li>\n";
        }

        $tmp .= $result['hesk_error_buffer'];

        $hesk_error_buffer = $hesklang['pcer'] . '<br /><br /><ul>' . $tmp . '</ul>';
        hesk_process_messages($hesk_error_buffer, 'register.php');
    }

    unset($_SESSION['register_captcha_retry']);

    // Generate a verification token for this user
    $verification_token = bin2hex(random_bytes(16));
    $hashed_password = hesk_password_hash($password);

    $myuser['language'] = HESK_DEFAULT_LANGUAGE;
    if ($hesk_settings['can_sel_lang']) {
        $myuser['language'] = $hesklang['LANGUAGE'];
    }

    if ($existing_account === null) {
        $extra_fields = ", `pass_changed_at`";
        $extra_values = ", NOW()";

        // Calculate customer expiry date
        if(isset($hesk_settings['pass_customers_expire']) && $hesk_settings['pass_customers_expire'] == '1'){
            $exp_unit  = $hesk_settings['pass_customers_expire_unit'] === 'months' ? 'MONTH' : 'DAY';
            $exp_value = intval($hesk_settings['pass_customers_expire_value']);
            $extra_fields .= ", `pass_expires_at`";
            $extra_values .= ", NOW() + INTERVAL {$exp_value} {$exp_unit}";
        }

        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`pass`, `name`, 
            `email`, `language`, `verified`, `verification_token`, `verification_email_sent_at` $extra_fields) VALUES (
                '".hesk_dbEscape($hashed_password)."',
                '".hesk_dbEscape($myuser['name'])."',
                '".hesk_dbEscape($myuser['email'])."',
                '".hesk_dbEscape($myuser['language'])."',
                0,
                '".hesk_dbEscape($verification_token)."',
                NOW()
                $extra_values
            )");

        $customer_id = hesk_dbInsertID();
        hesk_hook_customer_created(array(
            'id' => $customer_id,
            'name' => $myuser['name'],
            'email' => $myuser['email']
        ), true);

        // Insert hash into the history table
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."password_history` (`user_id`, `user_type`, `pass`, `dt`) VALUES (".intval($customer_id).",'0','".hesk_dbEscape($hashed_password)."', NOW()) ");
    }
    // Send verification email to the user
    require_once(HESK_PATH . 'inc/email_functions.inc.php');
    hesk_sendCustomerRegistrationEmail($myuser, $verification_token);
}
