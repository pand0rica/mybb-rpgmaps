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
    global $mybb, $db, $templates, $rpgmaps_notification;
    
    // Initialize variable
    $rpgmaps_notification = '';
    
    // Check if user is staff (admin or moderator)
    if ($mybb->usergroup['cancp'] != 1 && $mybb->usergroup['canmodcp'] != 1) {
        return;
    }
    
    // Only proceed if plugin is installed
    if (!$db->table_exists('rpgmaps_actions')) {
        return;
    }
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $pending_actions = $db_helper->getPendingActions();
    $count = count($pending_actions);
    
    if ($count > 0) {
        $plural = ($count == 1) ? '' : 's';
        $link_acp = '';
        $link_modcp = '';
        
        if ($mybb->usergroup['cancp'] == 1) {
            $link_acp = ' <a href="' . $mybb->settings['bburl'] . '/admin/index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions">[Admin-CP]</a>';
        }
        if ($mybb->usergroup['canmodcp'] == 1) {
            $link_modcp = ' <a href="modcp.php?action=rpgmaps"">[Mod-CP]</a>';
        }
        
        // Set template variable
        $rpgmaps_notification = '<div class="pm_alert">
            <strong>RPG Maps:</strong> Es gibt ' . $count . ' ausstehende Bauanträge/Anfrage' . $plural . ' zu prüfen.' . $link_acp . $link_modcp . '
        </div>';
    }
}

/**
 * Add ModCP navigation item
 */
function rpgmaps_modcp_nav()
{
    global $mybb, $templates, $modcp_nav, $db, $lang;
    
    // Check if user can access ModCP
    if ($mybb->usergroup['canmodcp'] != 1) {
        return;
    }
    
    // Only proceed if plugin is installed
    if (!$db->table_exists('rpgmaps_actions')) {
        return;
    }
    
    $lang->load('rpgmaps');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    $pending_count = count($db_helper->getPendingActions());
    
    $count_badge = '';
    if ($pending_count > 0) {
        $count_badge = ' <span class="rpgmaps-pending-badge">' . $pending_count . '</span>';
    }
    
    eval('$modcp_nav .= "' . $templates->get('modcp_nav_rpgmaps') . '";');
}

/**
 * Handle ModCP actions
 */
function rpgmaps_modcp_handler()
{
    global $mybb;
    
    if ($mybb->input['action'] == 'rpgmaps') {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/modules/modcp/rpgmaps_modcp.php';
        rpgmaps_modcp_main();
        exit;
    }
}
