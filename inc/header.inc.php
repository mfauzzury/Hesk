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

// We'll use this later
$onload='';

define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");

$extraHtmlClasses = '';
if (defined('EXTRA_PAGE_CLASSES')) {
    // add extra space, to make sure it doesn't concatenate with any existing classes by mistake
    $extraHtmlClasses .= ' ' . EXTRA_PAGE_CLASSES;
}
if(isset($hesk_settings['admin_theme']) && $hesk_settings['admin_theme'] !== '') {
    $extraHtmlClasses .= ' theme_'. $hesk_settings['admin_theme'];
}
// We also need to add customer theme class on the L&F page for it
if (defined('LOAD_CUSTOMER_THEME_VARS') &&
        (isset($hesk_settings['customer_theme']) && $hesk_settings['customer_theme'] !== '')
) {
    $extraHtmlClasses .= ' customer_theme_'. $hesk_settings['customer_theme'];
}


$assetVersion = $hesk_settings['hesk_version'] . '.' . max(@filemtime(HESK_PATH.'css/themes/black_white.css'), @filemtime(HESK_PATH.'css/core_overrides.css')); // cache-bust theme edits
/*
// Uncomment this if you want to force CSS refreshes on every load in debug mode 
if ($hesk_settings['debug_mode']) {
    $assetVersion .='_' . time();
}
*/
?>
<!DOCTYPE html>
<html lang="<?php echo $hesk_settings['languages'][$hesk_settings['language']]['folder'] ?>" class="<?php echo $extraHtmlClasses; ?>">
<head>
	<title><?php echo (isset($hesk_settings['tmp_title']) ? $hesk_settings['tmp_title'] : $hesk_settings['hesk_title']); ?></title>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0">
    <?php include(HESK_PATH . 'inc/favicon.inc.php'); ?>
    <meta name="format-detection" content="telephone=no">


    <!-- Note: The default vars are NOT bundled, but have to be loaded first to init vars, and then get overridden by theme vars, before further color calculations are done. -->
    <link id="default_theme" rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_00_default_theme_vars.css?<?php echo $assetVersion; ?>" />
    <?php
    // Check if use has any special CSS themes selected, and if they exist, load their CSS.
    // NOTE! Theme vars overrides have to be loaded in first, as they are then used as part of color calculations in 0_01_variables.css file!
    $attempt_load_theme = isset($hesk_settings['admin_theme']) && $hesk_settings['admin_theme'] !== '';
    if ($attempt_load_theme) {
        $loaded_theme = $hesk_settings['admin_theme'];

        $theme_path = HESK_PATH.'css/themes/'.$loaded_theme.'.css';
        if (file_exists($theme_path)) { ?>
            <link id="loaded_theme" rel="stylesheet" media="all" href="<?php echo $theme_path; ?>?<?php echo $assetVersion; ?>" />
            <?php
        }
    }
    ?>

    <!-- Now load any admin/setting overrides of theme variables values
    NOTE: these should be loaded even if it's production/not debug mode, on top of app.css!!! -->
    <?php if (!empty($hesk_settings['admin_theme_overrides']) && is_array($hesk_settings['admin_theme_overrides']))
    {
        // prepare CSS variables from any setting overrides
        // Note, store it in html, and not :root, as then it's easier to clear/Reference with Vanilla js properly, and so :root default stay the same
        // TODO note warning - actually seems like html is only fine on admin page, but elsewhere should be using :root???
        // TODO - temp solution- just switch it up for now as necessary. Maybe I can find a better solution later.
        if (defined('CLIENT_CSS_VAR_PREFIX') && CLIENT_CSS_VAR_PREFIX === '') {
            // Note, ONLY load this way on Admin L&F not on Customer L&F
            $theme_overrides_css = "html {\n";
        } else {
            $theme_overrides_css = ":root {\n";
        }
        foreach ($hesk_settings['admin_theme_overrides'] as $var => $value) {
            $theme_overrides_css .= "{$var}: {$value};\n";
        }
        $theme_overrides_css .= "}\n";
        echo '<style type="text/css">' . $theme_overrides_css . '</style>';
    }
    ?>
    <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/theme_overrides.css?<?php echo $assetVersion; ?>" />

    <?php if ($hesk_settings['debug_mode']): ?>
        <!--
            IMPORTANT NOTE: For any potential future CSS file additions:
            Please make sure to add name prefixes with numbers in whatever order you want them to be loaded.
            This will ensure that when they are combined and minified, any order of CSS will be properly preserved.
        -->
        <!-- TODO Could potentially load these dynamically based on all that are in /core folder??? (Make sure to exclude loading 0_00_default_theme_vars here if so!)
        -->
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_01_variables.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_02_font_setup.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_03_common.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_04_layout.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_05_layout_components.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_06_header.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_07_footer.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_08_buttons.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_09_forms.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_10_input.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_11_icons.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_12_dropdowns.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_13_datepickers.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_14_modal.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_15_navigation.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_16_tooltips.css?<?php echo $assetVersion; ?>" />

        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_18_tickets.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_19_kb.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_20_pages.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_21_responsive.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_21_popups.css?<?php echo $assetVersion; ?>" />
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_22_misc.css?<?php echo $assetVersion; ?>" />
        <!--  It's deprecated, generally we want to uncomment this out to test if it breaks anything we assume is deprecated anyway. -->
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core/0_23_deprecated.css?<?php echo $assetVersion; ?>" />
    <?php else: ?>
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>dist/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $assetVersion; ?>" />
    <?php endif; ?>

    <!-- NOTE: these should be loaded even if it's production/not debug mode, on top of app.css!!! -->
    <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/core_overrides.css?<?php echo $assetVersion; ?>" />

    <?php
    // TODO @andraz review -> Do we need to load JS/CSS for attachments? Needs to go before our app_backup.css
    if (defined('ATTACHMENTS')) {
        ?>
        <link rel="stylesheet" href="<?php echo HESK_PATH; ?>css/dropzone.min.css?<?php echo $assetVersion; ?>" type="text/css" />
        <script src="<?php echo HESK_PATH; ?>js/dropzone.min.js?<?php echo $assetVersion; ?>"></script>
        <?php
    }
    ?>

    <script src="<?php echo HESK_PATH; ?>js/jquery-3.5.1.min.js"></script>

    <?php
    // TODO check do we need something similar for Admin also, or not really, as those should already be loaded in?

    // Do we need to load customer-side theme/variables? (i.e. needed for live changes of theme settings with properly calculated colors)
    if (defined('LOAD_CUSTOMER_THEME_VARS')) {

        $clientCssVarPrefix = defined('CLIENT_CSS_VAR_PREFIX') ? CLIENT_CSS_VAR_PREFIX : '--customer';

        hesk_output_css_with_optional_var_prefix(
            TEMPLATE_PATH . 'customer/css/core/0_00_default_theme_vars.css',
            TEMPLATE_PATH . 'customer/css/core/0_00_default_theme_vars.css',
            $assetVersion,
            $clientCssVarPrefix,
            'default_theme_customer'
        );

        $attempt_load_theme = isset($hesk_settings['customer_theme']) && $hesk_settings['customer_theme'] !== '';

        if ($attempt_load_theme) {
            $loaded_theme = $hesk_settings['customer_theme'];

            $theme_path = TEMPLATE_PATH . 'customer/css/themes/' . $loaded_theme . '.css';

            if (file_exists($theme_path)) {
                hesk_output_css_with_optional_var_prefix(
                    $theme_path,
                    $theme_path,
                    $assetVersion,
                    $clientCssVarPrefix,
                    'loaded_theme_customer'
                );
            }
        }

        hesk_output_css_with_optional_var_prefix(
            TEMPLATE_PATH . 'customer/css/core/0_01_variables.css',
            TEMPLATE_PATH . 'customer/css/core/0_01_variables.css',
            $assetVersion,
            $clientCssVarPrefix
        );
    }
    ?>

    <?php
    // Do we need to load CSV parsing?
    if (defined('CSV')) {
        ?>
        <script src="<?php echo HESK_PATH; ?>js/jquery.csv.min.js?<?php echo $assetVersion; ?>"></script>
        <?php
    }
    ?>
    <script src="<?php echo HESK_PATH; ?>js/selectize.min.js?<?php echo $assetVersion; ?>"></script>
    <script type="text/javascript" src="<?php echo HESK_PATH; ?>js/hesk_javascript<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $assetVersion; ?>"></script>

    <?php
	/* Tickets shouldn't be indexed by search engines */
	if (defined('HESK_NO_ROBOTS'))
	{
		?>
		<meta name="robots" content="noindex, nofollow" />
		<?php
	}

	/* If page requires WYSIWYG editor include TinyMCE Javascript */
	if (defined('WYSIWYG') && ($hesk_settings['staff_ticket_formatting'] == 2 || $hesk_settings['kb_wysiwyg'] || defined('HTML_EMAIL_TEMPLATE')))
	{
        require(HESK_PATH . 'inc/tiny_mce/tinymce.inc.php');
		?>
		<script type="text/javascript" src="<?php echo HESK_PATH; ?>inc/tiny_mce/8.10.2/tinymce.min.js"></script>
		<?php
	}

    /* If page styles <code> blocks */
    if (defined('STYLE_CODE'))
    {
        ?>
        <script type="text/javascript" src="<?php echo HESK_PATH; ?>js/prism.js?<?php echo $assetVersion; ?>"></script>
        <link rel="stylesheet" media="all" href="<?php echo HESK_PATH; ?>css/prism.css?<?php echo $assetVersion; ?>">
        <?php
    }

	/* If page requires timer load Javascript */
	if (defined('TIMER'))
	{
		?>
		<script type="text/javascript" src="<?php echo HESK_PATH; ?>inc/timer/hesk_timer.js"></script>
		<?php

        /* Need to load default time or a custom one? */
        if ( isset($_SESSION['time_worked']) )
        {
        	$t = hesk_getHHMMSS($_SESSION['time_worked']);
			$onload .= "load_timer('time_worked', " . $t[0] . ", " . $t[1] . ", " . $t[2] . ");";
            unset($t);
        }
        else
        {
        	$onload .= "load_timer('time_worked', 0, 0, 0);";
        }

		/* Autostart timer? */
		if ( ! empty($_SESSION['autostart']) )
		{
			$onload .= "ss();";
		}
	}

	// Use ReCaptcha
	if (defined('RECAPTCHA'))
	{
		echo '<script src="https://www.google.com/recaptcha/api.js?hl='.$hesklang['RECAPTCHA'].'" async defer></script>';
        echo '<script language="Javascript" type="text/javascript">
        function recaptcha_submitForm() {
            document.getElementById("form1").submit();
        }
        </script>';
	}

	// Use Cloudflare Turnstile
	if (defined('TURNSTILE'))
	{
		echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
	}

	// Use hCaptcha
	if (defined('HCAPTCHA'))
	{
		echo '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
	}

	// Auto reload
	if (defined('AUTO_RELOAD') && hesk_checkPermission('can_view_tickets',0))
	{
		?>
		<script type="text/javascript">
		var count = <?php echo empty($_SESSION['autoreload']) ? 30 : intval($_SESSION['autoreload']); ?>;
		var reloadcounter;
		var countstart = count;

		function heskReloadTimer()
		{
			count=count-1;
			if (count <= 0)
			{
				clearInterval(reloadcounter);
				window.location.reload();
				return;
			}

			document.getElementById("timer").innerHTML = "(" + count + ")";
		}

		function heskCheckReloading()
		{
			if (<?php if ($_SESSION['autoreload']) echo "getCookie('autorefresh') == null || "; ?>getCookie('autorefresh') == '1')
			{
				document.getElementById("reloadCB").checked=true;
				document.getElementById("timer").innerHTML = "(" + count + ")";
				reloadcounter = setInterval(heskReloadTimer, 1000);
			}
		}

		function toggleAutoRefresh(cb)
		{
			if (cb.checked)
			{
				setCookie('autorefresh', '1');
				document.getElementById("timer").innerHTML = "(" + count + ")";
				reloadcounter = setInterval(heskReloadTimer, 1000);
			}
			else
			{
				setCookie('autorefresh', '0');
				count = countstart;
				clearInterval(reloadcounter);
				document.getElementById("timer").innerHTML = "";
			}
		}

		</script>
		<?php
	}

    // Timeago
    if (defined('TIMEAGO'))
    {
        ?>
        <script type="text/javascript" src="<?php echo HESK_PATH; ?>js/timeago/jquery.timeago.js?<?php echo $assetVersion; ?>"></script>
        <?php
        // Load language file if not English
        if ($hesklang['TIMEAGO_LANG_FILE'] != 'jquery.timeago.en.js')
        {
            ?>
            <script type="text/javascript" src="<?php echo HESK_PATH; ?>js/timeago/locales/<?php echo $hesklang['TIMEAGO_LANG_FILE']; ?>?<?php echo $assetVersion; ?>"></script>
            <?php
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function() {
            $("time.timeago").timeago();
        });
        </script>
        <?php
    }

    // Back to top button
    if (defined('BACK2TOP'))
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function() {
            var offset = 800;
            var duration = 250;
            jQuery(window).scroll(function() {
                if (jQuery(this).scrollTop() > offset) {
                    jQuery('.back-to-top').fadeIn(duration);
                } else {
                    jQuery('.back-to-top').fadeOut(duration);
                }
            });

            jQuery('.back-to-top').click(function(event) {
                event.preventDefault();
                jQuery('html, body').animate({scrollTop: 0}, duration);
                return false;
            })
        });
        </script>
        <?php
    }
	?>

    <script type="text/javascript" src="<?php echo HESK_PATH; ?>js/zebra_tooltips.min.js?<?php echo $assetVersion; ?>"></script>
    <link rel="stylesheet" href="<?php echo HESK_PATH; ?>css/zebra_tooltips.css">
    <script type="text/javascript">
    $(document).ready(function() {
        // show tooltips for any element that has a class named "tooltip"
        // the content of the tooltip will be taken from the element's "title" attribute
        new $.Zebra_Tooltips($('.tooltip'), {animation_offset: 0, animation_speed: 100, hide_delay: 0, show_delay: 0, vertical_alignment: 'above', vertical_offset: 5,
            // Mark the Delete note tooltip so CSS can hide it when hover is unavailable.
            onBeforeShow: function(link, tooltip) {
                if (link.hasClass('delete-note')) tooltip.addClass('delete-note-tooltip');
            }
        });
    });
    </script>

    <?php if ($hesk_settings['admin_css']): ?>
    <link rel="stylesheet" href="<?php echo $hesk_settings['admin_css_url']; ?>">
    <?php endif; ?>

    <?php if ($hesk_settings['admin_js']): ?>
    <script type="text/javascript" src="<?php echo $hesk_settings['admin_js_url']; ?>"></script>
    <?php endif; ?>

</head>
<body onload="<?php echo $onload; unset($onload); ?>">
<a href="#maincontent" class="skiplink"><?php echo $hesklang['skip_to_main_content']; ?></a>

<div class="wrapper">
