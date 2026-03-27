<?php
/**
 * RPG Maps Plugin - Frontend Templates
 * HTML templates for the frontend interface
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Register frontend templates
 * These would typically be loaded from database templates
 */

// Main frontend template
define('RPGMAPS_FRONTEND_TEMPLATE', '
<div class="rpgmaps-container">
    <h1>{$rpgmaps_map_title}</h1>
    <p>{$rpgmaps_map_description}</p>
    
    <div class="rpgmaps-map-wrapper" id="rpgmaps-map-{$rpgmaps_map_id}" data-map-id="{$rpgmaps_map_id}" data-csrf-token="{$rpgmaps_csrf_token}" data-logged-in="{$rpgmaps_is_logged_in}" data-lang="{$rpgmaps_lang_json}" data-ajax-url="{$mybb->settings['bburl']}/rpgmaps.php">
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
        <h2>{$lang->rpgmaps_build_house}</h2>
        <form id="form-build-house" method="post">
            <input type="hidden" name="action" value="rpgmaps">
            <input type="hidden" name="sub" value="build_house">
            <input type="hidden" name="plot_id" id="build-plot-id" value="">
            <div class="form-group">
                <label>{$lang->rpgmaps_select_house_type_label}</label>
                <select name="type_id" id="house-type-select" required>
                    {$rpgmaps_house_type_options}
                </select>
            </div>
            <div class="form-group">
                <label>{$lang->rpgmaps_maximum_occupants}:</label>
                <input type="number" name="max_occupants" id="max-occupants-input" min="1" max="20" value="5" required>
            </div>
            <div class="form-group">
                <label>{$lang->rpgmaps_description_optional}:</label>
                <textarea name="description" id="house-description-input" rows="3" placeholder="Describe your house..."></textarea>
            </div>
            <div class="form-group">
                <label>{$lang->rpgmaps_house_name_optional}:</label>
                <input type="text" name="house_name" id="house-name-input" maxlength="255" placeholder="{$lang->rpgmaps_house_name}">
            </div>
            <button type="submit" class="btn btn-primary">{$lang->rpgmaps_submit_build_request}</button>
            <button type="button" class="btn btn-secondary" onclick="RPGMaps.hideModal()">{$lang->rpgmaps_cancel}</button>
        </form>
    </div>
</div>
');

// Map list template
define('RPGMAPS_MAP_LIST_TEMPLATE', '
<div class="rpgmaps-container">
    <h1>Available Maps</h1>
    <p>Select a map to explore:</p>
    <ul>
        {$map_list}
    </ul>
</div>
');

// Tooltip template (dynamic, created by JavaScript)
define('RPGMAPS_TOOLTIP_TEMPLATE', '
<div class="rpgmaps-tooltip" id="rpgmaps-tooltip-{$rpgmaps_tooltip_id}">
    <div class="tooltip-header">
        <h3>{$lang->rpgmaps_house_information}</h3>
        <a href="javascript:void(0);" class="tooltip-close">&times;</a>
    </div>
    <div class="tooltip-body">
        {$rpgmaps_tooltip_content}
    </div>
</div>
');
