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
require_once(HESK_PATH . 'inc/hooks.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

$_SESSION['suppress_ticket_view_hook'] = true;
/* Ticket ID */
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);
hesk_verifyStaffTicketAccess($trackingID);

/* New archived status */
if (empty($_GET['archived']))
{
	$status = 0;
	$tmp = $hesklang['removedfromarchive'];
}
else
{
	$status = 1;
	$tmp = $hesklang['added2archive'];
}

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets', 1, function($failure_reason) use ($status) {
	hesk_hook_ticket_updated([
		'trackid' => hesk_cleanID(),
		'archive' => $status
	], $failure_reason);
});
hesk_checkPermission('can_add_archive', 1, function($failure_reason) use ($status) {
	hesk_hook_ticket_updated([
		'trackid' => hesk_cleanID(),
		'archive' => $status
	], $failure_reason);
});

/* A security check */
hesk_token_check();

/* Update database */
hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `archive`='$status' WHERE `trackid`='".hesk_dbEscape($trackingID)."' AND `archive` != '$status'");
if (hesk_dbAffectedRows() == 1) {
    hesk_hook_ticket_updated([
        'trackid' => $trackingID,
        'archive' => $status
    ], true);
}

/* Back to ticket page and show a success message */
hesk_process_messages($tmp,'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
