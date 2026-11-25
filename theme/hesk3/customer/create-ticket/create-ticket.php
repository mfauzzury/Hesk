<?php
global $hesk_settings, $hesklang;
/**
 * @var string $categoryName
 * @var int $categoryId
 * @var array $visibleCustomFieldsBeforeMessage
 * @var array $visibleCustomFieldsAfterMessage
 * @var array $customFieldsBeforeMessage
 * @var array $customFieldsAfterMessage
 * @var bool $customerLoggedIn - `true` if a customer is logged in, `false` otherwise
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
define('EXTRA_PAGE_CLASSES','page-create-ticket');

define('ALERTS',1);
define('CUSTOM_FIELDS',1);
define('ATTACHMENTS',1);
define('PRIORITIES',1);

define('LOAD_CSS_DROPZONE',1);
define('RENDER_COMMON_ELEMENTS',1);

define('TMP_TITLE',1); // TODO absolutelyRework

define('LOAD_JS_DATEPICKER',1);
define('LOAD_JS_DROPZONE',1);

define('FOOTER_DONT_CLOSE_HTML',1);

global $BREADCRUMBS;
$BREADCRUMBS = array(
    array('url' => $hesk_settings['site_url'], 'title' => $hesk_settings['site_title']),
    array('url' => "index.php", 'title' => $hesk_settings['hesk_title']),
    array('url' => "index.php?a=add", 'title' => $hesklang['submit_ticket']),
    array('title' => $categoryName)
);

/* Print header */
require_once(TEMPLATE_PATH . 'customer/inc/header.inc.php');
?>
        <div class="main__content sub-page">
            <div class="contr">
                <?php hesk3_show_messages($serviceMessages); ?>
                <?php hesk3_show_messages($messages); ?>
            </div>

            <section class="sub-hero">
                <div class="contr">
                    <a href="index.php" class="sub-hero__back"><span aria-hidden="true">&larr;</span> Back to help desk</a>
                    <h1 class="sub-hero__title"><?php echo $hesklang['submit_a_support_request']; ?></h1>
                    <p class="sub-hero__sub">Tell us what is going on and our team will get back to you by email.</p>
                    <?php if (!empty($categoryName)): ?>
                    <span class="sub-hero__chip"><?php echo $hesklang['category']; ?>: <?php echo $categoryName; ?></span>
                    <?php endif; ?>
                    <div class="article-heading-tip sub-hero__req">
                        <span><?php echo $hesklang['req_marked_with']; ?></span>
                        <span class="label required"></span>
                    </div>
                </div>
            </section>

            <div class="contr">
                <div class="sub-layout">
                    <div class="sub-layout__main">
                <form class="form form-submit-ticket ticket-create <?php echo count($_SESSION['iserror']) ? 'invalid' : ''; ?>" method="post" action="submit_ticket.php?submit=1" aria-label="<?php echo $hesklang['create_a_ticket']; ?>" name="form1" id="form1" enctype="multipart/form-data" onsubmit="<?php if ($hesk_settings['submitting_wait']): ?>hesk_showLoadingMessage('recaptcha-submit');<?php endif; ?>" <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>>
                    <?php if (!$customerLoggedIn) { ?>
                    <div class="form-group">
                        <label class="label required" for="name"><?php echo $hesklang['name']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('name', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        ?>
                        <input type="text" id="name" name="name"
                               class="<?php echo $input_css; ?>"
                               maxlength="50"
                               value="<?php
                               if (isset($_SESSION['c_name'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_name']));
                               } ?>"
                               <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>
                               required>
                    </div>
                    <div class="form-group">
                        <label class="label <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>" for="email"><?php echo $hesklang['email']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('email', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('email', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        ?>
                        <input type="email"
                               class="<?php echo $input_css; ?>"
                               name="email" id="email" maxlength="1000"
                               value="<?php
                               if (isset($_SESSION['c_email'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_email']));
                               } ?>"
                               <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>
                               <?php if($hesk_settings['detect_typos']) { echo ' onblur="HESK_FUNCTIONS.suggestEmail(\'email\', \'email_suggestions\', 0)"'; } ?>
                               <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>>
                        <div id="email_suggestions" class="email-suggestion"></div>
                    </div>
                    <?php
                    if ($hesk_settings['confirm_email']):
                        ?>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('email2', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('email2', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        if ($customerLoggedIn) {
                            $input_css .= ' as-text';
                        }
                        ?>
                        <div class="form-group">
                            <label class="label <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>" for="email2"><?php echo $hesklang['confemail']; ?>:</label>
                            <input type="<?php echo $hesk_settings['multi_eml'] ? 'text' : 'email'; ?>"
                                   class="<?php echo $input_css; ?>"
                                   name="email2" id="email2" maxlength="1000"
                                   <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>
                                   <?php if ($customerLoggedIn) { echo 'readonly'; } ?>
                                   value="<?php if (isset($_SESSION['c_email2'])) {echo stripslashes(hesk_input($_SESSION['c_email2']));} ?>"
                                   <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>>
                        </div>
                    <?php endif;
                    }
                    if ($hesk_settings['multi_eml'] && !isset($_SESSION['c_followers'])): ?>
                    <div class="form-group" id="cc-link">
                        <a href="#" onclick="HESK_FUNCTIONS.toggleLayerDisplay('cc-div');HESK_FUNCTIONS.toggleLayerDisplay('cc-link')">
                            <?php echo $hesklang['add_cc']; ?>
                        </a>
                    </div>
                    <?php endif;
                    if ($hesk_settings['multi_eml']):
                        $display = isset($_SESSION['c_followers']) ? 'block' : 'none';
                    ?>
                    <div class="form-group" id="cc-div" style="display: <?php echo $display; ?>">
                        <label class="label" for="follower_email"><?php echo $hesklang['cc']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('followers', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('followers', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        ?>
                        <input type="text"
                               class="<?php echo $input_css; ?>"
                               <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>
                               name="follower_email" id="follower_email" maxlength="1000"
                               value="<?php
                               if (isset($_SESSION['c_followers'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_followers']));
                               } ?>" <?php if($hesk_settings['detect_typos']) { echo ' onblur="HESK_FUNCTIONS.suggestEmail(\'follower_email\', \'follower_email_suggestions\', 0)"'; } ?>>
                        <div id="follower_email_suggestions" class="email-suggestion"></div>
                        <p><?php if ($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required']) echo $hesklang['only_verified_cc'] . ' '; echo $hesklang['cc_help']; ?></p>
                    </div>
                    <?php
                    endif;
                    if ($hesk_settings['cust_urgency']): ?>
                        <section class="param">
                            <span class="label required <?php if (in_array('priority',$_SESSION['iserror'])) echo 'isErrorStr'; ?>"><?php echo $hesklang['priority']; ?>:</span>
                            <div class="dropdown-select center out-close priority select-priority">
                                <select name="priority" aria-label="<?php echo $hesklang['priority']; ?>">
                                    <?php if ($hesk_settings['select_pri']): ?>
                                        <option value=""><?php echo $hesklang['select']; ?></option>
                                    <?php endif; ?>
                                    <?php
                                        //Get User access priority
                                        echo hesk_get_priority_select('', 0, $_SESSION['c_priority']);
                                    ?>
                                </select>
                            </div>
                        </section>
                    <?php
                    endif;
                    if (count($visibleCustomFieldsBeforeMessage) > 0):
                    ?>
                    <div class="divider"></div>
                    <?php
                    endif;
                    hesk3_output_custom_fields($customFieldsBeforeMessage);

                    if ($hesk_settings['require_subject'] != -1 || $hesk_settings['require_message'] != -1): ?>
                        <div class="divider"></div>
                        <?php if ($hesk_settings['require_subject'] != -1): ?>
                            <div class="form-group">
                                <label class="label <?php if ($hesk_settings['require_subject']) { ?>required<?php } ?>" for="subject">
                                    <?php echo $hesklang['subject']; ?>:
                                </label>
                                <input type="text" id="subject" class="form-control <?php if (in_array('subject',$_SESSION['iserror'])) {echo 'isError';} ?>"
                                       name="subject" maxlength="70"
                                       value="<?php if (isset($_SESSION['c_subject'])) {echo stripslashes(hesk_input($_SESSION['c_subject']));} ?>"
                                       <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>
                                       <?php if ($hesk_settings['require_subject']) { ?>required<?php } ?>>
                            </div>
                            <?php
                        endif;
                        if ($hesk_settings['require_message'] != -1): ?>
                            <div class="form-group">
                                <label class="label <?php if ($hesk_settings['require_message']) { ?>required<?php } ?>" for="message">
                                    <?php echo $hesklang['message']; ?>:
                                </label>
                                <textarea class="form-control <?php if (in_array('message',$_SESSION['iserror'])) {echo 'isError';} ?>"
                                          id="message" name="message" rows="12" cols="60"
                                          <?php if ($hesk_settings['require_message']) { ?>required<?php } ?>><?php if (isset($_SESSION['c_message'])) {echo stripslashes(hesk_input($_SESSION['c_message']));} ?></textarea>
                                <?php if (has_public_kb() && $hesk_settings['kb_recommendanswers'] && ! isset($_REQUEST['do_not_suggest'])): ?>
                                    <div class="kb-suggestions">
                                        <h2><?php echo $hesklang['sc']; ?>:</h2>
                                        <ul id="kb-suggestion-list" class="type--list">
                                        </ul>
                                        <div id="suggested-article-hidden-inputs" style="display: none">
                                            <?php // Will be populated with the list sent to the create ticket logic ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        endif;
                    endif;

                    if (count($visibleCustomFieldsAfterMessage) > 0): ?>
                    <div class="divider"></div>
                    <?php
                    endif;

                    hesk3_output_custom_fields($customFieldsAfterMessage);

                    if ($hesk_settings['attachments']['use']):
                    ?>
                        <div class="divider"></div>
                        <section class="param param--attach">
                            <span class="label"><?php echo $hesklang['attachments']; ?>:</span>
                            <div class="attach">
                                <div>
                                    <?php hesk3_output_drag_and_drop_attachment_holder(); ?>
                                </div>
                                <div class="attach-tooltype">
                                    <a class="link" href="file_limits.php" onclick="HESK_FUNCTIONS.openWindow('file_limits.php',250,500);return false;">
                                        <?php echo $hesklang['ful']; ?>
                                    </a>
                                </div>
                            </div>
                        </section>
                        <div class="divider"></div>
                        <?php
                    endif;

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
                            <input type="text" id="question" class="form-control <?php echo in_array('question',$_SESSION['iserror']) ? 'isError' : ''; ?>"
                                   name="question" size="20" value="<?php echo $value; ?>"
                                   <?php echo $hesk_settings['disable_autofill_customer'] ? 'autocomplete="off" aria-autocomplete="none"' : ''; ?>>
                        </div>
                        <?php
                            endif;

                            if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] != 1)
                            {
                                ?>
                                <div class="form-group">
                                    <?php
                                    // CAPTCHA verified for this ticket
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
                                        $cls = in_array('mysecnum',$_SESSION['iserror']) ? 'isError' : '';
                                        ?>
                                        <div id="captcha-retry-challenge"<?php if ($captchaVerified): ?> hidden<?php endif; ?>>
                                        <img name="secimg" <?php if (!$captchaVerified): ?>src="print_sec_img.php?<?php echo rand(10000,99999); ?>" <?php endif; ?>width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                        <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.form1.secimg.src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                            <svg class="icon icon-refresh">
                                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                            </svg>
                                        </a>
                                        <label class="required" for="mysecnum"><?php echo $hesklang['sec_enter']; ?></label>
                                        <input type="text" id="mysecnum" name="mysecnum" size="20" maxlength="5" autocomplete="off" aria-autocomplete="none" class="form-control <?php echo $cls; ?>">
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

                    if ($hesk_settings['submit_notice']):
                    ?>
                    <div class="alert browser-default">
                        <div class="alert__inner">
                            <b class="font-weight-bold"><?php echo $hesklang['before_submit']; ?>:</b>
                            <ul>
                                <li><?php echo $hesklang['all_info_in']; ?>.</li>
                                <li><?php echo $hesklang['all_error_free']; ?>.</li>
                            </ul>
                            <br>
                            <b class="font-weight-bold"><?php echo $hesklang['we_have']; ?>:</b>
                            <ul>
                                <li><?php echo hesk_htmlspecialchars(hesk_getClientIP()).' '.$hesklang['recorded_ip']; ?></li>
                                <li><?php echo $hesklang['recorded_time']; ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>


                    <?php if ($captchaVerified && $hesk_settings['recaptcha_use'] == 1): ?>
                        <p id="captcha-retry-status" role="status"><?php echo $hesklang['vrfy']; ?></p>
                    <?php endif; ?>
                    <div class="form-footer">
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                        <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                        <button type="submit" class="btn btn-full" ripple="ripple" id="recaptcha-submit">
                            <?php echo $hesklang['sub_ticket']; ?>
                        </button>
                        <!-- Do not delete or modify the code below, it is used to detect simple SPAM bots -->
                        <input type="hidden" name="hx" value="3" /><input type="hidden" name="hy" value="">
                        <!-- >
                        <input type="text" name="phone" value="3">
                        < -->
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
                    <aside class="sub-aside">
                        <div class="sub-aside__card">
                            <h2 class="sub-aside__title">Tips for a faster reply</h2>
                            <ul class="sub-aside__list">
                                <li>Be specific. Include the steps you took and any error message you saw.</li>
                                <li>Attach a screenshot if it helps show the problem.</li>
                                <li>Use an email address you check often. Replies are sent there.</li>
                            </ul>
                        </div>
                        <?php if (empty($customerLoggedIn) || true): ?>
                        <div class="sub-aside__card sub-aside__card--alt">
                            <h2 class="sub-aside__title">Already submitted a request?</h2>
                            <p class="sub-aside__text">Check the latest update with your tracking ID.</p>
                            <a href="ticket.php" class="sub-aside__link">View existing ticket <span aria-hidden="true">&rarr;</span></a>
                        </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </div>
        <div id="loading-overlay" class="loading-overlay">
            <div id="loading-message" class="loading-message">
                <div class="spinner"></div>
                <p><?php echo $hesklang['sending_wait']; ?></p>
            </div>
        </div>
<?php
    function hesk_jsString($str)
    {
        $str  = addslashes($str);
        $str  = str_replace('<br />' , '' , $str);
        $from = array("/\r\n|\n|\r/", '/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
        $to   = array("\\r\\n' + \r\n'", "$1", "$1");
        return preg_replace($from,$to,$str);
    } // END hesk_jsString()
?>
<script type="text/javascript">

<?php if ($captchaVerified): ?>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById('form1');
    var email = document.getElementById('email');
    var status = document.getElementById('captcha-retry-status');
    var challenge = document.getElementById('captcha-retry-challenge');
    var boundEmail = <?php echo json_encode(stripslashes(hesk_htmlspecialchars_decode($_SESSION['ticket_captcha_retry']['email'])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var remaining = <?php echo max(0, intval($_SESSION['ticket_captcha_retry']['expires']) - time()); ?>;
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
    var suggestions = document.getElementById('email_suggestions');
    if (suggestions) suggestions.addEventListener('click', updateRetry);
    setTimeout(updateRetry, Math.max(0, Math.ceil(deadline - Date.now())));
    updateRetry();

    <?php if ($hesk_settings['recaptcha_use'] == 1): ?>
    form.addEventListener('submit', function(event) {
        if (retryValid()) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        updateRetry();
        var widget = form.querySelector('.g-recaptcha');
        if (window.grecaptcha && widget && typeof widget.heskRecaptchaId !== 'undefined') {
            grecaptcha.execute(widget.heskRecaptchaId);
        } else {
            status.textContent = <?php echo json_encode($hesklang['captcha_retry_loading']); ?>;
        }
    }, true);
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
<?php if ($captchaVerified && $hesk_settings['recaptcha_use'] == 1): ?>
function recaptcha_submitForm() {
    <?php if ($hesk_settings['submitting_wait']): ?>hesk_showLoadingMessage('recaptcha-submit');<?php endif; ?>
    document.getElementById('form1').submit();
}
<?php endif; ?>

    document.addEventListener("DOMContentLoaded", function() {

        $('#select_category').selectize();
        hesk_loadNoResultsSelectizePlugin('<?php echo hesk_jsString($hesklang['no_results_found']); ?>');
        <?php

        foreach ($customFieldsBeforeMessage as $customField)
        {
            if ($customField['type'] == 'select')
            {
                if ($customField['value']['is_searchable'] == 1) {
                    echo "$('#{$customField['name']}').addClass('read-write').attr('placeholder', '".$hesklang["search_by_pattern"]."').selectize({
                        delimiter: ',',
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['displayName'],
                        create: false,
                        copyClassesToDropdown: true,
                        plugins: ['no_results'],
                    });";
                } else {
                    echo "$('#{$customField['name']}').selectize();";
                }
            }
        }
        foreach ($customFieldsAfterMessage as $customField)
        {
            if ($customField['type'] == 'select')
            {
                if ($customField['value']['is_searchable'] == 1) {
                    echo "$('#{$customField['name']}').addClass('read-write').attr('placeholder', '".$hesklang["search_by_pattern"]."').selectize({
                        delimiter: ',',
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['displayName'],
                        create: false,
                        copyClassesToDropdown: true,
                        plugins: ['no_results'],
                    });";
                } else {
                    echo "$('#{$customField['name']}').selectize();";
                }
            }
        }
        ?>
    });
</script>

<?php if (has_public_kb() && $hesk_settings['kb_recommendanswers']): ?>
    <script type="text/javascript">
        var noArticlesFoundText = <?php echo json_encode($hesklang['nsfo']); ?>;

        document.addEventListener("DOMContentLoaded", function() {
            HESK_FUNCTIONS.getKbTicketSuggestions($('input[name="subject"]'),
                $('textarea[name="message"]'),
                function(data) {
                    $('.kb-suggestions').show();
                    var $suggestionList = $('#kb-suggestion-list');
                    var $suggestedArticlesHiddenInputsList = $('#suggested-article-hidden-inputs');
                    $suggestionList.html('');
                    $suggestedArticlesHiddenInputsList.html('');
                    var format = '<a href="knowledgebase.php?article={0}" class="suggest-preview" target="_blank">' +
                        '<span class="icon-in-circle" aria-hidden="true">' +
                        '<svg class="icon icon-knowledge">' +
                        '<use xlink:href="./theme/hesk3/customer/img/sprite.svg#icon-knowledge"></use>' +
                        '</svg>' +
                        '</span>' +
                        '<div class="suggest-preview__text">' +
                        '<p class="suggest-preview__title">{1}</p>' +
                        '<p>{2}</p>' +
                        '</div>' +
                        '</a>';
                    var hiddenInputFormat = '<input type="hidden" name="suggested[]" value="{0}">';
                    var results = false;
                    $.each(data, function() {
                        results = true;
                        $suggestionList.append(format.replace('{0}', this.id).replace('{1}', this.subject).replace('{2}', this.contentPreview));
                        $suggestedArticlesHiddenInputsList.append(hiddenInputFormat.replace('{0}', this.hiddenInputValue));
                    });

                    if (!results) {
                        $suggestionList.append('<li class="no-articles-found">' + noArticlesFoundText + '</li>');
                    }
                }
            );
        });
    </script>
<?php endif; ?>
<?php
/* Print Footer */
require_once(TEMPLATE_PATH . 'customer/inc/footer.inc.php');

/*
 * Note: In this case, we have to make sure we load all footer scripts first, as otherwise it breaks some of the custom JS page code
 */
?>
<?php hesk3_output_drag_and_drop_script('c_attachments'); ?>
<?php if (isset($hesk_settings['datepicker'])): ?>
<script>
    $(document).ready(function() {
        <?php foreach ($hesk_settings['datepicker'] as $selector => $data): ?>
        var picker = $(<?php echo json_encode($selector); ?>).datepicker(<?php echo isset($data['position']) && is_string($data['position']) ? json_encode(array('position' => $data['position'])) : '{}'; ?>).data('datepicker');
        <?php if (isset($data['timestamp'])): ?>
        <?php $ts = intval($data['timestamp']); ?>
        <?php if (!empty($data['fromDB'])): ?>
        picker.selectDate(new Date(<?php echo gmdate('Y', $ts) . ', ' . (intval(gmdate('n', $ts)) - 1) . ', ' . gmdate('j', $ts); ?>));
        <?php else: ?>
        picker.selectDate(new Date(<?php echo $ts; ?> * 1000));
        <?php endif; ?>
        <?php endif; ?>
        <?php endforeach; ?>
    });
</script>
<?php endif; ?>
    </body>
</html>
