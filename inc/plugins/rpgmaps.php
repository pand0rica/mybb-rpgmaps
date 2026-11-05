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
        'website' => 'https://github.com/pand0rica/mybb-rpgmaps',
        'author' => 'pand0rica',
        'authorsite' => 'https://github.com/pand0rica',
        'version' => '1.1.0',
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
    rpgmaps_install_settings();
    rpgmaps_install_alert_type();
    // Templates, Header-Link und Stylesheet gehören zu activate()/deactivate() (debugging.md #8)
}

/**
 * Activate function called when plugin is activated/enabled
 * Updates templates for existing installations
 */
function rpgmaps_activate()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';
    rpgmaps_update_tables();
    rpgmaps_install_templates();
    rpgmaps_update_css();
    rpgmaps_install_alert_type();
}

/**
 * Spiegelbild zu activate(): Templates, Header-Link und Stylesheet entfernen.
 * Tabellen, Settings und Alert-Typ bleiben bis zur Deinstallation erhalten.
 */
function rpgmaps_deactivate()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';
    rpgmaps_uninstall_templates();
    rpgmaps_uninstall_css();
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
    rpgmaps_uninstall_alert_type();
    rpgmaps_uninstall_tables();
    rpgmaps_uninstall_templates();
    rpgmaps_uninstall_settings();
    rpgmaps_uninstall_css();
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
 * Räumt Häuser/Bewohner/Anträge gelöschter Accounts auf. datahandler_user_delete_start
 * deckt alle Löschwege ab (ACP einzeln/gesammelt, Zusammenführen, Frontend).
 *
 * @param UserDataHandler $userhandler
 */
function rpgmaps_user_delete_hook($userhandler)
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/hooks.php';
    foreach ((array)$userhandler->delete_uids as $uid) {
        rpgmaps_handle_user_deletion((int)$uid);
    }
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
        'title' => $lang->rpgmaps_admin_menu,
        'link' => 'index.php?module=tools-rpgmaps'
    );
}

// Load hook functions
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/hooks.php';

// Register hooks
$plugins->add_hook('global_intermediate', 'rpgmaps_build_nav');
$plugins->add_hook('global_intermediate', 'rpgmaps_register_myalerts_formatter', 20);
$plugins->add_hook('datahandler_user_delete_start', 'rpgmaps_user_delete_hook');
$plugins->add_hook('admin_tools_permissions', 'rpgmaps_admin_permissions');
$plugins->add_hook('admin_tools_menu', 'rpgmaps_admin_menu_hook');
$plugins->add_hook('admin_tools_action_handler', 'rpgmaps_admin_action_hook');
$plugins->add_hook('index_end', 'rpgmaps_index_notification');
$plugins->add_hook('modcp_start', 'rpgmaps_modcp_handler');
