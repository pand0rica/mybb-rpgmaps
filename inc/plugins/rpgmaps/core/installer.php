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
 * Upgrade database schema
 * Adds new fields to existing tables if they don't exist
 */
function rpgmaps_upgrade_tables()
{
    global $db;
    
    // Check if rotation column exists in buildplots table
    if ($db->table_exists('rpgmaps_buildplots')) {
        // Check if rotation column already exists
        $query = $db->query("SHOW COLUMNS FROM `" . TABLE_PREFIX . "rpgmaps_buildplots` LIKE 'rotation'");
        
        if ($db->num_rows($query) == 0) {
            // Add rotation column
            $db->write_query("ALTER TABLE `" . TABLE_PREFIX . "rpgmaps_buildplots` 
                ADD COLUMN `rotation` INT DEFAULT 0 COMMENT 'Rotation angle in degrees (0-360)' 
                AFTER `h`");
        }
    }
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
 * Install plugin templates
 */
function rpgmaps_install_templates()
{
    global $db;

    // Templates to install
    $templates = [
        // Frontend templates
        'rpgmaps_frontend' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$rpgmaps_map_title}</title>
{$headerinclude}
</head>
<body>
{$header}
{$rpgmaps_notification}
<div class="rpgmaps-container" {$rpgmaps_container_attributes}>
    <h1>{$rpgmaps_map_title}</h1>
    <p>{$rpgmaps_map_description}</p>
    <div class="rpgmaps-map-wrapper" id="rpgmaps-map-{$rpgmaps_map_id}" data-map-id="{$rpgmaps_map_id}" data-ajax-url="{$mybb->settings[\'bburl\']}/rpgmaps.php">
        <img src="{$rpgmaps_map_image}" alt="{$rpgmaps_map_title}" class="rpgmaps-map-image">
        <div id="rpgmaps-overlay-{$rpgmaps_map_id}" class="rpgmaps-overlay">
            {$rpgmaps_plot_overlays}
        </div>
    </div>
</div>

<!-- Build House Modal -->
<div id="rpgmaps-modal-build" class="rpgmaps-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Haus bauen</h2>
        <form id="form-build-house" method="post">
            <input type="hidden" name="action" value="build_house">
            <input type="hidden" name="plot_id" id="build-plot-id" value="">
            <div class="form-group">
                <label>Haustyp w&auml;hlen:</label>
                <select name="type_id" id="house-type-select" required>
                    {$rpgmaps_house_type_options}
                </select>
            </div>
            <div class="form-group">
                <label>Maximale Bewohner:</label>
                <input type="number" name="max_occupants" id="max-occupants-input" min="1" max="20" value="5" required>
            </div>
            <div class="form-group">
                <label>Beschreibung (optional):</label>
                <textarea name="description" id="house-description-input" rows="3" placeholder="Beschreibe dein Haus..."></textarea>
            </div>
                <div class="form-group">
                    <label>Hausname (optional):</label>
                    <input type="text" name="house_name" id="house-name-input" maxlength="255" placeholder="z. B. Eichenhof">
                </div>
            <button type="submit" class="button">Bauantrag einreichen</button>
        </form>
    </div>
</div>

{$footer}
</body>
</html>'),

        'rpgmaps_map_list' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->rpgmaps_maps}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgmaps-container">
    <h1>{$lang->rpgmaps_map_list_title}</h1>
    <div class="rpgmaps-map-list">
        {$rpgmaps_map_list}
    </div>
</div>
{$footer}
</body>
</html>'),

        'rpgmaps_tooltip' => $db->escape_string('<div class="rpgmaps-tooltip" id="rpgmaps-tooltip-{$rpgmaps_tooltip_id}">
    <div class="tooltip-header">
        <h3>{$rpgmaps_plot_name}</h3>
        <a href="javascript:void(0);" class="tooltip-close" onclick="rpgmaps_hide_tooltip();">&times;</a>
    </div>
    <div class="tooltip-body">
        {$rpgmaps_tooltip_content}
    </div>
</div>'),

        'rpgmaps_modal_build' => $db->escape_string('<div id="rpgmaps-modal-build" class="rpgmaps-modal">
    <div class="modal-content">
        <span class="close" onclick="rpgmaps_close_modal(\'build\')">&times;</span>
        <h2>{$rpgmaps_build_house}</h2>
        <form id="form-build-house" method="post">
            <input type="hidden" name="action" value="rpgmaps">
            <input type="hidden" name="sub" value="build_house">
            <input type="hidden" name="plot_id" id="build-plot-id" value="">
            <div class="form-group">
                <label>{$rpgmaps_select_house_type}</label>
                <select name="type_id" id="house-type-select" required>
                    <option value="">{$rpgmaps_choose_type}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{$rpgmaps_submit_build_request}</button>
        </form>
    </div>
</div>'),

        'modcp_nav_rpgmaps' => $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=rpgmaps" class="modcp_nav_item modcp_nav_rpgmaps">RPG Maps{$count_badge}</a></td></tr>'),

        'modcp_rpgmaps_pending' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->modcp} - RPG Maps</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="5"><strong>RPG Maps - Ausstehende Bauanträge</strong></td>
</tr>
<tr>
<td class="tcat" width="15%"><strong>Typ</strong></td>
<td class="tcat" width="20%"><strong>Benutzer</strong></td>
<td class="tcat" width="30%"><strong>Ort</strong></td>
<td class="tcat" width="20%"><strong>Erstellt</strong></td>
<td class="tcat rpgmaps-action-buttons" width="15%"><strong>Aktionen</strong></td>
</tr>
{$action_rows}
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>'),

        // ACP templates will be in admin modules
    ];

    foreach ($templates as $name => $template) {
        // Check if template group exists for rpgmaps
        $query = $db->simple_select('templategroups', '*', "prefix = 'rpgmaps'");
        $group = $db->fetch_array($query);

        if (!$group) {
            // Create template group if it doesn't exist
            $insert = [
                'prefix' => 'rpgmaps',
                'title' => 'RPG Maps Plugin',
            ];
            $db->insert_query('templategroups', $insert);

            // Get the newly created group ID
            $query = $db->simple_select('templategroups', 'gid', "prefix = 'rpgmaps'");
            $group = $db->fetch_array($query);
        }

        // Check if template already exists
        $existing = $db->simple_select('templates', '*', "title = '" . $db->escape_string($name) . "'");
        if (!$db->fetch_array($existing)) {
            // Insert template
            $insert = [
                'title' => $name,
                'template' => $template,
                'sid' => -1,
                'version' => 1833,
                'dateline' => TIME_NOW,
            ];
            $db->insert_query('templates', $insert);
        }
    }
}

/**
 * Uninstall plugin templates
 */
function rpgmaps_uninstall_templates()
{
    global $db;

    // Delete templates
    $db->delete_query('templates', "title LIKE 'rpgmaps_%'");

    // Delete template group
    $db->delete_query('templategroups', "prefix = 'rpgmaps'");
}

/**
 * Update plugin templates (for existing installations)
 */
function rpgmaps_update_templates()
{
    global $db;

    // Update rpgmaps_frontend template with max_occupants field
    $new_template = $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$rpgmaps_map_title}</title>
{$headerinclude}
</head>
<body>
{$header}
{$rpgmaps_notification}
<div class="rpgmaps-container" {$rpgmaps_container_attributes}>
    <h1>{$rpgmaps_map_title}</h1>
    <p>{$rpgmaps_map_description}</p>
    <div class="rpgmaps-map-wrapper" id="rpgmaps-map-{$rpgmaps_map_id}" data-map-id="{$rpgmaps_map_id}" data-ajax-url="{$mybb->settings[\'bburl\']}/rpgmaps.php">
        <img src="{$rpgmaps_map_image}" alt="{$rpgmaps_map_title}" class="rpgmaps-map-image">
        <div id="rpgmaps-overlay-{$rpgmaps_map_id}" class="rpgmaps-overlay">
            {$rpgmaps_plot_overlays}
        </div>
    </div>
</div>

<!-- Build House Modal -->
<div id="rpgmaps-modal-build" class="rpgmaps-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Haus bauen</h2>
        <form id="form-build-house" method="post">
            <input type="hidden" name="action" value="build_house">
            <input type="hidden" name="plot_id" id="build-plot-id" value="">
            <div class="form-group">
                <label>Haustyp w&auml;hlen:</label>
                <select name="type_id" id="house-type-select" required>
                    {$rpgmaps_house_type_options}
                </select>
            </div>
            <div class="form-group">
                <label>Maximale Bewohner:</label>
                <input type="number" name="max_occupants" id="max-occupants-input" min="1" max="20" value="5" required>
            </div>
            <div class="form-group">
                <label>Beschreibung (optional):</label>
                <textarea name="description" id="house-description-input" rows="3" placeholder="Beschreibe dein Haus..."></textarea>
            </div>
                <div class="form-group">
                    <label>Hausname (optional):</label>
                    <input type="text" name="house_name" id="house-name-input" maxlength="255" placeholder="z. B. Eichenhof">
                </div>
            <button type="submit" class="button">Bauantrag einreichen</button>
        </form>
    </div>
</div>

{$footer}
</body>
</html>');

    $db->update_query('templates', ['template' => $new_template], "title = 'rpgmaps_frontend'");
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
 * Install plugin hooks
 */
function rpgmaps_install_hooks()
{
    global $db;

    // Hooks are registered in the main plugin file via $plugins->add_hook()
    // This function is here for future reference if we need custom hook registration
}

/**
 * Uninstall plugin hooks
 */
function rpgmaps_uninstall_hooks()
{
    // Hooks are automatically removed when plugin is disabled
}

/**
 * Install CSS stylesheet
 */
function rpgmaps_install_css()
{
    global $db;

    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    // Read CSS content from file
    $css_file = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/rpgmaps.css';
    if (!file_exists($css_file)) {
        return;  // CSS file not found
    }

    $css_content = file_get_contents($css_file);
    if ($css_content === false) {
        return;  // Could not read CSS file
    }

    $stylesheet = [
        'name' => 'rpgmaps.css',
        'tid' => 1,
        'attachedto' => '',
        'stylesheet' => $db->escape_string($css_content),
        'cachefile' => 'rpgmaps.css',
        'lastmodified' => TIME_NOW,
    ];

    $sid = $db->insert_query("themestylesheets", $stylesheet);
    $db->update_query("themestylesheets", array("cachefile" => "rpgmaps.css"), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }

}

/**
 * Uninstall CSS stylesheet
 */
function rpgmaps_uninstall_css()
{
    global $db;

    // Remove stylesheets from all themes
    $db->delete_query('themestylesheets', "name = 'rpgmaps.css'");
}
