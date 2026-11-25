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

define('CALENDAR',1);
define('AUTO_RELOAD',1);
$_SESSION['hide']['ticket_list'] = true;

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets');

$_SERVER['PHP_SELF'] = './admin_main.php';
$href = 'find_tickets.php';

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

define('EXTRA_PAGE_CLASSES','page-admin-find-tickets');

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
// Is this a quick link?
$is_quick_link = hesk_GET('ql', false);

$sql_customer_count = "SELECT COUNT(1) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer_names`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `customer_names`
        ON `ticket_to_customer_names`.`customer_id` = `customer_names`.`id` 
    WHERE `ticket_id` = `ticket`.`id`";
$sql_email_count = "SELECT COUNT(1) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer_emails`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `customer_emails`
        ON `ticket_to_customer_emails`.`customer_id` = `customer_emails`.`id` 
    WHERE `ticket_id` = `ticket`.`id`
        AND COALESCE(`customer_emails`.`email`, '') <> ''";

// This SQL code will be used to retrieve results
$sql_final = "SELECT
`ticket`.`id` AS `id`,
`trackid`,
COALESCE(`customer`.`name`, '".hesk_dbEscape($hesklang['anon_name'])."') AS `name`,
COALESCE(`customer`.`email`, '".hesk_dbEscape($hesklang['anon_email'])."') AS `email`,
({$sql_customer_count}) AS `customer_count`,
({$sql_email_count}) AS `email_count`,
`category`,
`priority`,
`priority_order` AS `vv`,
`subject`,
LEFT(`message`, 400) AS `message`,
`dt`,
`lastchange`,
`firstreply`,
`closedat`,
`status`,
`openedby`,
`firstreplyby`,
`closedby`,
`ticket`.`replies`,
`staffreplies`,
`owner`,
`time_worked`,
`due_date`,
`lastreplier`,
`lastreplier_customer`.`name` AS `lastreplier_customername`,
`replierid`,
`archive`,
`locked`,
CASE WHEN `bookmarks`.`ticket_id` IS NOT NULL THEN 1 ELSE 0 END AS `is_bookmark`
";

foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'])
	{
		$sql_final .= ", `".$k."`";
	}
}

$sql_final.= " FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
LEFT JOIN (
    SELECT DISTINCT `ticket_id`, `user_id`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator`
    WHERE `user_id` = ".intval($_SESSION['id'])."
) AS `w` ON (`ticket`.`id` = `w`.`ticket_id`) ";

$sql_final.= "
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
    ON `customer`.`id` = (
        SELECT `customer_id`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
        WHERE `ticket_id` = `ticket`.`id`
            AND `customer_type` = 'REQUESTER'
        LIMIT 1
    )
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `lastreplier_customer`
    ON `ticket`.`lastreplier` = '0'
    AND `lastreplier_customer`.`id` = (
        SELECT `customer_id` 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` 
        WHERE `replyto` = `ticket`.`id`
            AND `customer_id` IS NOT NULL 
        ORDER BY `id` DESC 
        LIMIT 1)
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `lastreplier_staff`
    ON `ticket`.`lastreplier` <> '0'
    AND `ticket`.`replierid` = `lastreplier_staff`.`id`
LEFT JOIN (
    SELECT DISTINCT `ticket_id`, `user_id`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks`
    WHERE `user_id` = ".intval($_SESSION['id'])."
) AS `bookmarks` ON (`ticket`.`id` = `bookmarks`.`ticket_id`)
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` AS `custom_priorities` ON `ticket`.`priority` = `custom_priorities`.`id`
WHERE ";

if ($is_quick_link == 'cbm') {
    $sql_final.= " `w`.`user_id`=".intval($_SESSION['id'])." AND ".hesk_myCategories()." ";
} else {
    $sql_final .= " ".hesk_myCategories()." AND ".hesk_myOwnership(1);
}

// This code will be used to count number of results for this specific search
$sql_count = " SELECT COUNT(DISTINCT `ticket`.`id`) AS `cnt`, `status`,
                      IF (`owner` = " . intval($_SESSION['id']) . ", 1, IF (`owner` = 0, 0, IF (`assignedby` = " . intval($_SESSION['id']) . ", 3, 2) ) ) AS `assigned_to`,
                      IF (`due_date` < NOW(), 2, IF (`due_date` BETWEEN NOW() AND (NOW() + INTERVAL ".intval($hesk_settings['due_soon'])." DAY), 1, 0) ) AS `due`,
                      CASE WHEN `bookmarks`.`ticket_id` IS NOT NULL THEN 1 ELSE 0 END AS `is_bookmark`,
                      CASE WHEN `w`.`user_id` IS NOT NULL THEN 1 ELSE 0 END AS `is_collaborator`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
                LEFT JOIN (
                    SELECT DISTINCT `ticket_id`, `user_id`
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator`
                    WHERE `user_id` = ".intval($_SESSION['id'])."
                ) AS `w` ON (`ticket`.`id` = `w`.`ticket_id`)
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
                    ON `customer`.`id` = (
                        SELECT `customer_id`
                        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                        WHERE `ticket_id` = `ticket`.`id`
                            AND `customer_type` = 'REQUESTER'
                        LIMIT 1
                    )
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `lastreplier_customer`
                    ON `ticket`.`lastreplier` = '0'
                    AND `lastreplier_customer`.`id` = (
                        SELECT `customer_id`
                        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies`
                        WHERE `replyto` = `ticket`.`id`
                            AND `customer_id` IS NOT NULL
                        ORDER BY `id` DESC
                        LIMIT 1)
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `lastreplier_staff`
                    ON `ticket`.`lastreplier` <> '0'
                    AND `ticket`.`lastreplier` = `lastreplier_staff`.`id`
                LEFT JOIN (
                    SELECT DISTINCT `ticket_id`, `user_id`
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks`
                    WHERE `user_id` = ".intval($_SESSION['id'])."
                ) AS `bookmarks` ON (`ticket`.`id` = `bookmarks`.`ticket_id`)
                WHERE ".hesk_myCategories()." AND ".hesk_myOwnership(1);

// This code will be used to count collaborated tickets for this specific search
$sql_collaborator = " SELECT COUNT(DISTINCT `ticket`.`id`) AS `cnt`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
                    ON `customer`.`id` = (
                        SELECT `customer_id`
                        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                        WHERE `ticket_id` = `ticket`.`id`
                            AND `customer_type` = 'REQUESTER'
                        LIMIT 1
                    )                
                WHERE `w`.`user_id`=".intval($_SESSION['id'])." AND ".hesk_myCategories();

// This is common SQL for both queries
$sql = "";
$hook_filters = [];

// Some default settings
$search_include_resolved = (hesk_GET('resolved', '1') === '1') ? true : false;
$archive = array(1=>0,2=>0);
$s_my = array(1=>1,2=>1);
$s_ot = array(1=>1,2=>1);
$s_un = array(1=>1,2=>1);

// --> TICKET CATEGORY
if (isset($_GET['c']) && is_array($_GET['c'])) {
    $categories = [];
    foreach ($_GET['c'] as $category) {
        $category = intval($category);
        if ($category && hesk_okCategory($category, 0) ) {
            $categories[] = $category;
        }
    }
    if (count($categories)) {
        $sql .= " AND `category` IN (".implode(',', $categories).") ";
        $hook_filters['category'] = $categories;
    }
} elseif (isset($_GET['category'])) {
    // Legacy, select a single category
    $category = intval( hesk_GET('category', 0) );
    $categories = array($category);
    if ($category && hesk_okCategory($category, 0) ) {
        $sql .= " AND `category`='{$category}' ";
        $hook_filters['category'] = $categories;
    }
} else {
    $category = 0;
    $categories = [0];
}

// Include resolved tickets unless the search form explicitly excludes them.
if ( ! $search_include_resolved) {
    $sql .= " AND `ticket`.`status` <> '3' ";
    $hook_filters['exclude_resolved'] = true;
}

// Show only tagged tickets?
if ( ! empty($_GET['archive']) )
{
	$archive[2]=1;
	$sql .= " AND `archive`='1' ";
    $hook_filters['archive'] = $archive;
}

$sql_count .= $sql;
$sql_collaborator .= $sql;

// Ticket owner preferences
$fid = 2;
require(HESK_PATH . 'inc/assignment_search.inc.php');

$hesk_error_buffer = '';
$no_query = 0;

// Search query
$q = hesk_input( hesk_GET('q', '') );

// No query entered?
if ( ! strlen($q) )
{
	$no_query = 1;
}

// What field are we searching in
$what = hesk_GET('what', '');

if ( ! strlen($what))
{
    $hesk_error_buffer .= '<br />' . $hesklang['wsel'];
}
// Sequential ID supported?
elseif ($what == 'seqid' && ! $hesk_settings['sequential'])
{
	$what = 'trackid';
}
// Only known search fields are allowed
elseif ( ! in_array($what, array('trackid', 'seqid', 'name', 'email', 'subject', 'message', 'customer', 'notes', 'ip'), true) &&
         ( ! isset($hesk_settings['custom_fields'][$what]) || ! $hesk_settings['custom_fields'][$what]['use']))
{
    $what = 'trackid';
    $hesk_error_buffer .= '<br />' . $hesklang['invalid_search'];
}

// Sequential ID must be numeric
if ($what == 'seqid' && strlen($q) && !is_numeric($q)) {
    $q = '';
    $no_query = 1;
    $hesk_error_buffer .= $hesklang['seq_id_numeric'];
    $hesklang['fsq'] = '';
}

// Setup SQL based on searching preferences
if ( ! $no_query)
{
    $sql_previous = $sql;
    $sql = " AND ";

	switch ($what)
	{
		case 'trackid':
		    $sql  .= " ( `trackid` = '".hesk_dbEscape($q)."' OR `merged` LIKE '%#".hesk_dbEscape($q)."#%' ) ";
            $hook_filters['trackid'] = $q;
		    break;
		case 'name':
            $sql .= "`ticket`.`id` IN (
                SELECT `ticket_id`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
                    ON `ticket_to_customer`.`customer_id` = `customer`.`id`
                    AND `customer`.`name` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "'
            ) ";
            $hook_filters['customer_name'] = $q;
		    //$sql  .= "`name` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "' ";
		    break;
		case 'email':
            $sql .= "`ticket`.`id` IN (
                SELECT `ticket_id`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
                    ON `ticket_to_customer`.`customer_id` = `customer`.`id`
                    AND `customer`.`email` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%'
            ) ";
            $hook_filters['customer_email'] = $q;
			 break;
		case 'subject':
		    $sql  .= "`subject` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "' ";
            $hook_filters['subject'] = $q;
		    break;
		case 'message':
		    $sql  .= " ( `message` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "'
            		OR
                    `ticket`.`id` IN (
            		SELECT DISTINCT `replyto`
                	FROM   `".hesk_dbEscape($hesk_settings['db_pfix'])."replies`
                	WHERE  `message` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "' )
                    )
                    ";
            $hook_filters['message'] = $q;
		    break;
		case 'seqid':
	        $sql  .= "`ticket`.`id` = '".intval($q)."' ";
            $hook_filters['seqid'] = $q;
			break;
        case 'customer':
            $sql  .= "`ticket`.`id` IN (
                SELECT `ticket_id`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
                WHERE `customer_id` = '".intval($q)."'
            ) ";
            $hook_filters['customer_id'] = $q;
            break;
		case 'notes':
		    $sql  .= "`ticket`.`id` IN (
            		SELECT DISTINCT `ticket`
                	FROM   `".hesk_dbEscape($hesk_settings['db_pfix'])."notes`
                	WHERE  `message` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "' )
                	";
            $hook_filters['notes'] = $q;
		    break;
		case 'ip':
	         $sql  .= "`ip` LIKE '".preg_replace('/[^0-9\.\%]/', '', $q)."' ";
             $hook_filters['ip'] = $q;
			 break;
		default:
	    	if (isset($hesk_settings['custom_fields'][$what]) && $hesk_settings['custom_fields'][$what]['use'])
	        {
	        	$sql .= "`".hesk_dbEscape($what)."` LIKE '%".hesk_dbEscape( hesk_dbLike($q) )."%' COLLATE '" . hesk_dbCollate() . "' ";
                $hook_filters["custom_field_{$what}"] = $q;
	        }
	        else
	        {
	        	$hesk_error_buffer .= '<br />' . $hesklang['invalid_search'];
	        }
	}

    $sql_count .= $sql;
    $sql_collaborator .= $sql;
    $sql = $sql_previous . $sql;
}
// Some fields can be searched for empty (or NULL) values
else
{
    $sql_previous = $sql;
    $sql = " AND ";

    switch ($what)
    {
        case 'email':
            $sql  .= " COALESCE(`customer`.`email`, '') = '' ";
            $no_query = 0;
            $hook_filters['email'] = '';
            break;
        case 'message':
            $sql  .= " `message` = '' ";
            $no_query = 0;
            $hook_filters['message'] = '';
            break;
        case 'ip':
            $sql  .= "`ip` = '' ";
            $no_query = 0;
            $hook_filters['ip'] = '';
            break;
        default:
            if (isset($hesk_settings['custom_fields'][$what]) && $hesk_settings['custom_fields'][$what]['use'])
            {
                $sql .= "(`".hesk_dbEscape($what)."` IS NULL OR `".hesk_dbEscape($what)."` = '') ";
                $no_query = 0;
                $hook_filters["custom_field_{$what}"] = '';
            }
    }

    if ($no_query) {
        $hesk_error_buffer .= $hesklang['fsq'];
        $sql = "";
    }

    $sql_count .= $sql;
    $sql_collaborator .= $sql;
    $sql = $sql_previous . $sql;
}

// Owner
if ( $tmp = intval( hesk_GET('owner', 0) ) )
{
	$sql .= " AND `owner`={$tmp} ";
    $hook_filters['owner'] = $tmp;
    $sql_count .= " AND `owner`={$tmp} ";
    $sql_collaborator .= " AND `owner`={$tmp} ";
	$owner_input = $tmp;
	$hesk_error_buffer = str_replace($hesklang['fsq'],'',$hesk_error_buffer);
}
else
{
	$owner_input = 0;
}

/* Submitted date range */
$date_input = hesk_GET('dt_from');
$date_to_input = hesk_GET('dt_to');
$date_error = false;
if (!isset($_GET['dt_from']) && !isset($_GET['dt_to'])) {
    $date_input = $date_to_input = hesk_GET('dt');
    $date_error = isset($_GET['dt']) && is_array($_GET['dt']);
}

$date_range = array('dt_from' => '', 'dt_to' => '');
foreach (array('dt_from' => $date_input, 'dt_to' => $date_to_input) as $parameter => $input) {
    if ((isset($_GET[$parameter]) && is_array($_GET[$parameter])) || strpos($input, "\0") !== false) {
        $date_error = true;
        continue;
    }
    if ($input === '') {
        continue;
    }

    $parsed_date = hesk_datepicker_get_date($input);
    $date_errors = DateTime::getLastErrors();
    if ($parsed_date === false || ($date_errors !== false && ($date_errors['warning_count'] || $date_errors['error_count']))) {
        $date_error = true;
        continue;
    }
    $parts = date_parse_from_format($hesk_settings['format_datepicker_php'], hesk_translate_date_string($input, true));
    foreach (array('year' => 'Y', 'month' => 'n', 'day' => 'j') as $part => $format) {
        if ($parts[$part] !== false && $parts[$part] !== intval($parsed_date->format($format))) {
            $date_error = true;
            continue 2;
        }
    }

    $date_range[$parameter] = $parsed_date->format('Y-m-d');
    $selector = $parameter === 'dt_from' ? '#find-date' : '#find-date-to';
    $hesk_settings['datepicker'][$selector]['timestamp'] = DateTime::createFromFormat('!Y-m-d', $date_range[$parameter], new DateTimeZone('UTC'))->getTimestamp();
    $hesk_settings['datepicker'][$selector]['fromDB'] = true;
}

if ($date_error || $date_range['dt_from'] !== '' || $date_range['dt_to'] !== '') {
    $hesk_error_buffer = str_replace($hesklang['fsq'], '', $hesk_error_buffer);
}

if ($date_error) {
    $hesk_error_buffer .= '<br />' . sprintf($hesklang['audit_log']['date_invalid'], hesk_htmlspecialchars($hesk_settings['format_datepicker_php']));
} elseif ($date_range['dt_from'] !== '' && $date_range['dt_to'] !== '' && $date_range['dt_from'] > $date_range['dt_to']) {
    $hesk_error_buffer .= '<br />' . $hesklang['audit_log']['date_range_invalid'];
} else {
    $date_sql = '';
    if ($date_range['dt_from'] !== '') {
        $date_sql .= " AND `ticket`.`dt` >= '{$date_range['dt_from']} 00:00:00' ";
        $hook_filters['date_from'] = $date_range['dt_from'];
    }
    if ($date_range['dt_to'] !== '') {
        $date_sql .= " AND `ticket`.`dt` <= '{$date_range['dt_to']} 23:59:59' ";
        $hook_filters['date_to'] = $date_range['dt_to'];
    }
    if ($date_sql !== '') {
        $sql .= $date_sql;
        $sql_count .= $date_sql;
        $sql_collaborator .= $date_sql;
    }
}

/* Any errors? */
if (strlen($hesk_error_buffer))
{
	hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
}

/* This will handle error, success and notice messages */
$handle = hesk_handle_messages();

// Due date
if ($is_quick_link == 'due')
{
    $sql .= " AND `status` != 3 AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL ".intval($hesk_settings['due_soon'])." DAY) ";
}
elseif ($is_quick_link == 'ovr')
{
    $sql .= " AND `status` != 3 AND `due_date` < NOW() ";
}
elseif ($is_quick_link == 'alo')
{
    $sql .= " AND `status` != 3 ";
    $sql_count .= " AND `status` != 3 ";
    $sql_collaborator .= " AND `status` != 3 ";
}
elseif ($is_quick_link == 'bm')
{
    $sql .= " AND `bookmarks`.`ticket_id` IS NOT NULL";
}

if ($is_quick_link) {
    $hook_filters['quick_link'] = $is_quick_link;
}

// Complete the required SQL queries
$sql = $sql_final . $sql;
$sql_count .= " GROUP BY `assigned_to`, `due`, `status`, `is_bookmark`, `is_collaborator` ";

// Strip extra slashes
$q = stripslashes($q);

/* Prepare variables used in search and forms */
require_once(HESK_PATH . 'inc/prepare_ticket_search.inc.php');

/* If there has been an error message skip searching for tickets */
if ($handle !== FALSE)
{
    $totals = array(
        'all' => 0,
        'open' => 0,
        'resolved' => 0,
        'filtered' => array(
            'all' => 0,
            'open' => 0,
            'assigned_to_me' => 0,
            'assigned_to_others' => 0,
            'assigned_to_others_by_me' => 0,
            'unassigned' => 0,
            'bookmarks' => 0,
            'due_soon' => 0,
            'overdue' => 0,
            'by_status' => array(),
            'collaborator' => 0,
            'collaborator_assigned_to_me' => 0,
            'collaborator_assigned_to_others' => 0,
            'collaborator_assigned_to_others_by_me' => 0,
            'collaborator_unassigned' => 0,
        ),
    );

    $can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
    $can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
    $can_view_ass_by = hesk_checkPermission('can_view_ass_by',0);

	require_once(HESK_PATH . 'inc/ticket_list.inc.php');
}

/* Clean unneeded session variables */
hesk_cleanSessionVars('hide');

/* Show the search form */
require_once(HESK_PATH . 'inc/show_search_form.inc.php');

/* Print footer */
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
?>
