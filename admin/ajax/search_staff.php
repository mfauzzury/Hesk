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
 * @mention staff search endpoint
 * Returns active staff users who have access to a ticket's category,
 * filtered by a search keyword (name or email address).
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

header('Content-Type: application/json');

// Only staff who can assign others (or admins) are allowed to add collaborators.
// Return empty early so the dropdown never appears for users without this permission.
if (!hesk_checkPermission('can_assign_others', 0)) {
    print json_encode([]);
    exit();
}

/* Normalize &nbsp; (\u00a0, UTF-8: \xc2\xa0) to regular space before trim,
   so contenteditable trailing spaces don't break the LIKE query. */
$query             = isset($_GET['query'])     ? trim(str_replace("\xc2\xa0", ' ', hesk_GET('query'))) : '';
$ticket_id         = isset($_GET['ticket_id']) ? intval(hesk_GET('ticket_id')) : 0;
$category_override = isset($_GET['category'])  ? intval(hesk_GET('category'))  : 0;

if ($ticket_id < 1) {
    // New ticket — no ticket row yet; use the provided category directly
    if ($category_override < 1 || (!hesk_checkPermission('can_submit_any_cat', 0) && !hesk_okCategory($category_override, 0))) {
        print json_encode([]);
        exit();
    }
    $category_res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`={$category_override} LIMIT 1");
    if (hesk_dbNumRows($category_res) != 1) {
        print json_encode([]);
        exit();
    }
    $category = $category_override;
} else {
    // Get the ticket's category
    $res = hesk_dbQuery("SELECT `id`, `trackid`, `category`, `owner`, `assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`=".intval($ticket_id)." LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        print json_encode([]);
        exit();
    }
    $ticket   = hesk_dbFetchAssoc($res);
    hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) {
        print json_encode([]);
        exit();
    });
    $category = intval($ticket['category']);
}

// Filter eligible staff before LIMIT so inaccessible users cannot hide valid matches.
// Keep the owner and existing collaborators mentionable; exclude only the current author.
$eligible_ids = array_diff(
    hesk_getUserIdsWithAccessToFeatureAndCategory('can_view_tickets', $category),
    array(intval($_SESSION['id']))
);
if (empty($eligible_ids)) {
    print json_encode([]);
    exit();
}

// Search active staff matching query by name or email address.
// Empty query shows all eligible staff (triggered when user types just @).
$query_escaped = hesk_dbEscape(hesk_htmlspecialchars($query));
$where_query   = strlen($query) > 0
    ? "AND (`name` LIKE '%{$query_escaped}%' OR `email` LIKE '%{$query_escaped}%')"
    : '';
$users_rs = hesk_dbQuery("SELECT `id`, `name`, `email`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users`
    WHERE `active` = 1 AND `id` IN (".implode(',', array_map('intval', $eligible_ids)).")
    {$where_query}
    ORDER BY `name` ASC
    LIMIT 30");

$response = [];
while ($row = hesk_dbFetchAssoc($users_rs)) {
    $uid = intval($row['id']);

    $response[] = [
        'id'       => $uid,
        'name'     => hesk_html_entity_decode($row['name']),
        'email'    => hesk_html_entity_decode($row['email']),
    ];
}

print json_encode($response);
exit();
