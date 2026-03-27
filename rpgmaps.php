<?php
/**
 * RPG Maps Plugin - Frontend Entry Point
 * Public page for viewing interactive maps
 * 
 * @package rpgmaps
 * @version 1.0.0
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'rpgmaps.php');

$templatelist = 'rpgmaps_frontend,rpgmaps_tooltip,rpgmaps_modal_build,rpgmaps_map_list';

require_once "./global.php";

// Load language file FIRST
$lang->load('rpgmaps');

// Check if plugin is enabled
if (!isset($mybb->settings['rpgmaps_enabled']) || $mybb->settings['rpgmaps_enabled'] != 1) {
    error($lang->rpgmaps_plugin_disabled);
}

// Set action for hooks
$action = 'rpgmaps';

// Handle AJAX requests
if (isset($mybb->input['action']) && $mybb->input['action'] == 'ajax_rpgmaps') {
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/ajax.php';
    $sub = $mybb->get_input('sub');
    rpgmaps_handle_ajax($sub);
    exit;
}

// Load database helper
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/security.php';

try {
    $db_helper = new RPGMapsDatabase();
    $security = new RPGMapsSecurity();
} catch (Exception $e) {
    error('Error initializing RPG Maps: ' . $e->getMessage());
}

// Add JavaScript to header (CSS is loaded automatically by MyBB from themestylesheets)
$headerinclude .= '<script src="' . $mybb->settings['bburl'] . '/inc/plugins/rpgmaps/assets/rpgmaps.js"></script>' . "\n";

// Initialize notification variable (empty by default)
$rpgmaps_notification = '';

// Get the map ID from query string
$map_id = isset($mybb->input['map_id']) ? (int)$mybb->input['map_id'] : 0;

// Page title
add_breadcrumb($lang->rpgmaps_maps, "rpgmaps.php");

if ($map_id <= 0) {
    // Show map list if no map selected
    try {
        $maps = $db_helper->getMaps();
    } catch (Exception $e) {
        error('Database error: ' . $e->getMessage());
    }
    
    $map_list_items = '';
    if (!empty($maps)) {
        foreach ($maps as $map) {
            $map_list_items .= '<div class="rpgmaps-map-item">
                <h3><a href="rpgmaps.php?map_id=' . (int)$map['id'] . '">' . htmlspecialchars_uni($map['title']) . '</a></h3>
                <p>' . htmlspecialchars_uni($map['description']) . '</p>
                <p><strong>Größe:</strong> ' . (int)$map['width'] . ' x ' . (int)$map['height'] . ' px</p>
                <a href="rpgmaps.php?map_id=' . (int)$map['id'] . '" class="button">Karte ansehen</a>
            </div>';
        }
    } else {
        $map_list_items = '<p>' . $lang->rpgmaps_no_maps . '</p>';
    }
    
    // Prepare variables for template
    $rpgmaps_map_list = $map_list_items;
    
    // Use template
    eval('$page = "' . $templates->get('rpgmaps_map_list') . '";');
    output_page($page);
    exit;
} else {
    // Load and display specific map
    $map = $db_helper->getMapById($map_id);
    
    if (!$map) {
        error($lang->rpgmaps_map_not_found);
    }
    
    // Add breadcrumb for current map
    add_breadcrumb(htmlspecialchars_uni($map['title']));
    
    // Get plots
    $plots = $db_helper->getPlotsByMap($map_id);
    
    // Build plot HTML overlays
    $plot_overlays = '';
    $house_types = $db_helper->getHouseTypes();
    
    foreach ($plots as $plot) {
        $plot_key = htmlspecialchars_uni($plot['plot_key']);
        $x = (int)$plot['x'];
        $y = (int)$plot['y'];
        $w = (int)$plot['w'];
        $h = (int)$plot['h'];
        $rotation = isset($plot['rotation']) ? (int)$plot['rotation'] : 0;
        
        // Store original coordinates in data attributes for responsive scaling
        // The actual position will be calculated by JavaScript based on the displayed map size
        $data_attrs = 'data-orig-x="' . $x . '" data-orig-y="' . $y . '" data-orig-w="' . $w . '" data-orig-h="' . $h . '" data-rotation="' . $rotation . '"';
        
        // Initial style (will be adjusted by JavaScript)
        $style = "left: {$x}px; top: {$y}px; width: {$w}px; height: {$h}px; transform-origin: center center;";
        if ($rotation != 0) {
            $style .= " transform: rotate({$rotation}deg);";
        }
        
        // Get house on this plot
        $house = $db_helper->getHouseByPlot($plot['id']);
        
        if ($house) {
            // Occupied plot
            $house_type = $db_helper->getHouseTypeById($house['type_id']);
            $house_asset = htmlspecialchars_uni($house_type['asset_filename']);
            $house_type_name = htmlspecialchars_uni($house_type['name']);
            $custom_house_name_raw = isset($house['house_name']) ? trim($house['house_name']) : '';
            $custom_house_name = $custom_house_name_raw !== '' ? htmlspecialchars_uni($custom_house_name_raw) : '';
            $house_label = $house_type_name;
            if ($custom_house_name !== '') {
                $house_label .= ' (' . $custom_house_name . ')';
            }
            $plot_label_raw = isset($plot['tooltip_text']) ? trim($plot['tooltip_text']) : '';
            $plot_label = $plot_label_raw !== '' ? htmlspecialchars_uni($plot_label_raw) : $plot_key;
            
            $plot_overlays .= '<div class="rpgmaps-plot rpgmaps-plot-built" data-plotid="' . (int)$plot['id'] . '" data-houseid="' . (int)$house['id'] . '" ' . $data_attrs . ' style="' . $style . '">';
            $plot_overlays .= '<img src="' . $mybb->settings['bburl'] . '/inc/plugins/rpgmaps/assets/houses/' . htmlspecialchars_uni($house_asset) . '" alt="' . $house_label . '" class="rpgmaps-house-image">';
            $plot_overlays .= '<div class="rpgmaps-plot-info">' . $house_label . '<br>' . $plot_label . '</div>';
            $plot_overlays .= '</div>';
        } else {
            // Check if there's a pending build request for this plot
            $has_pending = $db_helper->hasPendingBuildRequest($plot['id']);
            
            if (!$has_pending) {
                // Free plot without pending request - show it
                $tooltip = htmlspecialchars_uni($plot['tooltip_text']);
                
                $plot_overlays .= '<div class="rpgmaps-plot rpgmaps-plot-free" data-plotid="' . (int)$plot['id'] . '" ' . $data_attrs . ' style="' . $style . '">';
                $plot_overlays .= '<div class="rpgmaps-plot-info">' . $tooltip . '</div>';
                $plot_overlays .= '</div>';
            }
            // If has_pending is true, we simply don't render anything for this plot
        }
    }
    
    // Prepare template variables
    $rpgmaps_map_title = htmlspecialchars_uni($map['title']);
    $rpgmaps_map_description = nl2br(htmlspecialchars_uni($map['description']));
    $rpgmaps_map_id = (int)$map['id'];
    $rpgmaps_map_width = (int)$map['width'];
    $rpgmaps_map_height = (int)$map['height'];
    $rpgmaps_map_image = $mybb->settings['bburl'] . '/inc/plugins/rpgmaps/assets/maps/' . htmlspecialchars_uni($map['filename']);
    $rpgmaps_plot_overlays = $plot_overlays;
    $rpgmaps_csrf_token = $security->generateCSRFToken();
    $rpgmaps_is_logged_in = (isset($mybb->user['uid']) && $mybb->user['uid'] > 0) ? '1' : '0';
    
    // Build house type options for modal
    $rpgmaps_house_type_options = '<option value="">' . $lang->rpgmaps_select_house_type . '</option>';
    foreach ($house_types as $type) {
        $rpgmaps_house_type_options .= '<option value="' . (int)$type['id'] . '">' . htmlspecialchars_uni($type['name']) . '</option>';
    }
    
    // Prepare language variables for JavaScript
    $rpgmaps_lang_json = json_encode([
        'status' => $lang->rpgmaps_status,
        'type' => $lang->rpgmaps_type,
        'house_name' => $lang->rpgmaps_house_name,
        'house_name_optional' => $lang->rpgmaps_house_name_optional,
        'plot_name' => $lang->rpgmaps_plot_name,
        'description' => $lang->rpgmaps_description,
        'no_description' => $lang->rpgmaps_no_description,
        'edit' => $lang->rpgmaps_edit,
        'save' => $lang->rpgmaps_save,
        'cancel' => $lang->rpgmaps_cancel,
        'house_information' => $lang->rpgmaps_house_information,
        'move_in' => $lang->rpgmaps_move_in,
        'move_out' => $lang->rpgmaps_move_out,
        'confirm_move_in' => $lang->rpgmaps_confirm_move_in,
        'confirm_move_out' => $lang->rpgmaps_confirm_move_out,
        'yes' => $lang->rpgmaps_yes,
        'no' => $lang->rpgmaps_no,
        'maximum_occupants' => $lang->rpgmaps_maximum_occupants,
        'occupants' => $lang->rpgmaps_occupants,
        'role_owner' => $lang->rpgmaps_role_owner,
        'role_resident' => $lang->rpgmaps_role_resident,
    ]);
    
    // Prepare all variables for template
    $rpgmaps_user_id = isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0;
    $rpgmaps_map_orig_width = (int)$map['width'];
    $rpgmaps_map_orig_height = (int)$map['height'];
    $rpgmaps_ajax_url = $mybb->settings['bburl'] . '/rpgmaps.php';
    $rpgmaps_container_attributes = 'data-csrf-token="' . $rpgmaps_csrf_token . '" data-map-id="' . $rpgmaps_map_id . '" data-logged-in="' . $rpgmaps_is_logged_in . '" data-user-id="' . $rpgmaps_user_id . '" data-lang="' . htmlspecialchars($rpgmaps_lang_json) . '" data-orig-width="' . $rpgmaps_map_orig_width . '" data-orig-height="' . $rpgmaps_map_orig_height . '" data-ajax-url="' . htmlspecialchars_uni($rpgmaps_ajax_url) . '"';
    
    // Build full page with myBB theme
    eval('$page = "' . $templates->get('rpgmaps_frontend') . '";');
    output_page($page);
    exit;
}
