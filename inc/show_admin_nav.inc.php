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

$num_mail = hesk_checkNewMail();

// Name of the page that is being requested, without '.php' at the end
$calling_script = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- NEW DESIGN -->
<aside class="main-menu">
    <nav class="navbar">
        <div class="navbar__header">
            <button class="btn navbar__toggler" id="navbarToggler" type="button" aria-label="<?php echo $hesklang['toggle_navigation']; ?>">
                <svg class="icon icon-menu">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-menu"></use>
                </svg>
            </button>
            <a class="navbar__logo" href="admin_main.php">
                <?php echo htmlspecialchars($hesk_settings['hesk_title']); ?>
            </a>
        </div>
        <div class="navbar__menu-wrap">
            <ul class="navbar__list">
                <?php
                    $pages = array('admin_main', 'show_tickets', 'find_tickets');
                    $open_menu = in_array($calling_script, $pages) ? 'current' : '';
                ?>
                <li class="listitem <?php echo $open_menu; ?>">
                    <div class="listitem__icon">
                        <a href="admin_main.php" aria-label="<?php echo $hesklang['tickets']; ?>">
                            <svg class="icon icon-tickets">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tickets"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="admin_main.php" class="listitem__caption">
                            <?php echo $hesklang['tickets']; ?>
                        </a>
                    </div>
                </li>
                <?php if (hesk_checkPermission('can_view_tickets',0) && ! empty($_SESSION['show_bookmarks'])): ?>
                <li class="listitem <?php if ($calling_script === 'bookmarks') { ?>current<?php } ?>">
                    <div class="listitem__icon">
                        <a href="bookmarks.php" aria-label="<?php echo $hesklang['ql_bookmarks']; ?>">
                            <svg class="icon icon-pin">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-pin"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="bookmarks.php" class="listitem__caption">
                            <?php echo $hesklang['ql_bookmarks']; ?>
                        </a>
                    </div>
                </li>
                <?php endif; ?>
                <?php if (hesk_checkPermission('can_man_canned',0) &&
                          hesk_checkPermission('can_man_ticket_tpl',0)) {
                    $pages = array('manage_canned', 'manage_ticket_templates');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                    ?>
                <li class="listitem submenu <?php echo $open_menu; ?>">
                    <div class="listitem__icon">
                        <a href="#" aria-label="<?php echo $hesklang['responses']; ?>">
                            <svg class="icon icon-templates">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-templates"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="#" class="listitem__caption"><?php echo $hesklang['nav_templates']; ?></a>
                        <ul class="submenu__list">
                            <li class="submenu__listitem <?php if ($calling_script === 'manage_canned') { ?>current<?php } ?>">
                                <a href="manage_canned.php">
                                    <?php echo $hesklang['responses']; ?>
                                </a>
                            </li>
                            <li class="submenu__listitem <?php if ($calling_script === 'manage_ticket_templates') { ?>current<?php } ?>">
                                <a href="manage_ticket_templates.php">
                                    <?php echo $hesklang['tickets']; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <?php } elseif (hesk_checkPermission('can_man_canned',0)) { ?>
                    <li class="listitem <?php if ($calling_script === 'manage_canned') { ?>current<?php } ?>">
                        <div class="listitem__icon">
                            <a href="manage_canned.php" aria-label="<?php echo $hesklang['responses']; ?>">
                                <svg class="icon icon-tickets">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-templates"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="manage_canned.php" class="listitem__caption">
                                <?php echo $hesklang['responses']; ?>
                            </a>
                        </div>
                    </li>
                <?php } elseif (hesk_checkPermission('can_man_ticket_tpl',0)) { ?>
                    <li class="listitem <?php if ($calling_script === 'manage_ticket_templates') { ?>current<?php } ?>">
                        <div class="listitem__icon">
                            <a href="manage_ticket_templates.php" aria-label="<?php echo $hesklang['nav_templates']; ?>">
                                <svg class="icon icon-tickets">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-templates"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="manage_ticket_templates.php" class="listitem__caption">
                                <?php echo $hesklang['nav_templates']; ?>
                            </a>
                        </div>
                    </li>
                <?php
                }

                if ($hesk_settings['kb_enable'] && hesk_checkPermission('can_man_kb',0)) {
                    $pages = array('manage_knowledgebase', 'knowledgebase_private');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                    ?>
                    <li class="listitem submenu <?php echo $open_menu; ?>">
                        <div class="listitem__icon">
                            <a href="manage_knowledgebase.php" aria-label="<?php echo $hesklang['menu_kb_manage']; ?>">
                                <svg class="icon icon-knowledge">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-knowledge"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="#" class="listitem__caption"><?php echo $hesklang['menu_kb']; ?></a>
                            <ul class="submenu__list">
                                <li class="submenu__listitem <?php if ($calling_script === 'manage_knowledgebase') { ?>current<?php } ?>">
                                    <a href="manage_knowledgebase.php">
                                        <?php echo $hesklang['menu_kb_manage']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'knowledgebase_private') { ?>current<?php } ?>">
                                    <a href="knowledgebase_private.php">
                                        <?php echo $hesklang['menu_kb_view']; ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php
                } elseif ($hesk_settings['kb_enable']) {
                    ?>
                    <li class="listitem <?php if ($calling_script === 'knowledgebase_private') { ?>current<?php } ?>">
                        <div class="listitem__icon">
                            <a href="knowledgebase_private.php" aria-label="<?php echo $hesklang['menu_kb']; ?>">
                                <svg class="icon icon-knowledge">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-knowledge"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="knowledgebase_private.php" class="listitem__caption">
                                <?php echo $hesklang['menu_kb']; ?>
                            </a>
                        </div>
                    </li>
                    <?php
                }
                ?>
                <li class="listitem <?php if ($calling_script === 'manage_categories') { ?>current<?php } ?>">
                    <div class="listitem__icon">
                        <a href="manage_categories.php" aria-label="<?php echo $hesklang['menu_cat']; ?>">
                            <svg class="icon icon-categories">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-categories"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="manage_categories.php" class="listitem__caption">
                            <?php echo $hesklang['menu_cat']; ?>
                        </a>
                    </div>
                </li>
                <li class="separator"></li>
                <?php
                $number_of_accessible_pages = 0;
                if (hesk_checkPermission('can_man_users',0) || hesk_checkPermission('can_view_users',0)) {
                    $number_of_accessible_pages++;
                }
                if (hesk_checkPermission('can_man_customers',0) || hesk_checkPermission('can_view_customers',0)) {
                    $number_of_accessible_pages++;
                }
                if (hesk_checkPermission('can_man_permission_groups',0)) {
                    $number_of_accessible_pages++;
                }

                if ($number_of_accessible_pages > 1):
                    $pages = array('manage_users', 'manage_customers', 'import_customers', 'manage_permission_groups');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                ?>
                    <li class="listitem submenu <?php echo $open_menu; ?>">
                        <div class="listitem__icon">
                            <a href="#" aria-label="<?php echo $hesklang['team']; ?>">
                                <svg class="icon icon-team">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-team"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="separator"></div>
                        <div class="listitem__menu">
                            <?php
                            $show_badge = 0;
                            if ((hesk_checkPermission('can_man_customers', 0) || hesk_checkPermission('can_man_customers', 0)) && $hesk_settings['customer_accounts_admin_approvals']) {
                                $pending_customers = hesk_dbQuery("SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
                                        WHERE `verified` = 2");
                                $show_badge = hesk_dbNumRows($pending_customers);
                            }
                            ?>
                            <a href="#" class="listitem__caption">
                                <?php
                                echo $hesklang['menu_users'];
                                if ($show_badge): ?>
                                    <span class="admin-nav-badge"><?php echo $show_badge; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="submenu__list">
                                <?php if (hesk_checkPermission('can_man_users',0) || hesk_checkPermission('can_view_users',0)): ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'manage_users') { ?>current<?php } ?>">
                                    <a href="manage_users.php">
                                        <?php echo $hesklang['team']; ?>
                                    </a>
                                </li>
                                <?php
                                endif;
                                if (hesk_checkPermission('can_man_customers',0) || hesk_checkPermission('can_view_customers',0)):
                                ?>
                                <li class="submenu__listitem <?php if (in_array($calling_script, ['manage_customers','import_customers'])) { ?>current<?php } ?>">
                                    <a href="manage_customers.php">
                                        <?php
                                        echo $hesklang['customers'];

                                        if ($show_badge > 0):
                                        ?>
                                        <span class="admin-nav-badge"><?php echo $show_badge; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php
                                endif;
                                if (hesk_checkPermission('can_man_permission_groups',0)):
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'manage_permission_groups') { ?>current<?php } ?>">
                                    <a href="manage_permission_groups.php">
                                        <?php echo $hesklang['permission_groups_title']; ?>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </li>
                <?php else: ?>
                    <?php if (hesk_checkPermission('can_man_users',0) || hesk_checkPermission('can_view_users',0)) { ?>
                    <li class="listitem <?php if (in_array($calling_script, ['manage_users'])) { ?>current<?php } ?>">
                        <div class="listitem__icon">
                            <a href="manage_users.php" aria-label="<?php echo $hesklang['team']; ?>">
                                <svg class="icon icon-team">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-team"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="manage_users.php" class="listitem__caption">
                                <?php echo $hesklang['team']; ?>
                            </a>
                        </div>
                    </li>
                    <?php
                    }
                    if (hesk_checkPermission('can_man_customers',0) || hesk_checkPermission('can_view_customers',0)) { ?>
                        <li class="listitem <?php if (in_array($calling_script, ['manage_customers','import_customers'])) { ?>current<?php } ?>">
                            <div class="listitem__icon">
                                <a href="manage_customers.php" aria-label="<?php echo $hesklang['customers']; ?>">
                                    <svg class="icon icon-team">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-team"></use>
                                    </svg>
                                </a>
                            </div>
                            <div class="listitem__menu">
                                <a href="manage_customers.php" class="listitem__caption">
                                    <?php echo $hesklang['customers']; ?>
                                </a>
                            </div>
                        </li>
                        <?php
                    }
                    if (hesk_checkPermission('can_man_permission_groups',0)) { ?>
                        <li class="listitem <?php if ($calling_script === 'manage_permission_groups') { ?>current<?php } ?>">
                            <div class="listitem__icon">
                                <a href="manage_permission_groups.php" aria-label="<?php echo $hesklang['permission_groups_title']; ?>">
                                    <svg class="icon icon-team">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-team"></use>
                                    </svg>
                                </a>
                            </div>
                            <div class="listitem__menu">
                                <a href="manage_permission_groups.php" class="listitem__caption">
                                    <?php echo $hesklang['permission_groups_title']; ?>
                                </a>
                            </div>
                        </li>
                        <?php
                    }
                endif;

                //Reports
                if (hesk_checkPermission('can_run_reports',0)) {
                    $pages = array('reports', 'export');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                ?>
                <li class="listitem submenu <?php echo $open_menu; ?>">
                    <div class="listitem__icon">
                        <a href="#" aria-label="<?php echo $hesklang['reports_tab']; ?>">
                            <svg class="icon icon-reports">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-reports"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="separator"></div>
                    <div class="listitem__menu">
                        <a href="#" class="listitem__caption"><?php echo $hesklang['reports']; ?></a>
                        <ul class="submenu__list">
                            <li class="submenu__listitem <?php if ($calling_script === 'reports') { ?>current<?php } ?>">
                                <a href="reports.php">
                                    <?php echo $hesklang['reports_tab']; ?>
                                </a>
                            </li>
                            <?php
                            if (hesk_checkPermission('can_export',0) && hesk_checkPermission('can_view_tickets',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'export') { ?>current<?php } ?>">
                                    <a href="export.php">
                                        <?php echo $hesklang['export']; ?>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </li>
                <?php
                } elseif (hesk_checkPermission('can_export',0) && hesk_checkPermission('can_view_tickets',0)) {
                    ?>
                    <li class="listitem <?php if ($calling_script === 'export') { ?>current<?php } ?>">
                        <div class="listitem__icon">
                            <a href="export.php" aria-label="<?php echo $hesklang['export']; ?>">
                                <svg class="icon icon-team">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-team"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="export.php" class="listitem__caption">
                                <?php echo $hesklang['export']; ?>
                            </a>
                        </div>
                    </li>
                    <?php
                }

                // Modules
                if (hesk_checkPermission('can_run_reports',0) ||
                    hesk_checkPermission('can_man_settings',0)) {
                    $pages = array('module_statistics', 'module_escalate', 'module_satisfaction', 'module_satisfaction_optout', 'module_recurring_tickets', 'module_audit_log');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                ?>
                <li class="listitem submenu <?php echo $open_menu; ?>">
                    <div class="listitem__icon">
                        <a href="#" aria-label="<?php echo $hesklang['modules']; ?>">
                            <svg class="icon icon-modules">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-modules"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="#" class="listitem__caption">
                            <?php echo $hesklang['modules']; ?>
                        </a>
                        <ul class="submenu__list">
                            <?php if (hesk_checkPermission('can_run_reports',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'module_statistics') { ?>current<?php } ?>">
                                    <a href="module_statistics.php">
                                        <?php echo $hesklang['statistics']['tab']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_man_settings',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'module_escalate') { ?>current<?php } ?>">
                                    <a href="module_escalate.php">
                                        <?php echo $hesklang['escalate']['tab']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'module_satisfaction' || $calling_script === 'module_satisfaction_optout') { ?>current<?php } ?>">
                                    <a href="module_satisfaction.php">
                                        <?php echo $hesklang['satisfaction']['tab']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'module_recurring_tickets') { ?>current<?php } ?>">
                                    <a href="module_recurring_tickets.php">
                                        <?php echo $hesklang['recurring_tickets']['tab']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'module_audit_log') { ?>current<?php } ?>">
                                    <a href="module_audit_log.php">
                                        <?php echo $hesklang['audit_log']['tab']; ?>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </li>
                <?php
                }

                // Tools
                if (hesk_checkPermission('can_ban_emails',0) ||
                    hesk_checkPermission('can_mute_emails',0) ||
                    hesk_checkPermission('can_ban_ips',0) ||
                    hesk_checkPermission('can_service_msg',0) ||
                    hesk_checkPermission('can_email_tpl',0) ||
                    hesk_checkPermission('can_man_settings',0)) {
                    $pages = array('banned_emails', 'muted_emails', 'banned_ips', 'service_messages', 'email_templates', 'custom_fields', 'custom_statuses', 'oauth_providers', 'custom_priorities');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                ?>
                <li class="separator"></li>
                <li class="listitem submenu <?php echo $open_menu; ?>">
                    <div class="listitem__icon">
                        <a href="#" aria-label="<?php echo $hesklang['tools']; ?>">
                            <svg class="icon icon-tools">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tools"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="#" class="listitem__caption">
                            <?php echo $hesklang['tools']; ?>
                        </a>
                        <ul class="submenu__list">
                            <?php if (hesk_checkPermission('can_ban_emails',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'banned_emails') { ?>current<?php } ?>">
                                    <a href="banned_emails.php">
                                        <?php echo $hesklang['banemail']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_mute_emails',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'muted_emails') { ?>current<?php } ?>">
                                    <a href="muted_emails.php">
                                        <?php echo $hesklang['mute_emails']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_ban_ips',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'banned_ips') { ?>current<?php } ?>">
                                    <a href="banned_ips.php">
                                        <?php echo $hesklang['banip']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_service_msg',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'service_messages') { ?>current<?php } ?>">
                                    <a href="service_messages.php">
                                        <?php echo $hesklang['sm_title']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_email_tpl',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'email_templates') { ?>current<?php } ?>">
                                    <a href="email_templates.php">
                                        <?php echo $hesklang['et_title']; ?>
                                    </a>
                                </li>
                                <?php
                            }

                            if (hesk_checkPermission('can_man_settings',0)) {
                                ?>
                                <li class="submenu__listitem <?php if ($calling_script === 'custom_fields') { ?>current<?php } ?>">
                                    <a href="custom_fields.php">
                                        <?php echo $hesklang['tab_4']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'custom_statuses') { ?>current<?php } ?>">
                                    <a href="custom_statuses.php">
                                        <?php echo $hesklang['statuses']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'custom_priorities') { ?>current<?php } ?>">
                                    <a href="custom_priorities.php">
                                        <?php echo $hesklang['priorities']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'oauth_providers') { ?>current<?php } ?>">
                                    <a href="oauth_providers.php">
                                        <?php echo $hesklang['email_oauth_providers']; ?>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </li>
                <?php
                }

                if (hesk_checkPermission('can_man_settings',0)) {
                    $pages = array('admin_settings_custom_html', 'admin_settings_general', 'admin_settings_help_desk', 'admin_settings_theme', 'admin_settings_theme_admin', 'admin_settings_knowledgebase',
                        'admin_settings_email', 'admin_settings_ticket_list', 'admin_settings_misc');
                    $open_menu = in_array($calling_script, $pages) ? 'current submenu-is-opened' : '';
                    ?>
                    <li class="listitem submenu <?php echo $open_menu; ?>">
                        <div class="listitem__icon">
                            <a href="#" aria-label="<?php echo $hesklang['settings']; ?>">
                                <svg class="icon icon-settings">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-settings"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="listitem__menu">
                            <a href="#" class="listitem__caption">
                                <?php echo $hesklang['settings']; ?>
                            </a>
                            <ul class="submenu__list">
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_general') { ?>current<?php } ?>">
                                    <a href="admin_settings_general.php">
                                        <?php echo $hesklang['tab_1']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_help_desk') { ?>current<?php } ?>">
                                    <a href="admin_settings_help_desk.php">
                                        <?php echo $hesklang['tab_2']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_theme' || $calling_script === 'admin_settings_custom_html') { ?>current<?php } ?>">
                                    <a href="admin_settings_theme.php">
                                        <?php echo $hesklang['tab_8']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_theme_admin') { ?>current<?php } ?>">
                                    <a href="admin_settings_theme_admin.php">
                                        <?php echo $hesklang['tab_9']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_knowledgebase') { ?>current<?php } ?>">
                                    <a href="admin_settings_knowledgebase.php">
                                        <?php echo $hesklang['tab_3']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_email') { ?>current<?php } ?>">
                                    <a href="admin_settings_email.php">
                                        <?php echo $hesklang['tab_6']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_ticket_list') { ?>current<?php } ?>">
                                    <a href="admin_settings_ticket_list.php">
                                        <?php echo $hesklang['tab_7']; ?>
                                    </a>
                                </li>
                                <li class="submenu__listitem <?php if ($calling_script === 'admin_settings_misc') { ?>current<?php } ?>">
                                    <a href="admin_settings_misc.php">
                                        <?php echo $hesklang['tab_5']; ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php
                }
                ?>
                <li class="separator mobile"></li>
                <li class="listitem mobile <?php if ($calling_script === 'mail') { ?>current<?php } ?>">
                    <div class="listitem__icon">
                        <a href="mail.php" aria-label="<?php echo $hesklang['menu_msg']; ?>">
                            <svg class="icon icon-mail">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-mail"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="mail.php" class="listitem__caption">
                            <?php echo $hesklang['menu_msg']; ?>
                        </a>
                        <?php if ($num_mail > 0): ?>
                        <span class="badge listitem__notification">
                            <?php echo ($num_mail > 99) ? '99+' : $num_mail; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </li>
                <li class="listitem mobile <?php if ($calling_script === 'profile') { ?>current<?php } ?>">
                    <div class="listitem__icon">
                        <a href="profile.php" class="mobile_ava" aria-label="<?php echo $hesklang['profile']; ?>">
                            <?php
                            $letter = hesk_mb_substr($_SESSION['name'], 0, 1);
                            echo hesk_mb_strtoupper($letter);
                            ?>
                        </a>
                    </div>
                    <div class="listitem__menu">
                        <a href="profile.php" class="listitem__caption">
                            <?php echo $hesklang['profile']; ?>
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</aside>
<main class="main" id="maincontent">
    <!-- begin header -->
    <?php
    // Standard page title shown in the top bar (taken from the menu labels)
    $ttl = function ($key, $default) use ($hesklang) {
        return (isset($hesklang[$key]) && is_string($hesklang[$key])) ? $hesklang[$key] : $default;
    };
    $settings_label = $ttl('settings', 'Settings');
    $page_titles = array(
        'admin_main' => $ttl('tickets', 'Tickets'),
        'show_tickets' => $ttl('tickets', 'Tickets'),
        'find_tickets' => $ttl('tickets', 'Tickets'),
        'admin_ticket' => $ttl('ticket', 'Ticket'),
        'admin_reply_ticket' => $ttl('ticket', 'Ticket'),
        'new_ticket' => $ttl('create_new_ticket', 'Create New Ticket'),
        'bookmarks' => $ttl('ql_bookmarks', 'Bookmarks'),
        'manage_canned' => $ttl('responses', 'Responses'),
        'manage_ticket_templates' => $ttl('nav_templates', 'Templates'),
        'manage_knowledgebase' => $ttl('menu_kb', 'Knowledgebase'),
        'knowledgebase_private' => $ttl('menu_kb', 'Knowledgebase'),
        'manage_categories' => $ttl('menu_cat', 'Categories'),
        'manage_category' => $ttl('menu_cat', 'Categories'),
        'manage_users' => $ttl('team', 'Team'),
        'manage_customers' => $ttl('customers', 'Customers'),
        'import_customers' => $ttl('import_customers', 'Import Customers'),
        'manage_permission_groups' => $ttl('permission_groups_title', 'Permission Groups'),
        'reports' => $ttl('reports_tab', 'Run Reports'),
        'export' => $ttl('export', 'Export Tickets'),
        'mail' => $ttl('menu_msg', 'Mail'),
        'profile' => $ttl('profile', 'Your profile'),
        'module_statistics' => 'Statistics',
        'module_escalate' => 'Escalation',
        'module_satisfaction' => 'Customer Satisfaction',
        'module_recurring_tickets' => 'Recurring Tickets',
        'module_audit_log' => 'Audit Log',
        'banned_emails' => $ttl('banemail', 'Ban Emails'),
        'muted_emails' => $ttl('mute_emails', 'Mute Emails'),
        'banned_ips' => $ttl('banip', 'Ban IPs'),
        'service_messages' => $ttl('sm_title', 'Service Messages'),
        'email_templates' => $ttl('et_title', 'Email Templates'),
        'custom_fields' => $ttl('tab_4', 'Custom Fields'),
        'custom_statuses' => $ttl('statuses', 'Statuses'),
        'custom_priorities' => $ttl('priorities', 'Priorities'),
        'oauth_providers' => $ttl('email_oauth_providers', 'OAuth Providers'),
        'admin_settings_general' => $settings_label . ' / ' . $ttl('tab_1', 'General'),
        'admin_settings_help_desk' => $settings_label . ' / ' . $ttl('tab_2', 'Help Desk'),
        'admin_settings_knowledgebase' => $settings_label . ' / ' . $ttl('tab_3', 'Knowledgebase'),
        'admin_settings_misc' => $settings_label . ' / ' . $ttl('tab_5', 'Misc'),
        'admin_settings_email' => $settings_label . ' / ' . $ttl('tab_6', 'Email'),
        'admin_settings_ticket_list' => $settings_label . ' / ' . $ttl('tab_7', 'Ticket list'),
        'admin_settings_theme' => $settings_label . ' / ' . $ttl('tab_8', 'Look & Feel'),
        'admin_settings_theme_admin' => $settings_label . ' / ' . $ttl('tab_9', 'Look & Feel (Admin)'),
        'admin_settings_custom_html' => $settings_label . ' / Custom HTML',
    );
    // Some pages rewrite PHP_SELF to include a query string (e.g. admin_ticket.php?track=...), so strip it
    $title_script = preg_replace('/\.php.*$/', '', basename($_SERVER['PHP_SELF']));
    $admin_page_title = isset($page_titles[$title_script]) ? $page_titles[$title_script] : '';
    if ($title_script === 'admin_ticket' && $admin_page_title !== '' && ! empty($trackingID)) {
        $admin_page_title .= ' &middot; ' . htmlspecialchars($trackingID);
    }
    ?>
    <header class="header">
        <div class="header__left">
            <?php if ($admin_page_title !== ''): ?>
            <h1 class="header__title"><span class="header__title_text"><?php echo $admin_page_title; ?></span></h1>
            <?php endif; ?>
        </div>
        <div class="header__right" style="border-left: none">
            <a href="new_ticket.php" class="btn btn-full" ripple="ripple" aria-label="<?php echo $hesklang['create_new_ticket']; ?>">
                <?php echo $hesklang['create_new_ticket']; ?>
            </a>
            <div class="profile">
                <div class="profile__item profile__item--mail">
                    <a href="mail.php" class="btn btn-empty tooltip" title="<?php echo $hesklang['m_h']; ?>">
                        <div class="profile__item_rel">
                            <svg class="icon icon-mail">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-mail"></use>
                            </svg>
                            <?php if ($num_mail > 0): ?>
                            <div class="badge"><?php echo ($num_mail > 99) ? '99+' : $num_mail; ?></div>
                            <?php
                            endif;
                            unset($num_mail);
                            ?>
                        </div>
                    </a>
                </div>
                <div class="profile__item profile__user out-close">
                    <div class="user__ava" data-action="show-profile">
                        <?php
                        $letter = hesk_mb_substr($_SESSION['name'], 0, 1);
                        echo hesk_mb_strtoupper($letter);
                        ?>
                    </div>
                    <div class="user__name" data-action="show-profile">
                        <p>
                            <span><?php echo $_SESSION['name']; ?></span>
                            <svg class="icon icon-chevron-down">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                            </svg>
                        </p>
                    </div>
                    <section class="profile__menu">
                        <div class="profile--view">
                            <a href="profile.php" class="btn btn-border" ripple="ripple"><?php echo $hesklang['view_profile']; ?></a>
                        </div>
                        <div class="profile--logout">
                            <a href="index.php?a=logout&token=<?php hesk_token_echo(); ?>">
                                <svg class="icon icon-log-out">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-log-out"></use>
                                </svg>
                                <span><?php echo $hesklang['logout']; ?></span>
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <div class="header__mobile">
            <button class="btn btn-empty header__menu" data-action="toggle-menu" aria-label="<?php echo $hesklang['toggle_navigation']; ?>">
                <svg class="icon icon-menu-mobile">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-menu-mobile"></use>
                </svg>
                <svg class="icon icon-close-mobile">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close-mobile"></use>
                </svg>
            </button>
            <a class="navbar__logo" href="admin_main.php"><?php echo htmlspecialchars($hesk_settings['hesk_title']); ?></a>
            <div class="header__mobile_actions">
                <a href="new_ticket.php" class="btn btn-empty" data-action="create-ticket" aria-label="<?php echo $hesklang['create_new_ticket']; ?>">
                    <svg class="icon icon-add">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-add"></use>
                    </svg>
                </a>
            </div>
        </div>
    </header>
    <script>
    // Move the page's own heading text and "i" tip into the top bar, and drop the duplicate heading (desktop only)
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.matchMedia('(min-width: 900px)').matches) { return; }
        var titleEl = document.querySelector('.header .header__title');
        var textEl = titleEl ? titleEl.querySelector('.header__title_text') : null;
        var content = document.querySelector('main .main__content');
        if (!textEl || !content) { return; }
        var h = content.querySelector('h2');
        if (!h) { return; }
        var tip = h.querySelector('.tooltype');
        var clone = h.cloneNode(true);
        var cTip = clone.querySelector('.tooltype');
        if (cTip) { cTip.parentNode.removeChild(cTip); }
        var hText = clone.textContent.replace(/\s+/g, ' ').trim();
        var barText = textEl.textContent.replace(/\s+/g, ' ').trim();
        // Only treat it as the page heading if it has an info tip, or repeats the bar title
        if (!tip && hText.toLowerCase() !== barText.toLowerCase()) { return; }
        if (hText) { textEl.textContent = hText; }
        if (tip) { titleEl.appendChild(tip); }
        var parent = h.parentElement;
        if (parent === content) {
            h.style.display = 'none';
        } else if (parent.children.length === 1) {
            parent.style.display = 'none';
        } else {
            // heading shares a row with buttons: keep an empty spacer so the buttons stay on the right
            h.textContent = '';
            h.style.flex = '1';
            h.style.margin = '0';
            h.style.padding = '0';
        }
    });
    </script>
<?php
// Show a notice if we are in maintenance mode
if ( hesk_check_maintenance(false) )
{
	echo '<br />';
	hesk_show_notice($hesklang['mma2'], $hesklang['mma1'], false);
}

// Show a notice if we are in "Knowledgebase only" mode
if ( hesk_check_kb_only(false) )
{
	echo '<br />';
	hesk_show_notice($hesklang['kbo2'], $hesklang['kbo1'], false);
}
?>
