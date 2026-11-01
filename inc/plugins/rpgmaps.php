<?php
/**
 * RPG Maps Plugin for myBB 1.8.x
 * Manage fantasy city maps, building plots, and house ownership
 * 
 * @license GPL-3.0-or-later
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
        'version' => '1.2.0',
        'codename' => 'rpgmaps',
        'compatibility' => '18*',
        'pl' => 0,
    ];
}

/**
 * Installation: Tabellen, Settings und MyAlerts-Typ anlegen (Nutzerdaten).
 * Templates, Stylesheet und Header-Link legt activate() an.
 */
function rpgmaps_install()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';

    rpgmaps_install_tables();
    rpgmaps_install_settings();
    rpgmaps_install_alert_type();
}

/**
 * Aktivierung: Migrationen, neue Settings nachrüsten, Templates, Stylesheet, Header-Link.
 * Spiegelbild: rpgmaps_deactivate().
 */
function rpgmaps_activate()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';

    rpgmaps_update_tables();
    rpgmaps_install_settings();   // idempotent, fasst vorhandene Werte nicht an
    rpgmaps_install_alert_type();
    rpgmaps_install_templates();
    rpgmaps_install_css();
    rpgmaps_add_nav_to_header();
}

/**
 * Deaktivierung: entfernt exakt, was activate() anlegt.
 * Settings und Daten bleiben erhalten (die entfernt erst uninstall()).
 */
function rpgmaps_deactivate()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';

    rpgmaps_remove_nav_from_header();
    rpgmaps_uninstall_templates();
    rpgmaps_uninstall_css();
}

/**
 * Deinstallation: Tabellen, Settings und MyAlerts-Typ entfernen.
 */
function rpgmaps_uninstall()
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/installer.php';

    rpgmaps_uninstall_alert_type();
    rpgmaps_uninstall_tables();
    rpgmaps_uninstall_settings();
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
 * Aufräumen beim Löschen von Accounts.
 * datahandler_user_delete_start deckt alle Löschpfade ab (ACP einzeln/bulk, Merge, Frontend).
 *
 * @param UserDataHandler $userhandler
 */
function rpgmaps_user_delete_hook($userhandler)
{
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/hooks.php';

    $uids = isset($userhandler->delete_uids) ? (array)$userhandler->delete_uids : [];
    foreach ($uids as $uid) {
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

// Nav-Template im Frontend mit vorab cachen (spart eine Query pro Seite)
// (Plugin-Dateien werden innerhalb von pluginSystem::load() eingebunden, daher $GLOBALS)
if (!defined('IN_ADMINCP') && isset($GLOBALS['templatelist'])) {
    $GLOBALS['templatelist'] .= ',rpgmaps_nav';
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
