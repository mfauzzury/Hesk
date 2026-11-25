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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets', 1, function($failure_reason) {
    hesk_hook_search_query_executed(null, $failure_reason);
});

define('CALENDAR',1);
define('AUTO_RELOAD',1);
define('EXTRA_PAGE_CLASSES','page-admin-bookmarks');

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

<div class="main__content tickets">
<div style="margin-left: -16px; margin-right: -24px;">
<?php

/* This will handle error, success and notice messages */
hesk_handle_messages();
?>
</div>
<?php

/* Print all accessible bookmarks without changing the saved ticket filters */
$is_bookmarks_page = true;
$href = 'bookmarks.php';
require_once(HESK_PATH . 'inc/print_tickets.inc.php');

/* Print footer */
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
