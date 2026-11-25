<?php
/**
 * Ticket overview panel shown at the top of the staff ticket list (admin_main.php).
 * Counts only tickets this agent is allowed to see (same helpers the ticket list uses).
 */
if (!defined('IN_SCRIPT')) {die();}

global $hesk_settings, $hesklang;

$ov_me   = intval($_SESSION['id']);
$ov_pfix = hesk_dbEscape($hesk_settings['db_pfix']);

$ov_res = hesk_dbQuery("SELECT
        SUM(`status` = 0) AS `s_new`,
        SUM(`status` = 1) AS `s_wait`,
        SUM(`status` = 4) AS `s_prog`,
        SUM(`status` != 3 AND `due_date` IS NOT NULL AND `due_date` < NOW()) AS `s_over`,
        SUM(`status` != 3 AND `owner` = 0) AS `s_unas`,
        SUM(`status` != 3 AND `owner` = {$ov_me}) AS `s_mine`,
        SUM(`status` != 3) AS `s_open`
    FROM `{$ov_pfix}tickets`
    LEFT JOIN (
        SELECT DISTINCT `ticket_id`, `user_id`
        FROM `{$ov_pfix}ticket_to_collaborator`
        WHERE `user_id` = {$ov_me}
    ) AS `w` ON (`{$ov_pfix}tickets`.`id` = `w`.`ticket_id`)
    WHERE `archive` = '0' AND " . hesk_myCategories() . " AND " . hesk_myOwnership(1));
$ov = hesk_dbFetchAssoc($ov_res);
foreach (array('s_new','s_wait','s_prog','s_over','s_unas','s_mine','s_open') as $ov_k) {
    $ov[$ov_k] = isset($ov[$ov_k]) ? intval($ov[$ov_k]) : 0;
}

$ov_first = '';
if (!empty($_SESSION['name'])) {
    $parts = preg_split('/\s+/', trim($_SESSION['name']));
    $ov_first = $parts[0];
}
$ov_attention = $ov['s_new'] + $ov['s_wait'];

$ov_all = 's_my=1&amp;s_ot=1&amp;s_un=1&amp;category=0';
$ov_status_name = function ($id, $fallback) use ($hesk_settings) {
    return isset($hesk_settings['statuses'][$id]['name']) ? $hesk_settings['statuses'][$id]['name'] : $fallback;
};
$ov_cards = array(
    array('label' => '<span class="open">' . $ov_status_name(0, 'New') . '</span>',
          'value' => $ov['s_new'], 'note' => 'Not answered yet',
          'href' => 'admin_main.php?s0=1&amp;' . $ov_all),
    array('label' => '<span class="waitingreply">' . $ov_status_name(1, 'Waiting reply') . '</span>',
          'value' => $ov['s_wait'], 'note' => 'Customer is waiting on us',
          'href' => 'admin_main.php?s1=1&amp;' . $ov_all),
    array('label' => '<span class="inprogress">' . $ov_status_name(4, 'In progress') . '</span>',
          'value' => $ov['s_prog'], 'note' => 'Being worked on',
          'href' => 'admin_main.php?s4=1&amp;' . $ov_all),
    array('label' => 'Overdue',
          'value' => $ov['s_over'], 'note' => 'Past the due date',
          'href' => 'admin_main.php?ql=ovr&amp;s_my=1&amp;s_ot=1&amp;s_un=1&amp;s_co=1', 'alert' => true),
    array('label' => 'Unassigned',
          'value' => $ov['s_unas'], 'note' => 'Waiting for an owner',
          'href' => 'admin_main.php?ql=un'),
    array('label' => 'Assigned to me',
          'value' => $ov['s_mine'], 'note' => 'Yours to handle',
          'href' => 'admin_main.php?ql=my'),
);
?>
<div class="tk-overview" role="region" aria-label="Ticket overview">
    <div class="tk-overview__head">
        <p class="tk-overview__hello">Welcome back<?php echo $ov_first !== '' ? ', ' . hesk_htmlspecialchars($ov_first) : ''; ?></p>
        <?php if ($ov['s_open'] === 0): ?>
            <p class="tk-overview__headline">You are all caught up</p>
            <p class="tk-overview__sub">There are no open tickets right now.</p>
        <?php else: ?>
            <p class="tk-overview__headline">
                <?php echo $ov_attention; ?> <?php echo $ov_attention === 1 ? 'ticket needs' : 'tickets need'; ?> your attention
            </p>
            <p class="tk-overview__sub">
                <?php echo $ov['s_open']; ?> open in total<?php if ($ov['s_over'] > 0): ?>, <strong><?php echo $ov['s_over']; ?> overdue</strong><?php endif; ?>.
            </p>
        <?php endif; ?>
    </div>
    <div class="tk-stats">
        <?php foreach ($ov_cards as $card): ?>
        <a href="<?php echo $card['href']; ?>" class="tk-stat<?php echo (!empty($card['alert']) && $card['value'] > 0) ? ' tk-stat--alert' : ''; ?>">
            <span class="tk-stat__label"><?php echo $card['label']; ?></span>
            <span class="tk-stat__value"><?php echo $card['value']; ?></span>
            <span class="tk-stat__note"><?php echo $card['note']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
