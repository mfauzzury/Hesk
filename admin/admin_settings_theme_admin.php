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

// Note, currently not needed, as we WANT admin colors to override in real time,
// but keeping here so code is similar ot customer L&F settings
define('CLIENT_CSS_VAR_PREFIX', ''); // This will overwrite all loaded customer theme variables, so they don't conflict with admin vars

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
    hesk_hook_settings_viewed('THEME_ADMIN', $failure_reason);
});

if (!isset($_SESSION['suppress_settings_view_hook'])) {
    hesk_hook_settings_viewed('THEME_ADMIN', true);
} else {
    unset($_SESSION['suppress_settings_view_hook']);
}

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Test languages function
if (isset($_GET['test_themes'])) {
    hesk_testTheme(0);
}
$help_folder = '../language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/help_files/';

$enable_save_settings   = 0;
$enable_use_attachments = 0;

// prefix to be used when referencing lang files and hesk settings
$php_css_var_prefix = 'theme_var';
require_once(HESK_PATH . 'inc/theme_variables_admin.inc.php');
global $admin_theme_color_settings_groups;

define('EXTRA_PAGE_CLASSES','page-admin-settings-theme');

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
?>
<div class="main__content settings admin_settings_theme">

    <?php require_once(HESK_PATH . 'inc/admin_settings_status.inc.php'); ?>

    <script language="javascript" type="text/javascript"><!--
        function hesk_checkFields() {
            var d = document.form1;

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

        function hesk_testTheme()
        {
            window.open('admin_settings_theme_admin.php?test_themes=1',"Hesk_window","height=400,width=500,menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
            return false;
        }
        //-->
    </script>
    <form method="post" action="admin_settings_save.php" name="form1" onsubmit="return hesk_checkFields()" aria-label="<?php echo $hesklang['lafa']; ?>">
        <div class="settings__form form" data-expanded="false">
            <section class="settings__form_block">
                <h3><?php echo $hesklang['lafa']; ?></h3>
                <div class="form-group flex-row force-flex">
                    <div class="input-info">
                        <label>
                            <span><?php echo $hesklang['admin_theme']; ?></span>
                        </label>
                        <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>theme.html#1','400','500'); return false;"
                           aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['admin_theme']; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </div>
                    <div class="dropdown-select center out-close">
                        <select name="s_admin_theme" onchange="hesk_adminThemeChanged(this.value)">
                            <?php echo hesk_displayAdminThemes(1); ?>
                        </select>
                    </div>
                </div>
                <div role="alert" class="theme-changed-note notification blue added-left-offset d_hide">
                    <b><?php echo $hesklang['note']; ?>:</b> <?php echo $hesklang['theme_changed_note']; ?>
                </div>
            </section>
            <section class="settings__form_block theme-overrides empty-section">
                <h3><?php echo $hesklang['theme_overrides']; ?>
                    <a href="#" onclick="hesk_window('<?php echo $help_folder; ?>theme.html#2','400','500'); return false;"
                       aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['theme_overrides']; ?>">
                        <div class="tooltype right">
                            <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                            </svg>
                        </div>
                    </a>
                </h3>
                <div role="alert" class="notification blue added-left-offset">
                    <?php echo $hesklang['theme_colors_note']; ?>
                </div>
                <button type="button" class="btn btn--blue-border reset-color" style="margin-left: 20px" ripple="ripple"
                        onclick="return hesk_resetAllThemeOverrides()">
                    <?php echo $hesklang['reset_all_overrides']; ?>
                </button>
            </section>
            <?php foreach ($admin_theme_color_settings_groups as $group_key => $group_variables): ?>
                <section class="settings__form_block theme-overrides theme-group-<?php echo $group_key; ?>">
                    <h3><?php echo $hesklang['theme_group_' . $group_key]; ?><a href="#" onclick="hesk_window('<?php echo $help_folder; ?>theme.html#<?php echo $group_key;?>','400','500'); return false;"
                            aria-label="<?php echo $hesklang['aria_help_prefix'] . $hesklang['theme_group_' . $group_key]; ?>">
                            <div class="tooltype right">
                                <svg class="icon icon-info" aria-hidden="true" focusable="false">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                                </svg>
                            </div>
                        </a>
                    </h3>
                    <?php
                    foreach ($group_variables as $css_variable) {
                        $color_setting = get_admin_theme_color_setting($css_variable);
                        if (!$color_setting) {
                            continue;
                        }

                        $hesk_sett_value = isset($hesk_settings['admin_theme_overrides'][$css_variable]) ? $hesk_settings['admin_theme_overrides'][$css_variable] : '';
                        ?>
                        <div class="form-group row flex-row color">
                            <label for="<?php echo $php_css_var_prefix . $css_variable; ?>">
                                <span><?php echo ($color_setting['derivative'] ? '<span class="left-asterix"></span>' : ''); ?><?php echo $hesklang[$php_css_var_prefix . $css_variable]; ?></span>
                            </label>
                            <?php $color = hesk_validate_color_hex(isset($hesk_sett_value) ? $hesk_sett_value : ''); ?>

                            <!-- Besides just the onFineChange, we need the oninput here, if we want keystrokes/typing to work with preview properly always too! -->
                            <input type="text"
                                   class="form-control jscolor {hash:true, uppercase:false, onFineChange:'hesk_preview_color(this, \'<?php echo $css_variable; ?>\', \'<?php echo $php_css_var_prefix ?>\')'}"
                                   id="<?php echo $php_css_var_prefix . $css_variable; ?>"
                                   name="<?php echo $php_css_var_prefix . $css_variable; ?>"
                                   oninput="hesk_preview_color(this, '<?php echo $css_variable; ?>', '<?php echo $php_css_var_prefix ?>')"
                            >
                            <span id="<?php echo $php_css_var_prefix . $css_variable; ?>_preview" style="color:<?php echo $color; ?>"><?php echo $hesklang['clr_view']; ?></span>
                            <button type="button" class="btn btn--blue-border reset-color" style="margin-left: 20px" ripple="ripple"
                                    onclick="return hesk_resetThemeColor('<?php echo $css_variable; ?>')">
                                <?php echo $hesklang['reset']; ?>
                            </button>
                        </div>
                    <?php } ?>
                    <!-- We use a hidden input field, to which we only store any overwritten values, which are then posted on save. -->

                    <!-- After main_brand, we also print a view/hide advanced settings-->
                    <?php if ($group_key === 'main_brand') { ?>
                        <button type="button" class="btn btn--blue-border toggle-advanced-settings" style="margin-left: 20px" ripple="ripple"
                                onclick="return hesk_toggleAdvancedSettings(this)" data-expanded="false">
                        <span data-type="advanced">
                            <?php echo $hesklang['view_advanced']; ?>
                            <svg class="icon icon-chevron-down">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                            </svg>
                        </span>
                            <span data-type="basic">
                            <?php echo $hesklang['view_basic']; ?>
                            <svg class="icon icon-chevron-up">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                            </svg>
                        </span>
                        </button>
                    <?php } ?>
                </section>
            <?php endforeach; ?>

            <div class="settings__form_submit">
                <input type="hidden" name="s_admin_theme_overrides" id="s_admin_theme_overrides" value="">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <input type="hidden" name="section" value="THEME_ADMIN">
                <button id="submitbutton" style="display: inline-flex" type="submit" class="btn btn-full" ripple="ripple"
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

<script type="text/javascript" src="<?php echo HESK_PATH; ?>inc/jscolor/jscolor.min.js"></script>
<script type="text/javascript">

    // Storing any used DOM refs to avoid re-querying the DOM for the same elements.
    // For performance/lag/stutter considerations, which might otherwise happens with 100s of refs being updated on color changes
    let cachedDomRefs = {};
    const phpCssVarPrefix = '<?php echo $php_css_var_prefix; ?>';
    const clientCssVarPrefix = '<?php echo CLIENT_CSS_VAR_PREFIX; ?>';
    function getCachedDomRef(selector, useJquery = false, queryAll = false) {
        // just adding option for jQuery and regular, depending on implementation
        let cacheRef = (useJquery? "jq_" : "") + selector; // need to store them separately, as they might be used in both ways
        if (/*true ||*/ !cachedDomRefs[cacheRef]) {
            let domRef;
            if (useJquery) {
                domRef = $(selector);
            } else {
                if (queryAll) {
                    domRef = document.querySelectorAll(selector);
                } else {
                    domRef = document.querySelector(selector);
                }
            }
            if (domRef && (!useJquery || domRef.length > 0)) {
                // only store cache if the domElement exists (if jQuery selecting, also check for length > 0!)
                cachedDomRefs[cacheRef] = domRef;
            } else {
                // if not caching, still return the result, so any chaining in case of jQuery will still work properly
                return domRef;
            }
        }
        return cachedDomRefs[cacheRef];
    }

    function clearCachedDomRef(selector, useJquery = false, queryAll = false) {
        let cacheRef = (useJquery? "jq_" : "") + selector;
        if (cachedDomRefs[cacheRef]) {
            delete cachedDomRefs[cacheRef];
        }
    }

    function hesk_adminThemeChanged(themeName) {
        const templatePath = "<?php echo HESK_PATH; ?>";

        let themeLink = getCachedDomRef("#loaded_theme");
        if (!themeLink) {
            // themeLink not existing yet (i.e. happens if no specific theme is loaded)

            const defaultThemeLink = getCachedDomRef("#default_theme");
            if (!defaultThemeLink) {
                console.error("Default theme element not found at all, can't really load themes anyway!");
                return;
            }

            // If themeLink does not already exist, then create a new link element to load the loaded theme into it, after the defaultThemeLink element
            themeLink = document.createElement("link");
            themeLink.rel = "stylesheet";
            themeLink.type = "text/css";
            themeLink.id = "loaded_theme";

            // append newThemeLink after defaultThemeLink
            defaultThemeLink.insertAdjacentElement('afterend', themeLink);
        }

        // Remove all classes from <html> element that start with "theme_"
        removeClassesByPrefix(document.documentElement, 'theme_');

        if (themeName === '') {
            // If themeName is empty, then remove the loaded theme link
            themeLink.remove();

            // Need to clear cached ref, as otherwise on next theme select it won't be added properly
            clearCachedDomRef("#loaded_theme");

            // Finally, we have to recalculate all the input colors
            recalculateInputColors();
            attemptToggleChangedThemeNote();
            return;
        }

        // Add the new class name
        document.documentElement.classList.add('theme_' + themeName);

        // Construct the new href (adjust path if needed)
        const newHref = templatePath + '/css/themes/' + themeName + '.css';

        // Set the new href to load the new theme
        themeLink.setAttribute("href", newHref);

        // wait for new CSS to load completely before we update the color preview
        themeLink.onload = function() {
            // Finally, we have to recalculate all the input colors
            recalculateInputColors();
            attemptToggleChangedThemeNote();
        };
    }

    function attemptToggleChangedThemeNote() {
        let $changedThemeNote = getCachedDomRef('.theme-changed-note', true);

        let showChangedThemeNote = Object.keys(themeOverrides).length > 0;
        $changedThemeNote.toggleClass('d_hide', !showChangedThemeNote);
    }

    function hesk_toggleAdvancedSettings(element) {
        element.setAttribute('data-expanded', element.dataset.expanded === 'false'? 'true' : 'false');

        // Get closest form, and add data-expanded attribute to the form
        const form = element.closest('.settings__form');
        form.setAttribute('data-expanded', element.dataset.expanded);
    }

    // Initialize the hidden field with an empty object
    // On any theme color changes, we will store them here, as this way we can identify if a setting has been overriden.
    // Note: we can directly use the variables HEX values, as they might just be dynamically calculated from other colors.
    // So we have to listen/check for these changes specifically.
    let themeOverrides = {};

    function updateThemeOverrides() {
        getCachedDomRef('#s_admin_theme_overrides').value = JSON.stringify(themeOverrides);
    }
    function setInputOverrideClass(cssVariable, isOverridden = false) {
        const $input = getCachedDomRef(`input[name="<?php echo $php_css_var_prefix ?>${cssVariable}"]`, true);
        $input.closest('.form-group').toggleClass('is-overridden', isOverridden);
    }
    function setColorOverride(cssVariable, color) {
        themeOverrides[cssVariable] = color;
        updateThemeOverrides();
        setInputOverrideClass(cssVariable, true);
    }
    function removeColorOverride(cssVariable) {
        delete themeOverrides[cssVariable];

        document.documentElement.style.removeProperty(clientCssVarPrefix + cssVariable);
        updateThemeOverrides();
        setInputOverrideClass(cssVariable, false);

        // If there was a note shown for overrides, we can also attempt to hide it now.
        attemptToggleChangedThemeNote();
    }
    function handleColorChange(color, cssVariable) {
        if (color !== '') {
            setColorOverride(cssVariable, color);
        } else {
            removeColorOverride(cssVariable);
        }
    }

    function hesk_resetThemeColor(cssVariable) {
        const input = getCachedDomRef(`input[name="<?php echo $php_css_var_prefix ?>${cssVariable}"]`);
        input.jscolor.fromString('');
        removeColorOverride(cssVariable);
        // While we could just recalculate for the specific variable here, it's best to just do all,
        // as if this color was part of calculations ofr other colors, others still need ot be recalculated as well
        recalculateInputColors();
        return false;
    }

    function hesk_resetAllThemeOverrides() {
        for (let cssVariable in themeOverrides) {
            hesk_resetThemeColor(cssVariable);
        }
    }

    // Updated preview color fro mGPT: It's similar, just with some extras
    function hesk_preview_color(jscolorOrString, cssVariable, idSelectorPrefix = 'theme_var', previewElementSuffix = '_preview') {
        //console.log("heskpreview.color , cssVariable: " + cssVariable + " , jsColoroRsintr: ", jscolorOrString)
        let input, color;
        if (typeof jscolorOrString === 'object' && jscolorOrString.valueElement) {
            // It's a jscolor object
            input = jscolorOrString.valueElement;
            color = jscolorOrString.toHEXString();
        } else if (jscolorOrString instanceof HTMLInputElement) {
            // normal input element, e.g. from keyup
            input = jscolorOrString;

            if (input.jscolor) {
                color = input.jscolor.toHEXString();
            } else {
                color = input.value.startsWith('#')
                    ? input.value
                    : "#" + input.value;
            }
        } else {
            // It's a string
            input = getCachedDomRef(`input[name="${idSelectorPrefix}${cssVariable}"]`);
            color = jscolorOrString.startsWith('#') ? jscolorOrString : "#" + jscolorOrString;
        }
        color = formatColorForCss(color);

        // Check if the input is currently focused (user is typing)
        let colorIsComplete = true;
        if (document.activeElement === input) {
            // User is typing - only update if the value is a valid complete hex color
            let inputValue = input.value.trim();
            if (!/^#?[0-9A-Fa-f]{6}$/.test(inputValue)) {
                // Incomplete or invalid color - mark as incomplete, so that we don't force/change the text from incomplete to complete/normalized
                // In other words - normalized is only to show immediate preview, the same as jscolor does on color picker
                colorIsComplete = false;
            }
            inputValue = normalizeHexColor(inputValue);
            color = inputValue;
        }

        // Track whether this is a real color change (or just initial preview load, or input event without changing the color)
        let colorActuallyChanged = true;
        const propertyName = clientCssVarPrefix + cssVariable;
        const rootStyle = document.documentElement.style;
        const previousValue = rootStyle.getPropertyValue(propertyName);
        const previousPriority = rootStyle.getPropertyPriority(propertyName);
        rootStyle.removeProperty(propertyName);
        const defaultHex = cssColorToHex(resolveCssVariableToColor(cssVariable));
        if (previousValue) {
            rootStyle.setProperty(propertyName, previousValue, previousPriority);
        }
        if (defaultHex && color) {
            colorActuallyChanged = '#' + defaultHex.toLowerCase() !== color.toLowerCase();
        }

        const previewElement = getCachedDomRef('#' + idSelectorPrefix + cssVariable + previewElementSuffix);
        if (previewElement) {
            previewElement.style.color = color;
        }

        // set the :root value of the css variable
        document.documentElement.style.setProperty(clientCssVarPrefix + cssVariable, color);

        // Only run expensive/theme-change logic if actual value changed
        if (colorIsComplete && colorActuallyChanged) {
            handleColorChange(color, cssVariable);
            recalculateInputColors();
        }

        if (!colorActuallyChanged) {
            // If same as default, then unset it completely
            hesk_resetThemeColor(cssVariable);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // The jscolor fields are not automatically updated on render (as PHP does NOT have css color calculations yet), so on page load, we have to check for color values and update the jscolor fields with the computed values
        recalculateInputColors(true);

        <?php
        foreach($hesk_settings['admin_theme_overrides'] as $css_variable => $color) {
            ?>
            var input = getCachedDomRef(`input[name="<?php echo $php_css_var_prefix . $css_variable?>"]`)
            hesk_preview_color('<?php echo $color; ?>', '<?php echo $css_variable; ?>');

        <?php }
        ?>

        function handleColorInputChangeEvent(e) {
            let id = e.target.id;
            const cssVariable = id.slice(phpCssVarPrefix.length);
            hesk_preview_color(
                e.target,
                cssVariable,
                phpCssVarPrefix
            );
        };

        // If we want keyup/typing to immediatel update preview colors, we also need this listener,
        // as colorjs won't handle it mid-changes otherwise
        document.addEventListener('keyup', function (e) {
            if (e.target.matches('.jscolor')) {
                handleColorInputChangeEvent(e);
            }
        });

        // Also handle input offocus, so that preview acccurately refreshes on of focous,
        // and uses jscolor's hexshort logic I.e. #1221 -> #112222 etc.
        document.addEventListener('focusout', function(e) {
            if (!e.target.matches('.form-control.jscolor')) {
                return;
            }
            handleColorInputChangeEvent(e);
        });
    });

    function normalizeHexColor(value) {
        value = value.trim();

        if (value[0] !== '#') {
            value = '#' + value;
        }

        // #ff0 -> #ffff00
        if (/^#[0-9a-f]{3}$/i.test(value)) {
            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        }

        if (/^#[0-9a-f]{6}$/i.test(value)) {
            return value.toLowerCase();
        }

        return null;
    }

    function formatColorForCss(color) {
        color = String(color).trim();

        // Already valid CSS color formats/functions
        if (
            color.startsWith('#') ||
            color.startsWith('rgb(') ||
            color.startsWith('rgba(') ||
            color.startsWith('hsl(') ||
            color.startsWith('hsla(') ||
            color.startsWith('color(') ||
            color.startsWith('color-mix(') ||
            color.startsWith('var(')
        ) {
            return color;
        }

        // Assume plain hex without #
        return '#' + color;
    }

    function resolveCssVariableToColor(cssVariable) {
        var probe = document.createElement('div');
        probe.style.color = `var(${clientCssVarPrefix + cssVariable})`;
        document.body.appendChild(probe);

        var resolvedColor = getComputedStyle(probe).color;
        probe.remove();

        return resolvedColor;
    }

    function cssColorToHex(color) {
        color = String(color).trim();

        // #fff / #ffffff
        var hexMatch = color.match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (hexMatch) {
            var hex = hexMatch[1];

            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }

            return hex.toLowerCase();
        }

        // rgb(183, 199, 227) / rgba(...)
        var rgbMatch = color.match(/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)/i);
        if (rgbMatch) {
            return [rgbMatch[1], rgbMatch[2], rgbMatch[3]]
                .map(function(v) {
                    return parseInt(v, 10).toString(16).padStart(2, '0');
                })
                .join('');
        }

        // color(srgb 0.719608 0.78098 0.891373)
        var srgbMatch = color.match(/color\(srgb\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)/i);
        if (srgbMatch) {
            return [srgbMatch[1], srgbMatch[2], srgbMatch[3]]
                .map(function(v) {
                    return Math.round(parseFloat(v) * 255).toString(16).padStart(2, '0');
                })
                .join('');
        }

        return null;
    }

    function recalculateInputColors(isInitialLoad = false) {
        var colorInputs = getCachedDomRef('.form-control.jscolor', false, true);

        colorInputs.forEach(function(input) {
            var cssVariable = input.name.replace('<?php echo $php_css_var_prefix; ?>', '');

            // Raw CSS variable value, e.g. "#ffffff" or "color-mix(...)"
            var rawColor = getComputedStyle(document.documentElement)
                .getPropertyValue(clientCssVarPrefix + cssVariable)
                .trim();

            if (!rawColor) {
                return;
            }

            var previewSpan = getCachedDomRef('#' + input.name + '_preview');

            // Preview text can use CSS-native values like color-mix(...)
            if (previewSpan) {
                previewSpan.style.color = formatColorForCss(rawColor);
            }

            // jscolor needs a plain hex value
            var resolvedColor = resolveCssVariableToColor(cssVariable);
            var resolvedHex = cssColorToHex(resolvedColor);

            if (!resolvedHex) {
                console.warn('Could not resolve color for:', cssVariable, rawColor, resolvedColor);
                return;
            }

            if (input.jscolor) {
                input.jscolor.fromString(resolvedHex);
            }

            input.value = '#' + resolvedHex;

            // CHANGE: on initial load, store the calculated/default value as baseline, so we can detect changes
            if (isInitialLoad) {
                input.dataset.defaultColor = formatColorForCss('#' + resolvedHex);
            }
        });
    }

    function removeClassesByPrefix(element, prefix) {
        [...element.classList]
            .filter(className => className.startsWith(prefix))
            .forEach(className => element.classList.remove(className));
    }
</script>
<script src="<?php echo HESK_PATH; ?>js/jquery-ui.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


function hesk_displayAdminThemes() {
    global $hesk_settings, $hesklang;

    $path = HESK_PATH . '/css/themes/';

    $html = '';
    $html .= '<option value="" ' . (!isset($hesk_settings['admin_theme']) || $hesk_settings['admin_theme'] === ''  ? 'selected' : '') . '>' . $hesklang['theme_name_default'] . '</option>';

    $themeCssFiles = glob($path.'*.css');
    if (is_array($themeCssFiles)) {
        foreach ($themeCssFiles as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME); // Get filename without extension
            $formattedText = ucwords(str_replace('_', ' ', $filename)); // Replace underscores & capitalize words
            if (!empty($hesklang['theme_name_' . $filename])) {
                $formattedText = $hesklang['theme_name_' . $filename];
            }
            $html .= '<option value="' . $filename . '" ' . ($hesk_settings['admin_theme'] === $filename ? 'selected' : '') . '>' . $formattedText . '</option>';
        }
    }

    return $html;
}


function hesk_validate_color_hex($hex, $def = '#000000')
{
    $hex = strtolower($hex);
    return preg_match('/^\#[a-f0-9]{6}$/', $hex) ? $hex : $def;
} // END hesk_validate_color_hex()


function hesk_get_text_color($bg_color)
{
    // Get RGB values
    list($r, $g, $b) = sscanf($bg_color, "#%02x%02x%02x");

    // Is Black a good text color?
    if (hesk_color_diff($r, $g, $b, 0, 0, 0) >= 500)
    {
        return '#000000';
    }

    // Use white instead
    return '#ffffff';
} // END hesk_get_text_color()


function hesk_color_diff($R1,$G1,$B1,$R2,$G2,$B2)
{
    return max($R1,$R2) - min($R1,$R2) +
        max($G1,$G2) - min($G1,$G2) +
        max($B1,$B2) - min($B1,$B2);
} // END hesk_color_diff()
