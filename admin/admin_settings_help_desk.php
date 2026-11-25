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

// Check permissions for this feature
hesk_checkPermission('can_man_settings', 1, function($failure_reason) {
    hesk_hook_settings_viewed('HELP_DESK', $failure_reason);
});

if (!isset($_SESSION['suppress_settings_view_hook'])) {
    hesk_hook_settings_viewed('HELP_DESK', true);
} else {
    unset($_SESSION['suppress_settings_view_hook']);
}

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

$help_folder = '../language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/help_files/';

$enable_save_settings   = 0;
$enable_use_attachments = 0;

define('EXTRA_PAGE_CLASSES','page-admin-settings-help-desk');

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

// Demo mode? Hide values of sensitive settings
if ( defined('HESK_DEMO') )
{
    require_once(HESK_PATH . 'inc/admin_settings_demo.inc.php');
}

/* This will handle error, success and notice messages */
hesk_handle_messages();

if ($hesk_settings['attachments']['use'] && ! defined('HESK_DEMO') ) {
// Check number of attachments per post
    if (version_compare(phpversion(), '5.2.12', '>=') && @ini_get('max_file_uploads') && @ini_get('max_file_uploads') < $hesk_settings['attachments']['max_number']) {
        hesk_show_notice($hesklang['fatte1']);
    }

// Check max attachment size
    $tmp = @ini_get('upload_max_filesize');
    if ($tmp) {
        $last = strtoupper(substr($tmp, -1));
        $number = substr($tmp, 0, -1);

        switch ($last) {
            case 'K':
                $tmp = $number * 1024;
                break;
            case 'M':
                $tmp = $number * 1048576;
                break;
            case 'G':
                $tmp = $number * 1073741824;
                break;
            default:
                $tmp = $number;
        }

        if ($tmp < $hesk_settings['attachments']['max_size']) {
            hesk_show_notice($hesklang['fatte2']);
        }
    }

// Check max post size
    /*
    $tmp = @ini_get('post_max_size');
    if ($tmp) {
        $last = strtoupper(substr($tmp, -1));
        $number = substr($tmp, 0, -1);

        switch ($last) {
            case 'K':
                $tmp = $number * 1024;
                break;
            case 'M':
                $tmp = $number * 1048576;
                break;
            case 'G':
                $tmp = $number * 1073741824;
                break;
            default:
                $tmp = $number;
        }

        if ($tmp < ($hesk_settings['attachments']['max_size'] * $hesk_settings['attachments']['max_number'] + 524288)) {
            hesk_show_notice($hesklang['fatte3']);
        }
    }
    */
}
?>
<div class="main__content settings admin_settings_help_desk">

    <?php require_once(HESK_PATH . 'inc/admin_settings_status.inc.php'); ?>

    <script language="javascript" type="text/javascript"><!--
        function hesk_checkFields() {
            var d = document.form1;

            // HELPDESK
            if (d.s_max_listings.value=='') {alert('<?php echo addslashes($hesklang['err_max']); ?>'); return false;}
            if (d.s_print_font_size.value=='') {alert('<?php echo addslashes($hesklang['err_psize']); ?>'); return false;}

            // DISABLE SUBMIT BUTTON
            d.submitbutton.disabled=true;

            return true;
        }

        function hesk_toggleLayer(nr,setto) {
            if (document.all)
                document.all[nr].style.display = setto;
            else if (document.getElementById)
                document.getElementById(nr).style.display = setto;
        }

        function checkRequiredEmail(field) {
            if (document.getElementById('s_require_email_0').checked && document.getElementById('s_email_view_ticket').checked)
            {
                if (field == 's_require_email_0' && confirm('<?php echo addslashes($hesklang['re_confirm1']); ?>'))
                {
                    document.getElementById('s_email_view_ticket').checked = false;
                    return true;
                }
                else if (field == 's_email_view_ticket' && confirm('<?php echo addslashes($hesklang['re_confirm2']); ?>'))
                {
                    document.getElementById('s_require_email_1').checked = true;
                    return true;
                }
                return false;
            }
            return true;
        }

        function hesk_generateUrlAccessKey(fID) {
            var length           = Math.random() * (30 - 20) + 20;
            var result           = '';
            var characters       = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ1234567890-_.';
            var charactersLength = characters.length;
            for ( var i = 0; i < length; i++ ) {
                result += characters.charAt(Math.floor(Math.random() * charactersLength));
            }
            $('#' + fID).val(result);
        }
        //Email attachment options
        $("body").on("change","input:radio[name=attachment_in_email_type]",function(){
            var f = $(this).val();
            if(f==0){
                $(".direct_attachment_section").css('display','none');
            }else{
                $(".direct_attachment_section").css('display','flex');
            }
        });
        //-->
    </script>
    <form method="post" action="admin_settings_save.php" name="form1" onsubmit="return hesk_checkFields()" aria-label="<?php echo $hesklang['hd']; ?>">
        <div class="settings__form form">
                <section class="settings__form_block">
                <h3><?php echo $hesklang['hd']; ?></h3>
                <div class="form-group">
                    <div class="input-info">
                        <label for="s_admin_dir">
                            <span><?php echo $hesklang['adf']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#61','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['adf']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_admin_dir" name="s_admin_dir" maxlength="255" value="<?php echo $hesk_settings['admin_dir']; ?>">
                </div>
                <div class="form-group">
                    <div class="input-info">
                        <label for="s_attach_dir">
                            <span><?php echo $hesklang['atf']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#62','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['atf']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_attach_dir" name="s_attach_dir" maxlength="255" value="<?php echo $hesk_settings['attach_dir']; ?>">
                </div>
                <div class="form-group">
                    <div class="input-info">
                        <label for="s_cache_dir">
                            <span><?php echo $hesklang['cf']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#77','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['cf']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_cache_dir" name="s_cache_dir" maxlength="255" value="<?php echo $hesk_settings['cache_dir']; ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_max_listings">
                            <span><?php echo $hesklang['max_listings']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#10','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['max_listings']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_max_listings" name="s_max_listings" maxlength="30" value="<?php echo $hesk_settings['max_listings']; ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_print_font_size">
                            <span><?php echo $hesklang['print_size']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#11','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['print_size']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_print_font_size" name="s_print_font_size" maxlength="3" value="<?php echo $hesk_settings['print_font_size']; ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_autoclose">
                            <span><?php echo $hesklang['aclose']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#15','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['aclose']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_autoclose" name="s_autoclose" size="5" maxlength="3" value="<?php echo $hesk_settings['autoclose']; ?>">
                    <span><?php echo $hesklang['aclose2']; ?></span>
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_max_open">
                            <span><?php echo $hesklang['mop']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#58','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['mop']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_max_open" name="s_max_open" size="5" maxlength="3" value="<?php echo $hesk_settings['max_open']; ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_due_soon">
                            <span><?php echo $hesklang['set_ds']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#84','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['set_ds']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_due_soon" name="s_due_soon" size="5" maxlength="3" value="<?php echo $hesk_settings['due_soon']; ?>">
                    <span><?php echo $hesklang['set_ds2']; ?></span>
                </div>
                <div class="radio-group mt24">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['rord']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#59','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rord']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <?php
                        $on = $hesk_settings['new_top'] ? 'checked' : '';
                        $off = $hesk_settings['new_top'] ? '' : 'checked';
                        ?>
                        <div class="radio-custom">
                            <input type="radio" id="s_new_top1" name="s_new_top" value="1" <?php echo $on; ?>>
                            <label for="s_new_top1"><?php echo $hesklang['newtop']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_new_top0" name="s_new_top" value="0"  <?php echo $off; ?>>
                            <label for="s_new_top0"><?php echo $hesklang['newbot']; ?></label>
                        </div>
                    </div>
                </div>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ford']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#60','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ford']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php
                        $on = $hesk_settings['reply_top'] ? 'checked="checked"' : '';
                        $off = $hesk_settings['reply_top'] ? '' : 'checked="checked"';
                    ?>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_reply_top1" name="s_reply_top" value="1" <?php echo $on; ?>>
                            <label for="s_reply_top1"><?php echo $hesklang['formtop']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_reply_top0" name="s_reply_top" value="0" <?php echo $off; ?>>
                            <label for="s_reply_top0"><?php echo $hesklang['formbot']; ?></label>
                        </div>
                    </div>
                </div>
                <?php
                $no = $hesk_settings['hide_replies']==0 ? 'checked' : '';
                $yes = $hesk_settings['hide_replies']>0 ? 'checked' : '';
                $def = $hesk_settings['hide_replies']==-1 ? 'checked' : '';
                ?>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['hide_replies']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#78','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['hide_replies']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_hide_replies0" name="s_hide_replies" value="0" <?php echo $no; ?>>
                            <label for="s_hide_replies0"><?php echo $hesklang['hide_replies_no']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_hide_replies-1" name="s_hide_replies" value="-1" <?php echo $def; ?>>
                            <label for="s_hide_replies-1"><?php echo $hesklang['hide_replies_def']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_hide_replies1" name="s_hide_replies" value="1" <?php echo $yes; ?>>
                            <label for="s_hide_replies1"><?php echo $hesklang['hide_replies_yes']; ?></label>
                            <div class="form-group short" style="margin-bottom: 0px;">
                                <input type="text" name="s_hide_replies_num" class="form-control" style="margin-left: 12px;" size="5" maxlength="4"
                                       value="<?php echo ($hesk_settings['hide_replies'] > 0 ? $hesk_settings['hide_replies'] : '10'); ?>" aria-label="<?php echo hesk_htmlspecialchars($hesklang['statistics']['axis6']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $no = $hesk_settings['limit_width']==0 ? 'checked' : '';
                $yes = $hesk_settings['limit_width']>0 ? 'checked' : '';
                ?>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['lwidth']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#79','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['lwidth']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_limit_width0" name="s_limit_width" value="0" <?php echo $no; ?>>
                            <label for="s_limit_width0"><?php echo $hesklang['lwidtall']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_limit_width1" name="s_limit_width" value="1" <?php echo $yes; ?>>
                            <label for="s_limit_width1"><?php echo $hesklang['lwidtpx']; ?></label>
                            <div class="form-group short" style="margin-bottom: 0px;">
                                <input type="text" name="s_limit_width_num" class="form-control" style="margin-left: 12px;" size="5" maxlength="4"
                                       value="<?php echo ($hesk_settings['limit_width'] > 0 ? $hesk_settings['limit_width'] : '800'); ?>" aria-label="<?php echo hesk_htmlspecialchars($hesklang['lwidtpx']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['features']; ?></h3>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['alo']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#44','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['alo']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['alo']; ?>">
                        <input type="checkbox" name="s_autologin" value="1" <?php if ($hesk_settings['autologin']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['saass']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#51','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['saass']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['saass']; ?>">
                        <input type="checkbox" name="s_autoassign" value="1" <?php if ($hesk_settings['autoassign']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['req_email']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#73','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['req_email']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['req_email']; ?>">
                        <input type="checkbox" name="s_require_email" value="1" <?php if ($hesk_settings['require_email']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="form-group">
                    <div class="input-info">
                        <label for="s_allowed_customer_email_domains">
                            <span><?php echo $hesklang['allowed_customer_email_domains']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>380.html#4','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['allowed_customer_email_domains']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <textarea name="s_allowed_customer_email_domains" id="s_allowed_customer_email_domains" class="form-control settings-control-indent" rows="4"><?php echo hesk_htmlspecialchars(implode("\n", $hesk_settings['allowed_customer_email_domains'])); ?></textarea>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['fass']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#70','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['fass']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['fass']; ?>">
                        <input type="checkbox" name="s_require_owner" value="1" <?php if ($hesk_settings['require_owner']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <?php
                $on = $hesk_settings['require_subject']==1 ? 'checked' : '';
                $off = $hesk_settings['require_subject']==0 ? 'checked' : '';
                $hide = $hesk_settings['require_subject']==-1 ? 'checked' : '';
                ?>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['req_sub']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#72','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['req_sub']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_require_subject0" name="s_require_subject" value="0" <?php echo $off; ?>>
                            <label for="s_require_subject0"><?php echo $hesklang['off']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_require_subject1" name="s_require_subject" value="1" <?php echo $on; ?>>
                            <label for="s_require_subject1"><?php echo $hesklang['on']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_require_subject-1" name="s_require_subject" value="-1" <?php echo $hide; ?>>
                            <label for="s_require_subject-1"><?php echo $hesklang['off-hide']; ?></label>
                        </div>
                    </div>
                </div>
                <?php
                $on = $hesk_settings['require_message']==1 ? 'checked' : '';
                $off = $hesk_settings['require_message']==0 ? 'checked' : '';
                $hide = $hesk_settings['require_message']==-1 ? 'checked' : '';
                ?>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['req_msg']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#74','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['req_msg']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_require_message0" name="s_require_message" value="0" <?php echo $off; ?>>
                            <label for="s_require_message0"><?php echo $hesklang['off']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_require_message1" name="s_require_message" value="1" <?php echo $on; ?>>
                            <label for="s_require_message1"><?php echo $hesklang['on']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_require_message-1" name="s_require_message" value="-1" <?php echo $hide; ?>>
                            <label for="s_require_message-1"><?php echo $hesklang['off-hide']; ?></label>
                        </div>
                    </div>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ccct']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#67','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ccct']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['ccct']; ?>">
                        <input type="checkbox" name="s_custclose" value="1" <?php if ($hesk_settings['custclose']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['s_ucrt']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#16','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['s_ucrt']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['s_ucrt']; ?>">
                        <input type="checkbox" name="s_custopen" value="1" <?php if ($hesk_settings['custopen']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['urate']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#17','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['urate']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['urate']; ?>">
                        <input type="checkbox" name="s_rating" value="1" <?php if ($hesk_settings['rating']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['cpri']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#45','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['cpri']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['cpri']; ?>">
                        <input type="checkbox" name="s_cust_urgency" value="1" <?php if ($hesk_settings['cust_urgency']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['eseqid']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#49','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['eseqid']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['eseqid']; ?>">
                        <input type="checkbox" name="s_sequential" value="1" <?php if ($hesk_settings['sequential']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ts']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#66','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ts']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['ts']; ?>">
                        <input type="checkbox" name="s_time_worked" value="1" <?php if ($hesk_settings['time_worked']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['spamn']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#68','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['spamn']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['spamn']; ?>">
                        <input type="checkbox" name="s_spam_notice" value="1" <?php if ($hesk_settings['spam_notice']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['lu']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#14','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['lu']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['lu']; ?>">
                        <input type="checkbox" name="s_list_users" value="1" <?php if ($hesk_settings['list_users']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['debug_mode']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#12','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['debug_mode']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['debug_mode']; ?>">
                        <input type="checkbox" name="s_debug_mode" value="1" <?php if ($hesk_settings['debug_mode']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['shu']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#63','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['shu']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['shu']; ?>">
                        <input type="checkbox" name="s_short_link" value="1" <?php if ($hesk_settings['short_link']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['sending_show']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#91','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['sending_show']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['sending_show']; ?>">
                        <input type="checkbox" name="s_submitting_wait" value="1" <?php if ($hesk_settings['submitting_wait']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['remember_custom_field_values']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>370.html#6','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['remember_custom_field_values']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['remember_custom_field_values']; ?>">
                        <input type="checkbox" name="s_remember_custom_field_values" value="1" <?php if ($hesk_settings['remember_custom_field_values']) { echo 'checked'; } ?>>
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['disable_autocomplete']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>370.html#7','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['disable_autocomplete']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div style="display:block;">
                        <label class="switch-checkbox" aria-label="<?php echo $hesklang['disable_autocomplete']; ?>">
                            <input type="checkbox" name="s_disable_autofill_admin" value="1" <?php if ($hesk_settings['disable_autofill_admin']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['disable_autofill_admin']; ?></span>
                        </label>
                        <br>
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_disable_autofill_customer" <?php if ($hesk_settings['disable_autofill_customer']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['disable_autofill_customer']; ?></span>
                        </label>
                    </div>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['setting_extend']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>370.html#2','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['setting_extend']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div style="display:block;">
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_extend_admin" value="1" <?php if ($hesk_settings['extend_admin']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['extend_admin']; ?></span>
                        </label>
                        <br>
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_extend_customer" <?php if ($hesk_settings['extend_customer']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['extend_customer']; ?></span>
                        </label>
                    </div>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['select']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#65','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['select']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div style="display:block;">
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_select_cat" value="1" <?php if ($hesk_settings['select_cat']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['category']; ?></span>
                        </label>
                        <br>
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_select_pri" <?php if ($hesk_settings['select_pri']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['priority']; ?></span>
                        </label>
                    </div>
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_cat_show_select">
                            <span><?php echo $hesklang['scat']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#71','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['scat']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_cat_show_select" name="s_cat_show_select" maxlength="3" value="<?php echo $hesk_settings['cat_show_select']; ?>">
                    <span><?php echo $hesklang['scat2']; ?></span>
                </div>
                <?php
                $plain = $hesk_settings['staff_ticket_formatting']==0 ? 'checked' : '';
                if ( ! class_exists('DOMDocument')) {
                    $rich_text = false;
                    $plain = 'checked';
                    $html = '';
                } else {
                    $rich_text = true;
                    $html = $hesk_settings['staff_ticket_formatting']==2 ? 'checked' : '';
                }
                ?>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ticket_formatting_staff']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#80','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ticket_formatting_staff']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_ticket_formatting_staff0" name="s_ticket_formatting_staff" value="0" <?php echo $plain; ?>>
                            <label for="s_ticket_formatting_staff0"><?php echo $hesklang['ticket_formatting_plaintext']; ?></label>
                        </div>
                        <?php if ($rich_text): ?>
                        <div class="radio-custom">
                            <input type="radio" id="s_ticket_formatting_staff2" name="s_ticket_formatting_staff" value="2" <?php echo $html; ?>>
                            <label for="s_ticket_formatting_staff2"><?php echo $hesklang['ticket_formatting_rich_text']; ?></label>
                        </div>
                        <?php else: ?>
                        <span style="margin-left: 24px;"><?php echo $hesklang['ticket_formatting_rich_text']; ?> - <?php echo $hesklang['require_xml']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ticket_followers']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>email.html#57','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ticket_followers']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="s_multi_eml1" name="s_multi_eml" value="1" <?php if ($hesk_settings['multi_eml']) {echo 'checked';} ?>>
                        <label for="s_multi_eml1"><?php echo $hesklang['ticket_followers2']; ?></label>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['nicknames']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>370.html#3','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['nicknames']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="s_staff_nicknames" name="s_staff_nicknames" value="1" <?php if ($hesk_settings['staff_nicknames']) {echo 'checked';} ?>>
                        <label for="s_staff_nicknames"><?php echo $hesklang['staff_nicknames']; ?></label>
                    </div>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['barcode']; ?></h3>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['barcode_use'];?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_use']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php
                    $div = $hesk_settings['barcode']['print'] ? 'block' : 'none';
                    ?>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['barcode']; ?>">
                        <input type="checkbox" name="s_barcode_print" value="1" <?php if ($hesk_settings['barcode']['print']) { echo 'checked'; } ?> onclick="hesk_toggleLayerDisplay('barcode')">
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div id="barcode" style="display: <?php echo $div; ?>;">
                    <div class="checkbox-group">
                        <span class="row-spacer" aria-hidden="true"></span>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="s_staff_only" name="s_staff_only" value="1" <?php if ($hesk_settings['barcode']['staff_only']) {echo 'checked';} ?>>
                            <label for="s_staff_only"><?php echo $hesklang['barcode_staff_only']; ?></label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-info">
                            <label for="barcode-type-select">
                                <span><?php echo $hesklang['barcode_type']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_type']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <div class="dropdown-select center out-close">
                            <select name="s_barcode_type" id="barcode-type-select">
                                <?php foreach ($hesk_settings['barcode_types'] as $type => $description): ?>
                                <option value="<?php echo $type; ?>" <?php echo $type == $hesk_settings['barcode']['type'] ? 'selected' : '' ?>>
                                    <?php echo $description; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-info">
                            <label for="barcode-format-select">
                                <span><?php echo $hesklang['barcode_format']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_format']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <div class="dropdown-select center out-close">
                            <select name="s_barcode_format" id="barcode-format-select">
                                <?php foreach ($hesk_settings['barcode_formats'] as $format => $description): ?>
                                <option value="<?php echo $format; ?>" <?php echo $format == $hesk_settings['barcode']['format'] ? 'selected' : '' ?>>
                                    <?php echo hesk_htmlspecialchars(sprintf($hesklang['barcode_image'], strtoupper($format))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group short">
                        <div class="input-info">
                            <label for="s_barcode_width">
                                <span><?php echo $hesklang['barcode_width']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_width']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" id="s_barcode_width" name="s_barcode_width" maxlength="30" value="<?php echo $hesk_settings['barcode']['width']; ?>">
                    </div>
                    <div class="form-group short">
                        <div class="input-info">
                            <label for="s_barcode_height">
                                <span><?php echo $hesklang['barcode_height']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_height']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" id="s_barcode_height" name="s_barcode_height" maxlength="30" value="<?php echo $hesk_settings['barcode']['height']; ?>">
                    </div>
                    <div class="form-group short">
                        <div class="input-info">
                            <label for="s_barcode_color">
                                <span><?php echo $hesklang['barcode_color']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_color']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" id="s_barcode_color" name="s_barcode_color" maxlength="30" value="<?php echo $hesk_settings['barcode']['color']; ?>">
                    </div>
                    <div class="form-group short">
                        <div class="input-info">
                            <label for="s_barcode_bg">
                                <span><?php echo $hesklang['barcode_bg']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#90','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['barcode_bg']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" id="s_barcode_bg" name="s_barcode_bg" maxlength="30" value="<?php echo $hesk_settings['barcode']['bg']; ?>">
                    </div>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['customer_accounts_heading']; ?></h3>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['customer_accounts']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#87','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['customer_accounts']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php
                    if ($hesk_settings['customer_accounts'] == 0) {
                        $ca0 = 'checked';
                        $ca1 = '';
                        $ca2 = '';
                        $ca3 = '';
                        $div = 'none';
                    } elseif ($hesk_settings['customer_accounts_required'] == 0) {
                        $ca0 = '';
                        $ca1 = 'checked';
                        $ca2 = '';
                        $ca3 = '';
                        $div = 'block';
                    } elseif ($hesk_settings['customer_accounts_required'] == 2) {
                        $ca0 = '';
                        $ca1 = '';
                        $ca2 = '';
                        $ca3 = 'checked';
                        $div = 'block';
                    } else {
                        $ca0 = '';
                        $ca1 = '';
                        $ca2 = 'checked';
                        $ca3 = '';
                        $div = 'block';
                    }
                    ?>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_customer_accounts0" name="s_customer_accounts" value="0" <?php echo $ca0; ?> onclick="hesk_toggleLayer('ca','none')">
                            <label for="s_customer_accounts0"><?php echo $hesklang['customer_accounts_off']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_customer_accounts1" name="s_customer_accounts" value="1" <?php echo $ca1; ?> onclick="hesk_toggleLayer('ca','block')">
                            <label for="s_customer_accounts1"><?php echo $hesklang['customer_accounts_allowed']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_customer_accounts2" name="s_customer_accounts" value="2" <?php echo $ca2; ?> onclick="hesk_toggleLayer('ca','block')">
                            <label for="s_customer_accounts2"><?php echo $hesklang['customer_accounts_required_to_submit']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_customer_accounts3" name="s_customer_accounts" value="3" <?php echo $ca3; ?> onclick="hesk_toggleLayer('ca','block')">
                            <label for="s_customer_accounts3"><?php echo $hesklang['customer_accounts_required']; ?></label>
                        </div>
                    </div>
                </div>
                <div id="ca" style="display: <?php echo $div; ?>;">
                    <?php
                    if ($hesk_settings['customer_accounts_customer_self_register'] == 0) {
                        $ca0 = '';
                        $ca1 = '';
                        $ca2 = 'checked';
                        $div = 'none';
                    } elseif ($hesk_settings['customer_accounts_admin_approvals'] == 1) {
                        $ca0 = '';
                        $ca1 = 'checked';
                        $ca2 = '';
                        $div = 'block';
                    } else {
                        $ca0 = 'checked';
                        $ca1 = '';
                        $ca2 = '';
                        $div = 'block';
                    }
                    ?>
                    <div class="radio-group">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['customer_accounts_reg']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#88','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['customer_accounts_reg']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <div class="radio-list">
                            <div class="radio-custom">
                                <input type="radio" id="s_customer_accounts_register0" name="s_customer_accounts_register" value="0" <?php echo $ca0; ?>>
                                <label for="s_customer_accounts_register0"><?php echo $hesklang['customer_accounts_reg_customer']; ?></label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_customer_accounts_register1" name="s_customer_accounts_register" value="1" <?php echo $ca1; ?>>
                                <label for="s_customer_accounts_register1"><?php echo $hesklang['customer_accounts_reg_approve']; ?></label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_customer_accounts_register2" name="s_customer_accounts_register" value="2" <?php echo $ca2; ?>>
                                <label for="s_customer_accounts_register2"><?php echo $hesklang['customer_accounts_reg_staff']; ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="checkbox-group row">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['alo']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#94','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['alo']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <label class="switch-checkbox" aria-label="<?php echo $hesklang['alo']; ?>">
                            <input type="checkbox" name="s_customer_autologin" value="1" <?php if ($hesk_settings['customer_autologin']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                        </label>
                    </div>
                    <div class="checkbox-group row">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['customer_accounts_allow_email_changes']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#92','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['customer_accounts_allow_email_changes']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <label class="switch-checkbox" aria-label="<?php echo $hesklang['customer_accounts_allow_email_changes']; ?>">
                            <input type="checkbox" name="s_customer_accounts_allow_email_changes" value="1"
                                <?php if ($hesk_settings['customer_accounts_allow_email_changes']) { echo 'checked'; } ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                        </label>
                    </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_customer_accounts_verify_email_cooldown">
                            <span><?php echo $hesklang['customer_accounts_allow_email_resends']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#93','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['customer_accounts_allow_email_resends']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_customer_accounts_verify_email_cooldown" name="s_customer_accounts_verify_email_cooldown" size="5" maxlength="3" value="<?php echo $hesk_settings['customer_accounts_verify_email_cooldown']; ?>">
                    <span><?php echo $hesklang['mm']; ?></span>
                </div>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['sp']; ?></h3>
                <div class="radio-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['use_secimg']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#13','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['use_secimg']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php
                    $onc = $hesk_settings['secimg_use'] == 1 ? 'checked' : '';
                    $ons = $hesk_settings['secimg_use'] == 2 ? 'checked' : '';
                    $off = $hesk_settings['secimg_use'] ? '' : 'checked';
                    $div = $hesk_settings['secimg_use'] ? 'block' : 'none';
                    ?>
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="s_secimg_use0" name="s_secimg_use" value="0" <?php echo $off; ?> onclick="hesk_toggleLayer('captcha','none')">
                            <label for="s_secimg_use0"><?php echo $hesklang['off']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_secimg_use1" name="s_secimg_use" value="1" <?php echo $onc; ?> onclick="hesk_toggleLayer('captcha','block')">
                            <label for="s_secimg_use1"><?php echo $hesklang['onc']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="s_secimg_use2" name="s_secimg_use" value="2" <?php echo $ons; ?> onclick="hesk_toggleLayer('captcha','block')">
                            <label for="s_secimg_use2"><?php echo $hesklang['ons']; ?></label>
                        </div>
                    </div>
                </div>
                <div id="captcha" style="display: <?php echo $div; ?>;">
                    <?php

                    $on  = '';
                    $on2 = '';
                    $on3 = '';
                    $on4 = '';
                    $off = '';
                    $divRecaptcha  = 'none';
                    $divTurnstile  = 'none';
                    $divHcaptcha   = 'none';

                    if ($hesk_settings['recaptcha_use'] == 1) {
                        $on = 'checked';
                        $divRecaptcha = 'block';
                    } elseif ($hesk_settings['recaptcha_use'] == 2) {
                        $on2 = 'checked';
                        $divRecaptcha = 'block';
                    } elseif ($hesk_settings['recaptcha_use'] == 3) {
                        $on3 = 'checked';
                        $divTurnstile = 'block';
                    } elseif ($hesk_settings['recaptcha_use'] == 4) {
                        $on4 = 'checked';
                        $divHcaptcha = 'block';
                    } else {
                        $off = 'checked';
                    }
                    ?>
                    <div class="radio-group">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['sit']; ?></span>
                            </label>
                        </div>
                        <div class="radio-list">
                            <div class="radio-custom">
                                <input type="radio" id="s_recaptcha_use0" name="s_recaptcha_use" value="0" onclick="hesk_toggleLayer('recaptcha','none');hesk_toggleLayer('turnstile','none');hesk_toggleLayer('hcaptcha','none');" <?php echo $off; ?>>
                                <label for="s_recaptcha_use0"><?php echo $hesklang['sis']; ?></label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_recaptcha_use2" name="s_recaptcha_use" value="2" onclick="hesk_toggleLayer('recaptcha','block');hesk_toggleLayer('turnstile','none');hesk_toggleLayer('hcaptcha','none');" <?php echo $on2; ?>>
                                <label for="s_recaptcha_use2"><?php echo $hesklang['recaptcha']; ?> v2</label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['recaptcha']; ?> v2">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_recaptcha_use1" name="s_recaptcha_use" value="1" onclick="hesk_toggleLayer('recaptcha','block');hesk_toggleLayer('turnstile','none');hesk_toggleLayer('hcaptcha','none');" <?php echo $on; ?>>
                                <label for="s_recaptcha_use1"><?php echo $hesklang['sir3']; ?></label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['sir3']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_recaptcha_use3" name="s_recaptcha_use" value="3" onclick="hesk_toggleLayer('recaptcha','none');hesk_toggleLayer('turnstile','block');hesk_toggleLayer('hcaptcha','none');" <?php echo $on3; ?>>
                                <label for="s_recaptcha_use3"><?php echo $hesklang['turnstile']; ?></label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#95','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['turnstile']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="s_recaptcha_use4" name="s_recaptcha_use" value="4" onclick="hesk_toggleLayer('recaptcha','none');hesk_toggleLayer('turnstile','none');hesk_toggleLayer('hcaptcha','block');" <?php echo $on4; ?>>
                                <label for="s_recaptcha_use4"><?php echo $hesklang['hcaptcha']; ?></label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#96','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['hcaptcha']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="recaptcha" style="display: <?php echo $divRecaptcha; ?>; margin-bottom: 20px">
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_recaptcha_public_key">
                                    <span><?php echo $hesklang['rcpb']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpb']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_recaptcha_public_key" name="s_recaptcha_public_key" maxlength="255" value="<?php echo $hesk_settings['recaptcha_public_key']; ?>">
                        </div>
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_recaptcha_private_key">
                                    <span><?php echo $hesklang['rcpv']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpv']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_recaptcha_private_key" name="s_recaptcha_private_key" maxlength="255" value="<?php echo $hesk_settings['recaptcha_private_key']; ?>">
                        </div>
                    </div>
                    <div id="turnstile" style="display: <?php echo $divTurnstile; ?>; margin-bottom: 20px">
                        <?php if ( ! function_exists('curl_init')) { ?>
                            <p class="text-danger"><?php echo $hesklang['require_curl']; ?></p>
                        <?php } ?>
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_turnstile_public_key">
                                    <span><?php echo $hesklang['rcpb']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#95','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpb']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_turnstile_public_key" name="s_turnstile_public_key" maxlength="255" value="<?php echo $hesk_settings['turnstile_public_key']; ?>">
                        </div>
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_turnstile_private_key">
                                    <span><?php echo $hesklang['rcpv']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#95','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpv']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_turnstile_private_key" name="s_turnstile_private_key" maxlength="255" value="<?php echo $hesk_settings['turnstile_private_key']; ?>">
                        </div>
                    </div>
                    <div id="hcaptcha" style="display: <?php echo $divHcaptcha; ?>; margin-bottom: 20px">
                        <?php if ( ! function_exists('curl_init')) { ?>
                            <p class="text-danger"><?php echo $hesklang['require_curl']; ?></p>
                        <?php } ?>
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_hcaptcha_public_key">
                                    <span><?php echo $hesklang['rcpb']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#96','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpb']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_hcaptcha_public_key" name="s_hcaptcha_public_key" maxlength="255" value="<?php echo $hesk_settings['hcaptcha_public_key']; ?>">
                        </div>
                        <div class="form-group">
                            <div class="input-info">
                                <label for="s_hcaptcha_private_key">
                                    <span><?php echo $hesklang['rcpv']; ?></span>
                                </label>
                                <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#96','400','500'); return false;"
                                   aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['rcpv']; ?>">
                                    <div class="tooltype right">
                                        <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                        </svg>
                                    </div>
                                </a>
                            </div>
                            <input type="text" class="form-control" id="s_hcaptcha_private_key" name="s_hcaptcha_private_key" maxlength="255" value="<?php echo $hesk_settings['hcaptcha_private_key']; ?>">
                        </div>
                    </div>
                    <div class="divider"></div>
                </div>
                <div class="checkbox-group row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['use_q']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#42','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['use_q']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php
                    $div = $hesk_settings['question_use'] ? 'block' : 'none';
                    ?>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['use_q']; ?>">
                        <input type="checkbox" name="s_question_use" value="1" <?php if ($hesk_settings['question_use']) { echo 'checked'; } ?> onclick="hesk_toggleLayerDisplay('question')">
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div id="question" style="display: <?php echo $div; ?>;">
                    <div class="form-group">
                        <span class="row-spacer" aria-hidden="true"></span>
                        <button style="margin-left: 24px" type="button" class="btn btn--blue-border" onclick="Javascript:hesk_rate('generate_spam_question.php','question')">
                            <?php echo $hesklang['genq']; ?>
                        </button>
                    </div>
                    <div class="form-group">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['q_q']; ?></span>
                            </label>
                        </div>
                        <textarea style="margin-left: 24px;" name="s_question_ask" class="form-control" rows="3" cols="40" aria-label="<?php echo $hesklang['err_qask']; ?>"><?php echo hesk_htmlentities($hesk_settings['question_ask']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['q_a']; ?></span>
                            </label>
                        </div>
                        <input class="form-control" type="text" name="s_question_ans" value="<?php echo $hesk_settings['question_ans']; ?>" aria-label="<?php echo $hesklang['err_qans']; ?>">
                    </div>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['security']; ?></h3>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_attempt_limit">
                            <span><?php echo $hesklang['banlim']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#47','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['banlim']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_attempt_limit" name="s_attempt_limit" maxlength="30" value="<?php echo ($hesk_settings['attempt_limit'] ? ($hesk_settings['attempt_limit']-1) : 0); ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_attempt_banmin">
                            <span><?php echo $hesklang['banmin']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#47','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['banmin']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_attempt_banmin" name="s_attempt_banmin" maxlength="3" value="<?php echo $hesk_settings['attempt_banmin']; ?>">
                </div>
                <div class="form-group short">
                    <div class="input-info">
                        <label for="s_flood">
                            <span><?php echo $hesklang['flood']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#81','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['flood']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_flood" name="s_flood" maxlength="3" value="<?php echo $hesk_settings['flood']; ?>">
                    <span><?php echo $hesklang['seconds']; ?></span>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['passr']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#69','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['passr']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="s_reset_pass" name="s_reset_pass" value="1" <?php if ($hesk_settings['reset_pass']) {echo 'checked';} ?>>
                        <label for="s_reset_pass"><?php echo $hesklang['passa']; ?></label>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['viewvtic']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#46','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['viewvtic']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="s_email_view_ticket" name="s_email_view_ticket" onclick="return checkRequiredEmail('s_email_view_ticket');" value="1" <?php if ($hesk_settings['email_view_ticket']) {echo 'checked';} ?>>
                        <label for="s_email_view_ticket"><?php echo $hesklang['reqetv']; ?></label>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['frames']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#76','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['frames']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="s_x_frame_opt" name="s_x_frame_opt" value="1" <?php if ($hesk_settings['x_frame_opt']) {echo 'checked';} ?>>
                        <label for="s_x_frame_opt"><?php echo $hesklang['frames2']; ?></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-info">
                        <label for="samesite-select">
                            <span><?php echo $hesklang['cookies']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#82','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['cookies']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="dropdown-select center out-close cookies-select">
                        <select name="s_samesite" id="samesite-select">
                        <?php
                        $samesite_options = array('Strict', 'Lax', 'None');
                        foreach ($samesite_options as $samesite_option)
                        {
                            echo '<option value="' . $samesite_option . '"' . ($hesk_settings['samesite'] == $samesite_option ? ' selected' : '') . '>' . $samesite_option . '</option>';
                        }
                        ?>
                        </select>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['ssl']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#75','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ssl']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="checkbox-custom">
                        <?php if (HESK_SSL): ?>
                            <input type="checkbox" id="s_force_ssl" name="s_force_ssl" value="1" <?php echo ($hesk_settings['force_ssl'] ? 'checked' : ''); ?>>
                            <label for="s_force_ssl"><?php echo $hesklang['force_ssl']; ?></label>
                        <?php else: ?>
                            <label for="s_force_ssl"><?php echo $hesklang['d_ssl']; ?></label>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="form-group flex-row">
                        <div class="input-info">
                            <label for="s_url_key">
                                <span><?php echo $hesklang['ukey']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#83','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['ukey']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input class="form-control" type="text" id="url_key" name="s_url_key"
                               value="<?php echo hesk_htmlentities($hesk_settings['url_key']); ?>" aria-label="<?php echo $hesklang['ukey']; ?>">
                    </div>
                    <div class="form-group">
                        <span class="row-spacer" aria-hidden="true"></span>
                        <button type="button" class="btn btn--blue-border settings-control-indent" onclick="Javascript:hesk_generateUrlAccessKey('url_key')">
                            <?php echo $hesklang['ukeyg']; ?>
                        </button>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['mfa_required']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#85','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['mfa_required']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div style="display:block;">
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_require_mfa" value="1" <?php if($hesk_settings['require_mfa'] === 1) {echo 'checked';} ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['for_staff']; ?></span>
                        </label>
                        <br>
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_require_mfa_customers" value="1" <?php if($hesk_settings['require_mfa_customers'] === 1) {echo 'checked';} ?>>
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['for_customers']; ?></span>
                        </label>
                    </div>
                </div>
                <div class="form-group short flex-row">
                    <?php
                    //-- Default value if duration in settings is invalid
                    $elevator_amount = 60;
                    $elevator_unit = 'M';

                    preg_match('/(\\d+)([MHD])/', $hesk_settings['elevator_duration'], $elevator_matches);
                    if (count($elevator_matches) === 3) {
                        $elevator_amount = $elevator_matches[1];
                        $elevator_unit = $elevator_matches[2];
                    }
                    ?>
                    <div class="input-info">
                        <label for="s_elevator_amount">
                            <span><?php echo $hesklang['elevator_duration_setting_title']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#86','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['elevator_duration_setting_title']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <input type="text" class="form-control" id="s_elevator_amount" name="s_elevator_amount" maxlength="6" value="<?php echo $elevator_amount; ?>">
                    <div class="dropdown-select center out-close">
                        <select name="s_elevator_unit">
                            <option value="M" <?php echo $elevator_unit === 'M' ? 'selected' : '' ?>>
                                <?php echo $hesklang['escalate']['minutes']; ?>
                            </option>
                            <option value="H" <?php echo $elevator_unit === 'H' ? 'selected' : '' ?>>
                                <?php echo $hesklang['escalate']['hours']; ?>
                            </option>
                            <option value="D" <?php echo $elevator_unit === 'D' ? 'selected' : '' ?>>
                                <?php echo $hesklang['escalate']['days']; ?>
                            </option>
                        </select>
                    </div>
                </div>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['enable_password_expiration']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>380.html#1','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['enable_password_expiration']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div style="display:block;">
                        <?php
                            $pass_staff_expire_value = isset($hesk_settings['pass_staff_expire_value']) ? $hesk_settings['pass_staff_expire_value'] : '30';
                            $pass_staff_expire_unit = isset($hesk_settings['pass_staff_expire_unit']) ? $hesk_settings['pass_staff_expire_unit'] : 'days';
                            $pass_customers_expire_value = isset($hesk_settings['pass_customers_expire_value']) ? $hesk_settings['pass_customers_expire_value'] : '30';
                            $pass_customers_expire_unit = isset($hesk_settings['pass_customers_expire_unit']) ? $hesk_settings['pass_customers_expire_unit'] : 'days';
                            $pass_minimum_length = isset($hesk_settings['pass_minimum_length']) ? $hesk_settings['pass_minimum_length'] : '8';
                            $pass_lowercase_letters = isset($hesk_settings['pass_lowercase_letters']) ? $hesk_settings['pass_lowercase_letters'] : '1';
                            $pass_uppercase_letters = isset($hesk_settings['pass_uppercase_letters']) ? $hesk_settings['pass_uppercase_letters'] : '1';
                            $pass_numbers = isset($hesk_settings['pass_numbers']) ? $hesk_settings['pass_numbers'] : '1';
                            $pass_special_symbols = isset($hesk_settings['pass_special_symbols']) ? $hesk_settings['pass_special_symbols'] : '1';
                            $pass_history_limit = isset($hesk_settings['pass_history_limit']) ? $hesk_settings['pass_history_limit'] : '5';
                            $staff_style_variable = $customer_style_variable = "display:none";
                            if($hesk_settings['pass_staff_expire'] === 1) {
                                $staff_style_variable = "display:block";
                            }
                            if($hesk_settings['pass_customers_expire'] === 1) {
                                $customer_style_variable = "display:block";
                            }
                        ?>
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_pass_expiration_staff" value="1" <?php if($hesk_settings['pass_staff_expire'] === 1) {echo 'checked';} ?> onclick="hesk_toggleLayerDisplay('password_setting_for_staff')">
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['for_staff']; ?></span>
                        </label>
                        
                        <label class="switch-checkbox">
                            <input type="checkbox" name="s_pass_expiration_customers" value="1" <?php if($hesk_settings['pass_customers_expire'] === 1) {echo 'checked';} ?> onclick="hesk_toggleLayerDisplay('password_setting_for_customers')">
                            <div class="switch-checkbox__bullet">
                                <i>
                                    <svg class="icon icon-close">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                    </svg>
                                    <svg class="icon icon-tick">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                    </svg>
                                </i>
                            </div>
                            <span><?php echo $hesklang['for_customers']; ?></span>
                        </label>
                        
                        <div class="form-group short flex-row mt10 keep-as-row" id="password_setting_for_staff" style="<?php echo $staff_style_variable;?>">
                            <div class="input-info">
                                <label><?php echo $hesklang['staff_password_expiration_text']; ?></label>
                            </div>
                            <input type="number" class="form-control" name="s_staff_expiry_value" min="1" max="3650" maxlength="4" value="<?php echo $pass_staff_expire_value; ?>">
                            <div class="dropdown-select center out-close">
                                <select name="s_staff_expiry_unit">
                                    <option value="days" <?php echo $pass_staff_expire_unit === 'days' ? 'selected' : '' ?>>
                                        <?php echo $hesklang['enable_pass_expiration']['days']; ?>
                                    <option value="months" <?php echo $pass_staff_expire_unit === 'months' ? 'selected' : '' ?>>
                                        <?php echo $hesklang['enable_pass_expiration']['months']; ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group short flex-row mt10 keep-as-row" id="password_setting_for_customers" style="<?php echo $customer_style_variable;?>">
                            <div class="input-info">
                                <label><?php echo $hesklang['customer_password_expiration_text']; ?></label>
                            </div>
                            <input type="number" class="form-control" name="s_customer_expiry_value" min="1" max="3650" maxlength="4" value="<?php echo $pass_customers_expire_value; ?>">
                            <div class="dropdown-select center out-close">
                                <select name="s_customer_expiry_unit">
                                    <option value="days" <?php echo $pass_customers_expire_unit === 'days' ? 'selected' : '' ?>>
                                        <?php echo $hesklang['enable_pass_expiration']['days']; ?>
                                    <option value="months" <?php echo $pass_customers_expire_unit === 'months' ? 'selected' : '' ?>>
                                        <?php echo $hesklang['enable_pass_expiration']['months']; ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                       
                    </div>   
                </div>
                <div class="form-group short flex-row">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['password_complexity']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>380.html#2','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['password_complexity']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <label><?php echo $hesklang['pass_minimum_length']; ?></label>
                    <input type="number" class="form-control" name="s_minimum_password_length" min="5" max="64" maxlength="2" value="<?php echo $pass_minimum_length;?>">
                    <span><?php echo $hesklang['password_character_text']; ?></span>
                </div>
                <div class="form-group short flex-row mt10">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <label><?php echo $hesklang['lowercase_letters']; ?></label>
                    <input type="number" class="form-control" name="s_pass_lowercase_letters" min="0" max="10" maxlength="2" value="<?php echo $pass_lowercase_letters;?>">
                </div>
                <div class="form-group short flex-row mt10">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <label><?php echo $hesklang['uppercase_letters']; ?></label>
                    <input type="number" class="form-control" name="s_pass_uppercase_letters" min="0" max="10" maxlength="2" value="<?php echo $pass_uppercase_letters;?>">
                </div>
                <div class="form-group short flex-row mt10">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <label><?php echo $hesklang['numbers']; ?></label>
                    <input type="number" class="form-control" name="s_pass_numbers" min="0" max="10" maxlength="2" value="<?php echo $pass_numbers;?>">
                </div>
                <div class="form-group short flex-row mt10">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <label><?php echo $hesklang['special_symbols']; ?></label>
                    <input type="number" class="form-control" name="s_pass_special_symbols" min="0" max="10" maxlength="2" value="<?php echo $pass_special_symbols;?>">
                </div>
                <div class="form-group short flex-row mt10">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <label><?php echo $hesklang['do_not_allow_last_password']; ?></label>
                    <input type="number" class="form-control" name="s_password_limit" min="0" max="64" maxlength="2" value="<?php echo $pass_history_limit;?>">
                    <span><?php echo $hesklang['passwords_text']; ?></span>
                </div>
                <div class="checkbox-custom">
                    <span class="row-spacer password-spacer" aria-hidden="true"></span>
                    <input type="checkbox" id="s_common_password" name="s_common_password" value="1"  <?php if ($hesk_settings['pass_check_common']) {echo 'checked';} ?>>
                    <label for="s_common_password"><?php echo $hesklang['detect_disallow_common_password']; ?></label>
                </div>
            </section>
            <section class="settings__form_block">
                <h3><?php echo $hesklang['attachments']; ?></h3>
                <div class="checkbox-group">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['attach_use']; $onload_status=''; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#37','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['attach_use']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <?php if ($enable_use_attachments) { ?>
                    <label class="switch-checkbox" aria-label="<?php echo $hesklang['attach_use']; ?>">
                        <input type="checkbox" name="s_attach_use" value="1" <?php if($hesk_settings['attachments']['use']) {echo 'checked'; $layer_onload = 'block';} else {$onload_status = 'disabled';$layer_onload = 'none';} ?> onchange="hesk_attach_handle(this, new Array('a1','a2','a3','a4')); hesk_toggleLayerDisplay('attachment_restrictions')">
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                        <?php if (!defined('HESK_DEMO')): ?>
                            <a href="javascript:void(0);" onclick="hesk_toggleLayerDisplay('attachments_limits');"><?php echo $hesklang['vscl']; ?></a>
                        <?php endif; ?>
                    </label>
                            <?php
                    } else {
                        $onload_status=' disabled="disabled" ';
                        $layer_onload = 'none';
                        echo '<input type="hidden" name="s_attach_use" value="0" /><span style="margin-left: 24px;" class="notice">'.$hesklang['e_attach'].'</span>';
                    }
                    ?>
                </div>
                <?php if (!defined('HESK_DEMO')): ?>
                <div class="form-group">
                    <span class="row-spacer" aria-hidden="true"></span>
                    <div id="attachments_limits" style="margin-left: 24px; display:none">
                        <i>upload_max_filesize</i>: <?php echo @ini_get('upload_max_filesize'); ?><br />
                        <?php
                        if (version_compare(phpversion(), '5.2.12', '>=')) {
                            echo '<i>max_file_uploads</i>: ' . @ini_get('max_file_uploads') . '<br />';
                        }
                        ?>
                        <i>post_max_size</i>: <?php echo @ini_get('post_max_size'); ?><br />
                    </div>
                </div>
                <?php endif; ?>
                <div id="attachment_restrictions" style="display:<?php echo $layer_onload; ?>;">
                    <div class="form-group short">
                        <div class="input-info">
                            <label for="a1">
                                <span><?php echo $hesklang['attach_num']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#38','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['attach_num']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" name="s_max_number" maxlength="2" id="a1" value="<?php echo $hesk_settings['attachments']['max_number']; ?>" <?php echo $onload_status; ?>>
                    </div>
                    <?php
                    $suffixes = array(
                        'B'  => $hesklang['B'] . ' (' . $hesklang['bytes'] . ')',
                        'kB' => $hesklang['kB'] . ' (' . $hesklang['kilobytes'] . ')',
                        'MB' => $hesklang['MB'] . ' (' . $hesklang['megabytes'] . ')',
                        'GB' => $hesklang['GB'] . ' (' . $hesklang['gigabytes'] . ')',
                    );
                    $tmp = hesk_formatBytes($hesk_settings['attachments']['max_size'], 0);
                    list($size, $unit) = explode(' ', $tmp);
                    ?>
                    <div class="form-group short flex-row">
                        <div class="input-info">
                            <label for="a2">
                                <span><?php echo $hesklang['attach_size']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#39','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['attach_size']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" name="s_max_size" maxlength="6" id="a2" value="<?php echo $size; ?>" <?php echo $onload_status; ?>>
                        <div class="dropdown-select center out-close">
                            <select name="s_max_unit" id="a4" <?php echo $onload_status; ?>>
                                <?php
                                foreach ($suffixes as $k => $v) {
                                    if ($k == $unit) {
                                        echo '<option value="'.$k.'" selected>'.$v.'</option>';
                                    } else {
                                        echo '<option value="'.$k.'">'.$v.'</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-info">
                            <label for="a3">
                                <span><?php echo $hesklang['attach_type']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#40','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['attach_type']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <input type="text" class="form-control" name="s_allowed_types" maxlength="255" id="a3" value="<?php echo implode(',',$hesk_settings['attachments']['allowed_types']); ?>" <?php echo $onload_status; ?>>
                    </div>
                    <!-- Section For Email Attachment Options -->
                    <div class="radio-group">
                        <div class="input-info">
                            <label>
                                <span><?php echo $hesklang['file_attachment_options']; ?></span>
                            </label>
                            <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>helpdesk.html#41','400','500'); return false;"
                               aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['file_attachment_options']; ?>">
                                <div class="tooltype right">
                                    <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                    </svg>
                                </div>
                            </a>
                        </div>
                        <?php
                            /*Email Attachment settings*/
                            $link_to_attachments_in_emails = (isset($hesk_settings['attachments']["attachment_in_email_type"]) && $hesk_settings['attachments']["attachment_in_email_type"] == "0")?"checked":"";
                            $add_attachments_directly_to_emails = (isset($hesk_settings['attachments']["attachment_in_email_type"]) && $hesk_settings['attachments']["attachment_in_email_type"] == "1")?"checked":"";
                            $directly_attach_file_smaller_than = (isset($hesk_settings['attachments']["direct_attachment_in_email"]) && $hesk_settings['attachments']["direct_attachment_in_email"] == "0")?"checked":"";
                            $directly_attach_file_of_any_size = (isset($hesk_settings['attachments']["direct_attachment_in_email"]) && $hesk_settings['attachments']["direct_attachment_in_email"] == "1")?"checked":"";
                            $directly_attach_only_first_x_attachments = (isset($hesk_settings['attachments']["direct_attachment_in_email_no_of_files"]) && $hesk_settings['attachments']["direct_attachment_in_email_no_of_files"] == "2")?"checked":"";
                            $directly_attach_all_attachments = (isset($hesk_settings['attachments']["direct_attachment_in_email_no_of_files"]) && $hesk_settings['attachments']["direct_attachment_in_email_no_of_files"] == "3")?"checked":"";;
                            if(isset($hesk_settings['attachments']["attachment_in_email_type"]) && $hesk_settings['attachments']["attachment_in_email_type"] == "0"){
                                $directly_attach_file_smaller_than = "checked";
                            }
                            $dd_attach_style = "display:none";
                            if(isset($hesk_settings['attachments']["attachment_in_email_type"]) && $hesk_settings['attachments']["attachment_in_email_type"] == "1"){
                                $dd_attach_style = "display:flex";
                                $file_size = 1;
                                $file_max_unit = 'kB';
                                if(isset($hesk_settings['attachments']['file_max_size']) && $hesk_settings['attachments']['file_max_size'] > 0){
                                    $tmp = hesk_formatBytes($hesk_settings['attachments']['file_max_size'], 0);
                                    list($file_size, $file_max_unit) = explode(' ', $tmp);
                                }
                                $first_x_attachments = 2;
                                if(isset($hesk_settings['attachments']['first_x_attachments']) && $hesk_settings['attachments']['first_x_attachments'] > 0){
                                    $first_x_attachments = $hesk_settings['attachments']['first_x_attachments'];
                                }

                            }else{
                                $file_size = 2;
                                $file_max_unit = 'kB';
                                $first_x_attachments = 2;
                            }
                            /*Email Attachment settings*/
                        ?>
                        <div class="radio-list">
                            <div class="radio-custom">
                                <input type="radio" id="attachment_in_email_type0" name="attachment_in_email_type" value="0" <?php echo $link_to_attachments_in_emails;?>>
                                <label for="attachment_in_email_type0"><?php echo $hesklang['link_to_attachments_in_emails']; ?></label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="attachment_in_email_type1" name="attachment_in_email_type" value="1" <?php echo $add_attachments_directly_to_emails;?>>
                                <label for="attachment_in_email_type1"><?php echo $hesklang['add_attachments_directly_to_emails']; ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="radio-group direct_attachment_section" style="<?php echo $dd_attach_style;?>">
                        <span class="row-spacer" aria-hidden="true"></span>
                        <div class="radio-list">
                            <div role="alert" class="notification orange">
                                <b><?php echo $hesklang['warn']; ?>:</b> <?php echo $hesklang['notice_about_directly_attach_files']; ?>        
                            </div>
                            <h5 class ="lbl-attach"><span><?php echo $hesklang['directly_attach_size'].':'; ?></span></h5>
                            <div class="radio-custom">
                                <input type="radio" id="direct_attachment_in_email0" name="direct_attachment_in_email" value="0" <?php echo $directly_attach_file_smaller_than;?>>
                                <label for="direct_attachment_in_email0"><?php echo $hesklang['directly_attach_file_smaller_than']; ?></label>
                                <input type="text" class="form-control form-cs" name="file_max_size" maxlength="6" id="file_max_size"
                                       value="<?php echo $file_size; ?>" <?php echo $onload_status; ?> aria-label="<?php echo $hesklang['directly_attach_no_of_files']; ?>">
                                <div class="dropdown-select center out-close">
                                    <select name="file_max_unit" id="file_max_unit" <?php echo $onload_status; ?>>
                                        <?php
                                        foreach ($suffixes as $k => $v) {
                                            if ($k == $file_max_unit) {
                                                echo '<option value="'.$k.'" selected>'.$v.'</option>';
                                            } else {
                                                echo '<option value="'.$k.'">'.$v.'</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="direct_attachment_in_email1" name="direct_attachment_in_email" value="1" <?php echo $directly_attach_file_of_any_size;?>>
                                <label for="direct_attachment_in_email1"><?php echo $hesklang['directly_attach_file_of_any_size']; ?></label>
                            </div>
                            <h5 class ="lbl-attach"><span><?php echo $hesklang['directly_attach_no_of_files'].':'; ?></span></h5>
                            <div class="radio-custom">
                                <input type="radio" id="direct_attachment_in_email2" name="direct_attachment_in_email_no_of_files" value="2" <?php echo $directly_attach_only_first_x_attachments;?>>
                                <label for="direct_attachment_in_email2"><?php echo $hesklang['directly_attach_only_first_x_attachments']; ?></label>
                                <input type="text" class="form-control form-cs" name="first_x_attachments" maxlength="" id="first_x_attachments"
                                       value="<?php echo $first_x_attachments; ?>" <?php echo $onload_status; ?> aria-label="<?php echo hesk_htmlspecialchars($hesklang['directly_attach_no_of_files']); ?>">
                                <span class="ml-12"><?php echo strtolower($hesklang['attachments']); ?> </span>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="direct_attachment_in_email3" name="direct_attachment_in_email_no_of_files" value="3" <?php echo $directly_attach_all_attachments;?>>
                                <label for="direct_attachment_in_email3"><?php echo $hesklang['directly_attach_all_attachments']; ?></label>
                            </div>
                        </div>
                    </div>
                    <!-- Section For Email Attachment Options -->
                </div>
            </section>
            <div class="settings__form_submit">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <input type="hidden" name="section" value="HELP_DESK">
                <button style="display: inline-flex" type="submit" id="submitbutton" class="btn btn-full" ripple="ripple"
                    <?php echo $enable_save_settings ? '' : 'disabled'; ?>>
                    <?php echo $hesklang['save_changes']; ?>
                </button>

                <?php if (!$enable_save_settings): ?>
                    <p>&nbsp;</p>
                    <div role="alert" class="notification red">
                        <?php echo $hesklang['e_save_settings']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
