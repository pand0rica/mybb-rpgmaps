<?php
/**
 * RPG Maps Plugin - Hooks Handler
 * Handles myBB hooks (user deletion, etc.)
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Register RPG Maps alert formatter with MyAlerts (if active)
 * Runs at global_intermediate priority 20, after MyAlerts sets up its instances at priority 10
 */
function rpgmaps_register_myalerts_formatter()
{
    if (!function_exists('myalerts_create_instances')) {
        return;
    }

    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
    if (!$formatterManager) {
        return;
    }

    global $mybb, $lang;

    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/src/Formatter/BuildApprovedFormatter.php';

    $formatterManager->registerFormatter(
        new RPGMaps_Formatter_BuildApproved($mybb, $lang, 'rpgmaps_build_approved')
    );
}

/**
 * Build navigation item for the header
 */
function rpgmaps_build_nav()
{
    global $templates, $rpgmaps_nav, $mybb, $lang;

    $rpgmaps_nav = '';

    if (empty($mybb->settings['rpgmaps_enabled'])) {
        return;
    }

    $lang->load('rpgmaps');
    $rpgmaps_nav_active = (THIS_SCRIPT == 'rpgmaps.php') ? 'class="selected_navi"' : '';
    eval('$rpgmaps_nav = "' . $templates->get('rpgmaps_nav') . '";');
}

/**
 * Handle user deletion hook
 * @param int $uid User ID being deleted
 */
function rpgmaps_handle_user_deletion($uid)
{
    global $db;
    
    // Only proceed if plugin is installed
    if (!$db->table_exists('rpgmaps_houses')) {
        return;
    }
    
    // Load the manager
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    // Clean up user data
    $manager->handleUserDeletion($uid);
}

/**
 * Show notification on index for staff members
 */
function rpgmaps_index_notification()
{
    global $mybb, $db, $header;

    if ($mybb->usergroup['cancp'] != 1 && $mybb->usergroup['canmodcp'] != 1) {
        return;
    }

    if (!$db->table_exists('rpgmaps_actions')) {
        return;
    }

    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();

    $count = count($db_helper->getPendingActions());

    if ($count < 1) {
        return;
    }

    $links = '';
    if ($mybb->usergroup['canmodcp'] == 1) {
        $links .= ' &mdash; <a href="' . $mybb->settings['bburl'] . '/modcp.php?action=rpgmaps">Mod-CP</a>';
    }
    if ($mybb->usergroup['cancp'] == 1) {
        $links .= ' &mdash; <a href="' . $mybb->settings['bburl'] . '/admin/index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions">Admin-CP</a>';
    }

    $text = ($count == 1)
        ? 'Es gibt 1 ausstehenden Bauantrag zu pr&uuml;fen.'
        : 'Es gibt ' . (int)$count . ' ausstehende Bauantr&auml;ge zu pr&uuml;fen.';

    $header .= '<div class="pm_alert"><strong>RPG Maps:</strong> ' . $text . $links . '</div>';
}

/**
 * Add ModCP nav item and handle ModCP actions
 * Runs at modcp_start — after $modcp_nav is fully compiled (line 250 in modcp.php)
 */
function rpgmaps_modcp_handler()
{
    global $mybb, $db, $lang, $templates, $modcp_nav;

    if ($mybb->usergroup['canmodcp'] != 1) {
        return;
    }

    if (!$db->table_exists('rpgmaps_actions')) {
        return;
    }

    $lang->load('rpgmaps');

    // Insert nav item inside the compiled $modcp_nav table (before closing </table>)
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    $pending_count = count($db_helper->getPendingActions());
    $count_badge = $pending_count > 0
        ? ' <span class="rpgmaps-pending-badge">' . (int)$pending_count . '</span>'
        : '';
    $nav_item = '';
    eval('$nav_item = "' . $templates->get('modcp_nav_rpgmaps') . '";');
    $insert_pos = strrpos($modcp_nav, '</table>');
    if ($insert_pos !== false) {
        $modcp_nav = substr_replace($modcp_nav, $nav_item, $insert_pos, 0);
    }

    // Handle rpgmaps action
    if ($mybb->get_input('action') === 'rpgmaps') {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/modules/modcp/rpgmaps_modcp.php';
        rpgmaps_modcp_main();
        exit;
    }
}
