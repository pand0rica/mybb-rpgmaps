<?php
/**
 * RPG Maps Plugin - AJAX Handler
 * Handles AJAX requests from frontend
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Helper function to output clean JSON and exit
 * @param array $data
 */
function rpgmaps_json_output($data)
{
    // Clear output buffer
    ob_clean();
    // Output JSON
    echo json_encode($data);
    // Exit
    exit;
}

/**
 * Helper function to handle database errors and output JSON error response
 * @param string $function_name
 * @param Exception|string $error
 */
function rpgmaps_handle_db_error($function_name, $error)
{
    global $db;
    
    $message = $error instanceof Exception ? $error->getMessage() : (string)$error;
    $db_error = isset($db->error) ? $db->error : 'Database error';
    
    // Return error response
    rpgmaps_json_output([
        'success' => false,
        'message' => $message,
        'error' => $message,
        'db_error' => $db_error,
    ]);
}

/**
 * Main AJAX handler dispatcher
 * @param string $action
 */
function rpgmaps_handle_ajax($action)
{
    global $mybb, $db;
    
    // Clear any previous output and set UTF-8 encoding for AJAX responses
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Load helpers
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/security.php';
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    
    $security = new RPGMapsSecurity();
    $manager = new RPGMapsManager();
    
    // Check CSRF token (exempt read-only actions)
    $csrf_exempt = ['rpgmaps_get_map', 'rpgmaps_get_house_info'];
    $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
    if (!in_array($action, $csrf_exempt, true) && !$security->verifyCSRFToken($token)) {
        rpgmaps_json_output(['success' => false, 'message' => 'CSRF token verification failed', 'error' => 'CSRF token verification failed']);
    }
    
    // Dispatch to appropriate handler
    switch ($action) {
        case 'rpgmaps_get_map':
            rpgmaps_ajax_get_map();
            break;
            
        case 'rpgmaps_build_request':
            rpgmaps_ajax_build_request($manager, $security);
            break;
            
        case 'rpgmaps_move_in_request':
            rpgmaps_ajax_move_in_request($manager, $security);
            break;
            
        case 'rpgmaps_move_out_request':
            rpgmaps_ajax_move_out_request($manager, $security);
            break;
            
        case 'rpgmaps_get_house_info':
            rpgmaps_ajax_get_house_info();
            break;
            
        case 'rpgmaps_update_description':
            rpgmaps_ajax_update_description($manager, $security);
            break;

        case 'rpgmaps_update_house_settings':
            rpgmaps_ajax_update_house_settings($manager, $security);
            break;
            
        default:
            rpgmaps_json_output(['success' => false, 'message' => 'Unknown action', 'error' => 'Unknown action']);
    }
}

/**
 * AJAX: Get map data
 */
function rpgmaps_ajax_get_map()
{
    global $db;
    
    $map_id = isset($_REQUEST['map_id']) ? (int)$_REQUEST['map_id'] : 0;
    
    if ($map_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid map ID', 'error' => 'Invalid map ID']);
    }
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    // Get map
    $map = $db_helper->getMapById($map_id);
    if (!$map) {
        rpgmaps_json_output(['success' => false, 'message' => 'Map not found', 'error' => 'Map not found']);
    }
    
    // Get plots
    $plots = $db_helper->getPlotsByMap($map_id);
    
    // Enrich plots with house data
    foreach ($plots as &$plot) {
        $house = $db_helper->getHouseByPlot($plot['id']);
        
        if ($house) {
            $house_type = $db_helper->getHouseTypeById($house['type_id']);
            $plot['house'] = [
                'id' => $house['id'],
                'type_id' => $house['type_id'],
                'type_name' => $house_type['name'],
                'type_asset' => $house_type['asset_filename'],
                'house_name' => isset($house['house_name']) ? $house['house_name'] : '',
                'status' => $house['status'],
            ];
            
            // Get occupants
            $occupants = $db_helper->getHouseOccupants($house['id']);
            $plot['occupants'] = $occupants;
        } else {
            $plot['house'] = null;
            $plot['occupants'] = [];
        }
    }
    
    rpgmaps_json_output([
        'success' => true,
        'map' => $map,
        'plots' => $plots,
    ]);
}

/**
 * AJAX: Request to build
 * @param RPGMapsManager $manager
 * @param RPGMapsSecurity $security
 */
function rpgmaps_ajax_build_request($manager, $security)
{
    // Check if user is logged in
    if (!$security->isUserLoggedIn()) {
        rpgmaps_json_output(['success' => false, 'message' => 'You must be logged in', 'error' => 'You must be logged in']);
    }
    
    $plot_id = isset($_REQUEST['plot_id']) ? (int)$_REQUEST['plot_id'] : 0;
    $house_type_id = isset($_REQUEST['house_type_id']) ? (int)$_REQUEST['house_type_id'] : 0;
    $max_occupants = isset($_REQUEST['max_occupants']) ? (int)$_REQUEST['max_occupants'] : 5;
    $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : '';
    $house_name = isset($_REQUEST['house_name']) ? trim($_REQUEST['house_name']) : '';
    $user_id = $security->getCurrentUserID();
    
    // Ensure proper UTF-8 encoding
    if (!mb_check_encoding($description, 'UTF-8')) {
        $description = mb_convert_encoding($description, 'UTF-8');
    }

    if (!mb_check_encoding($house_name, 'UTF-8')) {
        $house_name = mb_convert_encoding($house_name, 'UTF-8');
    }
    
    if ($plot_id <= 0 || $house_type_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid parameters', 'error' => 'Invalid parameters']);
    }
    
    if ($max_occupants < 1 || $max_occupants > 20) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid max occupants value', 'error' => 'Invalid max occupants value']);
    }
    
    // Request build
    $result = $manager->requestBuild($plot_id, $house_type_id, $user_id, $max_occupants, $description, $house_name);
    
    // Check if there was a database error
    global $db;
    if (isset($db->error) && $db->error) {
        rpgmaps_json_output([
            'success' => false,
            'message' => 'Database error occurred. Please check that the plugin tables are properly installed.',
            'error' => 'Database error: ' . $db->error,
        ]);
    }
    
    rpgmaps_json_output($result);
}

/**
 * AJAX: Request to move in
 * @param RPGMapsManager $manager
 * @param RPGMapsSecurity $security
 */
function rpgmaps_ajax_move_in_request($manager, $security)
{
    // Check if user is logged in
    if (!$security->isUserLoggedIn()) {
        rpgmaps_json_output(['success' => false, 'message' => 'You must be logged in', 'error' => 'You must be logged in']);
    }
    
    $house_id = isset($_REQUEST['house_id']) ? (int)$_REQUEST['house_id'] : 0;
    $user_id = $security->getCurrentUserID();
    
    if ($house_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid house ID', 'error' => 'Invalid house ID']);
    }
    
    // Request move in
    $result = $manager->requestMoveIn($house_id, $user_id);
    
    rpgmaps_json_output($result);
}

/**
 * AJAX: Request to move out
 * @param RPGMapsManager $manager
 * @param RPGMapsSecurity $security
 */
function rpgmaps_ajax_move_out_request($manager, $security)
{
    // Check if user is logged in
    if (!$security->isUserLoggedIn()) {
        rpgmaps_json_output(['success' => false, 'message' => 'You must be logged in', 'error' => 'You must be logged in']);
    }
    
    $house_id = isset($_REQUEST['house_id']) ? (int)$_REQUEST['house_id'] : 0;
    $user_id = $security->getCurrentUserID();
    
    if ($house_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid house ID', 'error' => 'Invalid house ID']);
    }
    
    // Request move out
    $result = $manager->requestMoveOut($house_id, $user_id);
    
    rpgmaps_json_output($result);
}

/**
 * AJAX: Get house information
 */
function rpgmaps_ajax_get_house_info()
{
    global $db, $parser;
    
    $house_id = isset($_REQUEST['house_id']) ? (int)$_REQUEST['house_id'] : 0;
    
    if ($house_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid house ID', 'error' => 'Invalid house ID']);
    }
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    // Get house
    $house = $db_helper->getHouseById($house_id);
    if (!$house) {
        rpgmaps_json_output(['success' => false, 'message' => 'House not found', 'error' => 'House not found']);
    }
    
    // Get house type
    $house_type = $db_helper->getHouseTypeById($house['type_id']);
    $house_types = $db_helper->getHouseTypes();
    
    // Get occupants with usernames
    $occupants = $db_helper->getHouseOccupants($house_id);
    
    // Get plot info
    $plot = $db_helper->getPlotById($house['plot_id']);
    
    // Parse BBCode in description
    if (!isset($parser)) {
        require_once MYBB_ROOT . 'inc/class_parser.php';
        $parser = new postParser;
    }
    
    $parser_options = [
        'allow_html' => 0,
        'allow_mycode' => 1,
        'allow_smilies' => 0,
        'allow_imgcode' => 1,
        'allow_videocode' => 1,
        'filter_badwords' => 1
    ];
    
    // Keep original description for editing
    $house['description_raw'] = $house['description'];
    // Parse BBCode to HTML for display
    $house['description_html'] = $parser->parse_message($house['description'], $parser_options);
    
    rpgmaps_json_output([
        'success' => true,
        'house' => $house,
        'house_type' => $house_type,
        'house_types' => $house_types,
        'occupants' => $occupants,
        'plot' => $plot,
    ]);
}

/**
 * AJAX: Update house description
 * @param RPGMapsManager $manager
 * @param RPGMapsSecurity $security
 */
function rpgmaps_ajax_update_description($manager, $security)
{
    // Check if user is logged in
    if (!$security->isUserLoggedIn()) {
        rpgmaps_json_output(['success' => false, 'message' => 'You must be logged in', 'error' => 'You must be logged in']);
    }
    
    $house_id = isset($_REQUEST['house_id']) ? (int)$_REQUEST['house_id'] : 0;
    $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : '';
    $user_id = $security->getCurrentUserID();
    
    // Ensure proper UTF-8 encoding
    if (!mb_check_encoding($description, 'UTF-8')) {
        $description = mb_convert_encoding($description, 'UTF-8');
    }
    
    if ($house_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid house ID', 'error' => 'Invalid house ID']);
    }
    
    // Update description
    $result = $manager->updateHouseDescription($house_id, $user_id, $description);

    if (!empty($result['success'])) {
        if (!isset($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';
            $parser = new postParser;
        }

        $parser_options = [
            'allow_html' => 0,
            'allow_mycode' => 1,
            'allow_smilies' => 0,
            'allow_imgcode' => 1,
            'allow_videocode' => 1,
            'filter_badwords' => 1
        ];

        $result['description_raw'] = $description;
        $result['description_html'] = $parser->parse_message($description, $parser_options);
    }

    rpgmaps_json_output($result);
}

/**
 * AJAX: Update house settings (house type + max occupants)
 * @param RPGMapsManager $manager
 * @param RPGMapsSecurity $security
 */
function rpgmaps_ajax_update_house_settings($manager, $security)
{
    // Check if user is logged in
    if (!$security->isUserLoggedIn()) {
        rpgmaps_json_output(['success' => false, 'message' => 'You must be logged in', 'error' => 'You must be logged in']);
    }

    $house_id = isset($_REQUEST['house_id']) ? (int)$_REQUEST['house_id'] : 0;
    $house_type_id = isset($_REQUEST['house_type_id']) ? (int)$_REQUEST['house_type_id'] : 0;
    $max_occupants = isset($_REQUEST['max_occupants']) ? (int)$_REQUEST['max_occupants'] : 0;
    $house_name = isset($_REQUEST['house_name']) ? trim($_REQUEST['house_name']) : '';
    $user_id = $security->getCurrentUserID();

    if ($house_id <= 0 || $house_type_id <= 0) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid parameters', 'error' => 'Invalid parameters']);
    }

    if ($max_occupants < 1 || $max_occupants > 20) {
        rpgmaps_json_output(['success' => false, 'message' => 'Invalid max occupants value', 'error' => 'Invalid max occupants value']);
    }

    if (!mb_check_encoding($house_name, 'UTF-8')) {
        $house_name = mb_convert_encoding($house_name, 'UTF-8');
    }

    $result = $manager->updateHouseSettings($house_id, $user_id, $house_type_id, $max_occupants, $house_name);

    rpgmaps_json_output($result);
}
