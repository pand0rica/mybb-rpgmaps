<?php
/**
 * RPG Maps Plugin for myBB 1.8.x
 * Manage fantasy city maps, building plots, and house ownership
 * 
 * @copyright 2026
 * @license MIT
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Plugin info function required by myBB
 * @return array Plugin information
 */
function rpgmaps_info()
{
    return [
        'name' => 'RPG Maps Plugin',
        'description' => 'Manage fantasy city maps, building plots, and house ownership with administrative approval system',
        'website' => '',
        'author' => 'Admin',
        'authorsite' => '',
        'version' => '1.0.0',
        'codename' => 'rpgmaps',
        'compatibility' => '18*',
        'pl' => 0,
    ];
}

/**
 * Install function called when plugin is activated
 * Creates database tables, templates, and settings
 */
function rpgmaps_install()
{
    global $db;
    
    // Include database setup
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';
    rpgmaps_install_tables();
    rpgmaps_install_templates();
    rpgmaps_install_settings();
    rpgmaps_install_hooks();
    rpgmaps_install_css();
}

/**
 * Activate function called when plugin is activated/enabled
 * Updates templates for existing installations
 */
function rpgmaps_activate()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';
    rpgmaps_update_templates();
    rpgmaps_update_tables();
}

/**
 * Uninstall function called when plugin is deactivated
 * Removes database tables, templates, and settings
 */
function rpgmaps_uninstall()
{
    global $db;
    
    // Include database setup
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';
    rpgmaps_uninstall_tables();
    rpgmaps_uninstall_templates();
    rpgmaps_uninstall_settings();
    rpgmaps_uninstall_css();
    rpgmaps_uninstall_hooks();
}

/**
 * is_installed function to check if plugin is installed
 * @return bool
 */
function rpgmaps_is_installed()
{
    global $db;
    
    // Check if at least one of our tables exists
    return $db->table_exists('rpgmaps_maps');
}

/**
 * Frontend action hook - register frontend script
 * Handles main rpgmaps.php page
 */
function rpgmaps_frontend_action_hook()
{
    global $action, $mybb;
    
    // Only load our plugin code if action is 'rpgmaps'
    if ($action === 'rpgmaps') {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/frontend.php';
    }
}

/**
 * AJAX hook for handling AJAX requests
 */
function rpgmaps_ajax_hook()
{
    global $mybb;
    
    // Get the sub-action from AJAX request
    $sub = isset($_REQUEST['sub']) ? $_REQUEST['sub'] : '';
    
    if (!empty($sub) && strpos($sub, 'rpgmaps_') === 0) {
        // Load AJAX handler
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/ajax.php';
        rpgmaps_handle_ajax($sub);
    }
}

/**
 * User delete hook
 * Cleans up occupants when users are deleted
 */
function rpgmaps_user_delete_hook($uid)
{
    global $db;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/hooks.php';
    rpgmaps_handle_user_deletion($uid);
}

/**
 * Admin action hook for ACP modules
 */
function rpgmaps_admin_action_hook(&$actions)
{
    $actions['rpgmaps'] = array(
        'active' => 'rpgmaps',
        'file' => 'rpgmaps.php'
    );
}

/**
 * Admin permissions hook
 */
function rpgmaps_admin_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load('rpgmaps');
    
    $admin_permissions['rpgmaps'] = $lang->rpgmaps_can_manage;
    
    return $admin_permissions;
}

/**
 * Admin menu hook - add RPG Maps to admin panel menu
 */
function rpgmaps_admin_menu_hook(&$sub_menu)
{
    global $lang;
    
    $lang->load('rpgmaps');
    
    $sub_menu['150'] = array(
        'id' => 'rpgmaps',
        'title' => 'RPG Maps',
        'link' => 'index.php?module=tools-rpgmaps'
    );
}

// Load hook functions
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/hooks.php';

// Register hooks
$plugins->add_hook('action_start', 'rpgmaps_frontend_action_hook');
$plugins->add_hook('ajax_rpgmaps', 'rpgmaps_ajax_hook');
$plugins->add_hook('user_delete', 'rpgmaps_user_delete_hook');
$plugins->add_hook('admin_action_handler', 'rpgmaps_admin_action_hook');
$plugins->add_hook('admin_permissions', 'rpgmaps_admin_permissions');
$plugins->add_hook('admin_tools_menu', 'rpgmaps_admin_menu_hook');
$plugins->add_hook('admin_tools_action_handler', 'rpgmaps_admin_action_hook');
$plugins->add_hook('index_start', 'rpgmaps_index_notification');
$plugins->add_hook('modcp_nav', 'rpgmaps_modcp_nav');
$plugins->add_hook('modcp_start', 'rpgmaps_modcp_handler');
