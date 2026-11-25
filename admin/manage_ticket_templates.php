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

$action = hesk_REQUEST('a');
/* Check permissions for this feature */
hesk_checkPermission('can_man_ticket_tpl', 1, function($failure_reason) use ($action) {
    if ($action === 'new') {
        hesk_hook_ticket_template_created(null, $failure_reason);
    } elseif ($action === 'edit' || $action === 'order') {
        hesk_hook_ticket_template_updated(null, $failure_reason);
    } elseif ($action === 'remove') {
        hesk_hook_ticket_template_deleted(null, $failure_reason);
    } else {
        hesk_hook_ticket_templates_read($failure_reason);
    }
});

// Only offer categories accessible directly, through permission groups, or as an administrator.
$hesk_settings['categories'] = array();
$can_manage_all_categories = true;
$all_categories = hesk_getCategoriesArray();
foreach ($all_categories as $cat_id => $cat_name) {
    if (hesk_okCategory($cat_id, 0)) {
        $hesk_settings['categories'][$cat_id] = $cat_name;
    } else {
        $can_manage_all_categories = false;
    }
}

$filter_category = intval(hesk_REQUEST('filter_category'));
if ($filter_category && !isset($hesk_settings['categories'][$filter_category])) {
    hesk_error($hesklang['no_permission']);
}
$manage_url = 'manage_ticket_templates.php?filter_category='.$filter_category;

// Keep inaccessible entries out of both the list and its JavaScript data.
$templates = array();
$editable_templates = array();
$order_ids = array();
$result = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'ticket_templates` ORDER BY `tpl_order` ASC, `id` ASC');
while ($row = hesk_dbFetchAssoc($result)) {
    $scope = $row['category'] === null ? null : array_map('intval', (array) json_decode($row['category'], true));
    if ($scope !== null) {
        // Deleted categories must not restrict access to this entry
        $scope = array_intersect($scope, array_keys($all_categories));
    }
    $category_names = $scope === null ? $hesk_settings['categories'] : array_intersect_key($hesk_settings['categories'], array_flip($scope));
    if (!$_SESSION['isadmin'] && !count($category_names)) {
        continue;
    }

    // Shared entries remain usable, but changing them requires access to their whole scope.
    $row['can_edit'] = $can_manage_all_categories || ($scope !== null && !array_diff($scope, array_keys($hesk_settings['categories'])));
    if ($row['can_edit']) {
        $editable_templates[$row['id']] = $row;
    }
    if ($filter_category && $scope !== null && !in_array($filter_category, $scope, true)) {
        continue;
    }
    $row['category_names'] = $category_names;
    $row['category_count'] = $scope === null ? count($all_categories) : count(array_intersect_key($all_categories, array_flip($scope)));
    $templates[$row['id']] = $row;
    if ($row['can_edit']) {
        $order_ids[] = intval($row['id']);
    }
}

// Do not redisplay an old edit after category access has been removed.
if (hesk_SESSION(array('canned', 'what')) === 'EDIT' && !isset($editable_templates[hesk_SESSION(array('canned', 'id'))])) {
    hesk_cleanSessionVars('canned');
}

// Define required constants
define('LOAD_TABS',1);

if ($hesk_settings['staff_ticket_formatting'] == 2) {
    define('WYSIWYG',1);
}


/* What should we do? */
if ($action)
{
    if ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_ticket_templates.php', 'NOTICE');}
    elseif ($action == 'new')    {new_saved();}
    elseif ($action == 'edit')   {edit_saved();}
    elseif ($action == 'remove') {remove();}
    elseif ($action == 'order')  {order_saved();}
}

define('EXTRA_PAGE_CLASSES','page-admin-manage-ticket-templates');

if (!isset($_SESSION['suppress_ticket_template_view_hook'])) {
    hesk_hook_ticket_templates_read(true);
} else {
    unset($_SESSION['suppress_ticket_template_view_hook']);
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<script language="javascript" type="text/javascript"><!--
    function confirm_delete()
    {
        if (confirm('<?php echo hesk_makeJsString($hesklang['delete_tpl']); ?>')) {return true;}
        else {return false;}
    }

    var heskManageTemplateSubmitting = false;
    function hesk_disableTemplateSubmit(buttonID)
    {
        if (heskManageTemplateSubmitting)
        {
            return false;
        }

        heskManageTemplateSubmitting = true;

        var button = document.getElementById(buttonID);
        if (button)
        {
            button.disabled = true;
            button.innerHTML = '<?php echo hesk_makeJsString($hesklang['please_wait']); ?>';
        }

        return true;
    }
//-->
</script>
<?php
/* This will handle error, success and notice messages */
if (!isset($_SESSION['canned']['what'])) {
    hesk_handle_messages();
}

$javascript_messages='';
$javascript_titles='';
$javascript_categories='';

$num = count($templates);
?>
<div class="main__content templates">
    <section class="templates__head">
        <h2>
            <?php echo $hesklang['ticket_tpl']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['ticket_tpl_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <?php if (count($hesk_settings['categories'])): ?>
        <div class="btn btn--blue-border" ripple="ripple" data-action="create-template" onclick="displayAddTitle()"><?php echo $hesklang['ticket_tpl_add']; ?></div>
        <?php endif; ?>
    </section>
    <form action="manage_ticket_templates.php" method="get" class="form" style="margin-top: 16px;" aria-label="<?php echo hesk_htmlspecialchars($hesklang['category']); ?>">
        <div class="form-group">
            <label for="filter_category"><?php echo $hesklang['category']; ?></label>
            <div style="display: flex; gap: 8px;">
                <select id="filter_category" name="filter_category" class="form-control" style="min-width: 0; flex: 1;">
                    <option value="0"><?php echo $hesklang['cf_all']; ?></option>
                    <?php foreach ($hesk_settings['categories'] as $cat_id => $cat_name): ?>
                        <option value="<?php echo $cat_id; ?>"<?php if ($filter_category == $cat_id) {echo ' selected';} ?>><?php echo $cat_name; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--blue-border"><?php echo $hesklang['show']; ?></button>
            </div>
        </div>
    </form>
    <ul class="response__list">
        <?php if ($num < 1): ?>
            <li><h3><?php echo $filter_category ? $hesklang['no_results_found'] : $hesklang['no_ticket_tpl']; ?></h3></li>
        <?php
        endif;

        foreach ($templates as $mysaved)
        {

            $table_row = '';
            if (isset($_SESSION['canned']['selcat2']) && $mysaved['id'] == $_SESSION['canned']['selcat2']) {
                $table_row = 'class="ticket-new"';
                unset($_SESSION['canned']['selcat2']);
            }

            $message_text = $hesk_settings['staff_ticket_formatting'] == 2 ? $mysaved['message_html'] : $mysaved['message'];

            // Visible shared entries may be copied, using only accessible categories.
            $javascript_messages.='myMsgTxt['.$mysaved['id'].']='.hesk_json_encode_for_js($message_text).";\n";
            $javascript_titles.='myTitle['.$mysaved['id'].']='.hesk_json_encode_for_js($mysaved['title']).";\n";
            $javascript_categories.='myCategories['.$mysaved['id'].']='.json_encode($mysaved['category'] === null ? null : array_map('intval', array_keys($mysaved['category_names']))).";\n";

            $category_summary = $hesklang['cf_all'];
            if ($mysaved['category'] !== null) {
                $category_summary = count($mysaved['category_names']) ? implode(', ', array_slice($mysaved['category_names'], 0, 3)) : $hesklang['none'];
                $categories_more = $mysaved['category_count'] - min(3, count($mysaved['category_names']));
                if ($categories_more > 0) {
                    $category_summary .= ' '.sprintf($hesklang['template_categories_more'], $categories_more);
                }
            }

            echo '
        <li '.$table_row.'>
        <h3 style="min-width: 0; overflow-wrap: anywhere;">'.$mysaved['title'].'<br><small>'.$hesklang['allowed_cat'].': '.$category_summary.'</small>'.
            ($mysaved['can_edit'] ? '' : '<br><small>'.$hesklang['template_read_only'].'</small>').'</h3>
        ';

            if (count($hesk_settings['categories'])) {
                echo '<a class="tooltip" title="'.hesk_htmlspecialchars($hesklang['template_copy']).'" aria-label="'.hesk_htmlspecialchars($hesklang['template_copy']).'" href="javascript:setMessage('.$mysaved['id'].', true)">
                    <svg class="icon icon-merge" aria-hidden="true"><use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-merge"></use></svg>
                </a>';
            }

            if (!$mysaved['can_edit']) {
                echo '</li>';
                continue;
            }

            $order_index = array_search(intval($mysaved['id']), $order_ids, true);
            foreach (array(-15 => 'move_up', 15 => 'move_dn') as $move => $label) {
                $can_move = isset($order_ids[$order_index + ($move < 0 ? -1 : 1)]);
                echo '<a class="tooltip"'.($can_move ? ' title="'.$hesklang[$label].'" href="'.$manage_url.'&amp;a=order&amp;replyid='.$mysaved['id'].'&amp;move='.$move.'&amp;token='.hesk_token_echo(0).'"' : ' style="visibility: hidden" aria-hidden="true"').' >
                    <svg class="icon icon-chevron-'.($move < 0 ? 'up' : 'down').'"><use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-chevron-down"></use></svg>
                </a>';
            }

            $modal_id = hesk_generate_old_delete_modal($hesklang['confirm_deletion'],
                $hesklang['delete_tpl'],
                $manage_url.'&amp;a=remove&amp;id='.$mysaved['id'].'&amp;token='.hesk_token_echo(0));

            echo '
            <a class="tooltip" title="'.$hesklang['edit'].'" href="javascript:setMessage(' . $mysaved['id'] . ')">
                <svg class="icon icon-edit-ticket">
                    <use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-edit-ticket"></use>
                </svg>
            </a>
            <a class="tooltip" title="'.$hesklang['remove'].'" href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']">
                <svg class="icon icon-delete">
                    <use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-delete"></use>
                </svg>
            </a>
	    </li>
		';
        } // End foreach
        ?>
    </ul>
</div>
<div class="right-bar template-create" <?php if (isset($_SESSION['canned']['what'])) { echo 'style="display: block"'; } ?>>
    <div class="right-bar__body template-create__body">
        <h3>
            <a href="javascript:">
                <svg class="icon icon-back">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                </svg>
                <span <?php if (isset($_SESSION['canned']['what']) && $_SESSION['canned']['what'] !== 'NEW') { echo 'style="display: none"'; } ?> id="add-title"><?php echo $hesklang['ticket_tpl_add']; ?></span>
                <span <?php if (isset($_SESSION['canned']['what']) && $_SESSION['canned']['what'] !== 'EDIT') { echo 'style="display: none"'; } ?> id="edit-title"><?php echo $hesklang['ticket_tpl_edit']; ?></span>
            </a>
        </h3>
        <div class="form">
            <?php
            /* This will handle error, success and notice messages */
            if (isset($_SESSION['canned']['what'])) {
                echo '<div style="margin: -24px -24px 10px -16px;">';
                hesk_handle_messages();
                echo '</div>';
            }

            $errors = hesk_SESSION(array('canned', 'errors'));
            $errors = is_array($errors) ? $errors : array();
            ?>
            <form action="manage_ticket_templates.php" method="post" name="form1"
                  class="form <?php echo hesk_SESSION(array('canned', 'errors')) ? 'invalid' : ''; ?>" aria-label="<?php echo $hesklang['ticket_tpl_man']; ?>" onsubmit="return hesk_disableTemplateSubmit('ticket-template-submit');">
                <div class="form-group">
                    <label for="canned_title"><?php echo $hesklang['saved_title']; ?></label>
                    <span id="HeskTitle">
                        <input type="text" class="form-control <?php echo in_array('name', $errors) ? 'isError' : ''; ?>" id="canned_title" name="name" maxlength="50"
                            <?php if (isset($_SESSION['canned']['name'])) {echo ' value="'.stripslashes($_SESSION['canned']['name']).'" ';} ?>>
                    </span>
                </div>
                <div class="form-group">
                    <label for="canned_message"><?php echo $hesklang['message']; ?></label>
                    <span id="HeskMsg">
                        <textarea class="form-control <?php echo in_array('msg', $errors) ? 'isError' : ''; ?>" name="msg" rows="40" cols="70" id="canned_message" style="resize: vertical; transition: none;"><?php
                            if (isset($_SESSION['canned']['msg'])) {
                                echo stripslashes($_SESSION['canned']['msg']);
                            }
                            ?></textarea>
                    </span>
                    <?php
                    if ($hesk_settings['staff_ticket_formatting'] == 2) {
                        hesk_tinymce_init('#canned_message');
                    }
                    ?>
                </div>
                <div class="form-group" role="group" aria-labelledby="canned_categories_label">
                    <label id="canned_categories_label"><?php echo $hesklang['allowed_cat']; ?></label>
                    <?php
                    $category = hesk_SESSION(array('canned', 'category')) ? 1 : 0;
                    $categories = hesk_SESSION(array('canned', 'categories'));
                    $categories = is_array($categories) ? $categories : array();
                    ?>
                    <div class="radio-custom">
                        <input type="radio" name="category" id="category0" value="0" onchange="document.getElementById('selcat').style.display = 'none';" <?php if (!$category) {echo 'checked';} ?>>
                        <label for="category0"><?php echo $can_manage_all_categories ? $hesklang['cf_all'] : $hesklang['template_my_categories']; ?></label>
                    </div>
                    <div class="radio-custom">
                        <input type="radio" name="category" id="category1" value="1" onchange="document.getElementById('selcat').style.display = 'block';" <?php if ($category) {echo 'checked';} ?>>
                        <label for="category1"><?php echo $hesklang['cf_cat']; ?></label>
                    </div>
                    <div id="selcat" style="display:<?php echo $category ? 'block' : 'none'; ?>">
                        <select id="canned_categories" class="multiple form-control <?php echo in_array('categories', $errors) ? 'isError' : ''; ?>" name="categories[]" multiple="multiple" size="10" aria-labelledby="canned_categories_label" aria-describedby="canned_categories_tip">
                            <?php
                            foreach ($hesk_settings['categories'] as $cat_id => $cat_name) {
                                echo '<option value="'.$cat_id.'"'.(in_array($cat_id, $categories) ? ' selected="selected"' : '').'>'.$cat_name.'</option>';
                            }
                            ?>
                        </select>
                        <div id="canned_categories_tip"><?php echo $hesklang['cf_ctrl']; ?></div>
                    </div>
                </div>
                <div class="template--submit">
                    <?php if(isset($_SESSION['canned']['what']) && $_SESSION['canned']['what'] == 'EDIT'): ?>
                        <input type="hidden" name="a" value="edit">
                        <input type="hidden" name="saved_replies" value="<?php echo $_SESSION['canned']['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="a" value="new">
                        <input type="hidden" name="saved_replies" value="0">
                    <?php endif; ?>
                    <input type="hidden" name="filter_category" value="<?php echo $filter_category; ?>">
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    <button type="submit" class="btn btn-full" ripple="ripple" id="ticket-template-submit"><?php echo $hesklang['save_ticket_tpl']; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<script language="javascript" type="text/javascript"><!--
var myMsgTxt = new Array();
myMsgTxt[0]='';
var myTitle = new Array();
myTitle[0]='';
var myCategories = new Array();

<?php
echo $javascript_titles;
echo $javascript_messages;
echo $javascript_categories;
?>

function heskDecodeHtmlEntities(value)
{
    var textarea = document.createElement('textarea');
    textarea.innerHTML = String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return textarea.value;
}

function setMessage(msgid, copy) {
    if (document.getElementById) {
        <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
        tinymce.get("canned_message").setContent('');
        tinymce.get("canned_message").setContent(myMsgTxt[msgid]);
        <?php else: ?>
        document.getElementById('canned_message').value = heskDecodeHtmlEntities(myMsgTxt[msgid]);
        <?php endif; ?>
        document.getElementById('canned_title').value = heskDecodeHtmlEntities(myTitle[msgid]);
    } else {
        document.form1.msg.value=heskDecodeHtmlEntities(myMsgTxt[msgid]);
        document.form1.name.value=heskDecodeHtmlEntities(myTitle[msgid]);
    }

    var categories = myCategories[msgid];
    document.getElementById('category0').checked = categories === null;
    document.getElementById('category1').checked = categories !== null;
    document.getElementById('selcat').style.display = categories === null ? 'none' : 'block';
    var options = document.getElementById('canned_categories').options;
    for (var i = 0; i < options.length; i++) {
        options[i].selected = categories !== null && categories.indexOf(parseInt(options[i].value, 10)) !== -1;
    }

    document.form1.a.value = copy ? 'new' : 'edit';
    document.form1.saved_replies.value = copy ? 0 : msgid;
    document.getElementById('add-title').style.display = copy ? 'block' : 'none';
    document.getElementById('edit-title').style.display = copy ? 'none' : 'block';
    document.getElementsByClassName('template-create')[0].style.display = 'block';
    document.getElementById('canned_title').focus();
}

function displayAddTitle() {
    document.form1.msg.value = '';
    document.form1.name.value = '';
    document.form1.saved_replies.value = 0;
    document.form1.a.value = 'new';
    document.getElementById('category0').checked = true;
    document.getElementById('category1').checked = false;
    document.getElementById('canned_categories').selectedIndex = -1;
    document.getElementById('selcat').style.display = 'none';
    <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
    tinymce.get("canned_message").setContent('');
    <?php endif; ?>
    document.getElementById('add-title').style.display = 'block';
    document.getElementById('edit-title').style.display = 'none';
}
//-->
</script>
<?php

hesk_cleanSessionVars('canned');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/
function edit_saved()
{
    global $hesk_settings, $hesklang, $editable_templates, $manage_url, $can_manage_all_categories;

    /* A security check */
    hesk_token_check('POST');
    $_SESSION['suppress_ticket_template_view_hook'] = true;

    $hesk_error_buffer = '';
    $errors = array();

    $id = intval( hesk_POST('saved_replies') );
    if ($id && !isset($editable_templates[$id])) {
        hesk_hook_ticket_template_updated(array('id' => $id), 'category|0');
        hesk_error($hesklang['no_permission']);
    }
    if (!$id) {
        $hesk_error_buffer .= '<li>' . $hesklang['sel_ticket_tpl'] . '</li>';
        $errors[] = 'id';
    }
    $savename = hesk_input( hesk_POST('name') );
    if (!$savename) {
        $hesk_error_buffer .= '<li>' . $hesklang['ent_ticket_tpl_title'] . '</li>';
        $errors[] = 'name';
    }
    $msg = hesk_input( hesk_POST('msg') );
    if (!$msg) {
        $hesk_error_buffer .= '<li>' . $hesklang['ent_ticket_tpl_msg'] . '</li>';
        $errors[] = 'msg';
    }

    // Category scope, using the same choices as custom fields.
    $category = hesk_POST('category') ? 1 : 0;
    $categories = array();
    if ($category) {
        $posted_categories = hesk_POST_array('categories');
        foreach ($posted_categories as $cat_id) {
            if (!is_string($cat_id) || !isset($hesk_settings['categories'][$cat_id])) {
                hesk_error($hesklang['no_permission']);
            }
        }
        foreach ($hesk_settings['categories'] as $cat_id => $cat_name) {
            if (in_array((string) $cat_id, $posted_categories, true)) {
                $categories[] = intval($cat_id);
            }
        }
        sort($categories, SORT_NUMERIC);
        if (!count($categories)) {
            $hesk_error_buffer .= '<li>' . $hesklang['escalate']['nocat'] . '</li>';
            $errors[] = 'categories';
        }
    }
    if (!$category && !$can_manage_all_categories) {
        $categories = array_map('intval', array_keys($hesk_settings['categories']));
        sort($categories, SORT_NUMERIC);
    }
    $category_value = $category || !$can_manage_all_categories ? json_encode($categories) : null;

    // Avoid problems with utf-8 newline chars in Javascript code, detect and remove them
    $msg = preg_replace('/\R/u', "\r\n", $msg);

    $_SESSION['canned']['what'] = 'EDIT';
    $_SESSION['canned']['id'] = $id;
    $_SESSION['canned']['name'] = $savename;
    $_SESSION['canned']['msg'] = $msg;
    $_SESSION['canned']['category'] = $category;
    $_SESSION['canned']['categories'] = $categories;
    $_SESSION['canned']['errors'] = $errors;

    /* Any errors? */
    if (strlen($hesk_error_buffer))
    {
        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,$manage_url.'&saved_replies='.$id);
    }

    if ($hesk_settings['staff_ticket_formatting'] == 2) {
        // Decode the message we encoded earlier
        $msg_html = hesk_html_entity_decode($msg);

        // Clean the HTML code and set the plaintext version
        require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
        require(HESK_PATH . 'inc/html2text/html2text.php');
        $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
        $msg_html = $purifier->heskPurify($msg_html);

        $msg = convert_html_to_text($msg_html);
        $msg = fix_newlines($msg);
        // Replace regular newlines with \r\n to match regular plaintext storage... but then get rid of any accidental \r\r\n outputs
        $msg = str_replace("\n", "\r\n", $msg);
        $msg = str_replace("\r\r\n", "\r\n", $msg);

        // Re-encode the message
        $msg = hesk_htmlspecialchars($msg);
    } else {
        $msg_html = hesk_makeURL($msg);
        $msg_html = nl2br($msg_html);
    }

    $existing_template = array_intersect_key($editable_templates[$id], array_flip(array('title', 'message', 'message_html', 'category')));
    $new_template_values = array(
        'title' => $savename,
        'message' => $msg,
        'message_html' => $msg_html,
        'category' => $category_value
    );
    $result = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` SET `title`='".hesk_dbEscape($savename)."',`message`='".hesk_dbEscape($msg)."', `message_html`='".hesk_dbEscape($msg_html)."', `category`=".($category_value === null ? 'NULL' : "'".hesk_dbEscape($category_value)."'")." WHERE `id`='".intval($id)."'");
    hesk_hook_ticket_template_updated(array(
        'id' => $id,
        'title' => $savename,
        'message' => $msg,
        'message_html' => $msg_html,
        'category' => $category_value,
        'changes' => hesk_hooks_get_changed_fields($existing_template, $new_template_values, array(), array(), array('title', 'message', 'message_html'))
    ), true);
    $_SESSION['canned']['selcat2'] = $id;

    unset($_SESSION['canned']['what']);
    unset($_SESSION['canned']['id']);
    unset($_SESSION['canned']['name']);
    unset($_SESSION['canned']['msg']);
    unset($_SESSION['canned']['errors']);
    unset($_SESSION['canned']['category']);
    unset($_SESSION['canned']['categories']);

    hesk_process_messages($hesklang['ticket_tpl_saved'],$manage_url.'&saved_replies='.$id,'SUCCESS');
} // End edit_saved()

function new_saved()
{
    global $hesk_settings, $hesklang, $manage_url, $can_manage_all_categories;

    /* A security check */
    hesk_token_check('POST');
    $_SESSION['suppress_ticket_template_view_hook'] = true;

    if (!count($hesk_settings['categories'])) {
        hesk_hook_ticket_template_created(null, 'category|0');
        hesk_error($hesklang['no_permission']);
    }

    $hesk_error_buffer = '';
    $errors = array();

    $savename = hesk_input( hesk_POST('name') );
    if (!$savename) {
        $hesk_error_buffer .= '<li>' . $hesklang['ent_ticket_tpl_title'] . '</li>';
        $errors[] = 'name';
    }
    $msg = hesk_input( hesk_POST('msg') );
    if (!$msg) {
        $hesk_error_buffer .= '<li>' . $hesklang['ent_ticket_tpl_msg'] . '</li>';
        $errors[] = 'msg';
    }

    // Category scope, using the same choices as custom fields.
    $category = hesk_POST('category') ? 1 : 0;
    $categories = array();
    if ($category) {
        $posted_categories = hesk_POST_array('categories');
        foreach ($posted_categories as $cat_id) {
            if (!is_string($cat_id) || !isset($hesk_settings['categories'][$cat_id])) {
                hesk_error($hesklang['no_permission']);
            }
        }
        foreach ($hesk_settings['categories'] as $cat_id => $cat_name) {
            if (in_array((string) $cat_id, $posted_categories, true)) {
                $categories[] = intval($cat_id);
            }
        }
        sort($categories, SORT_NUMERIC);
        if (!count($categories)) {
            $hesk_error_buffer .= '<li>' . $hesklang['escalate']['nocat'] . '</li>';
            $errors[] = 'categories';
        }
    }
    if (!$category && !$can_manage_all_categories) {
        $categories = array_map('intval', array_keys($hesk_settings['categories']));
        sort($categories, SORT_NUMERIC);
    }
    $category_value = $category || !$can_manage_all_categories ? json_encode($categories) : null;

    // Avoid problems with utf-8 newline chars in Javascript code, detect and remove them
    $msg = preg_replace('/\R/u', "\r\n", $msg);

    $_SESSION['canned']['what'] = 'NEW';
    $_SESSION['canned']['name'] = $savename;
    $_SESSION['canned']['msg'] = $msg;
    $_SESSION['canned']['category'] = $category;
    $_SESSION['canned']['categories'] = $categories;
    $_SESSION['canned']['errors'] = $errors;

    /* Any errors? */
    if (strlen($hesk_error_buffer))
    {
        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,$manage_url);
    }

    if ($hesk_settings['staff_ticket_formatting'] == 2) {
        // Decode the message we encoded earlier
        $msg_html = hesk_html_entity_decode($msg);

        // Clean the HTML code and set the plaintext version
        require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
        require(HESK_PATH . 'inc/html2text/html2text.php');
        $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
        $msg_html = $purifier->heskPurify($msg_html);

        $msg = convert_html_to_text($msg_html);
        $msg = fix_newlines($msg);
        // Replace regular newlines with \r\n to match regular plaintext storage... but then get rid of any accidental \r\r\n outputs
        $msg = str_replace("\n", "\r\n", $msg);
        $msg = str_replace("\r\r\n", "\r\n", $msg);

        // Re-encode the message
        $msg = hesk_htmlspecialchars($msg);
    } else {
        $msg_html = hesk_makeURL($msg);
        $msg_html = nl2br($msg_html);
    }

    /* Get the latest tpl_order */
    $result = hesk_dbQuery('SELECT `tpl_order` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'ticket_templates` ORDER BY `tpl_order` DESC LIMIT 1');
    $row = hesk_dbFetchRow($result);
    $my_order = isset($row[0]) ? intval($row[0]) + 10 : 10;

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` (`title`,`message`,`message_html`,`tpl_order`,`category`) VALUES ('".hesk_dbEscape($savename)."','".hesk_dbEscape($msg)."','".hesk_dbEscape($msg_html)."','".intval($my_order)."',".($category_value === null ? 'NULL' : "'".hesk_dbEscape($category_value)."'").")");
    $_SESSION['canned']['selcat2'] = hesk_dbInsertID();
    hesk_hook_ticket_template_created([
        'id' => $_SESSION['canned']['selcat2'],
        'title' => $savename,
        'message' => $msg,
        'message_html' => $msg_html,
        'tpl_order' => $my_order,
        'category' => $category_value,
    ], true);

    unset($_SESSION['canned']['what']);
    unset($_SESSION['canned']['name']);
    unset($_SESSION['canned']['msg']);
    unset($_SESSION['canned']['errors']);
    unset($_SESSION['canned']['category']);
    unset($_SESSION['canned']['categories']);

    hesk_process_messages($hesklang['ticket_tpl_saved'],$manage_url,'SUCCESS');
} // End new_saved()

function remove()
{
    global $hesk_settings, $hesklang, $editable_templates, $manage_url;

    /* A security check */
    hesk_token_check();
    $_SESSION['suppress_ticket_template_view_hook'] = true;

    $mysaved = intval( hesk_GET('id') ) or hesk_error($hesklang['id_not_valid']);
    if (!isset($editable_templates[$mysaved])) {
        hesk_hook_ticket_template_deleted($mysaved, 'category|0');
        hesk_error($hesklang['no_permission']);
    }

    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` WHERE `id`='".intval($mysaved)."'");
    if (hesk_dbAffectedRows() != 1)
    {
        hesk_error("$hesklang[int_error]: $hesklang[ticket_tpl_not_found].");
    }
    hesk_hook_ticket_template_deleted($mysaved, true);

    hesk_process_messages($hesklang['ticket_tpl_removed'],$manage_url,'SUCCESS');
} // End remove()

function order_saved()
{
    global $hesk_settings, $hesklang, $editable_templates, $order_ids, $manage_url;

    hesk_token_check();
    $_SESSION['suppress_ticket_template_view_hook'] = true;

    $id = intval(hesk_GET('replyid'));
    $move = intval(hesk_GET('move'));
    $index = array_search($id, $order_ids, true);
    if ($index === false || !in_array($move, array(-15, 15), true)) {
        hesk_hook_ticket_template_updated(array('id' => $id), 'category|0');
        hesk_error($hesklang['no_permission']);
    }
    $next = $index + ($move < 0 ? -1 : 1);
    if (isset($order_ids[$next])) {
        $other = $order_ids[$next];
        // Swap visible, editable entries without updating hidden or inaccessible templates.
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` SET `tpl_order` = CASE WHEN `id` = ".intval($id)." THEN ".intval($editable_templates[$other]['tpl_order'])." ELSE ".intval($editable_templates[$id]['tpl_order'])." END WHERE `id` IN (".intval($id).", ".intval($other).")");
        if (intval($editable_templates[$id]['tpl_order']) != intval($editable_templates[$other]['tpl_order'])) {
            foreach (array($id, $other) as $changed_id) {
                hesk_hook_ticket_template_updated(array('id' => $changed_id, 'changes' => array('tpl_order')), true);
            }
        }
        $_SESSION['canned']['selcat2'] = $id;
    }

    header('Location: '.$manage_url);
    exit();
} // End order_saved()

?>
