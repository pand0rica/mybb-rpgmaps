<?php
/**
 * RPG Maps Plugin - Installer
 * Database and initial setup
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Install database tables
 */
function rpgmaps_install_tables()
{
    global $db;

    // Check if tables already exist
    if ($db->table_exists('rpgmaps_maps')) {
        return; // Already installed
    }

    // rpgmaps_maps table - Store map definitions
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_maps` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `filename` VARCHAR(255) NOT NULL COMMENT 'Filename in assets/maps/',
            `width` INT UNSIGNED NOT NULL DEFAULT 800,
            `height` INT UNSIGNED NOT NULL DEFAULT 600,
            `scale_factor` FLOAT DEFAULT 1.0 COMMENT 'Scale factor for display',
            `created_by` INT UNSIGNED,
            `created_at` INT UNSIGNED NOT NULL,
            `updated_at` INT UNSIGNED,
            UNIQUE KEY `title` (`title`),
            KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    // rpgmaps_buildplots table - Building plots on maps
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_buildplots` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `map_id` INT UNSIGNED NOT NULL,
            `plot_key` VARCHAR(100) NOT NULL COMMENT 'Unique identifier per plot',
            `x` INT UNSIGNED NOT NULL COMMENT 'X coordinate',
            `y` INT UNSIGNED NOT NULL COMMENT 'Y coordinate',
            `w` INT UNSIGNED DEFAULT 50 COMMENT 'Width of plot',
            `h` INT UNSIGNED DEFAULT 50 COMMENT 'Height of plot',
            `rotation` INT DEFAULT 0 COMMENT 'Rotation angle in degrees (0-360)',
            `tooltip_text` VARCHAR(255),
            `status` ENUM('free', 'pending', 'built') DEFAULT 'free',
            `created_at` INT UNSIGNED NOT NULL,
            UNIQUE KEY `plot_key` (`map_id`, `plot_key`),
            KEY `map_id` (`map_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    // rpgmaps_house_types table - Types of houses
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_house_types` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `asset_filename` VARCHAR(255) NOT NULL COMMENT 'Filename in assets/houses/',
            `max_occupants` INT UNSIGNED DEFAULT 5,
            `icon_scale` FLOAT DEFAULT 1.0 COMMENT 'Scale factor for house icon',
            `created_at` INT UNSIGNED NOT NULL,
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    // rpgmaps_houses table - Actual houses built on plots
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_houses` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `plot_id` INT UNSIGNED NOT NULL,
            `type_id` INT UNSIGNED NOT NULL,
            `status` ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            `max_occupants` INT UNSIGNED DEFAULT 5,
            `description` TEXT,
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` INT UNSIGNED NOT NULL,
            `approved_at` INT UNSIGNED,
            KEY `plot_id` (`plot_id`),
                    `house_name` VARCHAR(255) DEFAULT '',
            KEY `status` (`status`),
            FOREIGN KEY (`plot_id`) REFERENCES `" . TABLE_PREFIX . "rpgmaps_buildplots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`type_id`) REFERENCES `" . TABLE_PREFIX . "rpgmaps_house_types`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    // rpgmaps_house_occupants table - Who lives in houses
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_house_occupants` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `house_id` INT UNSIGNED NOT NULL,
            `uid` INT UNSIGNED NOT NULL,
            `role` ENUM('owner', 'resident') DEFAULT 'resident',
            `joined_at` INT UNSIGNED NOT NULL,
            `left_at` INT UNSIGNED,
            KEY `house_id` (`house_id`),
            KEY `uid` (`uid`),
            KEY `left_at` (`left_at`),
            FOREIGN KEY (`house_id`) REFERENCES `" . TABLE_PREFIX . "rpgmaps_houses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    // rpgmaps_actions table - Pending actions requiring admin approval
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgmaps_actions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `action_type` ENUM('build', 'move_in', 'move_out', 'delete_house') NOT NULL,
            `target_id` INT UNSIGNED NOT NULL COMMENT 'house_id or plot_id',
            `user_id` INT UNSIGNED NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            `created_at` INT UNSIGNED NOT NULL,
            `reviewed_by` INT UNSIGNED,
            `reviewed_at` INT UNSIGNED,
            `admin_note` TEXT,
            `extra_data` TEXT COMMENT 'Additional metadata like max_occupants (JSON format)',
            KEY `status` (`status`),
            KEY `user_id` (`user_id`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");
}

/**
 * Uninstall database tables
 */
function rpgmaps_uninstall_tables()
{
    global $db;

    // Drop tables in reverse order of dependencies
    $tables = [
        'rpgmaps_actions',
        'rpgmaps_house_occupants',
        'rpgmaps_houses',
        'rpgmaps_house_types',
        'rpgmaps_buildplots',
        'rpgmaps_maps'
    ];

    foreach ($tables as $table) {
        if ($db->table_exists($table)) {
            $db->drop_table($table);
        }
    }
}

/**
 * Update plugin tables (for existing installations)
 * Adds missing columns without dropping data
 */
function rpgmaps_update_tables()
{
    global $db;

    // Ensure extra_data column exists on actions table
    if (!$db->field_exists('extra_data', 'rpgmaps_actions')) {
        // Use TEXT for broad MySQL compatibility
        $db->add_column('rpgmaps_actions', 'extra_data', "TEXT COMMENT 'Additional metadata like max_occupants'");
    }

    // Ensure max_occupants column exists on houses table
    if (!$db->field_exists('max_occupants', 'rpgmaps_houses')) {
        $db->add_column('rpgmaps_houses', 'max_occupants', "INT UNSIGNED DEFAULT 5 AFTER `status`");
    }

    // Ensure rotation column exists on buildplots table
    if (!$db->field_exists('rotation', 'rpgmaps_buildplots')) {
        $db->add_column('rpgmaps_buildplots', 'rotation', "INT DEFAULT 0 COMMENT 'Rotation angle in degrees (0-360)' AFTER `h`");
    }

    // Ensure description column exists on houses table
    if (!$db->field_exists('description', 'rpgmaps_houses')) {
        $db->add_column('rpgmaps_houses', 'description', "TEXT AFTER `max_occupants`");
    }

    // Ensure house_name column exists on houses table
    if (!$db->field_exists('house_name', 'rpgmaps_houses')) {
        $db->add_column('rpgmaps_houses', 'house_name', "VARCHAR(255) DEFAULT '' AFTER `description`");
    }
}

/**
 * Install plugin settings
 */
function rpgmaps_install_settings()
{
    global $db;

    // First create settings group
    $group_query = $db->simple_select('settinggroups', '*', "name = 'rpgmaps'");
    $group = $db->fetch_array($group_query);

    if (!$group) {
        $insert = [
            'name' => 'rpgmaps',
            'title' => 'RPG Maps Plugin',
            'description' => 'Settings for the RPG Maps plugin',
            'disporder' => 999,
        ];
        $db->insert_query('settinggroups', $insert);

        // Get the newly created group ID
        $group_query = $db->simple_select('settinggroups', 'gid', "name = 'rpgmaps'");
        $group = $db->fetch_array($group_query);
        $gid = $group['gid'];
    } else {
        $gid = $group['gid'];
    }

    $settings = [
        'rpgmaps_enabled' => [
            'title' => 'Enable RPG Maps Plugin',
            'description' => 'Enable or disable the RPG Maps plugin',
            'optionscode' => 'yesno',
            'value' => 1,
            'disporder' => 1,
        ],
        'rpgmaps_max_plot_size' => [
            'title' => 'Maximum Plot Size',
            'description' => 'Maximum number of pixels for plot width/height',
            'optionscode' => 'numeric',
            'value' => 200,
            'disporder' => 2,
        ],
        'rpgmaps_max_upload_size' => [
            'title' => 'Maximum Upload Size (KB)',
            'description' => 'Maximum file size for map and house graphics uploads',
            'optionscode' => 'numeric',
            'value' => 512,
            'disporder' => 3,
        ],
        'rpgmaps_allowed_extensions' => [
            'title' => 'Allowed File Extensions',
            'description' => 'Comma-separated list of allowed file extensions',
            'optionscode' => 'text',
            'value' => 'png,jpg,jpeg,gif',
            'disporder' => 4,
        ],
    ];

    foreach ($settings as $setting => $data) {
        // Check if setting already exists
        $query = $db->simple_select('settings', '*', "name = '" . $db->escape_string($setting) . "'");
        if (!$db->fetch_array($query)) {
            $insert = array_merge(['name' => $setting, 'gid' => $gid], $data);
            $db->insert_query('settings', $insert);
        }
    }

    // Rebuild settings cache
    rebuild_settings();
}

/**
 * Uninstall plugin settings
 */
function rpgmaps_uninstall_settings()
{
    global $db;

    // Delete settings
    $db->delete_query('settings', "name LIKE 'rpgmaps_%'");

    // Delete settings group
    $db->delete_query('settinggroups', "name = 'rpgmaps'");

    // Rebuild settings cache
    rebuild_settings();
}

/**
 * Helper: load MyAlerts classes if available.
 * @return bool True if classes are available, false otherwise.
 */
function rpgmaps_load_myalerts_classes()
{
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return true;
    }

    if (!defined('MYALERTS_PLUGIN_PATH')) {
        define('MYALERTS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MybbStuff/MyAlerts');
    }
    if (!defined('MYBBSTUFF_CORE_PATH')) {
        define('MYBBSTUFF_CORE_PATH', MYBB_ROOT . 'inc/plugins/MybbStuff/Core/');
    }

    $loader_path = MYBBSTUFF_CORE_PATH . 'ClassLoader.php';
    if (!is_readable($loader_path)) {
        return false;
    }

    require_once $loader_path;
    $classLoader = new MybbStuff_Core_ClassLoader();
    $classLoader->registerNamespace(
        'MybbStuff_MyAlerts',
        array(MYALERTS_PLUGIN_PATH . '/src')
    );
    $classLoader->register();

    return class_exists('MybbStuff_MyAlerts_AlertTypeManager');
}

/**
 * Register MyAlerts alert type for build-request approval notifications.
 */
function rpgmaps_install_alert_type()
{
    global $db, $cache;

    if (!$db->table_exists('alert_types')) {
        return; // MyAlerts not installed
    }

    if (!rpgmaps_load_myalerts_classes()) {
        return;
    }

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);

    $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
    $alertType->setCode('rpgmaps_build_approved');
    $alertType->setEnabled(true);
    $alertType->setCanBeUserDisabled(true);
    $alertType->setDefaultUserEnabled(true);

    $alertTypeManager->add($alertType);
}

/**
 * Remove MyAlerts alert type on uninstall.
 */
function rpgmaps_uninstall_alert_type()
{
    global $db, $cache;

    if (!$db->table_exists('alert_types')) {
        return;
    }

    if (!rpgmaps_load_myalerts_classes()) {
        return;
    }

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    $alertTypeManager->deleteByCode('rpgmaps_build_approved');
}

/**
 * Templates aus inc/plugins/rpgmaps/templates/*.tpl in die Datenbank laden.
 * Dateiname ohne .tpl = Templatetitel. Idempotent: vorhandene Master-Templates werden ersetzt.
 */
function rpgmaps_install_templates()
{
    global $db;

    $group = $db->fetch_array($db->simple_select('templategroups', 'gid', "prefix = 'rpgmaps'"));
    if (!$group) {
        $db->insert_query('templategroups', [
            'prefix' => 'rpgmaps',
            'title'  => 'RPG Maps',
            'isdefault' => 0,
        ]);
    }

    $template_dir = MYBB_ROOT . 'inc/plugins/rpgmaps/templates/';
    $files = glob($template_dir . '*.tpl');
    if (!is_array($files)) {
        return;
    }

    foreach ($files as $file) {
        $title = basename($file, '.tpl');
        $content = (string)file_get_contents($file);

        $db->delete_query('templates', "title = '" . $db->escape_string($title) . "' AND sid = -1");
        $db->insert_query('templates', [
            'title'    => $db->escape_string($title),
            'template' => $db->escape_string($content),
            'sid'      => -1,
            'version'  => '',
            'dateline' => TIME_NOW,
        ]);
    }
}

/**
 * Alle Plugin-Templates entfernen (Master und themespezifische Kopien).
 */
function rpgmaps_uninstall_templates()
{
    global $db;

    $db->delete_query('templates', "title LIKE 'rpgmaps\\_%'");
    // Altlasten aus Versionen <= 1.1.0
    $db->delete_query('templates', "title IN ('modcp_nav_rpgmaps', 'modcp_rpgmaps_pending', 'rpgmaps_tooltip', 'rpgmaps_modal_build')");
    $db->delete_query('templategroups', "prefix = 'rpgmaps'");
}

/**
 * {$rpgmaps_nav} in das header-Template aller Template-Sets einfügen (vor dem Such-Link).
 */
function rpgmaps_add_nav_to_header()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Idempotent: erst entfernen, dann neu einfügen
    rpgmaps_remove_nav_from_header();

    // MyBB 1.8 (ab 1.8.?): Menüpunkte als eigene Variablen
    $inserted = find_replace_templatesets('header', '#(\{\$menu_search\})#', "{\$rpgmaps_nav}\n\\1");

    // Ältere/angepasste Header: vor dem <li> mit dem Such-Link
    if (!$inserted) {
        $inserted = find_replace_templatesets('header', '#(<li[^>]*>\s*<a[^>]*search\.php)#i', "{\$rpgmaps_nav}\n\\1");
    }

    // Letzter Fallback: vor dem ersten </ul>
    if (!$inserted) {
        find_replace_templatesets('header', '#(</ul>)#i', "{\$rpgmaps_nav}\n\\1", 1, false, 1);
    }
}

/**
 * {$rpgmaps_nav} aus dem header-Template aller Template-Sets entfernen.
 */
function rpgmaps_remove_nav_from_header()
{
    global $db;

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('header', '#\{\$rpgmaps_nav\}(\r?\n)?#', '', 0);

    // Versionen <= 1.1.0 haben direkt das Master-Template (sid = -2) geändert
    $query = $db->simple_select('templates', 'tid, template', "title = 'header' AND sid = -2");
    while ($row = $db->fetch_array($query)) {
        $clean = preg_replace('#\{\$rpgmaps_nav\}(\r?\n)?#', '', $row['template']);
        if ($clean !== $row['template']) {
            $db->update_query('templates', ['template' => $db->escape_string($clean)], 'tid = ' . (int)$row['tid']);
        }
    }
}

/**
 * Stylesheet rpgmaps.css anlegen bzw. aktualisieren (Master-Theme, wird vererbt).
 */
function rpgmaps_install_css()
{
    global $db;

    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    $css_file = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/rpgmaps.css';
    if (!is_file($css_file)) {
        return;
    }
    $css = $db->escape_string((string)file_get_contents($css_file));

    $existing = $db->fetch_array($db->simple_select('themestylesheets', 'sid', "name = 'rpgmaps.css' AND tid = 1"));
    if ($existing) {
        $sid = (int)$existing['sid'];
        $db->update_query('themestylesheets', [
            'stylesheet'   => $css,
            'lastmodified' => TIME_NOW,
        ], 'sid = ' . $sid, 1);
    } else {
        $sid = (int)$db->insert_query('themestylesheets', [
            'name'         => 'rpgmaps.css',
            'tid'          => 1,
            'attachedto'   => '',
            'stylesheet'   => $css,
            'cachefile'    => 'rpgmaps.css',
            'lastmodified' => TIME_NOW,
        ]);
    }

    // Cache-Datei schreiben; schlägt das fehl (Rechte), über css.php ausliefern
    if (!cache_stylesheet(1, 'rpgmaps.css', (string)file_get_contents($css_file))) {
        $db->update_query('themestylesheets', ['cachefile' => 'css.php?stylesheet=' . $sid], 'sid = ' . $sid, 1);
    }

    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        update_theme_stylesheet_list((int)$theme['tid']);
    }
}

/**
 * Stylesheet rpgmaps.css aus allen Themes entfernen.
 */
function rpgmaps_uninstall_css()
{
    global $db;

    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    $db->delete_query('themestylesheets', "name = 'rpgmaps.css'");

    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        $cache_file = MYBB_ROOT . 'cache/themes/theme' . (int)$theme['tid'] . '/rpgmaps.css';
        if (is_file($cache_file)) {
            unlink($cache_file);
        }
        update_theme_stylesheet_list((int)$theme['tid']);
    }
}
