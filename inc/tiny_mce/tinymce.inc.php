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


function hesk_tinymce_init($selector='#message', $onKeyUpFunction = '', $onKeyUpTimeout = 3000)
{
    global $hesklang, $hesk_settings;
    ?>
    <script>
        <?php if ($onKeyUpFunction) {
            $onKeyUpTimer = $onKeyUpFunction . '_Timer';
            echo "var {$onKeyUpTimer};";
        }
        ?>

        var heskEditorColors = getComputedStyle(document.documentElement);

        tinymce.init({
            selector: '<?php echo $selector; ?>',
            license_key: 'gpl',
            convert_urls: false,
            branding: false,
            promotion: false,
            browser_spellcheck: true,
            contextmenu: 'link useBrowserSpellcheck image table',
            setup: function (editor) {

                <?php if ($onKeyUpFunction): ?>
                editor.on('KeyUp', function (e) {
                    clearTimeout(<?php echo $onKeyUpTimer; ?>);
                    <?php echo $onKeyUpTimer; ?> = setTimeout(<?php echo $onKeyUpFunction; ?>, <?php echo $onKeyUpTimeout; ?>);
                }),
                editor.on('submit', function (e) {
                    clearTimeout(<?php echo $onKeyUpTimer; ?>);
                }),
                <?php endif; ?>

                editor.ui.registry.addMenuItem('useBrowserSpellcheck', {
                text: '<?php echo hesk_slashJS($hesklang['tmce1']); ?>',
                onAction: function () {
                  editor.notificationManager.open({
                    text: '<?php echo hesk_slashJS($hesklang['tmce2']); ?>',
                    type: 'info',
                    timeout: 5000,
                    closeButton: true
                  });
                }
              });

              editor.ui.registry.addContextMenu('useBrowserSpellcheck', {
                update: function (node) {
                  return editor.selection.isCollapsed() ? ['useBrowserSpellcheck'] : [];
                }
              });
            },
            toolbar: 'undo redo | styleselect fontselect fontsizeselect | bold italic underline | alignleft aligncenter alignright alignjustify | forecolor backcolor | bullist numlist outdent indent | link unlink anchor image codesample code',
            plugins: 'charmap code codesample image link lists table autolink anchor',
            height: 350,
            toolbar_mode: 'sliding',
            mobile: {
                toolbar_mode: 'scrolling',
                height: 300
            },

            <?php
            // Keep the 3.8 dark theme when adding the mention styles.
            $darkThemes = ['midnight_ocean'];
            $isDarkTheme = !empty($hesk_settings['admin_theme']) && in_array($hesk_settings['admin_theme'], $darkThemes, true);
            if ($isDarkTheme):
            ?>
            skin: 'oxide-dark',
            content_css: 'dark',
            <?php endif; ?>
            content_style: [
                ':root{' + ['--info-2', '--notification__clr', '--surface', '--font__pri-clr', '--link__pri-clr', '--gray-2', '--input-bg', '--input-clr'].map(function (name) {
                    return name + ':' + heskEditorColors.getPropertyValue(name).trim() + ';';
                }).join('') + '}',
                <?php if ($isDarkTheme): ?>
                'body{background:var(--input-bg);color:var(--input-clr);}',
                <?php else: ?>
                'body{background:var(--surface);color:var(--font__pri-clr);}',
                <?php endif; ?>
                '.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before{color:var(--gray-2);}',
                '@media (forced-colors: active){.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before{color:Highlight;}}',
                'span.mention-in-progress{display:inline;background:var(--info-2);color:var(--notification__clr);border-radius:3px;padding:0 2px;}',
                'span.mention-chip{display:inline-block;background:var(--surface);color:var(--font__pri-clr);border-radius:50px;padding:1px 10px 2px;font-size:12px;font-weight:600;line-height:1.6;cursor:default;user-select:all;-webkit-user-select:all;white-space:nowrap;border:1px solid var(--link__pri-clr);margin-bottom:2px;}'
            ].join('')
        });
    </script>
    <?php
} // END hesk_tinymce_init()
