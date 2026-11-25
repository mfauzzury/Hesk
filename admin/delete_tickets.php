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
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

/* Set correct return URL */
if (isset($_SERVER['HTTP_REFERER']))
{
	$url = hesk_input($_SERVER['HTTP_REFERER']);
    $url = str_replace('&amp;','&',$url);
	if ($tmp = strstr($url,'show_tickets.php'))
    {
    	$referer = $tmp;
    }
	elseif ($tmp = strstr($url,'find_tickets.php'))
    {
    	$referer = $tmp;
    }
    elseif ($tmp = strstr($url,'bookmarks.php'))
    {
        $referer = $tmp;
    }
    elseif ($tmp = strstr($url,'admin_main.php'))
    {
    	$referer = $tmp;
    }
    else
    {
    	$referer = 'admin_main.php';
    }
}
else
{
	$referer = 'admin_main.php';
}

/* Is this a delete ticket request from within a ticket ("delete" icon)? */
if ( isset($_GET['delete_ticket']) )
{
    /* Check permissions for this feature */
	hesk_checkPermission('can_del_tickets', 1, function($failure_reason) {
        hesk_hook_tickets_deleted([hesk_cleanID()], $failure_reason);
    });

	/* A security check */
	hesk_token_check();

	// Tracking ID
	$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

	/* Get ticket info */
	$result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
	if (hesk_dbNumRows($result) != 1)
	{
		hesk_error($hesklang['ticket_not_found']);
	}
	$ticket = hesk_dbFetchAssoc($result);

	/* Is this user allowed to access this ticket? */
	hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($trackingID) {
        hesk_hook_tickets_deleted([$trackingID], $failure_reason);
    });

	hesk_fullyDeleteTicket($ticket['id'], $ticket['trackid']);

    hesk_process_messages(sprintf($hesklang['num_tickets_deleted'],1),$referer,'SUCCESS');
}


/* This is a request from ticket list. Must be POST and id must be an array */
if ( ! isset($_POST['id']) || ! is_array($_POST['id']) )
{
	hesk_process_messages($hesklang['no_selected'], $referer, 'NOTICE');
}
/* If not, then needs an action (a) POST variable set */
elseif ( ! isset($_POST['a']) )
{
	hesk_process_messages($hesklang['invalid_action'], $referer);
}

$i=0;

// Assign tickets to
if ( isset($_POST['action-type']) && $_POST['action-type'] == 'assi')
{
	hesk_token_check('POST');

	if ( ! isset($_POST['owner']) || $_POST['owner'] == '')
	{
		hesk_process_messages($hesklang['assign_no'], $referer, 'NOTICE');
	}

	$end_message = array();
	$num_assigned = 0;

	// Permissions
	$can_assign_others = hesk_checkPermission('can_assign_others',0);
	if ($can_assign_others)
	{
		$can_assign_self = TRUE;
	}
	else
	{
		$can_assign_self = hesk_checkPermission('can_assign_self',0);
	}

	$owner = intval( hesk_POST('owner') );

    // Make sure this staff member is allowed to perform the requested assignment action.
    if ($owner == -1)
    {
        if ( ! $can_assign_others && ! $can_assign_self)
        {
            hesk_error($hesklang['no_permission']);
        }
    }
    elseif ( ! $can_assign_others && ($owner != intval($_SESSION['id']) || ! $can_assign_self))
    {
        hesk_error($hesklang['no_permission']);
    }

	if ($owner == -1)
	{
		foreach ($_POST['id'] as $this_id)
		{
			if ( is_array($this_id) )
			{
				continue;
			}

			$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

			$result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$this_id} LIMIT 1");
			if (hesk_dbNumRows($result) != 1)
			{
				continue;
			}
			$ticket = hesk_dbFetchAssoc($result);
			hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id) {
                hesk_hook_ticket_updated([
                    'id' => $this_id,
                    'owner' => 0
                ], $failure_reason);
            });

            if ( ! $can_assign_others && intval($ticket['owner']) != intval($_SESSION['id']))
            {
                hesk_hook_ticket_updated([
                    'id' => $this_id,
                    'owner' => 0,
                    'assignedby' => null,
                ], 'feature|can_assign_others');
                $end_message[] = $ticket['trackid'] . ' error: ' . $hesklang['no_permission'];
                $i++;
                continue;
            }

			$revision = sprintf($hesklang['thist2'],hesk_date(),'<i>'.$hesklang['unas'].'</i>',addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
			$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 , `assignedby`=NULL , `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`={$this_id} LIMIT 1");
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'owner' => 0,
                'assignedby' => null,
            ], true);

			$end_message[] = sprintf($hesklang['assign_2'], $this_id);
            $num_assigned++;
			$i++;
		}

        if ($num_assigned == $i)
        {
            hesk_process_messages($hesklang['assign_1'],$referer,'SUCCESS');
        }

        hesk_process_messages(sprintf($hesklang['assign_log'], $num_assigned, ($i - $num_assigned), implode("\n", $end_message)),$referer,($num_assigned == 0) ? 'ERROR' : 'NOTICE');
	}

	$res = hesk_dbQuery("SELECT `id`,`user`,`name`,`email`,`isadmin`,`categories`,`notify_assigned` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='{$owner}' AND `active` = 1 LIMIT 1");
	$owner_data = hesk_dbFetchAssoc($res);
	if ( ! $owner_data)
	{
		hesk_process_messages($hesklang['no_valid_id'], $referer);
	}

	if ( ! $owner_data['isadmin'])
	{
		$owner_data['categories'] = hesk_getCategoriesForUser($owner);
	}

	require(HESK_PATH . 'inc/email_functions.inc.php');

	foreach ($_POST['id'] as $this_id)
	{
		if ( is_array($this_id) )
		{
			continue;
		}

		$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

		$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$this_id} LIMIT 1");
		if (hesk_dbNumRows($result) != 1)
		{
			continue;
		}
		$ticket = hesk_dbFetchAssoc($result);
		hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id, $owner) {
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'owner' => $owner
            ], $failure_reason);
        });

		if ( $ticket['owner'] == $owner )
		{
			$end_message[] = sprintf($hesklang['assign_3'], $ticket['trackid'], $owner_data['name']);
			$i++;
			continue;
		}
		if ( $owner_data['isadmin'] || in_array($ticket['category'],$owner_data['categories']))
		{
			$revision = sprintf($hesklang['thist2'],hesk_date(),addslashes($owner_data['name']).' ('.$owner_data['user'].')',addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
			hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`={$owner} , `assignedby`=".intval($_SESSION['id']).", `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`={$this_id} LIMIT 1");
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'owner' => $owner,
                'assignedby' => $_SESSION['id']
            ], true);

			$end_message[] = sprintf($hesklang['assign_4'], $ticket['trackid'], $owner_data['name']);
			$num_assigned++;

			$ticket['owner'] = $owner;
			$customers = hesk_get_customers_for_ticket($ticket['id']);
			$customer_names = array_map(function($customer) { return $customer['name']; }, $customers);
			$first_name = empty($customer_names) ? '' : $customer_names[0];
			$customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);

            /* --> Prepare message */

            // 1. Generate the array with ticket info that can be used in emails
            $info = array(
            'email'			=> implode(';', $customer_emails),
            'category'		=> $ticket['category'],
            'priority'		=> $ticket['priority'],
            'owner'			=> $ticket['owner'],
            'trackid'		=> $ticket['trackid'],
            'status'		=> $ticket['status'],
            'name'			=> implode(',', $customer_names),
            'subject'		=> $ticket['subject'],
            'message'		=> $ticket['message'],
            'message_html'  => $ticket['message_html'],
            'attachments'	=> $ticket['attachments'],
            'dt'			=> hesk_date($ticket['dt'], true),
            'lastchange'	=> hesk_date($ticket['lastchange'], true),
            'due_date'      => hesk_format_due_date($ticket['due_date']),
            'id'			=> $ticket['id'],
            'time_worked'   => $ticket['time_worked'],
            'last_reply_by' => hesk_getReplierNameArray($ticket),
            );

            // 2. Add custom fields to the array
            foreach ($hesk_settings['custom_fields'] as $k => $v)
            {
                $info[$k] = $v['use'] ? $ticket[$k] : '';
            }

            // 3. Make sure all values are properly formatted for email
            $ticket = hesk_ticketToPlain($info, 1, 0);

            /* Notify the new owner? */
            if ($ticket['owner'] != intval($_SESSION['id']))
            {
                hesk_notifyAssignedStaff(false, 'ticket_assigned_to_you', 'notify_assigned', false);
            }
		}
		else
		{
        	$end_message[] = sprintf($hesklang['assign_5'], $ticket['trackid'], $owner_data['name']);
		}

		$i++;
	}

	hesk_process_messages(sprintf($hesklang['assign_log'], $num_assigned, ($i - $num_assigned), implode("\n", $end_message)),$referer,($num_assigned == 0) ? 'ERROR' : ($num_assigned < $i ? 'NOTICE' : 'SUCCESS'));
}

// Change priority
if ( is_numeric($_POST['a']) && $_POST['action-type'] == 'bulk')
{
	// A security check
	hesk_token_check('POST');

    // Check permissions for this feature
    hesk_checkPermission('can_reply_tickets');

	// Priority info
	$priority = $_POST['a'];
    if ( ! isset($hesk_settings['priorities'][$priority])) {
        hesk_error($hesklang['priority_e_id']);
    }

	foreach ($_POST['id'] as $this_id)
	{
		if ( is_array($this_id) )
		{
			continue;
		}

		$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
		$result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby`,`priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$this_id} LIMIT 1");
		if (hesk_dbNumRows($result) != 1)
		{
			continue;
		}
		$ticket = hesk_dbFetchAssoc($result);
		hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id, $priority) {
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'priority' => $priority
            ], $failure_reason);
        });

		if ($ticket['priority'] == $priority)
		{
			continue;
		}

		$revision = sprintf($hesklang['thist8'],hesk_date(),$hesk_settings['priorities'][$priority]['name'],addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `priority`='{$priority}', `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`={$this_id}");
        hesk_hook_ticket_updated([
            'id' => $this_id,
            'priority' => $priority
        ], true);

		$i++;
	}

	hesk_process_messages($hesklang['pri_set_to'].' '.$hesk_settings['priorities'][$priority]['name'],$referer,'SUCCESS');
}
/* DELETE */
elseif ($_POST['a']=='delete')
{
    /* Check permissions for this feature */
	hesk_checkPermission('can_del_tickets', 1, function($failure_reason) {
        hesk_hook_tickets_deleted(hesk_POST_array('id'), $failure_reason);
    });

	/* A security check */
	hesk_token_check('POST');

    foreach ($_POST['id'] as $this_id)
    {
    	if ( is_array($this_id) )
        {
        	continue;
        }

        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($this_id)."' LIMIT 1");
		if (hesk_dbNumRows($result) != 1)
		{
			continue;
		}
        $ticket = hesk_dbFetchAssoc($result);

        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id) {
            hesk_hook_tickets_deleted([$this_id], $failure_reason);
        });

        hesk_fullyDeleteTicket($ticket['id'], $ticket['trackid']);
        $i++;
    }

    hesk_process_messages(sprintf($hesklang['num_tickets_deleted'],$i),$referer,'SUCCESS');
}
/* MERGE TICKETS */
elseif ($_POST['a']=='merge')
{
    /* Sort IDs, tickets will be merged to the lowest ID */
    sort($_POST['id'], SORT_NUMERIC);

    /* Select lowest ID as the target ticket */
    $merge_into = array_shift($_POST['id']);

    /* Check permissions for this feature */
	hesk_checkPermission('can_merge_tickets', 1, function($failure_reason) use ($merge_into) {
        hesk_hook_tickets_merged($merge_into, $_POST['id'], $failure_reason);
    });

	/* A security check */
	hesk_token_check('POST');

    /* Verify access to all tickets being merged */
    $merge_ids = array_merge(array($merge_into), $_POST['id']);
    foreach ($merge_ids as $this_id)
    {
        if ( is_array($this_id) )
        {
            hesk_error($hesklang['id_not_valid']);
        }

        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$this_id} LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            continue;
        }
        $ticket = hesk_dbFetchAssoc($result);
        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($merge_into) {
            hesk_hook_tickets_merged($merge_into, $_POST['id'], $failure_reason);
        });
    }

	/* Merge tickets or throw an error */
	if ( hesk_mergeTickets( $_POST['id'] , $merge_into ) )
    {
		hesk_process_messages($hesklang['merged'],$referer,'SUCCESS');
    }
    else
    {
    	$hesklang['merge_err'] .= ' ' . $_SESSION['error'];
        hesk_cleanSessionVars($_SESSION['error']);
    	hesk_process_messages($hesklang['merge_err'],$referer);
    }
}
/* TAG/UNTAG TICKETS */
elseif ($_POST['a']=='tag' || $_POST['a']=='untag')
{
    /* Check permissions for this feature */
	hesk_checkPermission('can_add_archive', 1, function($failure_reason) {
        hesk_hook_ticket_updated([
            'ids' => $_POST['id']
        ], $failure_reason);
    });

	/* A security check */
	hesk_token_check('POST');

    if ($_POST['a']=='tag')
    {
    	$archived = 1;
        $action = $hesklang['num_tickets_tag'];
    }
    else
    {
		$archived = 0;
        $action = $hesklang['num_tickets_untag'];
    }

    foreach ($_POST['id'] as $this_id)
    {
    	if ( is_array($this_id) )
        {
        	continue;
        }

        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($this_id)."' LIMIT 1");
		if (hesk_dbNumRows($result) != 1)
		{
			continue;
		}
        $ticket = hesk_dbFetchAssoc($result);

        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id, $archived) {
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'archive' => $archived
            ], $failure_reason);
        });

        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `archive`='$archived' WHERE `id`='".intval($this_id)."' AND `archive` != '$archived'");
        if (hesk_dbAffectedRows() == 1) {
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'archive' => $archived
            ], true);
        }
        $i++;
    }

    hesk_process_messages(sprintf($action,$i),$referer,'SUCCESS');
}
/* EXPORT */
elseif ($_POST['a']=='export')
{
    /* Check permissions for this feature */
    hesk_checkPermission('can_export', 1, function($failure_reason) {
        hesk_hook_ticket_exported(['ids' => $_POST['id']], $failure_reason);
    });
    hesk_checkPermission('can_view_tickets', 1, function($failure_reason) {
        hesk_hook_ticket_exported(['ids' => $_POST['id']], $failure_reason);
    });

    /* A security check */
    hesk_token_check('POST');

    if (defined('HESK_DEMO')) {
        hesk_process_messages($hesklang['ddemo'], 'admin_main.php', 'NOTICE');
    }

    $ids_to_export = array();

    foreach ($_POST['id'] as $this_id)
    {
        if ( is_array($this_id) )
        {
            continue;
        }

        $ids_to_export[] = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $i++;
    }

    if ($i < 1)
    {
        hesk_process_messages($hesklang['no_selected'], $referer, 'NOTICE');
    }

    // Start SQL statement for selecting tickets
    $sql = "SELECT DISTINCT `ticket`.* FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
WHERE `ticket`.`id` IN (".implode(',', $ids_to_export).") ";
    $sql .= " AND " . hesk_myCategories();
    $sql .= " AND " . hesk_myOwnership(1);

    require_once(HESK_PATH . 'inc/custom_fields.inc.php');
    require_once(HESK_PATH . 'inc/statuses.inc.php');
    require(HESK_PATH . 'inc/export_functions.inc.php');

    list($success_msg, $tickets_exported, $export_id) = hesk_export_to_XML($sql, true);

    if ($tickets_exported > 0)
    {
        hesk_hook_ticket_exported([
            'ids' => $_POST['id'],
            'export_id' => $export_id,
            'ticket_count' => $tickets_exported
        ], true);
        hesk_process_messages($success_msg,$referer,'SUCCESS');
    }
    else
    {
        hesk_process_messages($hesklang['n2ex'],$referer,'NOTICE');
    }
}
/* ANONYMIZE */
elseif ($_POST['a']=='anonymize')
{
    /* Check permissions for this feature */
    hesk_checkPermission('can_privacy', 1, function($failure_reason) {
        hesk_hook_ticket_anonymized(null, $failure_reason);
    });

    /* A security check */
    hesk_token_check('POST');

    if (defined('HESK_DEMO')) {
        hesk_process_messages($hesklang['ddemo'], 'admin_main.php', 'NOTICE');
    }    

    require(HESK_PATH . 'inc/privacy_functions.inc.php');

    foreach ($_POST['id'] as $this_id)
    {
        if ( is_array($this_id) )
        {
            continue;
        }

        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($this_id)."' AND ".hesk_myOwnership()." LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            continue;
        }
        $ticket = hesk_dbFetchAssoc($result);

        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($ticket) {
            hesk_hook_ticket_anonymized($ticket, $failure_reason);
        });

        hesk_anonymizeTicket(null, null, true);
        $i++;
    }

    hesk_process_messages(sprintf($hesklang['num_tickets_anon'],$i),$referer,'SUCCESS');
}
/* PRINT */
elseif ($_POST['a']=='print')
{
    /* Check permissions for this feature */
	hesk_checkPermission('can_view_tickets', 1, function($failure_reason) {
        hesk_hook_tickets_printed($_POST['id'], $failure_reason);
    });

	/* A security check */
	hesk_token_check('POST');

    // Load custom fields
    require_once(HESK_PATH . 'inc/custom_fields.inc.php');

    // Load statuses
    require_once(HESK_PATH . 'inc/statuses.inc.php');

	// List of staff
	if (!isset($admins))
	{
		$admins = array();
		$res2 = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `id` ASC");
		while ($row=hesk_dbFetchAssoc($res2))
		{
			$admins[$row['id']]=$row['name'];
		}
	}

	// List of categories
	$hesk_settings['categories'] = array();
	$res2 = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` WHERE ' . hesk_myCategories('id') . ' ORDER BY `cat_order` ASC');
	while ($row=hesk_dbFetchAssoc($res2))
	{
		$hesk_settings['categories'][$row['id']] = $row['name'];
	}

    // Print page head
    header('Content-Type: text/html; charset=utf-8');

	$tickets = array();
    // Loop through ticket IDs and print them
    foreach ($_POST['id'] as $this_id)
    {
        if ( is_array($this_id) ) {
            continue;
        }

        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($this_id)."' LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            continue;
        }
        $ticket = hesk_dbFetchAssoc($result);

        // Check that we have proper permissions to view this ticket
        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($ticket) {
            hesk_hook_tickets_printed($_POST['id'], $failure_reason);
        });

        // All good, continue...
        $customers = hesk_get_customers_for_ticket($ticket['id']);

        // Demo mode
        if ( defined('HESK_DEMO') )
        {
            foreach ($customers as $customer) {
                $customer['email'] = 'hidden@demo.com';
            }
            $ticket['ip']	 = '127.0.0.1';
        }

        $category['name'] = isset($hesk_settings['categories'][$ticket['category']]) ? $hesk_settings['categories'][$ticket['category']] : $hesklang['catd'];

        // Get replies
        $res  = hesk_dbQuery("SELECT `replies`.*, `reply_customer`.`name` AS `customer_name`, `reply_staff`.`name` AS `staff_name` 
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
            LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `reply_customer`
                ON `replies`.`customer_id` = `reply_customer`.`id`
            LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `reply_staff`
                ON `replies`.`staffid` = `reply_staff`.`id`
            WHERE `replyto`='{$ticket['id']}' ORDER BY `replies`.`id` ASC");

        $replies = [];
        while ($row = hesk_dbFetchAssoc($res)) {
            if (intval($row['staffid']) > 0) {
                $row['name'] = $row['staff_name'];
            } else {
                $row['name'] = $row['customer_name'];
            }
            $replies[] = $row;
        }

        // Get notes
        $notes = array();
        $res2 = hesk_dbQuery("SELECT t1.*, t2.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` AS t1 LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='{$ticket['id']}' ORDER BY t1.`id`");
        while ($note = hesk_dbFetchAssoc($res2))
        {
            $notes[] = $note;
        }

        $ticket['notes'] = $notes;
        $ticket['replies'] = $replies;
        $ticket['categoryName'] = $category['name'];
        $ticket['customers'] = $customers;
        $tickets[] = $ticket;
    }

	// Print tickets
	require(HESK_PATH . 'inc/print_template.inc.php');
	flush();

    exit();
}
/* Linked Selected Tickets */
elseif ($_POST['a'] == 'link_tickets') {

    // Check permissions for this feature
    hesk_checkPermission('can_link_tickets', 1, function($failure_reason) {
        hesk_hook_tickets_linked(array_values(hesk_POST_array('id')), $failure_reason);
    });

    // A security check
    hesk_token_check('POST');

    $ticket_ids = array();
    foreach (hesk_POST_array('id') as $ticket_id) {
        if (is_array($ticket_id)) {
            continue;
        }
        $ticket_id = intval($ticket_id) or hesk_error($hesklang['id_not_valid']);
        $ticket_ids[$ticket_id] = $ticket_id;
    }
    $ticket_ids = array_values($ticket_ids);

    // Need at least two tickets to link
    if (count($ticket_ids) < 2) {
        hesk_process_messages($hesklang['at_least_two_more'], $referer, 'NOTICE');
    }

    // Verify access to all tickets being linked
    foreach ($ticket_ids as $ticket_id)
    {
        if (is_array($ticket_id))
        {
            continue;
        }

        $ticket_id = intval($ticket_id) or hesk_error($hesklang['id_not_valid']);
        $result = hesk_dbQuery("SELECT `id`,`trackid`,`category`,`owner`,`assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$ticket_id} LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            hesk_error($hesklang['id_not_valid']);
        }
        $ticket = hesk_dbFetchAssoc($result);
        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($ticket_ids) {
            hesk_hook_tickets_linked($ticket_ids, $failure_reason);
        });
    }

    $link_created = 0;
    $link_already_exists = 0;
    $audit_linked_tickets = array();

    // Loop through all combinations and insert links
    foreach ($ticket_ids as $ticket_id) {
        if (is_array($ticket_id)) {
            continue;
        }

        if (count($ticket_ids) < 2) {
            continue;
        }

        $ticket_id = intval($ticket_id) or hesk_error($hesklang['id_not_valid']);

        // Get ticket tracking ID for the log
        $result = hesk_dbQuery("SELECT `trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$ticket_id}");
        $ticket_track_id = hesk_dbResult($result) or hesk_error($hesklang['id_not_valid']);

        foreach ($ticket_ids as $linked_ticket_id) {
            if (is_array($linked_ticket_id)) {
                continue;
            }

            $linked_ticket_id = intval($linked_ticket_id) or hesk_error($hesklang['id_not_valid']);

            // Prevent self linking
            if ($ticket_id === $linked_ticket_id) {
                continue;
            }

            // Check for linked data in table
            $result = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE (`ticket_id1` = {$ticket_id} AND `ticket_id2` = {$linked_ticket_id}) OR (`ticket_id1` = {$linked_ticket_id} AND `ticket_id2` = {$ticket_id})");
            $already_linked = hesk_dbResult($result);

            if (empty($already_linked)) {
                // Get linked ticket tracking ID for the log
                $result = hesk_dbQuery("SELECT `trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$linked_ticket_id}");
                $linked_ticket_track_id = hesk_dbResult($result) or hesk_error($hesklang['id_not_valid']);

                // Insert ticket relation into database
                hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` (`ticket_id1`, `ticket_id2`, `dt_created`) VALUES ('".hesk_dbEscape($ticket_id)."', '".hesk_dbEscape($linked_ticket_id)."',NOW())");

                // Update insert history log
                $link_ticket_log = sprintf($hesklang['link_history'], hesk_date(), $linked_ticket_track_id, addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');;
                hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($link_ticket_log)."') WHERE `id`='".intval($ticket_id)."'");

                $link_created++;
                $audit_linked_tickets[$ticket_id][] = $linked_ticket_id;
            } else {
                $link_already_exists++;
            }
        }

        // No need for duplicate checks with this $ticket_id, remove it
        if (($key = array_search($ticket_id, $ticket_ids)) !== false) {
            unset($ticket_ids[$key]);
        }
    }
    // Log only newly created links, grouped by their source ticket.
    foreach ($audit_linked_tickets as $ticket_id => $linked_ticket_ids) {
        hesk_hook_tickets_linked(array_merge(array($ticket_id), $linked_ticket_ids), true);
    }
    hesk_process_messages(sprintf($hesklang['selected_link_success'], $link_created, $link_already_exists), $referer, 'SUCCESS');
}
/* JUST CLOSE */
else
{
    /* Check permissions for this feature */
	hesk_checkPermission('can_view_tickets', 1, function($failure_reason) {
        hesk_hook_ticket_updated([
            'ids' => $_POST['id']
        ], $failure_reason);
    });
    hesk_checkPermission('can_resolve', 1, function($failure_reason) {
        hesk_hook_ticket_updated([
            'ids' => $_POST['id']
        ], $failure_reason);
    });

	/* A security check */
	hesk_token_check('POST');

    $revision = sprintf($hesklang['thist3'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

	foreach ($_POST['id'] as $this_id)
	{
    	if ( is_array($this_id) )
        {
        	continue;
        }

		$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

        $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($this_id)."' LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            continue;
        }
        $ticket = hesk_dbFetchAssoc($result);

        hesk_verifyStaffTicketAccess($ticket['trackid'], $ticket, function($failure_reason) use ($this_id) {
            hesk_hook_ticket_updated([
                'id' => $this_id,
                'status' => 3,
                'closedat' => true,
                'closedby' => $_SESSION['id']
            ], $failure_reason);
        });

		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `status`='3', `closedat`=NOW(), `closedby`=".intval($_SESSION['id']).", `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`='".intval($this_id)."'");
        hesk_hook_ticket_updated([
            'id' => $this_id,
            'status' => 3,
            'closedat' => true,
            'closedby' => $_SESSION['id']
        ], true);
		$i++;

        $ticket['collaborators'] = hesk_getTicketsCollaboratorIDs($ticket['id']);

		// Notify customer of closed ticket?
		if ($hesk_settings['notify_closed'] || count($ticket['collaborators']))
		{
            require_once(HESK_PATH . 'inc/email_functions.inc.php');

			$ticket['dt'] = hesk_date($ticket['dt'], true);
			$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
            $ticket['due_date'] = hesk_format_due_date($ticket['due_date']);

            $customers = hesk_get_customers_for_ticket($ticket['id']);
            $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);
            $customer_names = array_map(function($customer) { return $customer['name']; }, $customers);

            $ticket['email'] = implode(';', $customer_emails);
            $ticket['name'] = implode(';', $customer_names);
            $ticket['last_reply_by'] = hesk_getReplierNameArray($ticket);

			$ticket = hesk_ticketToPlain($ticket, 1, 0);

            if ($hesk_settings['notify_closed']) {
                hesk_notifyCustomer('ticket_closed');
            }

            if (count($ticket['collaborators'])) {
                hesk_notifyAssignedStaff(false, 'collaborator_resolved', 'notify_collaborator_resolved', 'notify_collaborator_resolved', array($_SESSION['id']));
            }
		}
	}

    hesk_process_messages(sprintf($hesklang['num_tickets_closed'],$i),$referer,'SUCCESS');
}
