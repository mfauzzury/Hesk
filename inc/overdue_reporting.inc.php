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

if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

// Use the same category and owner permissions as ordinary reports.
function hesk_overdue_report_where($alias, $date_from, $date_to, $can_run_reports_full, $category, $owner, $priority)
{
    global $hesk_settings;

    $table = '`' . $alias . '`';
    $where = $table . ".`dt` BETWEEN '" . hesk_dbEscape($date_from) . " 00:00:00' AND '" . hesk_dbEscape($date_to) . " 23:59:59'";

    if (!$can_run_reports_full) {
        $categories = array_map('intval', $_SESSION['categories']);
        $where .= " AND " . $table . ".`owner` = " . intval($_SESSION['id']);
        $where .= count($categories) ? " AND " . $table . ".`category` IN (" . implode(',', $categories) . ")" : ' AND 1=0';
    }

    if ($category !== 'all') {
        $where .= " AND " . $table . ".`category` = " . intval($category);
    }
    if ($owner !== 'all') {
        $where .= " AND " . $table . ".`owner` = " . intval($owner);
    }
    if ($priority !== 'all') {
        $where .= " AND " . $table . ".`priority` = " . intval($priority);
    }

    return $where;
}

function hesk_overdue_report_filter_value($name)
{
    $value = hesk_GET($name, 'all');
    return $value === 'all' || $value === '' ? 'all' : (preg_match('/^(0|[1-9][0-9]*)$/D', $value) ? intval($value) : -1);
}

function hesk_overdue_report_filters_form($can_run_reports_full, $enabled = true)
{
    global $hesk_settings, $hesklang;

    $category = hesk_overdue_report_filter_value('overdue_category');
    $owner = hesk_overdue_report_filter_value('overdue_owner');
    $priority = hesk_overdue_report_filter_value('overdue_priority');
    ?>
    <div class="reports__type">
        <h4><?php echo $hesklang['category']; ?></h4>
        <div class="dropdown-select center out-close">
            <select name="overdue_category" aria-label="<?php echo hesk_htmlspecialchars($hesklang['category']); ?>"<?php if (!$enabled) { echo ' disabled'; } ?>>
                <option value="all"><?php echo $hesklang['all']; ?></option>
                <?php
                $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".($can_run_reports_full ? '1' : hesk_myCategories('id'))." ORDER BY `cat_order` ASC");
                while ($row = hesk_dbFetchAssoc($res)) {
                    echo '<option value="'.intval($row['id']).'"'.($category === intval($row['id']) ? ' selected' : '').'>'.htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8', false).'</option>';
                }
                ?>
            </select>
        </div>
    </div>
    <div class="reports__type">
        <h4><?php echo $hesklang['assigned_to']; ?></h4>
        <div class="dropdown-select center out-close">
            <select name="overdue_owner" aria-label="<?php echo hesk_htmlspecialchars($hesklang['assigned_to']); ?>"<?php if (!$enabled) { echo ' disabled'; } ?>>
                <option value="all"><?php echo $hesklang['all']; ?></option>
                <?php if ($can_run_reports_full) { ?>
                    <option value="0"<?php echo $owner === 0 ? ' selected' : ''; ?>><?php echo $hesklang['unas']; ?></option>
                <?php }
                $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE ".($can_run_reports_full ? '1' : '`id` = '.intval($_SESSION['id']))." ORDER BY `name` ASC");
                while ($row = hesk_dbFetchAssoc($res)) {
                    echo '<option value="'.intval($row['id']).'"'.($owner === intval($row['id']) ? ' selected' : '').'>'.htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8', false).'</option>';
                }
                ?>
            </select>
        </div>
    </div>
    <div class="reports__type">
        <h4><?php echo $hesklang['priority']; ?></h4>
        <div class="dropdown-select center out-close">
            <select name="overdue_priority" aria-label="<?php echo hesk_htmlspecialchars($hesklang['priority']); ?>"<?php if (!$enabled) { echo ' disabled'; } ?>>
                <option value="all"><?php echo $hesklang['all']; ?></option>
                <?php
                require_once(HESK_PATH . 'inc/priorities.inc.php');
                foreach ($hesk_settings['priorities'] as $row) {
                    echo '<option value="'.intval($row['id']).'"'.($priority === intval($row['id']) ? ' selected' : '').'>'.htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8', false).'</option>';
                }
                ?>
            </select>
        </div>
    </div>
    <?php
}

function hesk_overdue_report_duration($seconds)
{
    global $hesklang;

    $seconds = max(0, (float) $seconds);
    if ($seconds > 0 && $seconds < 60) {
        return '&lt; 1' . hesk_htmlspecialchars($hesklang['abbr']['minute']);
    }

    // Keep seconds for calculations; display only complete hours and minutes.
    $minutes = intval(floor($seconds / 60));
    return floor($minutes / 60) . hesk_htmlspecialchars($hesklang['abbr']['hour']) . ' ' . ($minutes % 60) . hesk_htmlspecialchars($hesklang['abbr']['minute']);
}

function hesk_overdue_report_xml_row($values)
{
    $xml = '<Row>';
    foreach ($values as $value) {
        // Match Hesk's Excel exports: explicit text cells cannot execute formulas.
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
        $xml .= '<Cell><Data ss:Type="String">' . $value . '</Data></Cell>';
    }
    return $xml . "</Row>\n";
}

function hesk_overdue_report($date_from, $date_to, $can_run_reports_full, $export = false)
{
    global $hesk_settings, $hesklang;

    require_once(HESK_PATH . 'inc/priorities.inc.php');

    $category = hesk_overdue_report_filter_value('overdue_category');
    $owner = hesk_overdue_report_filter_value('overdue_owner');
    $priority = hesk_overdue_report_filter_value('overdue_priority');
    $table = '`'.hesk_dbEscape($hesk_settings['db_pfix']).'log_overdue`';
    $where = hesk_overdue_report_where('log', $date_from, $date_to, $can_run_reports_full, $category, $owner, $priority);
    $duration = 'GREATEST(0, TIMESTAMPDIFF(SECOND, `log`.`due_date`, `log`.`dt`))';

    $res = hesk_dbQuery("SELECT COUNT(*) AS `occurrences`, COUNT(DISTINCT `log`.`ticket`) AS `tickets`, AVG($duration) AS `average_seconds`, MAX($duration) AS `maximum_seconds`,
        SUM(CASE WHEN $duration < 3600 THEN 1 ELSE 0 END) AS `under_1`,
        SUM(CASE WHEN $duration >= 3600 AND $duration < 14400 THEN 1 ELSE 0 END) AS `under_4`,
        SUM(CASE WHEN $duration >= 14400 AND $duration < 86400 THEN 1 ELSE 0 END) AS `under_24`,
        SUM(CASE WHEN $duration >= 86400 AND $duration < 259200 THEN 1 ELSE 0 END) AS `under_72`,
        SUM(CASE WHEN $duration >= 259200 THEN 1 ELSE 0 END) AS `over_72`
        FROM $table AS `log` WHERE $where");
    $totals = hesk_dbFetchAssoc($res);
    $total = intval($totals['occurrences']);

    if ($export && $total > 10000) {
        hesk_error($hesklang['overdue_report']['export_limit']);
    }

    // Resolve names from the historical IDs; deleted users/categories retain their IDs.
    $categories = array();
    $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".($can_run_reports_full ? '1' : hesk_myCategories('id')));
    while ($row = hesk_dbFetchAssoc($res)) {
        $categories[$row['id']] = $row['name'];
    }
    $users = array();
    $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE ".($can_run_reports_full ? '1' : '`id` = '.intval($_SESSION['id'])));
    while ($row = hesk_dbFetchAssoc($res)) {
        $users[$row['id']] = $row['name'];
    }

    $page = max(1, intval(hesk_GET('page', 1)));
    $pages = max(1, ceil($total / 50));
    $page = min($page, $pages);
    $limit = $export ? ' LIMIT 10001' : ' LIMIT 50 OFFSET '.intval(($page - 1) * 50);
    $repeat_where = hesk_overdue_report_where('repeat', $date_from, $date_to, $can_run_reports_full, $category, $owner, $priority);

    // A historical owner must not receive a tracking link after losing current ticket access.
    $ticket_access = '';
    if (!hesk_checkPermission('can_view_tickets', 0)) {
        $ticket_access = ' AND 1=0';
    } elseif (empty($_SESSION['isadmin'])) {
        $allowed_categories = array_map('intval', $_SESSION['categories']);
        // A deleted category falls back to category 1 on the staff ticket page.
        $category_table = '`'.hesk_dbEscape($hesk_settings['db_pfix']).'categories`';
        $ticket_category = "COALESCE((SELECT `id` FROM $category_table WHERE `id`=`ticket`.`category` LIMIT 1), (SELECT `id` FROM $category_table WHERE `id`=1 LIMIT 1))";
        $ticket_access = count($allowed_categories) ? " AND $ticket_category IN (" . implode(',', $allowed_categories) . ")" : ' AND 1=0';
        $ticket_access .= " AND EXISTS (SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `visible`
            LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w`
                ON `w`.`ticket_id` = `visible`.`id` AND `w`.`user_id` = ".intval($_SESSION['id'])."
            WHERE `visible`.`id` = `ticket`.`id` AND ".hesk_myOwnership(true).")";
    }

    $details_sql = "SELECT `log`.`id`, `log`.`ticket`, `log`.`category`, `log`.`priority`, `log`.`owner`, `log`.`status`, `log`.`dt`, `log`.`due_date`,
        `ticket`.`trackid`, $duration AS `overdue_seconds`,
        (SELECT COUNT(*) FROM $table AS `repeat` WHERE `repeat`.`ticket` = `log`.`ticket` AND $repeat_where) AS `repeat_count`
        FROM $table AS `log`
        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket` ON `ticket`.`id` = `log`.`ticket` $ticket_access
        WHERE $where ORDER BY `log`.`dt` DESC, `log`.`id` DESC$limit";

    if ($export) {
        $res = hesk_dbQuery($details_sql);
        // Catch new matches between the total and detail queries; never silently truncate.
        if (hesk_dbNumRows($res) > 10000) {
            hesk_error($hesklang['overdue_report']['export_limit']);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<?mso-application progid="Excel.Sheet"?>' . "\n" .
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' .
            '<Styles><Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/>' .
            '<Alignment ss:Vertical="Top" ss:WrapText="1"/></Style></Styles>' .
            '<Worksheet ss:Name="Sheet1"><Table>' . str_repeat('<Column ss:AutoFitWidth="0" ss:Width="120"/>', 8) . "\n";
        $xml .= hesk_overdue_report_xml_row(array($hesklang['ticket'], $hesklang['category'], $hesklang['assigned_to'], $hesklang['priority'], $hesklang['overdue_report']['recorded'], $hesklang['due_date'], $hesklang['overdue_report']['delay'], $hesklang['overdue_report']['repeat_count']));
        while ($row = hesk_dbFetchAssoc($res)) {
            $catname = isset($categories[$row['category']]) ? $categories[$row['category']] : $hesklang['catd'].' #'.$row['category'];
            $username = !$row['owner'] ? $hesklang['unas'] : (isset($users[$row['owner']]) ? $users[$row['owner']] : $hesklang['e_udel'].' #'.$row['owner']);
            $priority_name = isset($hesk_settings['priorities'][$row['priority']]) ? $hesk_settings['priorities'][$row['priority']]['name'] : $row['priority'];
            // Export full timestamps and exact durations, not the shortened display values.
            $xml .= hesk_overdue_report_xml_row(array($row['trackid'] ? $row['trackid'] : $row['ticket'], $catname, $username, $priority_name, $row['dt'], $row['due_date'], (hesk_SecondsToHHMMSS($row['overdue_seconds']) ?: '00:00:00'), intval($row['repeat_count'])));
        }
        $xml .= '</Table></Worksheet></Workbook>';

        // Download an XML spreadsheet in a ZIP, like ticket and audit exports.
        $save_to_zip = @tempnam(sys_get_temp_dir(), 'hesk_overdue_');
        if ($save_to_zip === false) {
            hesk_error($hesklang['eef']);
        }
        try {
            if (extension_loaded('zip')) {
                $zip = new ZipArchive;
                if ($zip->open($save_to_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException($hesklang['eZIP']);
                }
                $added = $zip->addFromString('hesk_overdue_tickets.xml', $xml);
                $closed = $zip->close();
                if (!$added || !$closed) {
                    throw new RuntimeException($hesklang['eZIP']);
                }
            } else {
                require_once(HESK_PATH . 'inc/zip/pclzip.lib.php');
                $zip = new PclZip($save_to_zip);
                if ($zip->create(array(array(PCLZIP_ATT_FILE_NAME => 'hesk_overdue_tickets.xml', PCLZIP_ATT_FILE_CONTENT => $xml))) === 0) {
                    throw new RuntimeException($hesklang['eZIP']);
                }
            }
        } catch (Exception $e) {
            hesk_unlink($save_to_zip);
            hesk_error($e->getMessage());
        }
        clearstatcache(true, $save_to_zip);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        hesk_sendFileDownloadHeaders('hesk_overdue_tickets.zip', filesize($save_to_zip));
        readfile($save_to_zip);
        hesk_unlink($save_to_zip);
        return;
    }

    $res = hesk_dbQuery("SELECT COUNT(*) AS `repeated` FROM (SELECT `log`.`ticket` FROM $table AS `log` WHERE $where GROUP BY `log`.`ticket` HAVING COUNT(*) > 1) AS `repeated_tickets`");
    $repeated = hesk_dbFetchAssoc($res);

    $url_params = array('type' => 5, 'w' => intval(hesk_GET('w', 0)), 'time' => intval(hesk_GET('time', 3)), 'datefrom' => hesk_GET('datefrom'), 'dateto' => hesk_GET('dateto'),
        'overdue_category' => $category, 'overdue_owner' => $owner, 'overdue_priority' => $priority);
    $base_url = 'reports.php?'.http_build_query($url_params, '', '&', PHP_QUERY_RFC3986);
    ?>
    <style>
    .main__content.reports .overdue-report .reports__table { margin-right: 0; max-width: 100%; overflow-x: auto; }
    .overdue-report .table { table-layout: fixed; }
    .overdue-report .table th, .overdue-report .table td { white-space: normal; overflow-wrap: anywhere; }
    .overdue-report .overdue-details .table { min-width: 700px; }
    </style>
    <div class="overdue-report">
    <h3><?php echo $hesklang['overdue_report']['title']; ?></h3>
    <?php hesk_show_notice($hesklang['overdue_report']['note']); ?>
    <div class="reports__table">
        <table class="table sindu-table sindu_origin_table">
            <thead><tr>
                <th><?php echo $hesklang['overdue_report']['occurrences']; ?></th>
                <th><?php echo $hesklang['overdue_report']['unique_tickets']; ?></th>
                <th><?php echo $hesklang['overdue_report']['repeat_tickets']; ?></th>
                <th><?php echo $hesklang['overdue_report']['average_delay']; ?></th>
                <th><?php echo $hesklang['overdue_report']['maximum_delay']; ?></th>
            </tr></thead>
            <tbody><tr>
                <td><?php echo $total; ?></td><td><?php echo intval($totals['tickets']); ?></td><td><?php echo intval($repeated['repeated']); ?></td>
                <td><?php echo hesk_overdue_report_duration($totals['average_seconds']); ?></td>
                <td><?php echo hesk_overdue_report_duration($totals['maximum_seconds']); ?></td>
            </tr></tbody>
        </table>
    </div>
    <?php
    // Fill every month in the selected range, even when no events match the filters.
    $months = array();
    $res = hesk_dbQuery("SELECT DATE_FORMAT(`log`.`dt`, '%Y-%m') AS `period`, COUNT(*) AS `count`, COUNT(DISTINCT `log`.`ticket`) AS `tickets` FROM $table AS `log` WHERE $where GROUP BY `period` ORDER BY `period` ASC");
    while ($row = hesk_dbFetchAssoc($res)) {
        $months[$row['period']] = $row;
    }
    echo '<h4>'.$hesklang['overdue_report']['by_month'].'</h4><div class="reports__table overdue-months"><table class="table sindu-table sindu_origin_table"><thead><tr><th>'.$hesklang['month'].'</th><th>'.$hesklang['overdue_report']['occurrences'].'</th><th>'.$hesklang['overdue_report']['unique_tickets'].'</th></tr></thead><tbody>';
    $month = new DateTime(substr($date_from, 0, 7) . '-01');
    $last_month = new DateTime(substr($date_to, 0, 7) . '-01');
    while ($month <= $last_month) {
        $key = $month->format('Y-m');
        $row = isset($months[$key]) ? $months[$key] : array('count' => 0, 'tickets' => 0);
        echo '<tr><td>'.htmlspecialchars($hesklang['m'.$month->format('n')].' '.$month->format('Y'), ENT_QUOTES, 'UTF-8').'</td><td>'.intval($row['count']).'</td><td>'.intval($row['tickets']).'</td></tr>';
        $month->modify('+1 month');
    }
    echo '</tbody></table></div>';

    if (!$total) {
        echo '<p>'.$hesklang['no_results_found'].'</p></div>';
        return;
    }
    ?>
    <h4><?php echo $hesklang['overdue_report']['delay_breakdown']; ?></h4>
    <div class="reports__table"><table class="table sindu-table sindu_origin_table">
        <thead><tr><?php foreach (array('lt1','lt4','lt24','lt72','gte72') as $key) { echo '<th>'.$hesklang['overdue_report'][$key].'</th>'; } ?></tr></thead>
        <tbody><tr><?php foreach (array('under_1','under_4','under_24','under_72','over_72') as $key) { echo '<td>'.intval($totals[$key]).'</td>'; } ?></tr></tbody>
    </table></div>
    <?php
    foreach (array(
        array('category', 'category', $hesklang['overdue_report']['by_category']),
        array('owner', 'owner', $hesklang['overdue_report']['by_assignee'])
    ) as $group) {
        $res = hesk_dbQuery("SELECT `log`.`".$group[0]."` AS `item_id`, COUNT(*) AS `count`, COUNT(DISTINCT `log`.`ticket`) AS `tickets`, AVG($duration) AS `average_seconds`, MAX($duration) AS `maximum_seconds`
            FROM $table AS `log` WHERE $where GROUP BY `log`.`".$group[0]."` ORDER BY `count` DESC, `item_id` ASC");
        echo '<h4>'.$group[2].'</h4><div class="reports__table"><table class="table sindu-table sindu_origin_table"><thead><tr><th>'.($group[1] === 'category' ? $hesklang['category'] : $hesklang['assigned_to']).'</th><th>'.$hesklang['overdue_report']['occurrences'].'</th><th>'.$hesklang['overdue_report']['unique_tickets'].'</th><th>'.$hesklang['overdue_report']['average_delay'].'</th><th>'.$hesklang['overdue_report']['maximum_delay'].'</th></tr></thead><tbody>';
        while ($row = hesk_dbFetchAssoc($res)) {
            if ($group[1] === 'category') {
                $name = isset($categories[$row['item_id']]) ? $categories[$row['item_id']] : $hesklang['catd'].' #'.$row['item_id'];
            } else {
                $name = !$row['item_id'] ? $hesklang['unas'] : (isset($users[$row['item_id']]) ? $users[$row['item_id']] : $hesklang['e_udel'].' #'.$row['item_id']);
            }
            echo '<tr><td>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8', false).'</td><td>'.intval($row['count']).'</td><td>'.intval($row['tickets']).'</td><td>'.hesk_overdue_report_duration($row['average_seconds']).'</td><td>'.hesk_overdue_report_duration($row['maximum_seconds']).'</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<h4>'.$hesklang['overdue_report']['details'].'</h4>';
    if (hesk_checkPermission('can_export', 0) && $total <= 10000) {
        echo '<p><a class="btn btn--blue-border" href="'.htmlspecialchars($base_url.'&overdue_export=1&token='.hesk_token_echo(0), ENT_QUOTES, 'UTF-8').'">'.$hesklang['audit_log']['export_btn'].'</a></p>';
    } elseif (hesk_checkPermission('can_export', 0) && $total > 10000) {
        echo '<p>'.$hesklang['overdue_report']['export_limit'].'</p>';
    }
    echo '<div class="reports__table overdue-details" tabindex="0" role="region" aria-label="'.htmlspecialchars($hesklang['overdue_report']['details'], ENT_QUOTES, 'UTF-8').'"><table class="table sindu-table sindu_origin_table"><thead><tr><th>'.$hesklang['ticket'].'</th><th>'.$hesklang['category'].'</th><th>'.$hesklang['assigned_to'].'</th><th>'.$hesklang['priority'].'</th><th>'.$hesklang['overdue_report']['recorded'].'</th><th>'.$hesklang['due_date'].'</th><th>'.$hesklang['overdue_report']['delay'].'</th><th title="'.htmlspecialchars($hesklang['overdue_report']['repeat_count'], ENT_QUOTES, 'UTF-8').'">'.$hesklang['overdue_report']['occurrences'].'</th></tr></thead><tbody>';
    $res = hesk_dbQuery($details_sql);
    while ($row = hesk_dbFetchAssoc($res)) {
        $catname = isset($categories[$row['category']]) ? $categories[$row['category']] : $hesklang['catd'].' #'.$row['category'];
        $username = !$row['owner'] ? $hesklang['unas'] : (isset($users[$row['owner']]) ? $users[$row['owner']] : $hesklang['e_udel'].' #'.$row['owner']);
        $priority_name = isset($hesk_settings['priorities'][$row['priority']]) ? $hesk_settings['priorities'][$row['priority']]['name'] : $row['priority'];
        $ticket = $row['trackid'] ? '<a href="admin_ticket.php?track='.rawurlencode($row['trackid']).'">'.htmlspecialchars($row['trackid'], ENT_QUOTES, 'UTF-8').'</a>' : '#'.intval($row['ticket']);
        echo '<tr><td>'.$ticket.'</td><td>'.htmlspecialchars($catname, ENT_QUOTES, 'UTF-8', false).'</td><td>'.htmlspecialchars($username, ENT_QUOTES, 'UTF-8', false).'</td><td>'.htmlspecialchars($priority_name, ENT_QUOTES, 'UTF-8', false).'</td><td>'.hesk_date($row['dt'], true).'</td><td>'.hesk_format_due_date($row['due_date']).'</td><td>'.hesk_overdue_report_duration($row['overdue_seconds']).'</td><td>'.intval($row['repeat_count']).'</td></tr>';
    }
    echo '</tbody></table></div>';
    if ($pages > 1) {
        echo '<p>'.$hesklang['pg'].' '.intval($page).' / '.intval($pages).' &nbsp; ';
        if ($page > 1) {
            echo '<a href="'.htmlspecialchars($base_url.'&page='.($page-1), ENT_QUOTES, 'UTF-8').'">'.$hesklang['pager_previous'].'</a> ';
        }
        if ($page < $pages) {
            echo '<a href="'.htmlspecialchars($base_url.'&page='.($page+1), ENT_QUOTES, 'UTF-8').'">'.$hesklang['pager_next'].'</a>';
        }
        echo '</p>';
    }
    echo '</div>';
}
