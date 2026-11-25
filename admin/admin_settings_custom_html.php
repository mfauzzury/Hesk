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
define('HESK_PATH','../');

define('LOAD_TABS',1);

define('LOAD_CUSTOMER_THEME_VARS',1); // Need this here to show off calculated colors as examples and make them work properly

// Make sure the install folder is deleted
if (is_dir(HESK_PATH . 'install')) {die('Please delete the <b>install</b> folder from your server for security reasons then refresh this page!');}

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');

// Save the default language for the settings page before choosing user's preferred one
$hesk_settings['language_default'] = $hesk_settings['language'];
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

$action = hesk_REQUEST('a');
// Check permissions for this feature
hesk_checkPermission('can_man_settings', 1, function($failure_reason) use ($action) {
    if ($action === 'save') {
        hesk_hook_settings_updated(array('settings_page' => 'CUSTOM_HTML'), $failure_reason);
    } else {
        hesk_hook_settings_viewed('CUSTOM_HTML', $failure_reason);
    }
});

// Is this feature disabled?
if (file_exists(HESK_PATH . 'disable_custom_html_ui.txt')) {
    hesk_error($hesklang['custom_html_disabled']);
}

// JavaScript approval can also be disabled by the server owner.
$custom_html_js_disabled = file_exists(HESK_PATH . 'disable_custom_html_js.txt');

// What should we do?
if ($action) {
    if ( defined('HESK_DEMO') ) {
        hesk_process_messages($hesklang['ddemo'], 'admin_settings_custom_html.php', 'NOTICE');
    } else if ($action == 'save') {
        hesk_token_check('POST');
        hesk_save_custom_html();
    }
}

define('EXTRA_PAGE_CLASSES','page-admin-settings-custom-html');

if (!isset($_SESSION['suppress_settings_view_hook'])) {
    hesk_hook_settings_viewed('CUSTOM_HTML', true);
} else {
    unset($_SESSION['suppress_settings_view_hook']);
}

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

// Demo mode? Hide values of sensitive settings
if ( defined('HESK_DEMO') )
{
    require_once(HESK_PATH . 'inc/admin_settings_demo.inc.php');
}

$enable_save_settings = 1;

/* This will handle error, success and notice messages */
hesk_handle_messages();
?>
<div class="main__content settings admin_settings_theme">

    <?php require_once(HESK_PATH . 'inc/admin_settings_status.inc.php'); ?>

    <form method="post" action="admin_settings_custom_html.php" name="form1" aria-label="<?php echo $hesklang['custom_html_link']?>">
        <div class="settings__form form">
            <section class="settings__form_block">
                <div style="margin-left:40px; margin-bottom:50px;">
                    <div role="note" class="notification blue">
                        <?php echo $hesklang['custom_html_filter_note']; ?>
                    </div>
                    <?php if ($custom_html_js_disabled): ?>
                    <?php elseif (!empty($_SESSION['isadmin'])): ?>
                        <div class="checkbox-custom" style="margin-bottom:20px;">
                            <input type="checkbox" id="allow_js" name="allow_js" value="1" autocomplete="off" aria-describedby="allow_js_note">
                            <label for="allow_js"><?php echo $hesklang['custom_html_allow_js']; ?></label>
                        </div>
                        <p id="allow_js_note"><?php echo $hesklang['custom_html_js_warning']; ?></p>
                    <?php endif; ?>
                </div>
                <h3><?php echo $hesklang['custom_head']; ?></h3>
                <div style="margin-left:40px; margin-bottom:50px;">
                    <p><?php echo $hesklang['custom_head_desc']; ?></span></p>
                    <?php
                    $template_file = HESK_PATH . 'head.txt';
                    if (file_exists($template_file) && is_writable($template_file)) {
                    ?>
                        <textarea class="form-control" id="head" name="head" style="width:100%;height:300px;resize: both;" aria-label="<?php echo $hesklang['custom_head_desc']; ?>"><?php echo htmlspecialchars(file_get_contents($template_file)); ?></textarea>
                    <?php
                    } else {
                        $enable_save_settings = 0;
                        hesk_show_notice(sprintf($hesklang['file_missing_not_writable'], $template_file));
                    }
                    ?>
                </div>

                <h3><?php echo $hesklang['custom_header']; ?></h3>
                <div style="margin-left:40px; margin-bottom:50px;">
                    <p><?php echo sprintf($hesklang['custom_header_desc'], 'https://www.hesk.com/knowledgebase/?article=62'); ?></span></p>
                    <?php
                    $template_file = HESK_PATH . 'header.txt';
                    if (file_exists($template_file) && is_writable($template_file)) {
                    ?>
                        <textarea class="form-control" id="header" name="header" style="width:100%;height:300px;resize: both;" aria-label="<?php echo hesk_htmlspecialchars($hesklang['custom_header_desc']); ?>"><?php echo htmlspecialchars(file_get_contents($template_file)); ?></textarea>
                    <?php
                    } else {
                        $enable_save_settings = 0;
                        hesk_show_notice(sprintf($hesklang['file_missing_not_writable'], $template_file));
                    }
                    ?>
                </div>

                <h3><?php echo $hesklang['custom_footer']; ?></h3>
                <div style="margin-left:40px; margin-bottom:50px;">
                    <p><?php echo $hesklang['custom_footer_desc']; ?></span></p>
                    <?php
                    $template_file = HESK_PATH . 'footer.txt';
                    if (file_exists($template_file) && is_writable($template_file)) {
                    ?>
                        <textarea class="form-control" id="footer" name="footer" style="width:100%;height:300px;resize: both;" aria-label="<?php echo $hesklang['custom_footer_desc']; ?>"><?php echo htmlspecialchars(file_get_contents($template_file)); ?></textarea>
                    <?php
                    } else {
                        $enable_save_settings = 0;
                        hesk_show_notice(sprintf($hesklang['file_missing_not_writable'], $template_file));
                    }
                    ?>
                </div>
            </section>

            <div class="settings__form_submit">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <input type="hidden" name="a" value="save">
                <button style="display: inline-flex" type="submit" id="submitbutton" class="btn btn-full" ripple="ripple"
                    <?php echo $enable_save_settings ? '' : 'disabled'; ?> aria-label="<?php echo $hesklang['save_changes']; ?>">
                    <?php echo $hesklang['save_changes']; ?>
                </button>

                <a style="height: 40px" href="admin_settings_theme.php" class="btn btn--blue-border" ripple="ripple">
                    <?php echo $hesklang['cancel']; ?> / <?php echo $hesklang['back']; ?>
                </a>

                <?php if (!$enable_save_settings): ?>
                    <p>&nbsp;</p>
                    <div role="alert" class="notification red">
                        <?php echo $hesklang['uanble_not_writable']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script src="<?php echo HESK_PATH; ?>js/jquery-ui.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


function hesk_save_custom_html()
{
    global $hesk_settings, $hesklang;
    $_SESSION['suppress_settings_view_hook'] = true;

    // This is a per-save opt-in, never a permission granted to other staff.
    $allow_js = hesk_POST('allow_js') === '1';
    if ($allow_js && empty($_SESSION['isadmin'])) {
        hesk_custom_html_error($hesklang['no_permission'], 'administrator_only');
    }
    if ($allow_js && file_exists(HESK_PATH . 'disable_custom_html_js.txt')) {
        hesk_custom_html_error($hesklang['custom_html_js_disabled'], 'custom_html_js_disabled');
    }

    // Validate and prepare ALL files before writing any of them.
    $new_files = array();
    foreach (array('head', 'header', 'footer') as $part) {
        if (!isset($_POST[$part]) || !is_string($_POST[$part])) {
            hesk_custom_html_error($hesklang['custom_html_invalid'], 'invalid_custom_html');
        }

        $template_file = HESK_PATH . $part . '.txt';
        if (!is_file($template_file) || !is_writable($template_file) || !is_readable($template_file)) {
            hesk_custom_html_error($hesklang['uanble_not_writable'], 'custom_html_file_unavailable');
        }
        $old_file_content = file_get_contents($template_file);
        if ($old_file_content === false) {
            hesk_custom_html_error($hesklang['uanble_not_writable'], 'custom_html_file_unavailable');
        }

        $file_content = HESK_SLASH ? $_POST[$part] : stripslashes($_POST[$part]);
        hesk_custom_html_check_php($file_content);

        // Browsers normalize textarea line endings. Do not strip an administrator's
        // existing scripts when another file is edited without the JS opt-in.
        if (str_replace(array("\r\n", "\r"), "\n", $file_content) === str_replace(array("\r\n", "\r"), "\n", $old_file_content)) {
            continue;
        }

        $file_content = hesk_sanitize_html($file_content, $allow_js);
        if ($file_content === false) {
            hesk_custom_html_error($hesklang['custom_html_filter_failed'], 'custom_html_filter_failed');
        }
        $new_file_content = '<!-- ' . hesk_htmlspecialchars_decode($hesklang['custom_' . $part . '_cmnt']) . " -->\n\n" . ltrim($file_content);

        // These files are included by PHP. Check the FINAL bytes as well, after
        // HTML normalization, comment removal and restoring trusted JS/CSS.
        hesk_custom_html_check_php($new_file_content);
        if ($old_file_content !== $new_file_content) {
            $new_files[$part] = $new_file_content;
        }
    }

    $custom_html_changes = array();
    foreach ($new_files as $part => $new_file_content) {
        if (file_put_contents(HESK_PATH . $part . '.txt', $new_file_content, LOCK_EX) !== strlen($new_file_content)) {
            hesk_custom_html_error($hesklang['uanble_not_writable'], 'custom_html_write_failed', $custom_html_changes);
        }
        $custom_html_changes[] = 'custom_' . $part;
    }

    hesk_hook_settings_updated(array(
        'settings_page' => 'CUSTOM_HTML',
        'changes' => $custom_html_changes,
        'allow_js' => $allow_js
    ), true);
    hesk_process_messages($hesklang['custom_html_saved'], 'NOREDIRECT', 'SUCCESS');

} // END hesk_save_custom_html()


function hesk_custom_html_error($message, $reason, $changes = array())
{
    hesk_hook_settings_updated(array('settings_page' => 'CUSTOM_HTML', 'changes' => $changes), $reason);
    hesk_process_messages($message, 'admin_settings_custom_html.php');
} // END hesk_custom_html_error()


function hesk_custom_html_check_php($in)
{
    global $hesklang;

    if (strpos($in, "\0") !== false || preg_match('//u', $in) !== 1) {
        hesk_custom_html_error($hesklang['custom_html_invalid'], 'invalid_custom_html');
    }

    // Include short/echo tags, ASP tags and the legacy PHP 5 script tag.
    // Check everywhere, including inside HTML comments and JS/CSS strings.
    if (strpos($in, '<?') !== false || strpos($in, '<%') !== false ||
        preg_match('~<script\s+language\s*=\s*(?:"php"|\'php\'|php)\s*>~i', $in) !== 0) {
        hesk_custom_html_error($hesklang['custom_html_no_php'], 'php_not_allowed');
    }
} // END hesk_custom_html_check_php()


function hesk_sanitize_html($in, $allow_js = false)
{
    // The caller checks administrator approval and rejects PHP before and after this.
    if ($allow_js) {
        return $in;
    }

    require_once(HESK_PATH . 'inc/htmlpurifier/HTMLPurifier.standalone.php');

    // Preserve CSS text, not raw HTML tags. HTML Purifier must still validate
    // style/link attributes (especially event handlers) without JS approval.
    $styles = array();
    $invalid_css = false;
    if (!$allow_js) {
        $prefix = 'HESKCUSTOMCSS' . bin2hex(random_bytes(16)) . 'X';
        $in = preg_replace_callback(
            '~(<(script|style)(?=[\s/>])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(.*?)(</\2(?=[\s/>])[^>]*>)~is',
            function ($matches) use (&$styles, &$invalid_css, $prefix) {
                if (strtolower($matches[2]) === 'script') {
                    return '';
                }
                if (!hesk_custom_html_safe_css($matches[3])) {
                    $invalid_css = true;
                    return '';
                }
                $key = $prefix . count($styles) . 'END';
                $styles[$key] = $matches[3];
                return $matches[1] . $key . '</style>';
            },
            $in
        );
        if ($in === null || $invalid_css) {
            return false;
        }
    }

    // Keep this configuration local: ticket/email/KB filtering must not change.
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('Attr.EnableID', true);
    $config->set('Attr.AllowedRel', array('follow', 'referrer', 'nofollow', 'noreferrer', 'noopener'));
    $config->set('Attr.AllowedFrameTargets', array('_blank', '_self', '_parent', '_top'));
    $config->set('Core.ConvertDocumentToFragment', false);

    // Preserve ordinary head metadata, but not redirects or charset overrides.
    $definition = $config->getHTMLDefinition(true);
    $definition->addElement('meta', 'Inline', 'Empty', array(), array('name' => 'Text', 'content' => 'Text'));

    if (!$allow_js) {
        $definition->addElement('style', 'Inline', 'Optional: #PCDATA', array(), array(
            'type' => 'Enum#text/css', 'media' => 'Text', 'title' => 'Text', 'id' => 'ID', 'nonce' => 'Text'
        ));
        $definition->addElement('link', 'Inline', 'Empty', array(), array(
            'rel*' => 'Enum#stylesheet,alternate stylesheet,stylesheet alternate',
            'href*' => new HTMLPurifier_AttrDef_URI(true),
            'type' => 'Enum#text/css', 'media' => 'Text', 'title' => 'Text', 'id' => 'ID',
            'integrity' => 'Text', 'crossorigin' => 'Enum#anonymous,use-credentials',
            'referrerpolicy' => 'Enum#no-referrer,no-referrer-when-downgrade,origin,origin-when-cross-origin,same-origin,strict-origin,strict-origin-when-cross-origin,unsafe-url'
        ));
    }

    $purifier = new HTMLPurifier($config);
    $out = $purifier->purify($in);
    if (!$allow_js) {
        // Restore only a complete style element's text, NEVER a token inside an
        // attribute. Drop malformed/unclosed styles that were not checked above.
        $out = preg_replace_callback(
            '~(<style\b[^>]*>)(.*?)</style>~is',
            function ($matches) use ($styles) {
                return isset($styles[$matches[2]]) ? $matches[1] . $styles[$matches[2]] . '</style>' : '';
            },
            $out
        );
        if ($out === null) {
            return false;
        }
        return str_replace(array_keys($styles), '', $out);
    }
} // END hesk_sanitize_html()


function hesk_custom_html_safe_css($css)
{
    // CSS customization is trusted to staff with settings permission. Keep modern
    // CSS intact, but reject legacy scripting features and script URL schemes.
    // This is not a general CSS sandbox and cannot inspect external stylesheets.
    // Recognize strings and escapes before comments; comment-looking text inside
    // a CSS string must not hide a scripting declaration elsewhere in the block.
    $check = preg_replace_callback(
        '~\\\\(?:[0-9a-f]{1,6}(?:\r\n|[ \t\r\n\f])?|[^0-9a-f\r\n\f])|"(?:[^"\\\\\r\n\f]++|\\\\(?:\r\n|.))*(?:"|(?=[\r\n\f])|$)|\'(?:[^\'\\\\\r\n\f]++|\\\\(?:\r\n|.))*(?:\'|(?=[\r\n\f])|$)|/\*.*?(?:\*/|$)~is',
        function ($matches) {
            return substr($matches[0], 0, 2) === '/*' ? '' : $matches[0];
        },
        $css
    );
    if ($check === null) {
        return false;
    }
    $check = preg_replace('~\\\\(?:\r\n|[\r\n\f])~', '', $check);
    if ($check === null) {
        return false;
    }
    $check = preg_replace_callback(
        '~\\\\(?:([0-9a-f]{1,6})(?:\r\n|[ \t\r\n\f])?|([^0-9a-f\r\n\f]))~i',
        function ($matches) {
            if ($matches[1] !== '') {
                $code = hexdec($matches[1]);
                return $code < 128 ? chr($code) : 'X';
            }
            return $matches[2];
        },
        $check
    );
    if ($check === null) {
        return false;
    }
    $check = preg_replace('~[\x00-\x20\x7f]+~', '', $check);
    return $check !== null && preg_match('~expression\(|(?:java|vb)script:|behavior:|-moz-binding:|-o-link:|</?style~i', $check) === 0;
} // END hesk_custom_html_safe_css()
