<?php
global $hesk_settings, $hesklang;
/**
 * @var array $validationFailures - Form fields that resulted in an error when attempting to register, if any
 * @var array $messages - Feedback messages to be displayed, if any
 * @var array $model - User model, populated if registration attempt resulted in an error
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
define('EXTRA_PAGE_CLASSES','page-register');

define('ALERTS',1);

define('RENDER_COMMON_ELEMENTS',1);
define('LOAD_CSS_MODAL',1);

define('LOAD_JS_JQUERY_MODAL',1);

global $BREADCRUMBS;
$BREADCRUMBS = array(
    array('url' => $hesk_settings['site_url'], 'title' => $hesk_settings['site_title']),
    array('url' => $hesk_settings['hesk_url'], 'title' => $hesk_settings['hesk_title']),
    array('title' => $hesklang['customer_register'])
);

/* Print header */
require_once(TEMPLATE_PATH . 'customer/inc/header.inc.php');
?>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($serviceMessages); ?>
                    <?php hesk3_show_messages($messages); ?>
                </div>
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-team"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['customer_register']; ?></span>
                </h1>
                <div class="article-heading-tip">
                    <span><?php echo $hesklang['req_marked_with']; ?></span>
                    <span class="label required"></span>
                </div>
                <form action="register.php" method="post" name="form1" id="formNeedValidation"
                      class="form form-submit-ticket ticket-create <?php echo count($validationFailures) ? 'invalid' : ''; ?>"
                      novalidate>
                    <section class="form-groups centered">
                        <div class="form-group required">
                            <label class="label" for="name"><?php echo $hesklang['name']; ?></label>
                            <input type="text" id="name" name="name" maxlength="255"
                                   class="form-control <?php if (in_array('name', $validationFailures)) {echo 'isError';} ?>"
                                   value="<?php echo isset($model['name']) ? stripslashes(hesk_input($model['name'])) : ''; ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <div class="form-group required">
                            <label class="label" for="email"><?php echo $hesklang['customer_email']; ?></label>
                            <input type="text" id="email" name="email" maxlength="255"
                                   class="form-control <?php if (in_array('email', $validationFailures)) {echo 'isError';} ?>"
                                   value="<?php echo isset($model['email']) ? stripslashes($model['email']) : ''; ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <div class="form-group required">
                            <label class="label" for="password"><?php echo $hesklang['pass']; ?></label>
                            <input type="password" id="password" name="password"
                                   class="form-control <?php if (in_array('password', $validationFailures)) {echo 'isError';} ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                            <div id="hesk-pass-requirements-register" style="display:none;"></div>
                        </div>
                        <div class="form-group required">
                            <label class="label" for="confirm-password"><?php echo $hesklang['confirm_pass']; ?></label>
                            <input type="password" id="confirm-password" name="confirm-password"
                                   class="form-control <?php if (in_array('password', $validationFailures)) {echo 'isError';} ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                            <div id="hesk-confirm-pass-register" style="display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label class="label"><?php echo $hesklang['pwdst']; ?></label>
                            <div style="border: 1px solid  var(--white-8); width: 100%; height: 14px">
                                <div id="progressBar" style="font-size: 1px; height: 12px; width: 0px; border: none;">
                                </div>
                            </div>
                        </div>
                        <?php
                        if ($hesk_settings['question_use'] || ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] !== 1)):
                        ?>
                            <div class="captcha-block">
                                <h2><?php echo $hesklang['verify_header']; ?></h2>

                                <?php if ($hesk_settings['question_use']): ?>
                                    <div class="form-group">
                                        <label class="required" for="question"><?php echo $hesk_settings['question_ask']; ?></label>
                                        <?php
                                        $value = '';
                                        if (isset($_SESSION['c_question']))
                                        {
                                            $value = stripslashes(hesk_input($_SESSION['c_question']));
                                        }
                                        ?>
                                        <input type="text" class="form-control <?php echo in_array('question',$validationFailures) ? 'isError' : ''; ?>"
                                               id="question" name="question" size="20" value="<?php echo $value; ?>">
                                    </div>
                                <?php
                                endif;

                                if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] != 1)
                                {
                                    ?>
                                    <div class="form-group">
                                        <?php
                                        // CAPTCHA verified for this registration
                                        if ($captchaVerified)
                                        {
                                            ?>
                                            <p id="captcha-retry-status" role="status"><?php echo $hesklang['vrfy']; ?></p>
                                            <?php
                                        }
                                        // Use reCAPTCHA V2?
                                        if (!$captchaVerified && $hesk_settings['recaptcha_use'] == 2)
                                        {
                                            ?>
                                            <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
                                            <?php
                                        }
                                        // Use Cloudflare Turnstile?
                                        elseif (!$captchaVerified && $hesk_settings['recaptcha_use'] == 3)
                                        {
                                            ?>
                                            <div class="cf-turnstile" data-sitekey="<?php echo $hesk_settings['turnstile_public_key']; ?>"></div>
                                            <?php
                                        }
                                        // Use hCaptcha?
                                        elseif (!$captchaVerified && $hesk_settings['recaptcha_use'] == 4)
                                        {
                                            ?>
                                            <div class="h-captcha" data-sitekey="<?php echo $hesk_settings['hcaptcha_public_key']; ?>"></div>
                                            <?php
                                        }
                                        // At least use some basic PHP generated image (better than nothing)
                                        elseif (!$captchaVerified || $hesk_settings['recaptcha_use'] == 0)
                                        {
                                            $cls = isset($_SESSION['iserror']) && in_array('mysecnum',$_SESSION['iserror']) ? 'isError' : '';
                                            ?>
                                            <div id="captcha-retry-challenge"<?php if ($captchaVerified): ?> hidden<?php endif; ?>>
                                            <img name="secimg" <?php if (!$captchaVerified): ?>src="print_sec_img.php?<?php echo rand(10000,99999); ?>" <?php endif; ?>width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                            <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.form1.secimg.src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                                <svg class="icon icon-refresh">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                                </svg>
                                            </a>
                                            <label class="required" for="mysecnum"><?php echo $hesklang['sec_enter']; ?></label>
                                            <input type="text" id="mysecnum" name="mysecnum" size="20" maxlength="5" autocomplete="off" class="form-control <?php echo $cls; ?>">
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                            <div class="divider"></div>
                        <?php
                        endif;
                        ?>
                    </section>
                    <?php if ($captchaVerified && $hesk_settings['recaptcha_use'] == 1): ?>
                        <p id="captcha-retry-status" role="status"><?php echo $hesklang['vrfy']; ?></p>
                    <?php endif; ?>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-full" ripple="ripple" id="recaptcha-submit"><?php echo $hesklang['customer_register']; ?></button>
                    </div>
                    <?php
                    // Use Invisible reCAPTCHA?
                    if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] == 1)
                    {
                        ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" <?php if ($captchaVerified): ?>data-size="invisible"<?php else: ?>data-bind="recaptcha-submit"<?php endif; ?> data-callback="recaptcha_submitForm"></div>
                        <?php
                    }
                    ?>
                </form>
            </div>
        </div>
<script>
<?php if ($captchaVerified): ?>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById('formNeedValidation');
    var email = document.getElementById('email');
    var status = document.getElementById('captcha-retry-status');
    var challenge = document.getElementById('captcha-retry-challenge');
    var boundEmail = <?php echo json_encode(stripslashes(hesk_htmlspecialchars_decode($_SESSION['register_captcha_retry']['email'])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var remaining = <?php echo max(0, intval($_SESSION['register_captcha_retry']['expires']) - time()); ?>;
    var deadline = Date.now() + remaining * 1000 -
        (window.performance && performance.now ? performance.now() : 0);

    function retryValid() {
        return Date.now() < deadline &&
            (boundEmail === '' || !email || email.value.trim() === boundEmail);
    }

    function updateRetry() {
        var valid = retryValid();
        var message = valid ? <?php echo json_encode($hesklang['vrfy']); ?> :
            <?php echo json_encode($hesklang[in_array($hesk_settings['recaptcha_use'], array(2, 3, 4)) ? 'captcha_retry_reload' : 'captcha_retry_required']); ?>;
        if (status.textContent !== message) status.textContent = message;
        if (challenge && challenge.hidden !== valid) {
            challenge.hidden = valid;
            if (!valid) {
                form.elements['mysecnum'].value = '';
                challenge.querySelector('img[name="secimg"]').src =
                    'print_sec_img.php?' + (Math.floor(90000 * Math.random()) + 10000);
            }
        }
    }

    if (email) {
        email.addEventListener('input', updateRetry);
        email.addEventListener('change', updateRetry);
    }
    setTimeout(updateRetry, Math.max(0, Math.ceil(deadline - Date.now())));
    updateRetry();

    <?php if ($hesk_settings['recaptcha_use'] == 1): ?>
    document.addEventListener('submit', function(event) {
        if (event.target !== form || event.defaultPrevented || retryValid()) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        updateRetry();
        var widget = form.querySelector('.g-recaptcha');
        if (window.grecaptcha && widget && typeof widget.heskRecaptchaId !== 'undefined') {
            grecaptcha.execute(widget.heskRecaptchaId);
        } else {
            status.textContent = <?php echo json_encode($hesklang['captcha_retry_loading']); ?>;
        }
    });
    <?php elseif ($hesk_settings['recaptcha_use'] == 0): ?>
    form.addEventListener('submit', function(event) {
        if (retryValid()) return;
        updateRetry();
        var answer = form.elements['mysecnum'];
        if (!answer || !answer.value.trim()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            status.textContent = <?php echo json_encode($hesklang['captcha_retry_required']); ?>;
            alert(<?php echo json_encode($hesklang['sec_miss']); ?>);
            if (answer) answer.focus();
        }
    }, true);
    <?php endif; ?>
});
<?php endif; ?>
window.heskPassConfig = {
    inputId:              'password',
    containerId:          'hesk-pass-requirements-register',
    confirmId:            'confirm-password',
    confirmContainerId:   'hesk-confirm-pass-register',
    minLength:       <?php echo intval(isset($hesk_settings['pass_minimum_length']) ? $hesk_settings['pass_minimum_length'] : 0); ?>,
    lowercase:       <?php echo intval(isset($hesk_settings['pass_lowercase_letters']) ? $hesk_settings['pass_lowercase_letters'] : 0); ?>,
    uppercase:       <?php echo intval(isset($hesk_settings['pass_uppercase_letters']) ? $hesk_settings['pass_uppercase_letters'] : 0); ?>,
    numbers:         <?php echo intval(isset($hesk_settings['pass_numbers']) ? $hesk_settings['pass_numbers'] : 0); ?>,
    special:         <?php echo intval(isset($hesk_settings['pass_special_symbols']) ? $hesk_settings['pass_special_symbols'] : 0); ?>,
    checkCommon:     false,
    commonPasswords: [],
    historyLimit:    0,
    msg: {
        minLength:    <?php echo json_encode(sprintf($hesklang['error_minimum_password_length'], intval(isset($hesk_settings['pass_minimum_length']) ? $hesk_settings['pass_minimum_length'] : 0))); ?>,
        lowercase:    <?php echo json_encode(sprintf($hesklang['error_pass_lowercase_letters'], intval(isset($hesk_settings['pass_lowercase_letters']) ? $hesk_settings['pass_lowercase_letters'] : 0))); ?>,
        uppercase:    <?php echo json_encode(sprintf($hesklang['error_pass_uppercase_letters'], intval(isset($hesk_settings['pass_uppercase_letters']) ? $hesk_settings['pass_uppercase_letters'] : 0))); ?>,
        numbers:      <?php echo json_encode(sprintf($hesklang['error_pass_numbers'], intval(isset($hesk_settings['pass_numbers']) ? $hesk_settings['pass_numbers'] : 0))); ?>,
        special:      <?php echo json_encode(sprintf($hesklang['error_pass_special_symbols'], intval(isset($hesk_settings['pass_special_symbols']) ? $hesk_settings['pass_special_symbols'] : 0))); ?>,
        common:       <?php echo json_encode($hesklang['error_common_password']); ?>,
        passMatch:    <?php echo json_encode($hesklang['pass_match']); ?>,
        passNoMatch:  <?php echo json_encode($hesklang['pass_no_match']); ?>
    }
};
    document.addEventListener("DOMContentLoaded", function() {
        $('input[name="password"]').keyup(function() {
            HESK_FUNCTIONS.checkPasswordStrength(this.value);
        });
    });
<?php if (defined('RECAPTCHA')) : ?>
    // Override default RECAPTCHA handling functions
    function recaptcha_submitForm() {
        document.getElementById("formNeedValidation").submit();
    }
<?php endif; ?>
</script>
<script src="<?php echo HESK_PATH; ?>js/password_complexity.js"></script>
<?php
/* Print Footer */
require_once(TEMPLATE_PATH . 'customer/inc/footer.inc.php');
?>
