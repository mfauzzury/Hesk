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
require(HESK_PATH . 'inc/posting_functions.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/hooks.inc.php');

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Ticket ID */
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets', 1, function($failure_reason) use ($trackingID) {
    if (isset($_POST['save'])) {
        hesk_hook_ticket_updated([
            'trackid' => $trackingID
        ], $failure_reason);
    } else {
        hesk_hook_ticket_viewed([
            'trackid' => $trackingID
        ], $failure_reason);
    }
});
hesk_checkPermission('can_edit_tickets');

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load calendar JS and CSS
define('CALENDAR',1);
define('ATTACHMENTS',1);

if ($hesk_settings['staff_ticket_formatting'] == 2) {
    define('WYSIWYG',1);
}

$hesk_settings['datepicker'] = array();
$is_reply = 0;
$tmpvar = array();

if (!isset($_SESSION['iserror']))
{
	$_SESSION['iserror'] = array();
}

/* Get ticket info */
$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
if (hesk_dbNumRows($result) != 1)
{
	hesk_error($hesklang['ticket_not_found']);
}
$ticket = hesk_dbFetchAssoc($result);

$customers = hesk_get_customers_for_ticket($ticket['id']);

// Demo mode
if ( defined('HESK_DEMO') )
{
	foreach ($customers as $customer) {
        $customer['email'] = 'hidden@demo.com';
    }
}
$is_reply = hesk_isREQUEST('reply');

/* Is this user allowed to view and edit this ticket? */
hesk_verifyStaffTicketAccess($trackingID, $ticket, function($failure_reason) use ($trackingID, $is_reply, $ticket) {
    if (isset($_POST['save'])) {
        if ($is_reply) {
            hesk_hook_ticket_reply_updated([
                'replyto' => $ticket['id'],
                'trackid' => $trackingID,
                'id' => intval( hesk_POST('reply') )
            ], $failure_reason);
        } else {
            hesk_hook_ticket_updated([
                    'trackid' => $trackingID
            ], $failure_reason);
        }
    } else {
        hesk_hook_ticket_viewed([
            'trackid' => $trackingID
        ], $failure_reason);
    }
});

if ( hesk_isREQUEST('reply') )
{
	$tmpvar['id'] = intval( hesk_REQUEST('reply') ) or die($hesklang['id_not_valid']);

	$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`='{$tmpvar['id']}' AND `replyto`='".intval($ticket['id'])."' LIMIT 1");
	if (hesk_dbNumRows($result) != 1)
    {
    	hesk_error($hesklang['id_not_valid']);
    }
    $reply = hesk_dbFetchAssoc($result);
    $ticket['message'] = $reply['message'];
    $ticket['message_html'] = $reply['message_html'];
}

if (!isset($_POST['save'])) {
    if ($is_reply) {
        hesk_hook_ticket_reply_edit_form_viewed(array(
            'id' => $reply['id'],
            'replyto' => $ticket['id'],
            'trackid' => $ticket['trackid']
        ), true);
    } else {
        hesk_hook_ticket_edit_form_viewed($ticket, true);
    }
}

// Count number of existing attachments for this post
$number_of_attachments = $is_reply ? hesk_countAttachments($reply['attachments']) : hesk_countAttachments($ticket['attachments']);

if (isset($_POST['save']))
{
    $_SESSION['suppress_ticket_view_hook'] = true;
	/* A security check */
	hesk_token_check('POST');

	$hesk_error_buffer = array();

    // Add attachments?
    $use_legacy_attachments = hesk_POST('use-legacy-attachments', 0);
    if ($hesk_settings['attachments']['use'] && $number_of_attachments < $hesk_settings['attachments']['max_number'])
    {
        require(HESK_PATH . 'inc/attachments.inc.php');
        $attachments = array();

        if ($use_legacy_attachments) {
            for ($i = $number_of_attachments + 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                $att = hesk_uploadFile($i);
                if ($att !== false && !empty($att)) {
                    $attachments[$i] = $att;
                }
            }
        } else {
            // The user used the new drag-and-drop system.
            $temp_attachment_ids = hesk_POST_array('attachments');
            foreach ($temp_attachment_ids as $temp_attachment_id) {
                $temp_attachment = hesk_getTemporaryAttachment($temp_attachment_id);

                if ($temp_attachment !== null) {
                    $attachments[] = $temp_attachment;
                }
            }
        }
    }
    $myattachments = '';

    if ($is_reply)
    {
		$tmpvar['message'] = hesk_input( hesk_POST('message') ) or $hesk_error_buffer[]=$hesklang['enter_message'];
        $tmpvar['message_html'] = $tmpvar['message'];
        if ($hesk_settings['staff_ticket_formatting'] == 2) {
            // Decode the message we encoded earlier
            $tmpvar['message_html'] = hesk_html_entity_decode($tmpvar['message_html']);

            // Clean the HTML code and set the plaintext version
            require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
            require(HESK_PATH . 'inc/html2text/html2text.php');
            $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
            $tmpvar['message_html'] = $purifier->heskPurify($tmpvar['message_html']);

            $tmpvar['message'] = hesk_format_mentions($tmpvar['message_html'], false, true);
            $tmpvar['message'] = fix_newlines($tmpvar['message']);

            // Prepare plain message for storage as HTML
            $tmpvar['message'] = hesk_format_mentions(hesk_htmlspecialchars($tmpvar['message']));
            $tmpvar['message'] = nl2br($tmpvar['message']);
        } else {
            // `message` already contains a HTML friendly version. May as well just re-use it
            $tmpvar['message'] = hesk_makeURL($tmpvar['message']);
            $tmpvar['message'] = nl2br($tmpvar['message']);
            $tmpvar['message_html'] = $tmpvar['message'];
        }


        if (count($hesk_error_buffer))
	    {
            // Remove any successfully uploaded attachments
            if ($hesk_settings['attachments']['use'] && isset($attachments))
            {
                if ($use_legacy_attachments) {
                    hesk_removeAttachments($attachments);
                } else {
                    $_SESSION['edit_attachments'] = $attachments;
                }

            }

	    	$myerror = '<ul>';
		    foreach ($hesk_error_buffer as $error)
		    {
		        $myerror .= "<li>$error</li>\n";
		    }
	        $myerror .= '</ul>';
	    	hesk_error($myerror);
	    }

        if ($hesk_settings['attachments']['use'] && !empty($attachments))
        {
            if (!$use_legacy_attachments) {
                $attachments = hesk_migrateTempAttachments($attachments, $trackingID);
            }

            foreach ($attachments as $myatt)
            {
                hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('".hesk_dbEscape($trackingID)."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."')");
                $myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
                hesk_hook_ticket_reply_attachment_created([
                    'id' => hesk_dbInsertID(),
                    'ticket_id' => $trackingID,
                    'real_name' => $myatt['real_name']
                ], true);
            }
        }

        $new_reply_values = array(
            'message' => $tmpvar['message'],
            'message_html' => $tmpvar['message_html']
        );
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `message`='".hesk_dbEscape($tmpvar['message'])."', `message_html`='".hesk_dbEscape($tmpvar['message_html'])."', `attachments`=CONCAT(`attachments`, '".hesk_dbEscape($myattachments)."') WHERE `id`='".intval($tmpvar['id'])."' AND `replyto`='".intval($ticket['id'])."'");
        hesk_hook_ticket_reply_updated(array(
            'id' => $tmpvar['id'],
            'replyto' => $ticket['id'],
            'message' => $tmpvar['message'],
            'message_html' => $tmpvar['message_html'],
            'changes' => hesk_hooks_get_changed_fields($reply, $new_reply_values, array(), array(), array('message', 'message_html'))
        ), true);

        // Process newly added @mentions in the edited reply
        hesk_process_mentions($hesk_settings['staff_ticket_formatting'] == 2 ? stripslashes($tmpvar['message_html']) : hesk_POST('message'), $ticket['id'], $ticket['category'], $hesk_settings['staff_ticket_formatting'] == 2, $hesk_settings['staff_ticket_formatting'] == 2 ? $reply['message_html'] : hesk_msgToPlain($reply['message'], 1, 0, true));
    }
    else
    {
        $tmpvar['customer_id'] = hesk_POST('customer_id') or $hesk_error_buffer[]=$hesklang['customer_required'];
        $tmpvar['follower_ids'] = hesk_POST_array('follower_id');

        $existing_requester_id = 0;
        $existing_follower_ids = array();
        foreach ($customers as $customer) {
            if ($customer['customer_type'] === 'REQUESTER') {
                $existing_requester_id = intval($customer['id']);
            } elseif ($customer['customer_type'] === 'FOLLOWER') {
                $existing_follower_ids[] = intval($customer['id']);
            }
        }

        // Existing historical assignments remain editable, but a new assignment
        // must respect customer-account email ownership.
        if ($tmpvar['customer_id'] &&
            intval($tmpvar['customer_id']) !== $existing_requester_id &&
            hesk_get_selectable_customer_by_id($tmpvar['customer_id']) === null) {
            $hesk_error_buffer[] = $hesklang['customer_required'];
        }

        $actual_followers = array();
        foreach ($tmpvar['follower_ids'] as $follower_id) {
            $follower_id_int = intval($follower_id);

            if ($follower_id_int === intval($tmpvar['customer_id']) ||
                in_array($follower_id_int, $actual_followers)) {
                continue;
            }

            $is_existing_follower = in_array($follower_id_int, $existing_follower_ids);
            $follower_record = $is_existing_follower ?
                hesk_get_customer_account_by_id($follower_id_int) :
                hesk_get_selectable_customer_by_id($follower_id_int);

            if ($follower_record && ($is_existing_follower ||
                ($follower_record['email'] && ! hesk_isBannedEmail($follower_record['email'])))) {
                $actual_followers[] = $follower_id_int;
            }
        }
        $tmpvar['follower_ids'] = $actual_followers;

        // Set Ticket Language
        if (($tmpvar['set_language'] = hesk_input( hesk_POST('set_language') ))) {
            $language_SQL = "`language`='".hesk_dbEscape($tmpvar['set_language'])."',";
        } else {
            $language_SQL = '';
        }

		$tmpvar['subject'] = hesk_input( hesk_POST('subject') ) or $hesk_error_buffer[]=$hesklang['enter_ticket_subject'];
		$tmpvar['message'] = hesk_input( hesk_POST('message') );
        $tmpvar['message_html'] = $tmpvar['message'];
        if ($hesk_settings['require_message'] == 1 && $tmpvar['message'] == '')
        {
            $hesk_error_buffer[] = $hesklang['enter_message'];
        }
        if ($hesk_settings['staff_ticket_formatting'] == 2) {
            // Decode the message we encoded earlier
            $tmpvar['message_html'] = hesk_html_entity_decode($tmpvar['message_html']);

            // Clean the HTML code and set the plaintext version
            require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
            require(HESK_PATH . 'inc/html2text/html2text.php');
            $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
            $tmpvar['message_html'] = $purifier->heskPurify($tmpvar['message_html']);

            $tmpvar['message'] = hesk_format_mentions($tmpvar['message_html'], false, true);
            $tmpvar['message'] = fix_newlines($tmpvar['message']);

            // Prepare plain message for storage as HTML
            $tmpvar['message'] = hesk_format_mentions(hesk_htmlspecialchars($tmpvar['message']));
            $tmpvar['message'] = nl2br($tmpvar['message']);
        } else {
            // `message` already contains a HTML friendly version. May as well just re-use it
            $tmpvar['message'] = hesk_makeURL($tmpvar['message']);
            $tmpvar['message'] = nl2br($tmpvar['message']);
            $tmpvar['message_html'] = $tmpvar['message'];
        }

        // Custom fields
        foreach ($hesk_settings['custom_fields'] as $k=>$v)
        {
            if ($v['use'])
            {
                // Don't overwrite existing not used fields, but don't require them either if not required by category
                if ( ! hesk_is_custom_field_in_category($k, $ticket['category'])) {
                    $v['req'] = 0;
                }

                if ($v['type'] == 'checkbox')
                {
                    $tmpvar[$k]='';

                    if (isset($_POST[$k]) && is_array($_POST[$k]))
                    {
                        foreach ($_POST[$k] as $myCB)
                        {
                            $tmpvar[$k] .= ( is_array($myCB) ? '' : hesk_input($myCB) ) . '<br />';;
                        }
                        $tmpvar[$k]=substr($tmpvar[$k],0,-6);
                    }
                    else
                    {
                        if ($v['req'] == 2)
                        {
                            $hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
                        }
                        $_POST[$k] = '';
                    }
                }
                elseif ($v['type'] == 'date')
                {
                    $tmpvar[$k] = hesk_POST($k);
                    $_SESSION["as_$k"] = '';

                    $date = strpos($tmpvar[$k], "\0") === false ? hesk_datepicker_get_date($tmpvar[$k], false, 'UTC') : false;
                    $date_errors = DateTime::getLastErrors();
                    if ($date && (! $date_errors || ! ($date_errors['warning_count'] || $date_errors['error_count'])))
                    {
                        $_SESSION["as_$k"] = $tmpvar[$k];

                        $date->setTime(0, 0);
                        $dmin = strlen($v['value']['dmin']) ? new DateTime($v['value']['dmin'] . ' t00:00:00') : false;
                        $dmax = strlen($v['value']['dmax']) ? new DateTime($v['value']['dmax'] . ' t00:00:00') : false;

                        if ($dmin && $dmin->format('Y-m-d') > $date->format('Y-m-d'))
                        {
                            $hesk_error_buffer[$k] = sprintf($hesklang['d_emin'], $v['name'], hesk_translate_date_string($dmin->format($hesk_settings['format_datepicker_php'])));
                        }
                        elseif ($dmax && $dmax->format('Y-m-d') < $date->format('Y-m-d'))
                        {
                            $hesk_error_buffer[$k] = sprintf($hesklang['d_emax'], $v['name'], hesk_translate_date_string($dmax->format($hesk_settings['format_datepicker_php'])));
                        }
                        elseif ($date->format('Y-m-d') < '1901-12-15' || $date->format('Y-m-d') > '2038-01-17')
                        {
                            // Keep an existing date when an unrelated ticket field is edited.
                            if ($date->format('U') === (string) $ticket[$k])
                            {
                                $tmpvar[$k] = $ticket[$k];
                            }
                            else
                            {
                                $hesk_error_buffer[$k] = sprintf($hesklang['d_range'], $v['name']);
                            }
                        }
                        else
                        {
                            $tmpvar[$k] = $date->getTimestamp();
                        }
                    }
                    else
                    {
                        if (strlen($tmpvar[$k]))
                        {
                            $hesk_error_buffer[$k] = sprintf($hesklang['d_invalid'], $v['name']);
                        }
                        elseif ($v['req'] == 2)
                        {
                            $hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
                        }

                        $tmpvar[$k] = '';
                    }
                }
                elseif ($v['type'] == 'email')
                {
                    $tmp = $hesk_settings['multi_eml'];
                    $hesk_settings['multi_eml'] = $v['value']['multiple'];
                    $tmpvar[$k] = hesk_validateEmail( hesk_POST($k), 'ERR', 0, $v['value']['multiple']);
                    $hesk_settings['multi_eml'] = $tmp;

                    if ($tmpvar[$k] != '')
                    {
                        $_SESSION["as_$k"] = hesk_POST($k);
                    }
                    else
                    {
                        $_SESSION["as_$k"] = hesk_POST($k);

                        if ($v['req'] == 2 || strlen(trim(hesk_POST($k))))
                        {
                            $hesk_error_buffer[$k] = $v['value']['multiple'] ? sprintf($hesklang['cf_noem'], $v['name']) : sprintf($hesklang['cf_noe'], $v['name']);
                        }
                    }
                }
                elseif ($v['req'] == 2)
                {
                    $tmpvar[$k]=hesk_makeURL(nl2br(hesk_input( hesk_POST($k) )));
                    if ($tmpvar[$k] == '')
                    {
                        $hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
                    }
                }
                else
                {
                    $tmpvar[$k]=hesk_makeURL(nl2br(hesk_input(hesk_POST($k))));
                }
            }
            else
            {
                $tmpvar[$k] = '';
            }
        }

	    if (count($hesk_error_buffer))
	    {
            // Remove any successfully uploaded attachments
            if ($hesk_settings['attachments']['use'] && isset($attachments))
            {
                if ($use_legacy_attachments) {
                    hesk_removeAttachments($attachments);
                } else {
                    $_SESSION['edit_attachments'] = $attachments;
                }
            }

	    	$myerror = '<ul>';
		    foreach ($hesk_error_buffer as $error)
		    {
		        $myerror .= "<li>$error</li>\n";
		    }
	        $myerror .= '</ul>';
	    	hesk_error($myerror);
	    }

        if ($hesk_settings['attachments']['use'] && !empty($attachments))
        {
            if (!$use_legacy_attachments) {
                $attachments = hesk_migrateTempAttachments($attachments, $trackingID);
            }

            foreach ($attachments as $myatt)
            {
                hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('".hesk_dbEscape($trackingID)."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."')");
                $myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
                hesk_hook_ticket_attachment_created([
                        'id' => hesk_dbInsertID(),
                        'ticket_id' => $trackingID,
                        'real_name' => $myatt['real_name']
                ], true);
            }
        }

        $hook_values = array(
            'id' => $ticket['id'],
            'subject' => hesk_mb_substr($tmpvar['subject'], 0, 255),
            'message' => $tmpvar['message'],
            'message_html' => $tmpvar['message_html']
        );
        if ($tmpvar['set_language'] !== '') {
            $hook_values['language'] = $tmpvar['set_language'];
        }
		$custom_SQL = '';
		for ($i=1; $i<=100; $i++)
		{
			$custom_SQL .= '`custom'.$i.'`=' . (isset($tmpvar['custom'.$i]) ? "'".hesk_dbEscape($tmpvar['custom'.$i])."'" : "''") . ',';
            $hook_values['custom'.$i] = isset($tmpvar['custom'.$i]) ? $tmpvar['custom'.$i] : '';
		}
		$custom_SQL = rtrim($custom_SQL, ',');
        $hook_values['changes'] = hesk_hooks_get_changed_fields($ticket, $hook_values, array('id'), array(), array_keys($hook_values));

        $old_customer_values = array('customer_id' => null, 'follower_ids' => array());
        foreach ($customers as $customer) {
            if ($customer['customer_type'] === 'REQUESTER') {
                $old_customer_values['customer_id'] = intval($customer['id']);
            } elseif ($customer['customer_type'] === 'FOLLOWER') {
                $old_customer_values['follower_ids'][] = intval($customer['id']);
            }
        }
        $old_customer_values['follower_ids'] = array_values(array_unique($old_customer_values['follower_ids']));
        $new_customer_values = array(
            'customer_id' => intval($tmpvar['customer_id']),
            'follower_ids' => array_values(array_unique(array_map('intval', $tmpvar['follower_ids'])))
        );
        $customer_changes = hesk_hooks_get_changed_fields($old_customer_values, $new_customer_values);
        $hook_values['changes'] = array_merge($hook_values['changes'], $customer_changes);
        foreach ($customer_changes as $field) {
            $hook_values['old_'.$field] = $old_customer_values[$field];
            $hook_values['new_'.$field] = $new_customer_values[$field];
        }
        // Supply the new associations so the audit hook cannot reuse cached old IDs.
        $hook_values['customer_ids'] = array_values(array_unique(array_merge(
            array($new_customer_values['customer_id']), $new_customer_values['follower_ids']
        )));

		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET
		`subject`='".hesk_dbEscape( hesk_mb_substr($tmpvar['subject'], 0, 255) )."',
		`message`='".hesk_dbEscape($tmpvar['message'])."',
		`message_html`='".hesk_dbEscape($tmpvar['message_html'])."',
        `attachments`=CONCAT(`attachments`, '".hesk_dbEscape($myattachments)."'),
        $language_SQL
		$custom_SQL
		WHERE `id`='".intval($ticket['id'])."'");

        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
        WHERE `ticket_id` = ".intval($ticket['id']));

        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`)
                      VALUES (".intval($ticket['id']).", ".intval($tmpvar['customer_id']).", 'REQUESTER')");
        foreach ($tmpvar['follower_ids'] as $follower_id) {
            hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`)
                          VALUES (".intval($ticket['id']).", ".intval($follower_id).", 'FOLLOWER')");
        }
        hesk_hook_ticket_updated($hook_values, true);

        // Process newly added @mentions after updating the ticket and customers
        hesk_process_mentions($hesk_settings['staff_ticket_formatting'] == 2 ? stripslashes($tmpvar['message_html']) : hesk_POST('message'), $ticket['id'], $ticket['category'], $hesk_settings['staff_ticket_formatting'] == 2, $hesk_settings['staff_ticket_formatting'] == 2 ? $ticket['message_html'] : hesk_msgToPlain($ticket['message'], 1, 0, true));
    }

    unset($tmpvar);
    hesk_cleanSessionVars('tmpvar');

    hesk_process_messages($hesklang['edt2'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

$ticket['message'] = hesk_msgToPlain($ticket['message'],0,0,true);

define('EXTRA_PAGE_CLASSES','page-admin-edit-post');

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<div class="main__content categories ticket-create">
    <div class="table-wrap">
        <h3 style="margin-bottom: 20px"><?php echo $hesklang['edtt']; ?></h3>
        <form method="post" class="form" action="edit_post.php" name="form1" enctype="multipart/form-data" aria-label="<?php echo $hesklang['edtt']; ?>">
            <?php
            /* If it's not a reply edit all the fields */
            if (!$is_reply)
            {
                $requester = null;
                foreach ($customers as $customer) {
                    if ($customer['customer_type'] === 'REQUESTER') {
                        $requester = $customer;
                        break;
                    }
                }

                if ($requester !== null && $requester['name'] == '') {
                    $requester['name'] = ( ! empty($requester['email']) ? $requester['email'] : $hesklang['pde'] );
                }
                ?>
                <div class="form-group">
                    <label for="create_customer">
                        <?php echo $hesklang['customer']; ?> <span class="important">*</span><a href="javascript:" class='ml5' id="new-customer-link" data-modal="[data-modal-id='create-customer']">[<?php echo $hesklang['new_customer']; ?>]</a>
                    </label>
                    <select name="customer_id" id="create_customer" class="read-write" placeholder="<?php echo hesk_addslashes($hesklang['search_by_name_or_email']); ?>">
                        <?php if ($requester !== null): ?>
                            <option value="<?php echo $requester['id']; ?>" selected>
                                <?php echo $requester['email'] ? "{$requester['name']} <{$requester['email']}>" : $requester['name']; ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php
                $followers = array_filter($customers, function($customer) { return $customer['customer_type'] === 'FOLLOWER'; });
                if (count($followers) > 0 || $hesk_settings['multi_eml']):
                ?>
                <div class="form-group">
                    <label for="followers_input">
                        <?php echo $hesklang['followers']; ?> <a href="javascript:" class='ml5' id="new-follower-link" data-modal="[data-modal-id='create-customer']">[<?php echo $hesklang['new_follower']; ?>]</a>
                    </label>
                    <select name="follower_id[]" multiple id="followers_input" class="read-write" placeholder="<?php echo hesk_addslashes($hesklang['search_by_name_or_email']); ?>">
                        <?php foreach ($followers as $row) { ?>
                            <option value="<?php echo $row['id']; ?>" selected>
                                <?php echo $row['email'] ? "{$row['name']} &lt;{$row['email']}&gt;" : $row['name']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="edit_subject"><?php echo $hesklang['subject']; ?>:</label>
                    <input type="text" class="form-control" id="edit_subject" name="subject" maxlength="70" value="<?php echo $ticket['subject'];?>">
                </div>
                <script>
                    hesk_loadNoResultsSelectizePlugin('<?php echo hesk_jsString($hesklang['no_results_found']); ?>');
                    var plugins = ['no_results'];
                    var createCustomerSelectize = $('#create_customer').selectize({
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['name','email'],
                        copyClassesToDropdown: true,
                        preload: 'focus',
                        options: [],
                        loadThrottle: 300,
                        persist: false,
                        plugins: plugins,
                        load: function(query, callback) {
                            $.ajax({
                                url: 'ajax/search_customers.php?query=' + encodeURIComponent(query),
                                dataType: 'json',
                                success: function(data) {
                                    callback(data);
                                }
                            });
                        },

                        /* Using deconstruct (requires EMCA6, but it's required in a bunch of other code already, so shouldn't be an issue)
                        here to add a bunch of general functionality needed for the custom "Add Entry",
                        And passing only the necessary custom behaviour for this specific dropdown.
                        */
                        ...hesk_selectizeAddCustomAddEntryToDropdown(
                            {
                                newEntryTextPrefix: '<?php echo hesk_jsString($hesklang['add_customer']); ?>',
                                onAddEntryClickedFunction: function(selectizeInstance, selectizeSearchValue) {
                                // populate the customer input field with the selected search value (either name or email)
                                let nameValue = selectizeSearchValue;
                                    let emailValue = '';
                                    if (selectizeSearchValue.indexOf('@') > -1) {
                                        // if there's an @ part of search string, we simply assume it's an email
                                        nameValue = '';
                                        emailValue = selectizeSearchValue;
                                    }
                                    // simply reuse what new-customer-link already does for adding a new customer.
                                    $('#new-customer-link').trigger('click', { nameValue: nameValue, emailValue: emailValue });
                                }
                            }
                        )
                    });

                    var plugins = ['no_results'<?php echo $hesk_settings['multi_eml'] ? ",'remove_button'" : ''; ?>];
                    var createFollowerSelectize = $('#followers_input').selectize({
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['name','email'],
                        copyClassesToDropdown: true,
                        preload: 'focus',
                        options: [],
                        loadThrottle: 300,
                        persist: false,
                        plugins: plugins,
                        load: function(query, callback) {
                            $.ajax({
                                url: 'ajax/search_customers.php?query=' + encodeURIComponent(query),
                                dataType: 'json',
                                success: function(data) {
                                    callback(data);
                                }
                            });
                        },

                        /* Using deconstruct (requires EMCA6, but it's required in a bunch of other code already, so shouldn't be an issue)
                        here to add a bunch of general functionality needed for the custom "Add Entry",
                        And passing only the necessary custom behaviour for this specific dropdown.
                        */

                        ...hesk_selectizeAddCustomAddEntryToDropdown(
                            {
                                newEntryTextPrefix: '<?php echo hesk_jsString($hesklang['add_follower']); ?>',
                                onAddEntryClickedFunction: function(selectizeInstance, selectizeSearchValue) {
                                    // populate the follower input field with the selected search value (either name or email)
                                    let nameValue = selectizeSearchValue;
                                    let emailValue = '';
                                    if (selectizeSearchValue.indexOf('@') > -1) {
                                        nameValue = '';
                                        emailValue = selectizeSearchValue;
                                    }
                                    // simply reuse what new-follower-link already does for adding a new customer.
                                    $('#new-follower-link').trigger('click', { nameValue: nameValue, emailValue: emailValue });
                                }
                            }
                        )
                    });
                </script>
                <?php
                foreach ($hesk_settings['custom_fields'] as $k=>$v) {
                    if ($v['use'] && $v['place']==0 && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category'])) ) {
                        $k_value  = $ticket[$k];
                        if ($v['type'] == 'radio' || $v['type'] == 'select') {
                            $k_value = preg_replace_callback('/<a href="([^"]*)"[^>]*>(.*?)<\/a>/is', function($match) {
                                if (strpos($match[2], ' ... ') === false) { return $match[2]; }
                                if (strpos($match[1], 'mailto:') === 0) { return substr($match[1], 7); }
                                if (stripos($match[2], 'www.') === 0 && strpos($match[1], 'http://') === 0) { return substr($match[1], 7); }
                                return $match[1];
                            }, $k_value);
                            $k_value = hesk_matchCustomFieldOption($k_value, $v['value'][$v['type'].'_options']);
                        }

                        if ($v['type'] == 'checkbox') {
                            $k_value = strlen($k_value) ? explode('<br />',$k_value) : array();
                            $k_value = hesk_matchCustomFieldOption($k_value, $v['value']['checkbox_options']);
                        }

                        $v['req'] = ($v['req']==2 && hesk_is_custom_field_in_category($k, $ticket['category'])) ? '<span class="important">*</span>' : '';

                        switch ($v['type']) {
                            /* Radio box */
                            case 'radio':
                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="radio-list">';

                                        $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                        $index = 0;
                                        foreach ($v['value']['radio_options'] as $option)
                                        {
                                            if (strlen($k_value) == 0)
                                            {
                                                $k_value = $option;
                                                $checked = empty($v['value']['no_default']) ? 'checked' : '';
                                            }
                                            elseif ($k_value === $option)
                                            {
                                                $k_value = $option;
                                                $checked = 'checked';
                                            }
                                            else
                                            {
                                                $checked = '';
                                            }

                                            echo '
                                            <div class="radio-custom" style="margin-bottom: 5px">
                                                <input type="radio" id="edit_'.$k.$index.'" name="'.$k.'" value="'.$option.'" '.$checked.' '.$cls.'>
                                                <label for="edit_'.$k.$index.'">'.$option.'</label>
                                            </div>';
                                            $index++;
                                        }
                                    echo '</div>
                                </div>';
                                break;

                            /* Select drop-down box */
                            case 'select':

                                $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                echo '
                                <div class="form-group">
                                    <label for="edit_">'.$v['name:'].' '.$v['req'].'</label>
                                        <select name="'.$k.'" id="'.$k.'" '.$cls.'>';
                                        // Show "Click to select"?
                                        if ( ! empty($v['value']['show_select']))
                                        {
                                            echo '<option value="">'.$hesklang['select'].'</option>';
                                        }

                                        foreach ($v['value']['select_options'] as $option)
                                        {
                                            if ($k_value === $option)
                                            {
                                                $k_value = $option;
                                                $selected = 'selected';
                                            }
                                            else
                                            {
                                                $selected = '';
                                            }

                                            echo '<option '.$selected.'>'.$option.'</option>';
                                        }
                                        echo '</select>
                                </div>
                                <script>
                                    $(\'#'.$k.'\').selectize();
                                </script>
                                ';
                                break;

                            /* Checkbox */
                            case 'checkbox':
                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>';

                                $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                $index = 0;
                                foreach ($v['value']['checkbox_options'] as $option)
                                {
                                    if (($match = array_search($option,$k_value,true)) !== false)
                                    {
                                        $checked = 'checked';
                                        unset($k_value[$match]);
                                    }
                                    else
                                    {
                                        $checked = '';
                                    }

                                    echo '
                                    <div class="checkbox-custom">
                                        <input type="checkbox" id="edit_'.$k.$index.'" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$cls.'>
                                        <label for="edit_'.$k.$index.'"> '.$option.'</label>
                                    </div>';
                                    $index++;
                                }

                                echo '</div>';
                                break;

                            /* Large text box */
                            case 'textarea':
                                $cls = in_array($k,$_SESSION['iserror']) ? ' isError" ' : '';
                                $k_value = hesk_msgToPlain($k_value,0,0);

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <textarea name="'.$k.'" class="form-control'.$cls.'" style="height: inherit" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" >'.$k_value.'</textarea>
                                </div>';
                                break;

                            // Date
                            case 'date':
                                $cls = in_array($k,$_SESSION['iserror']) ? 'isErrorStr' : '';
                                $datepicker_date = '';
                                if ($k_value != '') {
                                    $date = new DateTime('@' . $k_value);
                                    $datepicker_date = hesk_datepicker_format_date($date->getTimestamp(), 'UTC');
                                    $hesk_settings['datepicker']['#'.$k]['timestamp'] = $date->getTimestamp();
                                    $hesk_settings['datepicker']['#'.$k]['fromDB'] = true;
                                }

                                echo '
                                <section class="param calendar">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="calendar--button">
                                        <button type="button" aria-label="' . $hesklang['date'] . '">
                                            <svg class="icon icon-calendar">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-calendar"></use>
                                            </svg>
                                        </button>
                                        <input name="'. $k .'" id="'. $k .'"
                                               value="'. $datepicker_date .'"
                                               type="text" class="datepicker">
                                    </div>
                                    <div class="calendar--value" '. ($datepicker_date ? 'style="display: block"' : '') . '>
                                        <span class="'. $cls .'"><i>'. $datepicker_date .'</i></span>
                                        <i class="close">
                                            <svg class="icon icon-close">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-close"></use>
                                            </svg>
                                        </i>
                                    </div>
                                </section>';
                                break;

                            // Email
                            case 'email':
                                $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                                $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="'.($v['value']['multiple'] ? 'text' : 'email').'" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$suggest.'>
                                </div>
                                <div id="'.$k.'_suggestions"></div>';
                                break;

                            // Hidden
                            // Handle as text fields for staff

                            /* Default text input */
                            default:
                                $k_value = hesk_msgToPlain($k_value,0,0);

                                $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$k_value.'">
                                </div>';
                        }
                    }
                }
                ?>
                <?php
            }
            ?>
            <div class="form-group">
                <label for="edit_message"><?php echo $hesklang['message']; ?>:</label>
                <textarea style="height: inherit" class="form-control" id="edit_message" name="message" rows="12" cols="60" aria-label="<?php echo hesk_htmlspecialchars($hesklang['message']); ?>:"><?php echo $hesk_settings['staff_ticket_formatting'] == 2 ? hesk_htmlspecialchars(hesk_format_mentions($ticket['message_html'], true)) : $ticket['message']; ?></textarea>
            </div>

            <?php
            if ($hesk_settings['staff_ticket_formatting'] == 2) {
                hesk_tinymce_init('#edit_message');
            }

            if (!$is_reply)
            {
                foreach ($hesk_settings['custom_fields'] as $k=>$v) {
                    if ($v['use'] && $v['place'] && hesk_is_custom_field_in_category($k, $ticket['category']) ) {
                        $k_value  = $ticket[$k];
                        if ($v['type'] == 'radio' || $v['type'] == 'select') {
                            $k_value = preg_replace_callback('/<a href="([^"]*)"[^>]*>(.*?)<\/a>/is', function($match) {
                                if (strpos($match[2], ' ... ') === false) { return $match[2]; }
                                if (strpos($match[1], 'mailto:') === 0) { return substr($match[1], 7); }
                                if (stripos($match[2], 'www.') === 0 && strpos($match[1], 'http://') === 0) { return substr($match[1], 7); }
                                return $match[1];
                            }, $k_value);
                            $k_value = hesk_matchCustomFieldOption($k_value, $v['value'][$v['type'].'_options']);
                        }

                        if ($v['type'] == 'checkbox') {
                            $k_value = strlen($k_value) ? explode('<br />',$k_value) : array();
                            $k_value = hesk_matchCustomFieldOption($k_value, $v['value']['checkbox_options']);
                        }

                        $v['req'] = $v['req']==2 ? '<span class="important">*</span>' : '';

                        switch ($v['type']) {
                            /* Radio box */
                            case 'radio':
                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="radio-list">';

                                        $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                        $index = 0;
                                        foreach ($v['value']['radio_options'] as $option)
                                        {
                                            if (strlen($k_value) == 0)
                                            {
                                                $k_value = $option;
                                                $checked = empty($v['value']['no_default']) ? 'checked' : '';
                                            }
                                            elseif ($k_value === $option)
                                            {
                                                $k_value = $option;
                                                $checked = 'checked';
                                            }
                                            else
                                            {
                                                $checked = '';
                                            }

                                            echo '
                                            <div class="radio-custom" style="margin-bottom: 5px">
                                                <input type="radio" id="edit_'.$k.$index.'" name="'.$k.'" value="'.$option.'" '.$checked.' '.$cls.'>
                                                <label for="edit_'.$k.$index.'">'.$option.'</label>
                                            </div>';
                                            $index++;
                                        }
                                    echo '</div>
                                </div>';
                                break;

                            /* Select drop-down box */
                            case 'select':

                                $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                echo '
                                <div class="form-group">
                                    <label for="edit_">'.$v['name:'].' '.$v['req'].'</label>
                                        <select name="'.$k.'" id="'.$k.'" '.$cls.'>';
                                        // Show "Click to select"?
                                        if ( ! empty($v['value']['show_select']))
                                        {
                                            echo '<option value="">'.$hesklang['select'].'</option>';
                                        }

                                        foreach ($v['value']['select_options'] as $option)
                                        {
                                            if ($k_value === $option)
                                            {
                                                $k_value = $option;
                                                $selected = 'selected';
                                            }
                                            else
                                            {
                                                $selected = '';
                                            }

                                            echo '<option '.$selected.'>'.$option.'</option>';
                                        }
                                        echo '</select>
                                </div>
                                <script>
                                    $(\'#'.$k.'\').selectize();
                                </script>
                                ';
                                break;

                            /* Checkbox */
                            case 'checkbox':
                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>';

                                $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                                $index = 0;
                                foreach ($v['value']['checkbox_options'] as $option)
                                {
                                    if (($match = array_search($option,$k_value,true)) !== false)
                                    {
                                        $checked = 'checked';
                                        unset($k_value[$match]);
                                    }
                                    else
                                    {
                                        $checked = '';
                                    }

                                    echo '
                                    <div class="checkbox-custom">
                                        <input type="checkbox" id="edit_'.$k.$index.'" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$cls.'>
                                        <label for="edit_'.$k.$index.'"> '.$option.'</label>
                                    </div>';
                                    $index++;
                                }

                                echo '</div>';
                                break;

                            /* Large text box */
                            case 'textarea':
                                $cls = in_array($k,$_SESSION['iserror']) ? ' isError" ' : '';
                                $k_value = hesk_msgToPlain($k_value,0,0);

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <textarea name="'.$k.'" class="form-control'.$cls.'" style="height: inherit" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" >'.$k_value.'</textarea>
                                </div>';
                                break;

                            // Date
                            case 'date':
                                $cls = in_array($k,$_SESSION['iserror']) ? 'isErrorStr' : '';
                                $datepicker_date = '';
                                if ($k_value != '') {
                                    $date = new DateTime('@' . $k_value);
                                    $datepicker_date = hesk_datepicker_format_date($date->getTimestamp(), 'UTC');
                                    $hesk_settings['datepicker']['#'.$k]['timestamp'] = $date->getTimestamp();
                                    $hesk_settings['datepicker']['#'.$k]['fromDB'] = true;
                                }

                                echo '
                                <section class="param calendar">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="calendar--button">
                                        <button type="button" aria-label="' . $hesklang['date'] . '">
                                            <svg class="icon icon-calendar">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-calendar"></use>
                                            </svg>
                                        </button>
                                        <input name="'. $k .'" id="'. $k .'"
                                               value="'. $datepicker_date .'"
                                               type="text" class="datepicker">
                                    </div>
                                    <div class="calendar--value" '. ($datepicker_date ? 'style="display: block"' : '') . '>
                                        <span class="'. $cls .'"><i>'. $datepicker_date .'</i></span>
                                        <i class="close">
                                            <svg class="icon icon-close">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-close"></use>
                                            </svg>
                                        </i>
                                    </div>
                                </section>';
                                break;

                            // Email
                            case 'email':
                                $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                                $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="'.($v['value']['multiple'] ? 'text' : 'email').'" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$suggest.'>
                                </div>
                                <div id="'.$k.'_suggestions"></div>';
                                break;

                            // Hidden
                            // Handle as text fields for staff

                            /* Default text input */
                            default:
                                $k_value = hesk_msgToPlain($k_value,0,0);

                                $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                                echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$k_value.'">
                                </div>';
                        }
                    }
                }
            } // End if not a reply

            // attachments
            if ($hesk_settings['attachments']['use'] && $number_of_attachments < $hesk_settings['attachments']['max_number'])
            {
                require_once(HESK_PATH . 'inc/attachments.inc.php');
                echo '<div class="form-group attachments">';
                echo '<label>' . $hesklang['attachments'] . ': (<a class="link" href="javascript:" onclick="hesk_window(\'../file_limits.php\',250,500);return false;">' . $hesklang['ful'] . '</a>)</label>';
                build_dropzone_markup(true, 'filedrop', $number_of_attachments + 1);
                display_dropzone_field(HESK_PATH . 'upload_attachment.php',
                    true,
                    'filedrop',
                    $hesk_settings['attachments']['max_number'] - $number_of_attachments);
                dropzone_display_existing_files(hesk_SESSION_array('edit_attachments'));
                echo '</div>';
            }
            ?>
            <input type="hidden" name="save" value="1">
            <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
            <?php
            if ($is_reply)
            {
                ?>
                <input type="hidden" name="reply" value="<?php echo $tmpvar['id']; ?>" />
                <?php
            }

            if ($hesk_settings['can_sel_lang']) {
                ?>
                <div class="form-group">
                    <label for="set-language"><?php echo $hesklang['set_lang']; ?>:</label>
                    <select name="set_language" id="set-language">
                        <option value="0">----</option>
                        <?php
                        foreach ($hesk_settings['languages'] as $setting_lang_key => $setting_lang) {
                            if ( ! empty($ticket['language']) && $ticket['language'] === $setting_lang_key) {
                                echo '<option value="'.$setting_lang_key.'" selected="selected">'.$setting_lang_key.'</option>';
                            } else {
                                echo '<option value="'.$setting_lang_key.'">'.$setting_lang_key.'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <script>
                    $('#set-language').selectize();
                </script>
                <?php
            }
            ?>
            <button type="submit" class="btn btn-full" style="display: inline-flex">
                <?php echo $hesklang['save_changes']; ?>
            </button>
            <a href="javascript:history.go(-1)" class="btn btn--blue-border"><?php echo $hesklang['back']; ?></a>
        </form>
    </div>
</div>

<p style="text-align:center"></p>

<p>&nbsp;</p>

<?php 
    //Common include for the "Create Customer / Follower"
    require_once(HESK_PATH . 'inc/customer_modal.inc.php'); 
?>

<?php
hesk_cleanSessionVars('edit_attachments');
?>
<?php /* Preserve existing mention chips even when this staff cannot add collaborators. */ ?>
<script>
window.HeskMention = {
    canAssign:      <?php echo hesk_checkPermission('can_assign_others', 0) ? 'true' : 'false'; ?>,
    ticketId:       <?php echo intval($ticket['id']); ?>,
    editorSelector: '<?php echo $hesk_settings['staff_ticket_formatting'] != 2 ? '#edit_message' : ''; ?>',
    tinyMceId:      '<?php echo $hesk_settings['staff_ticket_formatting'] == 2  ? 'edit_message'  : ''; ?>',
    searchingText:  <?php echo hesk_json_encode_for_js($hesklang['mention_searching']); ?>
};
</script>
<script src="<?php echo HESK_PATH; ?>js/mention-autocomplete.js"></script>
<?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
<script src="<?php echo HESK_PATH; ?>js/mention-autocomplete-tinymce.js"></script>
<?php endif; ?>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


function hesk_countAttachments($attachments_string)
{
    if ( ! strlen($attachments_string) || strpos($attachments_string, ',') === false)
    {
        return 0;
    }

    $att = explode(',', substr($attachments_string, 0, -1));

    return count($att);

} // END hesk_countAttachments()
