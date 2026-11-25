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
require(HESK_PATH . 'inc/privacy_functions.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

$_SESSION['suppress_ticket_view_hook'] = true;

// Tracking ID
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

// Check permissions for this feature
hesk_checkPermission('can_export', 1, function($failure_reason) use ($trackingID) {
    hesk_hook_ticket_exported([
        'trackid' => $trackingID
    ], $failure_reason);
});
hesk_checkPermission('can_view_tickets', 1, function($failure_reason) use ($trackingID) {
    hesk_hook_ticket_exported([
        'trackid' => $trackingID
    ], $failure_reason);
});

// A security check
hesk_token_check();

// Generate SQL for the ticket, make sure the user has access to it
$sql = "SELECT DISTINCT `ticket`.* FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
WHERE `ticket`.`trackid`='".hesk_dbEscape($trackingID)."' AND ";
$sql .= hesk_myCategories();
$sql .= " AND " . hesk_myOwnership(1);
$sql .= " LIMIT 1";

require_once(HESK_PATH . 'inc/custom_fields.inc.php');
require_once(HESK_PATH . 'inc/priorities.inc.php');
require_once(HESK_PATH . 'inc/statuses.inc.php');
require(HESK_PATH . 'inc/export_functions.inc.php');

list($success_msg, $tickets_exported, $export_id) = hesk_export_to_XML($sql, true);

if ($tickets_exported == 1)
{
    hesk_hook_ticket_exported([
        'trackid' => $trackingID,
        'export_id' => $export_id,
        'ticket_count' => $tickets_exported
    ], true);
    hesk_process_messages($success_msg,'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

hesk_error($hesklang['n2ex']);
