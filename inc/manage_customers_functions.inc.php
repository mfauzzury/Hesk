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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}


function hesk_customer_tab($session_array='new', $customer_verified = false)
{
	global $hesk_settings, $hesklang;

	$errors = hesk_SESSION(array($session_array, 'errors'));
	$errors = is_array($errors) ? $errors : array();
	?>
    <div class="step-slider">
        <div class="step-item step-1">
            <div class="form-group">
                <label for="prof_name"><?php echo $hesklang['name']; ?></label>
                <input type="text" class="form-control <?php echo in_array('name', $errors) ? 'isError' : ''; ?>" id="prof_name" name="name" maxlength="50"
                       value="<?php echo $_SESSION[$session_array]['name']; ?>">
            </div>
            <div class="form-group">
                <label for="prof_email"><?php echo $hesklang['email']; ?></label>
                <input type="text" class="form-control <?php echo in_array('email', $errors) ? 'isError' : ''; ?>" name="email" maxlength="255" id="prof_user"
                       value="<?php echo $_SESSION[$session_array]['email']; ?>" aria-label="<?php echo $hesklang['email']; ?>">
            </div>
            <?php if (empty($_SESSION[$session_array]['id']) && $hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1): ?>
            <div class="form-group">
                <label for="prof_language"><?php echo $hesklang['chol']; ?></label>
                <select name="customer_language" id="prof_language">
                    <?php
                    $current_language = $hesk_settings['language'];
                    $language = hesk_SESSION(array($session_array, 'language'), HESK_DEFAULT_LANGUAGE);
                    $hesk_settings['language'] = isset($hesk_settings['languages'][$language]) ? $language : HESK_DEFAULT_LANGUAGE;
                    hesk_listLanguages();
                    $hesk_settings['language'] = $current_language;
                    ?>
                </select>
            </div>
            <script>
                $('#prof_language').selectize();
            </script>
            <?php endif; ?>
            <?php if ($hesk_settings['customer_accounts']): ?>
            <section class="item--section customer-password">
                <h4>
                    <?php echo $hesklang['pass']; ?>
                </h4>
                <div class="optional">
                    (<?php echo $hesklang['optional']; ?>)
                </div>
                <div class="form-group">
                    <label for="prof_newpass"><?php echo (empty($_SESSION[$session_array]['id']) ? $hesklang['pass'] : $hesklang['new_pass']); ?></label>
                    <input type="password" id="prof_newpass" name="newpass" autocomplete="off" class="form-control <?php echo in_array('passwords', $errors) ? 'isError' : ''; ?>"
                           value="<?php echo isset($_SESSION[$session_array]['cleanpass']) ? $_SESSION[$session_array]['cleanpass'] : ''; ?>"
                           onkeyup="hesk_checkPassword(this.value)">
                    <div id="hesk-pass-requirements" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="prof_newpass2"><?php echo (empty($_SESSION[$session_array]['id']) ? $hesklang['confirm_pass'] : $hesklang['confirm_new_pass']); ?></label>
                    <input type="password" class="form-control <?php echo in_array('passwords', $errors) ? 'isError' : ''; ?>" id="prof_newpass2" name="newpass2" autocomplete="off"
                           value="<?php echo isset($_SESSION[$session_array]['cleanpass']) ? $_SESSION[$session_array]['cleanpass'] : ''; ?>">
                    <div id="hesk-confirm-pass-customer" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label><?php echo $hesklang['pwdst']; ?></label>
                    <div style="border: 1px solid #d4d6e3; width: 100%; height: 14px">
                        <div id="progressBar" style="font-size: 1px; height: 12px; width: 0px; border: none;">
                        </div>
                    </div>
                </div>
                <?php if (!$customer_verified) {
                    hesk_show_info($hesklang['customer_account_setting_password_will_verify_user']);
                } ?>
            </section>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (document.form1.newpass) {
            hesk_checkPassword(document.form1.newpass.value);
        }
    </script>
    <?php if ($hesk_settings['customer_accounts']): ?>
    <script>
    window.heskPassConfig = {
        inputId:            'prof_newpass',
        containerId:        'hesk-pass-requirements',
        confirmId:          'prof_newpass2',
        confirmContainerId: 'hesk-confirm-pass-customer',
        minLength:       <?php echo intval(isset($hesk_settings['pass_minimum_length']) ? $hesk_settings['pass_minimum_length'] : 0); ?>,
        lowercase:       <?php echo intval(isset($hesk_settings['pass_lowercase_letters']) ? $hesk_settings['pass_lowercase_letters'] : 0); ?>,
        uppercase:       <?php echo intval(isset($hesk_settings['pass_uppercase_letters']) ? $hesk_settings['pass_uppercase_letters'] : 0); ?>,
        numbers:         <?php echo intval(isset($hesk_settings['pass_numbers']) ? $hesk_settings['pass_numbers'] : 0); ?>,
        special:         <?php echo intval(isset($hesk_settings['pass_special_symbols']) ? $hesk_settings['pass_special_symbols'] : 0); ?>,
        checkCommon:     false,
        commonPasswords: [],
        historyLimit:    <?php echo intval(isset($hesk_settings['pass_history_limit']) ? $hesk_settings['pass_history_limit'] : 0); ?>,
        msg: {
            minLength:    <?php echo json_encode(sprintf($hesklang['error_minimum_password_length'], intval(isset($hesk_settings['pass_minimum_length']) ? $hesk_settings['pass_minimum_length'] : 0))); ?>,
            lowercase:    <?php echo json_encode(sprintf($hesklang['error_pass_lowercase_letters'], intval(isset($hesk_settings['pass_lowercase_letters']) ? $hesk_settings['pass_lowercase_letters'] : 0))); ?>,
            uppercase:    <?php echo json_encode(sprintf($hesklang['error_pass_uppercase_letters'], intval(isset($hesk_settings['pass_uppercase_letters']) ? $hesk_settings['pass_uppercase_letters'] : 0))); ?>,
            numbers:      <?php echo json_encode(sprintf($hesklang['error_pass_numbers'], intval(isset($hesk_settings['pass_numbers']) ? $hesk_settings['pass_numbers'] : 0))); ?>,
            special:      <?php echo json_encode(sprintf($hesklang['error_pass_special_symbols'], intval(isset($hesk_settings['pass_special_symbols']) ? $hesk_settings['pass_special_symbols'] : 0))); ?>,
            common:       <?php echo json_encode($hesklang['error_common_password']); ?>,
            historyLimit: <?php echo json_encode(sprintf($hesklang['error_password_limit'], intval(isset($hesk_settings['pass_history_limit']) ? $hesk_settings['pass_history_limit'] : 0))); ?>,
            passMatch:    <?php echo json_encode($hesklang['pass_match']); ?>,
            passNoMatch:  <?php echo json_encode($hesklang['pass_no_match']); ?>
        }
    };
    </script>
    <script src="<?php echo HESK_PATH; ?>js/password_complexity.js"></script>
    <?php endif; ?>
	<?php
} // END hesk_customer_tab()
