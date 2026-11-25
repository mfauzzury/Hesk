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

require_once(HESK_PATH . 'inc/hooks.inc.php');

// Possible fields to be displayed in ticket list
$hesk_settings['possible_ticket_list'] = array(
'id' => $hesklang['id'],
'trackid' => $hesklang['trackID'],
'dt' => $hesklang['submitted'],
'lastchange' => $hesklang['last_update'],
'closedat' => $hesklang['resolved_at'],
'category' => $hesklang['category'],
'name' => $hesklang['customer'],
'email' => $hesklang['email'],
'subject' => $hesklang['subject'],
'status' => $hesklang['status'],
'owner' => $hesklang['owner'],
'replies' => $hesklang['replies'],
'staffreplies' => $hesklang['replies'] . ' (' . $hesklang['staff'] .')',
'lastreplier' => $hesklang['last_replier'],
'time_worked' => $hesklang['ts'],
'due_date' => $hesklang['due_date']
);

define('HESK_NO_ROBOTS', true);
define('IS_ADMIN_PAGE', true);

/*** FUNCTIONS ***/


function hesk_show_column($column)
{
	global $hesk_settings;

	return in_array($column, $hesk_settings['ticket_list']) ? true : false;

} // END hesk_show_column()


function hesk_getHHMMSS($in)
{
	$in = hesk_getTime($in);
    return explode(':', $in);
} // END hesk_getHHMMSS();


function hesk_getTime($in)
{
	$in = trim($in);

	/* If everything is OK this simple check should return true */
    if ( preg_match('/^([0-9]{2,3}):([0-5][0-9]):([0-5][0-9])$/', $in) )
    {
    	return $in;
    }

	/* No joy, let's try to figure out the correct values to use... */
    $h = 0;
    $m = 0;
    $s = 0;

    /* How many parts do we have? */
    $parts = substr_count($in, ':');

    switch ($parts)
    {
    	/* Only two parts, let's assume minutes and seconds */
		case 1:
	        list($m, $s) = explode(':', $in);
	        break;

        /* Three parts, so explode to hours, minutes and seconds */
        case 2:
	        list($h, $m, $s) = explode(':', $in);
	        break;

        /* Something other was entered, let's assume just minutes */
        default:
	        $m = $in;
    }

	/* Make sure all inputs are integers */
	$h = intval($h);
    $m = intval($m);
    $s = intval($s);

	/* Convert seconds to minutes if 60 or more seconds */
    if ($s > 59)
    {
    	$m = floor($s / 60) + $m;
        $s = intval($s % 60);
    }

	/* Convert minutes to hours if 60 or more minutes */
    if ($m > 59)
    {
    	$h = floor($m / 60) + $h;
        $m = intval($m % 60);
    }

    /* MySQL accepts max time value of 838:59:59 */
    if ($h > 838)
    {
    	return '838:59:59';
    }    

	/* That's it, let's send out formatted time string */
    return str_pad($h, 2, "0", STR_PAD_LEFT) . ':' . str_pad($m, 2, "0", STR_PAD_LEFT) . ':' . str_pad($s, 2, "0", STR_PAD_LEFT);

} // END hesk_getTime();


function hesk_mergeTickets($merge_these, $merge_into)
{
	global $hesk_settings, $hesklang, $hesk_db_link;

    /* Target ticket must not be in the "merge these" list */
    if ( in_array($merge_into, $merge_these) )
    {
        $merge_these = array_diff($merge_these, array( $merge_into ) );
    }

    /* At least 1 ticket needs to be merged with target ticket */
    if ( count($merge_these) < 1 )
    {
    	$_SESSION['error'] = $hesklang['merr1'];
    	return false;
    }

    /* Make sure target ticket exists */
	$res = hesk_dbQuery("SELECT `id`,`trackid`,`category` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($merge_into)."' LIMIT 1");
	if (hesk_dbNumRows($res) != 1)
	{
    	$_SESSION['error'] = $hesklang['merr2'];
		return false;
	}
	$ticket = hesk_dbFetchAssoc($res);

	/* Make sure user has access to ticket category */
	if ( ! hesk_okCategory($ticket['category'], 0) )
	{
        hesk_hook_tickets_merged($merge_into, $merge_these, 'category|'.$ticket['category']);
    	$_SESSION['error'] = $hesklang['merr3'];
		return false;
	}

    /* Set some variables for later */
    $sec_worked = 0;
    $history = '';
    $merged = '';
    $merged_tickets = array();

	/* Get messages, replies, notes and attachments of tickets that will be merged */
    foreach ($merge_these as $this_id)
    {
		/* Validate ID */
    	if ( is_array($this_id) )
        {
        	continue;
        }
    	$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

        /* Get required ticket information */
        $res = hesk_dbQuery("SELECT `tickets`.`id` AS `id`,`trackid`,`category`,`ticket_customers`.`customer_id` AS `customer_id`,`message`,`message_html`,`dt`,`time_worked`,`attachments` 
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
            LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_customers`
                ON `tickets`.`id` = `ticket_customers`.`ticket_id`
                AND `ticket_customers`.`customer_type` = 'REQUESTER' 
            WHERE `tickets`.`id`='".intval($this_id)."' LIMIT 1");
		if (hesk_dbNumRows($res) != 1)
		{
			continue;
		}
        $row = hesk_dbFetchAssoc($res);

        /* Has this user access to the ticket category? */
        if ( ! hesk_okCategory($row['category'], 0) )
        {
        	continue;
        }

        $merged_customer_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `ticket_id`=".intval($row['id']))));

		/* Insert ticket message as a new reply to target ticket */
        $customer_id = $row['customer_id'] !== null ? intval($row['customer_id']) : 'NULL';
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (`replyto`,`customer_id`,`message`,`message_html`,`dt`,`attachments`) VALUES ('".intval($ticket['id'])."',".$customer_id.",'".hesk_dbEscape(addslashes($row['message']))."','".hesk_dbEscape(addslashes($row['message_html']))."','".hesk_dbEscape($row['dt'])."','".hesk_dbEscape($row['attachments'])."')");
        $merged_reply_count = 1;

        $merged_bookmark_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` WHERE `ticket_id`=".intval($row['id']))));
        $merged_collaborator_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `ticket_id`=".intval($row['id']))));
        $merged_linked_ticket_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE `ticket_id1`=".intval($row['id'])." OR `ticket_id2`=".intval($row['id']))));
        $merged_email_mapping_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."email_id_to_ticket` WHERE `ticket_id`=".intval($row['id']))));

		/* Update attachments  */
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` SET `ticket_id`='".hesk_dbEscape($ticket['trackid'])."' WHERE `ticket_id`='".hesk_dbEscape($row['trackid'])."'");
        $merged_attachment_count = hesk_dbAffectedRows();

        /* Get old ticket replies and insert them as new replies */
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($row['id'])."' ORDER BY `id` ASC");
        while ( $reply = hesk_dbFetchAssoc($res) )
        {
            $customer_id = $reply['customer_id'] !== null ? intval($reply['customer_id']) : 'NULL';
			hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (`replyto`,`message`,`message_html`,`dt`,`attachments`,`staffid`,`customer_id`,`rating`,`read`,`submitted_by`) VALUES ('".intval($ticket['id'])."','".hesk_dbEscape(addslashes($reply['message']))."','".hesk_dbEscape(addslashes($reply['message_html']))."','".hesk_dbEscape($reply['dt'])."','".hesk_dbEscape($reply['attachments'])."','".intval($reply['staffid'])."',".$customer_id.",'".intval($reply['rating'])."','".intval($reply['read'])."','".intval($reply['submitted_by'])."')");
            $merged_reply_count++;
        }

		/* Delete replies to the old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($row['id'])."'");

        /* Get old ticket notes and insert them as new notes */
        $merged_note_count = 0;
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($row['id'])."' ORDER BY `id` ASC");
        while ( $note = hesk_dbFetchAssoc($res) )
        {
			hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`) VALUES ('".intval($ticket['id'])."','".intval($note['who'])."','".hesk_dbEscape($note['dt'])."','".hesk_dbEscape(addslashes($note['message']))."','".hesk_dbEscape($note['attachments'])."')");
            $merged_note_count++;
        }

		/* Delete replies to the old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($row['id'])."'");

        /* Insert old ticket's requester and followers to the new tickets as followers, assuming they're not already on it */
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`)
            SELECT ".intval($ticket['id']).", `customer_id`, 'FOLLOWER'
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `outer_ticket_to_customer`
            WHERE `ticket_id` = ".intval($row['id'])."
                AND NOT EXISTS (
                    SELECT 1
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `inner_ticket_to_customer`
                    WHERE `inner_ticket_to_customer`.`ticket_id` = ".intval($ticket['id'])."
                    AND `inner_ticket_to_customer`.`customer_id` = `outer_ticket_to_customer`.`customer_id` 
                )");
        /* Delete old ticket's customer mappings */
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
            WHERE `ticket_id` = ".intval($row['id']));

	    /* Delete old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($row['id'])."'");
        if (hesk_dbAffectedRows() == 1) {
            $merged_tickets[] = array(
                'id' => intval($row['id']),
                'trackid' => $row['trackid'],
                'customer_count' => $merged_customer_count,
                'reply_count' => $merged_reply_count,
                'note_count' => $merged_note_count,
                'attachment_count' => $merged_attachment_count,
                'bookmark_count' => $merged_bookmark_count,
                'collaborator_count' => $merged_collaborator_count,
                'linked_ticket_count' => $merged_linked_ticket_count,
                'email_mapping_count' => $merged_email_mapping_count
            );
        }

		/* Log that ticket has been merged */
		$history .= sprintf($hesklang['thist13'],hesk_date(),$row['trackid'],addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

        /* Add old ticket ID to target ticket "merged" field */
        $merged .= '#' . $row['trackid'];

		/* Convert old ticket "time worked" to seconds and add to $sec_worked variable */
		list ($hr, $min, $sec) = explode(':', $row['time_worked']);
		$sec_worked += (((int)$hr) * 3600) + (((int)$min) * 60) + ((int)$sec);
    }

	/* Convert seconds to HHH:MM:SS */
	$sec_worked = hesk_getTime('0:'.$sec_worked);

	// Get number of replies
	$total			= 0;
	$staffreplies	= 0;

	$res = hesk_dbQuery("SELECT COUNT(*) as `cnt`, (CASE WHEN `staffid` = 0 THEN 0 ELSE 1 END) AS `staffcnt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`=".intval($ticket['id'])." GROUP BY `staffcnt`");
	while ( $row = hesk_dbFetchAssoc($res) )
	{
		$total += $row['cnt'];
		$staffreplies += ($row['staffcnt'] ? $row['cnt'] : 0);
	}

	$replies_sql = " `replies`={$total}, `staffreplies`={$staffreplies} , ";

	// Get first staff reply
	if ($staffreplies)
	{
		$res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`=".intval($ticket['id'])." AND `staffid`>0 ORDER BY `dt` ASC LIMIT 1");
		$reply = hesk_dbFetchAssoc($res);
		$replies_sql .= " `firstreply`='".hesk_dbEscape($reply['dt'])."', `firstreplyby`=".intval($reply['staffid'])." , ";
	}

    /* Update history (log) and merged IDs of target ticket */
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET $replies_sql `time_worked`=ADDTIME(`time_worked`, '".hesk_dbEscape($sec_worked)."'), `merged`=CONCAT(`merged`,'".hesk_dbEscape($merged . '#')."'), `history`=CONCAT(`history`,'".hesk_dbEscape($history)."') WHERE `id`='".intval($merge_into)."'");
    hesk_hook_tickets_merged($merge_into, $merged_tickets, true);

    return true;

} // END hesk_mergeTickets()


function hesk_updateStaffDefaults()
{
	global $hesk_settings, $hesklang;

	// Demo mode
	if ( defined('HESK_DEMO') )
	{
		return true;
	}
	// Remove the part that forces saving as default - we don't need it every time
    $default_list = str_replace('&def=1','',$_SERVER['QUERY_STRING']);

    // Update database
	$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `default_list`='".hesk_dbEscape($default_list)."' WHERE `id`='".intval($_SESSION['id'])."'");

    // Update session values so the changes take effect immediately
    $_SESSION['default_list'] = $default_list;

    return true;
    
} // END hesk_updateStaffDefaults()


function hesk_makeJsString($in)
{
	return addslashes(preg_replace("/\s+/",' ',$in));
} // END hesk_makeJsString()


function hesk_json_encode_for_js($in)
{
    $json = json_encode((string) $in, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    return $json === false ? '""' : $json;
} // END hesk_json_encode_for_js()


function hesk_json_encode_jsString($str)
{
    $str  = str_replace('<br />', '', (string) $str);
    $from = array('/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
    $to   = array("$1", "$1");
    return hesk_json_encode_for_js(preg_replace($from, $to, $str));
} // END hesk_json_encode_jsString()


function hesk_checkNewMail()
{
	global $hesk_settings, $hesklang;

	$res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `to`='".intval($_SESSION['id'])."' AND `read`='0' AND `deletedby`!='".intval($_SESSION['id'])."' ");
	$num = hesk_dbResult($res,0,0);

	return $num;
} // END hesk_checkNewMail()


function hesk_dateToString($dt, $returnName=1, $returnTime=0, $returnMonth=0, $from_database=false)
{
	global $hesk_settings, $hesklang;

	$dt = strtotime($dt);

	// Adjust MySQL time if different from PHP time
	if ($from_database)
	{
		if ( ! defined('MYSQL_TIME_DIFF') )
		{
			define('MYSQL_TIME_DIFF', time()-hesk_dbTime() );
		}

		if (MYSQL_TIME_DIFF != 0)
		{
			$dt += MYSQL_TIME_DIFF;
		}
	}

	list($y,$m,$n,$d,$G,$i,$s) = explode('-', date('Y-n-j-w-G-i-s', $dt) );

	$m = $hesklang['m'.$m];
	$d = $hesklang['d'.$d];

	if ($returnName)
	{
		return "$d, $m $n, $y";
	}

    if ($returnTime)
    {
    	return "$d, $m $n, $y $G:$i:$s";
    }

    if ($returnMonth)
    {
    	return "$m $y";
    }

	return "$m $n, $y";
} // End hesk_dateToString()


function hesk_getCategoriesArray($kb = 0) {
	global $hesk_settings, $hesklang, $hesk_db_link;

	$categories = array();
    if ($kb)
    {
    	$result = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'kb_categories` ORDER BY `cat_order` ASC');
    }
    else
    {
		$result = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` ORDER BY `cat_order` ASC');
    }

	while ($row=hesk_dbFetchAssoc($result))
	{
		$categories[$row['id']] = $row['name'];
	}

    return $categories;
} // END hesk_getCategoriesArray()


function hesk_getHTML($in)
{
	global $hesk_settings, $hesklang;

	$replace_from = array("\t","<?","?>","$","<%","%>");
	$replace_to   = array("","&lt;?","?&gt;","\$","&lt;%","%&gt;");

	$in = trim($in);
	$in = str_replace($replace_from,$replace_to,$in);
	$in = preg_replace('/\<script(.*)\>(.*)\<\/script\>/Uis',"<script$1></script>",$in);
	$in = preg_replace('/\<\!\-\-(.*)\-\-\>/Uis',"<!-- comments have been removed -->",$in);

	if (HESK_SLASH === true)
	{
		$in = addslashes($in);
	}
    $in = str_replace('\"','"',$in);

	return $in;
} // END hesk_getHTML()


function hesk_autoLogin($noredirect=0)
{
	global $hesk_settings, $hesklang, $hesk_db_link;

    $log_auto_login = function($success, $user_id = null) {
        hesk_hook_authentication_event(array(
            'event' => 'auth:staff_login',
            'data' => array('method' => 'remember_token'),
        ), $success, array(
            'actor_type' => 'STAFF',
            'user_id' => $user_id,
            'auth_method' => 'TOKEN',
        ));
    };

    if (!$hesk_settings['autologin']) {
        return false;
    }

    if (empty($remember = hesk_COOKIE('hesk_remember')) || substr_count($remember, ':') !== 1) {
        return false;
    }

    // Login cookies exist, now lets limit brute force attempts
    hesk_limitBfAttempts(true, 'STAFF');

    // Admin login URL
    $url = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/index.php?a=login&notice=1';

    // Get and verify authentication tokens
    list($selector, $authenticator) = explode(':', $remember);
    $authenticator = base64_decode($authenticator);
    if (strlen($authenticator) > 256) {
        $log_auto_login('invalid_remember_token');
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    $result = hesk_dbQuery('SELECT * FROM `'.$hesk_settings['db_pfix']."auth_tokens` WHERE `selector` = '".hesk_dbEscape($selector)."' AND `expires` > NOW() AND `user_type` = 'STAFF' LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        $log_auto_login('remember_token_not_found');
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
	}

    $auth = hesk_dbFetchAssoc($result);

    if ( ! hash_equals($auth['token'], hash('sha256', $authenticator))) {
        $log_auto_login('remember_token_mismatch', intval($auth['user_id']));
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    // Token OK, let's regenerate session ID and get user data
    hesk_session_regenerate_id();

    $result = hesk_dbQuery('SELECT *, (`pass_expires_at` IS NOT NULL AND `pass_expires_at` <= NOW()) AS `is_pass_expired` FROM `'.$hesk_settings['db_pfix']."users` WHERE `id` = ".intval($auth['user_id'])." AND `active` = 1 LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        $log_auto_login('account_unavailable', intval($auth['user_id']));
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    $row = hesk_dbFetchAssoc($result);

    // Did the password expire since last login?
    if ($row['is_pass_expired']) {
        $log_auto_login('password_expired', intval($row['id']));
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }


    // Auto-login must not preserve a previously elevated session
    hesk_cleanSessionVars(array('elevated', 'elevator_target'));

    foreach ($row as $k => $v) {
        if ($k == 'pass') {
            continue;
        }
        $_SESSION[$k] = $v;
    }
    $_SESSION['auth_method'] = 'TOKEN';
    $_SESSION['session_ref'] = bin2hex(random_bytes(16));

    $user = $row['user'];
    define('HESK_USER', $user);

    // Each token should only be used once, so update the old one with a new one
    $selector = base64_encode(random_bytes(9));
    $authenticator = random_bytes(33);
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` SET `selector`='".hesk_dbEscape($selector)."', `token` = '".hesk_dbEscape(hash('sha256', $authenticator))."', `created` = NOW() WHERE `id` = ".intval($auth['id']));
    hesk_setcookie('hesk_remember', $selector.':'.base64_encode($authenticator), strtotime('+1 year'));

	// Set a tag that will be used to expire sessions after username or password change
	$_SESSION['session_verify'] = hesk_activeSessionCreateTag($user, $row['pass']);

	/* Login successful, clean brute force attempts */
	hesk_cleanBfAttempts('STAFF');
    $log_auto_login(true, intval($row['id']));

	/* Get allowed categories */
	if (empty($_SESSION['isadmin']))
	{
	    $_SESSION['categories']=explode(',',$_SESSION['categories']);
	    hesk_setPermissionGroupAccess($row['id']);
	}

    /* Close any old tickets here so Cron jobs aren't necessary */
	if ($hesk_settings['autoclose'])
    {
		$revision = sprintf($hesklang['thist3'],hesk_date(),$hesklang['auto']);
		$dt  = date('Y-m-d H:i:s',time() - $hesk_settings['autoclose']*86400);

        $autoclosed_tickets = array();
        $result = hesk_dbQuery("SELECT `id`, `trackid` FROM `".$hesk_settings['db_pfix']."tickets` WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."'");
        while ($autoclosed_ticket = hesk_dbFetchAssoc($result)) {
            $autoclosed_tickets[] = $autoclosed_ticket;
        }

		// Notify customer of closed ticket?
		if ($hesk_settings['notify_closed'])
		{
			// Get list of tickets
			$result = hesk_dbQuery("SELECT * FROM `".$hesk_settings['db_pfix']."tickets` WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."' ");
			if (hesk_dbNumRows($result) > 0)
			{
				global $ticket;

				// Load required functions?
				if ( ! function_exists('hesk_notifyCustomer') )
				{
					require(HESK_PATH . 'inc/email_functions.inc.php');
				}

				while ($ticket = hesk_dbFetchAssoc($result))
				{
					$ticket['dt'] = hesk_date($ticket['dt'], true);
					$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
					$ticket = hesk_ticketToPlain($ticket, 1, 0);
					hesk_notifyCustomer('ticket_closed');
				}
			}
		}

		// Update ticket statuses and history in database
        foreach ($autoclosed_tickets as $autoclosed_ticket) {
            hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."tickets` SET `status`='3', `closedat`=NOW(), `closedby`='-1', `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`=".intval($autoclosed_ticket['id'])." AND `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."'");
            if (hesk_dbAffectedRows() == 1) {
                hesk_hook_ticket_updated(array(
                    'id' => intval($autoclosed_ticket['id']),
                    'trackid' => $autoclosed_ticket['trackid'],
                    'changes' => array('status', 'closedat', 'closedby', 'history')
                ), true, array(
                    'actor_type' => 'SYSTEM',
                    'user_id' => null,
                    'auth_method' => 'NONE'
                ));
            }
        }
    }

	/* If session expired while a HESK page is open just continue using it, don't redirect */
    if ($noredirect)
    {
    	return true;
    }

	/* Redirect to the destination page */
	header('Location: ' . hesk_verifyGoto() );
	exit();
} // END hesk_autoLogin()


function hesk_isLoggedIn()
{
	global $hesk_settings;

	$referer = hesk_input($_SERVER['REQUEST_URI']);
	$referer = str_replace('&amp;','&',$referer);

	// Admin login URL
	$url = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/index.php?a=login&notice=1&goto='.urlencode($referer);

    // A password reset link must never be treated as a full staff login.
    // profile.php handles reset-only sessions before calling hesk_isLoggedIn().
    if ( ! empty($_SESSION['password_reset']))
    {
        hesk_session_stop();
        header('Location: '.$url);
        exit();
    }

    if ( empty($_SESSION['id']) || empty($_SESSION['session_verify']))
    {
    	if ($hesk_settings['autologin'] && hesk_autoLogin(1) )
        {
			// Users online
        	if ($hesk_settings['online'])
            {
            	require(HESK_PATH . 'inc/users_online.inc.php');
                hesk_initOnline($_SESSION['id']);
            }

        	return true;
        }

		hesk_session_stop();
        header('Location: '.$url);
        exit();
    }
    else
    {
        // hesk_session_regenerate_id();

		// Let's make sure access data is up-to-date
        $res = hesk_dbQuery( "SELECT `id`,`user`, `pass`, `isadmin`, `categories`, `heskprivileges`, `signature`, `show_customer_email`, `show_bookmarks`, `mfa_enrollment` FROM `".$hesk_settings['db_pfix']."users` WHERE `id` = '".intval($_SESSION['id'])."' AND `active` = 1 AND (`pass_expires_at` IS NULL OR `pass_expires_at` > NOW()) LIMIT 1" );

		// Exit if user not found
		if (hesk_dbNumRows($res) != 1)
		{
			hesk_session_stop();
			header('Location: '.$url);
			exit();
		}

		// Fetch results from database
		$me = hesk_dbFetchAssoc($res);

		// Verify this session is still valid
		if ( ! hesk_activeSessionValidate($me['user'], $me['pass'], $_SESSION['session_verify']) )
		{
			hesk_session_stop();
			header('Location: '.$url);
			exit();
		}

		// Update session variables as needed
		if ($me['isadmin'] == 1)
		{
			$_SESSION['isadmin'] = 1;
		}
		else
		{
			$_SESSION['isadmin'] = 0;
			$_SESSION['categories'] = explode(',', $me['categories']);
			$_SESSION['heskprivileges'] = $me['heskprivileges'];
            hesk_setPermissionGroupAccess($me['id']);
		}

        $_SESSION['signature'] = $me['signature'];
        $_SESSION['show_customer_email'] = $me['show_customer_email'];
        $_SESSION['show_bookmarks'] = $me['show_bookmarks'];
		$_SESSION['mfa_enrollment'] = intval($me['mfa_enrollment']);
		if (empty($_SESSION['mfa_enrollment']) && $hesk_settings['require_mfa'])
		{
			$_SESSION['mfa_enrollment'] = 1;
		}

		// Users online
		if ($hesk_settings['online'])
		{
			require(HESK_PATH . 'inc/users_online.inc.php');
            hesk_initOnline($_SESSION['id']);
		}

        return true;
    }

} // END hesk_isLoggedIn()

function hesk_setPermissionGroupAccess($user_id) {
    global $hesk_settings;

    // Set permissions obtained via permission groups
    $permission_group_categories_rs = hesk_dbQuery("SELECT DISTINCT `category_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_categories`
        WHERE `group_id` IN (
            SELECT `group_id` 
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members` 
            WHERE `user_id` = ".intval($user_id).
        ")");
    while ($row = hesk_dbFetchAssoc($permission_group_categories_rs)) {
        $_SESSION['categories'][] = $row['category_id'];
    }
    $_SESSION['categories'] = array_unique($_SESSION['categories']);
    $permission_group_features_rs = hesk_dbQuery("SELECT DISTINCT `feature` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_features`
        WHERE `group_id` IN (
            SELECT `group_id` 
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members` 
            WHERE `user_id` = ".intval($user_id).
        ")");
    $_SESSION['heskprivileges'] = explode(',', (string) $_SESSION['heskprivileges']);
    while ($row = hesk_dbFetchAssoc($permission_group_features_rs)) {
        $_SESSION['heskprivileges'][] = $row['feature'];
    }
    $_SESSION['heskprivileges'] = implode(',', array_unique($_SESSION['heskprivileges']));
}


function hesk_Pass2Hash($plaintext) {
    // This is a LEGACY function, only used to check and update legacy passwords
    // Use hesk_password_hash/hesk_password_verify functions instead!
    $majorsalt  = '';
    $len = strlen($plaintext);
    for ($i=0;$i<$len;$i++)
    {
        $majorsalt .= sha1(substr($plaintext,$i,1));
    }
    $corehash = sha1($majorsalt);
    return $corehash;
} // END hesk_Pass2Hash()


function hesk_formatDate($dt, $from_database=true)
{
    $dt=hesk_date($dt, $from_database);
	$dt=str_replace(' ','<br />',$dt);
    return $dt;
} // End hesk_formatDate()


function hesk_jsString($str)
{
    $str  = addslashes($str);
    $str  = str_replace('<br />' , '' , $str);
    $from = array("/\r\n|\n|\r/", '/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
    $to   = array("\\r\\n' + \r\n'", "$1", "$1");
    return preg_replace($from,$to,$str);
} // END hesk_jsString()


function hesk_myOwnership($consider_collaborators = false)
{
    // Admins can see all tickets
    if ( ! empty($_SESSION['isadmin']) )
    {
        return '1';
    }

    // For staff, let's check permissions
    $can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
    $can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
    $can_view_ass_by     = hesk_checkPermission('can_view_ass_by', 0);

    // Can view all tickets, regrdless of ownership
    if ($can_view_unassigned == 1 && $can_view_ass_others == 1) {
        return '1';
    }

    $sql_ownership = '';

    // Can view assigned to me + unassigned
    if ($can_view_unassigned == 1 && $can_view_ass_others == 0 && $can_view_ass_by == 0) {
        $sql_ownership .= " `owner` IN ('0', '" . intval($_SESSION['id']) . "') ";
    }

    // Can view assigned to me + unassigned + tickets I assigned to others
    elseif ($can_view_unassigned == 1 && $can_view_ass_others == 0 && $can_view_ass_by == 1) {
        $sql_ownership .= " (`owner` IN ('0', '" . intval($_SESSION['id']) . "') OR `assignedby` = " . intval($_SESSION['id']) . ") ";
    }

    // Can view assigned to me + assigned to others
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 1) {
        $sql_ownership .= " `owner` != 0 ";
    }

    // Can view assigned to me + tickets I assigned to others
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 0 && $can_view_ass_by == 1) {
        $sql_ownership .= " (`owner` = " . intval($_SESSION['id']) . " OR (`owner` != 0 AND `assignedby` = " . intval($_SESSION['id']) . ")) ";
    }

    // Can only view assigned to me
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 0 && $can_view_ass_by == 0) {
        $sql_ownership .= " `owner` = " . intval($_SESSION['id']) . " ";
    }

    // Must be an internal error
    else {
        die('Invalid view attempt (1)');
    }

    // Add a collaborator check for certain use cases
    if ($consider_collaborators) {
        return " ($sql_ownership OR `w`.`user_id`= " . intval($_SESSION['id']) . ") ";
    } else {
        return $sql_ownership;
    }

} // END hesk_myOwnership()


function hesk_myCategories($what='category')
{
    global $hesk_settings;

    if ( ! empty($_SESSION['isadmin']) )
    {
        return '1';
    }
    else
    {
        $categories = array_map('intval', $_SESSION['categories']);
        $sql = " `".hesk_dbEscape($what)."` IN ('" . implode("','", $categories) . "')";

        // Deleted ticket categories require access to the default category
        if ($what == 'category' && !in_array(1, $categories, true))
        {
            $sql .= " AND `category` IN (SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories`)";
        }

        return $sql;
    }
} // END hesk_myCategories()


function hesk_verifyStaffTicketAccess($trackingID, $ticket = null, $failure_callback = null)
{
    global $hesk_settings, $hesklang;

    if ($failure_callback === null && function_exists('hesk_hook_permission_failure'))
    {
        $failure_callback = function($failure_reason) use ($trackingID) {
            hesk_hook_permission_failure($failure_reason, array(
                'type' => 'ticket',
                'secondary_id' => $trackingID
            ));
        };
    }

    // Staff must be allowed to view tickets in general
    hesk_checkPermission('can_view_tickets', 1, $failure_callback);

    // Use an already-loaded ticket when possible to avoid another query
    if ( ! is_array($ticket) ||
         ! isset($ticket['id'], $ticket['trackid'], $ticket['category'], $ticket['owner']) ||
         ! array_key_exists('assignedby', $ticket) ||
         $ticket['trackid'] != $trackingID)
    {
        $res = hesk_dbQuery("SELECT `id`, `trackid`, `category`, `owner`, `assignedby` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");

        if (hesk_dbNumRows($res) != 1)
        {
            hesk_error($hesklang['ticket_not_found']);
        }

        $ticket = hesk_dbFetchAssoc($res);
    }
    $collaborators = hesk_getTicketsCollaboratorIDs($ticket['id']);
    $am_I_collaborator = in_array($_SESSION['id'], $collaborators);

    // Permission to view this ticket based on owner / assignment / collaboration
    if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! hesk_checkPermission('can_view_ass_others', 0) && ! $am_I_collaborator)
    {
        // Maybe this user is allowed to view tickets he/she assigned?
        if ( ! hesk_checkPermission('can_view_ass_by', 0) || $ticket['assignedby'] != $_SESSION['id'])
        {
            if ($failure_callback) {
                $failure_callback('feature|can_view_ass_by');
            }
            hesk_error($hesklang['ycvtao']);
        }
    }

    if ( ! $ticket['owner'] && ! hesk_checkPermission('can_view_unassigned', 0) && ! $am_I_collaborator)
    {
        if ($failure_callback) {
            $failure_callback('feature|can_view_unassigned');
        }
        hesk_error($hesklang['ycovtay']);
    }

    // Get category ID; if this category has been deleted use the default category with ID 1
    $result = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($ticket['category'])."' LIMIT 1");
    if (hesk_dbNumRows($result) != 1)
    {
        $result = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='1' LIMIT 1");
    }

    $category = hesk_dbFetchAssoc($result);

    // Is this user allowed to view tickets inside this category?
    if (!hesk_okCategory($category['id'], 0)) {
        if ($failure_callback) {
            $failure_callback('category|'.$category['id']);
        }
        hesk_error($hesklang['not_authorized_tickets']);
    }

    return $ticket;

} // END hesk_verifyStaffTicketAccess()


function hesk_okCategory($cat,$error=1,$user_isadmin=false,$user_cat=false)
{
	global $hesklang;

	/* Checking for current user or someone else? */
    if ($user_isadmin === false)
    {
		$user_isadmin = $_SESSION['isadmin'];
    }

    if ($user_cat === false)
    {
		$user_cat = $_SESSION['categories'];
    }

    /* Is admin? */
    if ($user_isadmin)
    {
        return true;
    }
    /* Staff with access? */
    elseif (in_array($cat,$user_cat))
    {
        return true;
    }
    /* No access */
    else
    {
        if ($error)
        {
        	hesk_error($hesklang['not_authorized_tickets']);
        }
        else
        {
        	return false;
        }
    }

} // END hesk_okCategory()


function hesk_checkPermission($feature, $showerror = 1, $failure_callback = null) {
	global $hesklang;

    /* Admins have full access to all features */
    if ( isset($_SESSION['isadmin']) && $_SESSION['isadmin'])
    {
        return true;
    }

    /* Check other staff for permissions */
    if ( ! isset($_SESSION['heskprivileges']) || strpos($_SESSION['heskprivileges'], $feature) === false)
    {
        if ($failure_callback) {
            $failure_callback('feature|'.$feature);
        }
    	if ($showerror)
        {
        	hesk_error($hesklang['no_permission'].'<p>&nbsp;</p><p align="center"><a href="index.php">'.$hesklang['click_login'].'</a>');
        }
        else
        {
        	return false;
        }
    }
    else
    {
        return true;
    }

} // END hesk_checkPermission()


function hesk_purge_cache($type = '', $expire_after_seconds = 0)
{
    global $hesk_settings;

    if ( function_exists('opcache_reset') )
    {
        opcache_reset();
    }

    $cache_dir = dirname(dirname(__FILE__)).'/'.$hesk_settings['cache_dir'].'/';

    if ( ! is_dir($cache_dir))
    {
        return false;
    }

    switch ($type)
    {
        case 'export':
            $files = glob($cache_dir.'hesk_export_*', GLOB_NOSORT);
            break;
        case 'status':
            $files = glob($cache_dir.'status_*', GLOB_NOSORT);
            break;
        case 'priority':
            $files = glob($cache_dir.'priority_*', GLOB_NOSORT);
            break;
        case 'cf':
            $files = glob($cache_dir.'cf_*', GLOB_NOSORT);
            break;
        case 'kb':
            $files = array($cache_dir.'kb.cache.php');
            break;
        case 'hooks':
            $files = array($cache_dir.'hooks.cache.php');
            break;
        default:
            hesk_purge_cache('export');
            hesk_rrmdir(rtrim($cache_dir, '/'), true);
            return true;
    }

    if (is_array($files))
    {
        foreach ($files as $file)
        {
            if (hesk_unlink($file, $expire_after_seconds) && $type === 'export' && function_exists('hesk_hook_ticket_export_deleted')) {
                hesk_hook_ticket_export_deleted(preg_replace('/\.zip$/i', '', basename($file)), true, array(
                    'actor_type' => 'SYSTEM',
                    'user_id' => null,
                    'auth_method' => 'NONE'
                ));
            }
        }
    }

    return true;

} // END hesk_purge_cache()


function hesk_rrmdir($dir, $keep_top_level=false)
{
    if ( ! is_dir($dir)) {
        return false;
    }

	$files = $keep_top_level ? array_diff(scandir($dir), array('.','..','index.htm','.htaccess')) : array_diff(scandir($dir), array('.','..'));

    foreach ($files as $file)
    {
        (is_dir("$dir/$file")) ? hesk_rrmdir("$dir/$file") : @unlink("$dir/$file");
    }

    return $keep_top_level ? true : @rmdir($dir);

} // END hesk_rrmdir()


function hesk_deleteTicketsForCustomer($customer_id) {
    global $hesk_settings;

    $sql = "SELECT `tickets`.`id`, `tickets`.`trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
            ON `ticket_to_customer`.`ticket_id` = `tickets`.`id`
        WHERE `ticket_to_customer`.`customer_id` = ".intval($customer_id)."
        AND `ticket_to_customer`.`customer_type` = 'REQUESTER'";

    $tickets_rs = hesk_dbQuery($sql);
    while ($ticket = hesk_dbFetchAssoc($tickets_rs)) {
        hesk_fullyDeleteTicket($ticket['id'], $ticket['trackid']);
    }
}


function hesk_fullyDeleteTicket($ticket_id, $ticket_trackid)
{
    global $hesk_settings;

    /* Count dependent records before deleting the ticket */
    $attachment_count = 0;
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket_trackid)."'");
    if (hesk_dbNumRows($res))
    {
        $hesk_settings['server_path'] = dirname(dirname(__FILE__));
        while ($file = hesk_dbFetchAssoc($res))
        {
            $attachment_count++;
            hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
        }
    }

    $ticket_customer_ids = array();
    $res = hesk_dbQuery("SELECT `customer_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `ticket_id` = ".intval($ticket_id));
    while ($row = hesk_dbFetchAssoc($res)) {
        $ticket_customer_ids[] = intval($row['customer_id']);
    }

    $reply_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket_id)."'")));
    $note_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($ticket_id)."'")));
    $bookmark_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` WHERE `ticket_id` = ".intval($ticket_id))));
    $collaborator_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `ticket_id` = ".intval($ticket_id))));
    $linked_ticket_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE `ticket_id1` = ".intval($ticket_id)." OR `ticket_id2` = ".intval($ticket_id))));
    $email_mapping_count = intval(hesk_dbResult(hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."email_id_to_ticket` WHERE `ticket_id` = ".intval($ticket_id))));

    /* Delete the ticket and dependent records */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket_trackid)."'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($ticket_id)."'");
    if (hesk_dbAffectedRows() != 1) {
        return false;
    }
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket_id)."'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($ticket_id)."'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `ticket`=".intval($ticket_id));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `ticket_id` = ".intval($ticket_id));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` WHERE `ticket_id` = ".intval($ticket_id));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `ticket_id` = ".intval($ticket_id));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE `ticket_id1` = ".intval($ticket_id)." OR `ticket_id2` = ".intval($ticket_id));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."email_id_to_ticket` WHERE `ticket_id` = ".intval($ticket_id));

    hesk_hook_tickets_deleted([
        [
            'id' => $ticket_id,
            'trackid' => $ticket_trackid,
            'customer_ids' => array_values(array_unique($ticket_customer_ids)),
            'reply_count' => $reply_count,
            'note_count' => $note_count,
            'attachment_count' => $attachment_count,
            'bookmark_count' => $bookmark_count,
            'collaborator_count' => $collaborator_count,
            'linked_ticket_count' => $linked_ticket_count,
            'email_mapping_count' => $email_mapping_count
        ]
    ], true);
    return true;
}


function hesk_isTicketBookmarked($ticket_id, $user_id)
{
    global $hesk_settings, $hesklang, $hesk_db_link;

    $result = hesk_dbQuery('SELECT `id` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'bookmarks` WHERE `ticket_id`='.intval($ticket_id).' AND `user_id`='.intval($user_id).' LIMIT 1');

    return hesk_dbNumRows($result);
} // END hesk_isTicketBookmarked()


function hesk_json_exit($status = 'Error', $data = '') {
    $json_data = [
        'status' => $status,
        'data'   => $data,
    ];
    echo json_encode($json_data);
    exit;
} // END hesk_json_exit()


function hesk_mergeCustomers($merge_these, $merge_into)
{
    global $hesk_settings, $hesklang, $hesk_db_link;

    // Target customer must not be in the "merge these" list
    if ( in_array($merge_into, $merge_these) ) {
        $merge_these = array_diff($merge_these, array( $merge_into ) );
    }

    // At least 1 customer needs to be merged with target customer
    if ( count($merge_these) < 1 ) {
        $_SESSION['error'] = $hesklang['merge_more_error'];
        return false;
    }

    $merge_into = intval($merge_into);

    // Make sure target customer exists
    $res = hesk_dbQuery("SELECT `id`, `name`, `email` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id`={$merge_into} LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        $_SESSION['error'] = $hesklang['merge_target_error'];
        return false;
    }
    $destination_identity = hesk_hooks_get_identity(hesk_dbFetchAssoc($res));
    $merged_customers = array();
    $tickets_affected = 0;
    $replies_affected = 0;
    $customers_deleted = 0;
    $merged_customer_ids = array();
    foreach ($merge_these as $this_id)
    {
        // Validate ID
        if ( is_array($this_id) ) {
            continue;
        }
        $this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

        $source_customer_rs = hesk_dbQuery("SELECT `id`, `name`, `email` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id`={$this_id} LIMIT 1");
        if (hesk_dbNumRows($source_customer_rs) != 1) {
            continue;
        }
        $source_customer = hesk_dbFetchAssoc($source_customer_rs);
        // Update customer tickets to new customer
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` SET `customer_id`={$merge_into} WHERE `customer_id`={$this_id}");
        $tickets_affected += hesk_dbAffectedRows();

        // Migrate ticket replies to new customer
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `customer_id`={$merge_into} WHERE `customer_id`={$this_id}");
        $replies_affected += hesk_dbAffectedRows();

        // Remove old customer information
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id`={$this_id}");
        if (hesk_dbAffectedRows() == 1) {
            hesk_prune_password_history($this_id, 0, 0);
            $customers_deleted++;
            $merged_customer_ids[] = $this_id;
            $merged_customers[] = array('id' => $this_id) + hesk_hooks_get_identity($source_customer);
        }
    }
    hesk_hook_customers_merged([
        'id' => $merge_into,
        'tickets_affected' => $tickets_affected,
        'replies_affected' => $replies_affected,
        'customers_deleted' => $customers_deleted,
        'merged_customer_ids' => $merged_customer_ids,
        'destination_identity' => $destination_identity,
        'merged_customers' => $merged_customers
    ], true);

    return true;
} // END hesk_mergeCustomers()


function hesk_getUserIdsWithAccessToFeatureAndCategory($feature = null, $category = null, $include_inactive = false)
{
    global $hesk_settings;

    // Need to provide at least one of the two
    if ($feature === null && $category === null) {
        return [];
    }

    $access_parts = [];

    if ($feature !== null) {
        $feature = hesk_dbEscape($feature);
        $access_parts[] = "(
            FIND_IN_SET('{$feature}', `user`.`heskprivileges`) > 0
            OR EXISTS (
                SELECT 1
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members` AS `member_feature`
                INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_features` AS `feature`
                    ON `member_feature`.`group_id` = `feature`.`group_id`
                WHERE `user`.`id` = `member_feature`.`user_id`
                    AND `feature`.`feature` = '{$feature}'
            )
        )";
    }

    if ($category !== null) {
        $category = intval($category);
        $access_parts[] = "(
            FIND_IN_SET('{$category}', `user`.`categories`) > 0
            OR EXISTS (
                SELECT 1
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members` AS `member_category`
                INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_categories` AS `category`
                    ON `member_category`.`group_id` = `category`.`group_id`
                WHERE `user`.`id` = `member_category`.`user_id`
                    AND `category`.`category_id` = {$category}
            )
        )";
    }

    $access_sql = implode(' AND ', $access_parts);
    $active_sql = $include_inactive ? '' : '`active` = 1 AND ';

    $users_rs = hesk_dbQuery("SELECT `user`.`id`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `user`
        WHERE {$active_sql}(`isadmin` = '1' OR ({$access_sql}))");
    $users = [];
    while ($row = hesk_dbFetchAssoc($users_rs)) {
        $users[] = intval($row['id']);
    }

    return $users;
} // END hesk_getUserIdsWithAccessToFeatureAndCategory()


function hesk_removeGroupTicketAccess($user_ids, $force = false, $keep_resolved = false, $ticket_ids = null)
{
    global $hesk_settings, $hesklang;

    $counts = array(0, 0);
    $user_ids = array_filter(array_map('intval', $user_ids));
    if (!count($user_ids)) {
        return $counts;
    }
    $ticket_sql = '';
    $collaborator_sql = '';
    if ($ticket_ids !== null) {
        $ticket_ids = array_filter(array_map('intval', $ticket_ids), function($id) { return $id > 0; });
        if (!count($ticket_ids)) {
            return $counts;
        }
        $ticket_sql = " AND `id` IN (".implode(',', $ticket_ids).")";
        $collaborator_sql = " AND `t`.`id` IN (".implode(',', $ticket_ids).")";
    }
    $open_sql = $keep_resolved ? " AND `status` <> '3'" : '';
    $affected_users = implode(',', $user_ids);
    $categories_rs = hesk_dbQuery("SELECT DISTINCT `category` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
        WHERE (`owner` IN ({$affected_users}) OR `id` IN (
            SELECT `ticket_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `user_id` IN ({$affected_users})
        )){$ticket_sql}");
    while ($category = hesk_dbFetchAssoc($categories_rs)) {
        $removed_users = $force ? $user_ids : array_diff($user_ids, hesk_getUserIdsWithAccessToFeatureAndCategory('can_view_tickets', $category['category'], true));
        if (!count($removed_users)) {
            continue;
        }
        $removed_users = implode(',', $removed_users);
        $collaborators_rs = hesk_dbQuery("SELECT `t`.`id`, `t`.`trackid`, `t`.`status`, `c`.`user_id`, `u`.`name`, `u`.`user`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `c`
            JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t` ON `c`.`ticket_id` = `t`.`id`
            JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `u` ON `c`.`user_id` = `u`.`id`
            WHERE `c`.`user_id` IN ({$removed_users}) AND `t`.`category` = ".intval($category['category']).$collaborator_sql);
        while ($collaborator = hesk_dbFetchAssoc($collaborators_rs)) {
            hesk_dbQuery("DELETE `c` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `c` JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t` ON `c`.`ticket_id` = `t`.`id`
                WHERE `c`.`ticket_id`=".intval($collaborator['id'])." AND `c`.`user_id`=".intval($collaborator['user_id'])." AND `t`.`category`=".intval($category['category']));
            if (hesk_dbAffectedRows() == 1) {
                $counts[1]++;
                $revision = sprintf($hesklang['thist25'], hesk_date(), addslashes($collaborator['name']).' ('.$collaborator['user'].')', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
                $lastchange_sql = $collaborator['status'] == 3 ? ', `lastchange`=`lastchange`' : '';
                hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."')".$lastchange_sql." WHERE `id`=".intval($collaborator['id']));
                hesk_hook_ticket_collaborator_removed($collaborator, intval($collaborator['user_id']), true);
            }
        }
        $tickets_rs = hesk_dbQuery("SELECT `id`, `trackid`, `owner`, `assignedby`, `status` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            WHERE `owner` IN ({$removed_users}) AND `category` = ".intval($category['category']).$ticket_sql.$open_sql);
        $revision = sprintf($hesklang['thist2'], hesk_date(), '<i>'.$hesklang['unas'].'</i>', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
        while ($ticket = hesk_dbFetchAssoc($tickets_rs)) {
            $lastchange_sql = $ticket['status'] == 3 ? ', `lastchange`=`lastchange`' : '';
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0, `assignedby`=NULL, `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."')".$lastchange_sql." WHERE `id`=".intval($ticket['id'])." AND `owner`=".intval($ticket['owner'])." AND `category`=".intval($category['category']).$open_sql);
            if (hesk_dbAffectedRows() == 1) {
                $counts[0]++;
                $changes = array('id' => $ticket['id'], 'trackid' => $ticket['trackid'], 'owner' => 0);
                if ($ticket['assignedby'] !== null) {
                    $changes['assignedby'] = null;
                }
                hesk_hook_ticket_updated($changes, true);
            }
        }
    }
    return $counts;
}


function hesk_getCategoriesForUser($user_id)
{
    global $hesk_settings;

    $categories = [];
    $sanitized_user_id = intval($user_id);
    $base_categories_rs = hesk_dbQuery("SELECT `categories` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='{$sanitized_user_id}' AND `active` = 1 LIMIT 1");
    while ($row = hesk_dbFetchAssoc($base_categories_rs)) {
        $categories = explode(',', $row['categories']);
    }
    $categories = array_map(function($category) { return intval($category); }, $categories);

    $pg_categories = hesk_dbQuery("SELECT DISTINCT `category_id`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_categories`
        WHERE `group_id` IN (
            SELECT `group_id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members`
            WHERE `user_id` = {$sanitized_user_id}
        )");
    while ($row = hesk_dbFetchAssoc($pg_categories)) {
        $category_id = intval($row['category_id']);
        if (!in_array($category_id, $categories)) {
            $categories[] = $category_id;
        }
    }

    return $categories;
} // END hesk_getCategoriesForUser()


function hesk_getFeaturesForUser($user_id)
{
    global $hesk_settings;

    $features = [];
    $sanitized_user_id = intval($user_id);
    $base_features_rs = hesk_dbQuery("SELECT `heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='{$sanitized_user_id}' AND `active` = 1 LIMIT 1");
    while ($row = hesk_dbFetchAssoc($base_features_rs)) {
        $features = explode(',', $row['heskprivileges']);
    }

    $pg_features = hesk_dbQuery("SELECT DISTINCT `feature`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_features`
        WHERE `group_id` IN (
            SELECT `group_id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."permission_group_members`
            WHERE `user_id` = {$sanitized_user_id}
        )");
    while ($row = hesk_dbFetchAssoc($pg_features)) {
        if (!in_array($row['feature'], $features)) {
            $features[] = $row['feature'];
        }
    }

    return $features;
} // END hesk_getFeaturesForUser()


/**
 * Extract staff mentions and add eligible users as ticket collaborators.
 * Callers must validate ticket access and save the message before calling.
 * Returns users notified here, to exclude from subsequent reply notifications.
 */
function hesk_process_mentions($message_text, $ticket_id, $ticket_category, $is_html = false, $original_message = null)
{
    global $hesk_settings, $hesklang;

    if (!hesk_checkPermission('can_assign_others', 0)) {
        return array();
    }

    $ticket_id = intval($ticket_id);
    $ticket_category = intval($ticket_category);
    $notified = array();
    $selected_mentions = array(array(), array());
    foreach (array($message_text, $original_message) as $index => $text) {
        if ($text !== null && preg_match_all('/<span\b[^>]*\s+id=(["\'])hesk-mention-([0-9]+)(?:-[a-z0-9]+)*\1[^>]*>.*?<\/span>/is', $text, $matches)) {
            foreach ($matches[2] as $id) {
                $selected_mentions[$index][intval($id)] = true;
            }
        }
    }
    if (empty($selected_mentions[0])) {
        return $notified;
    }

    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`={$ticket_id} LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        return $notified;
    }
    $notify_ticket = hesk_dbFetchAssoc($res);
    $ticket_category = intval($notify_ticket['category']);
    $eligible_ids = hesk_getUserIdsWithAccessToFeatureAndCategory('can_view_tickets', $ticket_category);

    if (empty($eligible_ids)) {
        return $notified;
    }
    // Selected IDs must still pass the same active-user and category access checks.
    $users = hesk_dbQuery("SELECT `id`, `user`, `name`, `notify_collaborator_added`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users`
        ORDER BY `id`");
    while ($user = hesk_dbFetchAssoc($users)) {
        $user_id = intval($user['id']);
        $mentioned = isset($selected_mentions[0][$user_id]);
        $was_mentioned = isset($selected_mentions[1][$user_id]);

        if (!$mentioned || $was_mentioned || !in_array($user_id, $eligible_ids) || $user_id == intval($_SESSION['id']) || $user_id == intval($notify_ticket['owner'])) {
            continue;
        }

        // Match the manual collaborator action, including duplicate and hook handling.
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` (`ticket_id`, `user_id`)
            SELECT {$ticket_id}, {$user_id} FROM DUAL WHERE NOT EXISTS (
                SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator`
                WHERE `ticket_id`={$ticket_id} AND `user_id`={$user_id}
            )");
        if (hesk_dbAffectedRows() != 1) {
            continue;
        }
        hesk_hook_ticket_collaborator_added($notify_ticket, $user_id, true);

        $revision = sprintf($hesklang['thist24'], hesk_date(),
            addslashes($user['name']).' ('.$user['user'].')',
            addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            SET `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`={$ticket_id}");

        if ($user['notify_collaborator_added']) {
            if (!function_exists('hesk_notifyAssignedStaff')) {
                require(HESK_PATH . 'inc/email_functions.inc.php');
            }
            if (!function_exists('hesk_get_customers_for_ticket')) {
                require(HESK_PATH . 'inc/customer_accounts.inc.php');
            }

            $customers = hesk_get_customers_for_ticket($ticket_id);
            $info = array(
                'email'         => implode(';', array_map(function($c) { return $c['email']; }, $customers)),
                'language'      => $notify_ticket['language'],
                'category'      => $notify_ticket['category'],
                'priority'      => $notify_ticket['priority'],
                'owner'         => $notify_ticket['owner'],
                'collaborators' => hesk_getTicketsCollaboratorIDs($ticket_id),
                'trackid'       => $notify_ticket['trackid'],
                'status'        => $notify_ticket['status'],
                'name'          => implode(';', array_map(function($c) { return $c['name']; }, $customers)),
                'subject'       => $notify_ticket['subject'],
                'message'       => $notify_ticket['message'],
                'message_html'  => $notify_ticket['message_html'],
                'attachments'   => $notify_ticket['attachments'],
                'dt'            => hesk_date($notify_ticket['dt'], true),
                'lastchange'    => hesk_date($notify_ticket['lastchange'], true),
                'due_date'      => hesk_format_due_date($notify_ticket['due_date']),
                'id'            => $notify_ticket['id'],
                'time_worked'   => $notify_ticket['time_worked'],
                'last_reply_by' => hesk_getReplierNameArray($notify_ticket),
            );
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                $info[$k] = $v['use'] ? $notify_ticket[$k] : '';
            }

            // Use the normal recipient lookup so the owner cache retains the actual owner.
            $exclude_users = array_diff(array_merge(array($info['owner']), $info['collaborators']), array($user_id));
            global $ticket;
            $ticket_backup = $ticket;
            $ticket = hesk_ticketToPlain($info, 1, 0);
            hesk_notifyAssignedStaff(false, 'collaborator_added', 'notify_collaborator_added', 'notify_collaborator_added', $exclude_users);
            $ticket = $ticket_backup;
            $notified[] = $user_id;
        }
    }
    return $notified;
} // END hesk_process_mentions()
