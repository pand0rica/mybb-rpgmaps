<?php
/**
 * RPG Maps - Main Manager Class
 * Core business logic for the plugin
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Sprachvariable lesen; lädt die Sprachdatei bei Bedarf (Frontend: rpgmaps, ACP: admin/rpgmaps).
 *
 * @param string $key
 * @return string
 */
function rpgmaps_lang($key)
{
    global $lang;

    if (!isset($lang->rpgmaps_msg_invalid_params)) {
        $lang->load('rpgmaps');
    }

    return isset($lang->$key) ? (string)$lang->$key : $key;
}

class RPGMapsManager
{
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
        $this->db = new RPGMapsDatabase();
    }
    
    /**
     * Build house workflow - create pending request
     * @param int $plot_id
     * @param int $house_type_id
     * @param int $user_id
     * @return array ['success' => bool, 'message' => string, 'action_id' => int|null]
     */
    public function requestBuild($plot_id, $house_type_id, $user_id, $max_occupants = 5, $description = '', $house_name = '')
    {
        // Verify plot exists and is free
        $plot = $this->db->getPlotById($plot_id);
        if (!$plot) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_plot_not_found')];
        }
        
        if ($plot['status'] !== 'free') {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_plot_unavailable')];
        }
        
        // Verify house type exists
        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_type_not_found')];
        }
        
        // Validate max_occupants
        if ($max_occupants < 1 || $max_occupants > 20) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_invalid_max_occupants')];
        }
        
        $house_name = trim($house_name);
        if (function_exists('mb_substr')) {
            $house_name = mb_substr($house_name, 0, 255);
        } else {
            $house_name = substr($house_name, 0, 255);
        }

        // Create pending build action with max_occupants, house_type_id, description, and house_name stored
        $extra = [
            'max_occupants' => (int)$max_occupants,
            'house_type_id' => (int)$house_type_id,
            'description' => trim($description),
            'house_name' => $house_name,
        ];
        $action_id = $this->db->createAction('build', $plot_id, $user_id, 'pending', json_encode($extra));

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_build_requested'),
            'action_id' => $action_id,
        ];
    }
    
    /**
     * Move in workflow - create pending request
     * @param int $house_id
     * @param int $user_id
     * @return array ['success' => bool, 'message' => string, 'action_id' => int|null]
     */
    public function requestMoveIn($house_id, $user_id)
    {
        // Verify house exists and has space
        $house = $this->db->getHouseById($house_id);
        if (!$house || $house['status'] !== 'active') {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_unavailable')];
        }
        
        // Use house's max_occupants instead of house type default
        $max_occupants = $house['max_occupants'];
        $occupant_count = $this->db->countActiveOccupants($house_id);
        
        if ($occupant_count >= $max_occupants) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_full')];
        }
        
        // Check if user is already an occupant
        $occupants = $this->db->getHouseOccupants($house_id);
        foreach ($occupants as $occ) {
            if ($occ['uid'] == $user_id) {
                return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_already_resident')];
            }
        }
        
        // Add user as resident immediately (no admin approval needed)
        $this->db->addOccupant($house_id, $user_id, 'resident');

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_moved_in'),
        ];
    }
    
    /**
     * Move out workflow - remove user from house immediately
     * @param int $house_id
     * @param int $user_id
     * @return array ['success' => bool, 'message' => string]
     */
    public function requestMoveOut($house_id, $user_id)
    {
        // Verify house exists
        $house = $this->db->getHouseById($house_id);
        if (!$house) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_not_found')];
        }
        
        // Check if user is an occupant and get the occupant ID
        $occupants = $this->db->getHouseOccupants($house_id);
        $occupant_id = null;
        foreach ($occupants as $occ) {
            if ($occ['uid'] == $user_id) {
                $occupant_id = $occ['id'];
                break;
            }
        }
        
        if ($occupant_id === null) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_not_resident')];
        }
        
        // Remove user from house immediately (no admin approval needed)
        $this->db->removeOccupant($occupant_id);
        
        // Check if house is now empty - if so, delete it and mark plot as free
        $remaining_count = $this->db->countActiveOccupants($house_id);
        $house_deleted = false;
        $plot_id = $house['plot_id'];
        
        if ($remaining_count === 0) {
            // Delete house
            $this->db->deleteHouse($house_id);
            
            // Mark plot as free
            $this->db->updatePlot($plot_id, ['status' => 'free']);

            $house_deleted = true;
        }

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_moved_out'),
            'house_deleted' => $house_deleted,
            'plot_id' => $plot_id,
        ];
    }
    
    /**
     * Update house description (only occupants can edit)
     * @param int $house_id
     * @param int $user_id
     * @param string $description
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateHouseDescription($house_id, $user_id, $description)
    {
        // Verify house exists
        $house = $this->db->getHouseById($house_id);
        if (!$house) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_not_found')];
        }
        
        // Check if user is an occupant
        $occupants = $this->db->getHouseOccupants($house_id);
        $is_occupant = false;
        foreach ($occupants as $occ) {
            if ($occ['uid'] == $user_id) {
                $is_occupant = true;
                break;
            }
        }
        
        if (!$is_occupant) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_only_occupants')];
        }
        
        // Update description
        $this->db->updateHouseDescription($house_id, $description);

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_description_updated'),
        ];
    }

    /**
     * Update house settings (only occupants can edit)
     * @param int $house_id
     * @param int $user_id
     * @param int $house_type_id
     * @param int $max_occupants
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateHouseSettings($house_id, $user_id, $house_type_id, $max_occupants, $house_name = '')
    {
        global $db;

        // Verify house exists
        $house = $this->db->getHouseById($house_id);
        if (!$house) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_not_found')];
        }

        // Check if user is an occupant
        $occupants = $this->db->getHouseOccupants($house_id);
        $is_occupant = false;
        foreach ($occupants as $occ) {
            if ((int)$occ['uid'] === (int)$user_id) {
                $is_occupant = true;
                break;
            }
        }

        if (!$is_occupant) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_only_occupants')];
        }

        // Validate house type
        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_type_not_found')];
        }

        // Validate max occupants
        if ($max_occupants < 1 || $max_occupants > 20) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_invalid_max_occupants')];
        }

        // Ensure max occupants cannot be lower than current residents
        $current_occupants = $this->db->countActiveOccupants($house_id);
        if ($max_occupants < $current_occupants) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_max_below_current')];
        }

        $house_name = trim($house_name);
        if (function_exists('mb_substr')) {
            $house_name = mb_substr($house_name, 0, 255);
        } else {
            $house_name = substr($house_name, 0, 255);
        }

        // Update house settings (update_query escapet nicht selbst)
        $this->db->updateHouse($house_id, [
            'type_id' => (int)$house_type_id,
            'max_occupants' => (int)$max_occupants,
            'house_name' => $db->escape_string($house_name),
        ]);

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_settings_updated'),
            'type_id' => (int)$house_type_id,
            'max_occupants' => (int)$max_occupants,
            'type_name' => $house_type['name'],
            'type_asset' => $house_type['asset_filename'],
            'house_name' => $house_name,
        ];
    }
    
    /**
     * Approve a build request - creates house and sets plot status
     * @param int $action_id
     * @param int $admin_id
     * @param string $admin_note
     * @return array ['success' => bool, 'message' => string]
     */
    public function approveBuildRequest($action_id, $admin_id, $admin_note = '')
    {
        global $db;

        // Get action
        $action = $this->db->getActionById($action_id);
        if (!$action || $action['action_type'] !== 'build' || $action['status'] !== 'pending') {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_invalid_action')];
        }
        
        $plot_id = $action['target_id'];
        $user_id = $action['user_id'];
        
        // Get plot
        $plot = $this->db->getPlotById($plot_id);
        if (!$plot) {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_plot_not_found')];
        }
        
        // Start transaction-like behavior by checking again
        if ($plot['status'] !== 'free') {
            $this->db->rejectAction($action_id, $admin_id, 'Plot no longer available');
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_plot_unavailable')];
        }
        
        // Extract house_type_id, max_occupants, and description from action extra_data
        $max_occupants = 5; // default
        $house_type_id = null;
        $description = '';
        $house_name = '';
        if (!empty($action['extra_data'])) {
            $extra = json_decode($action['extra_data'], true);
            if (is_array($extra)) {
                if (isset($extra['max_occupants']) && is_numeric($extra['max_occupants'])) {
                    $max_occupants = max(1, min(20, (int)$extra['max_occupants']));
                }
                if (isset($extra['house_type_id']) && is_numeric($extra['house_type_id'])) {
                    $house_type_id = (int)$extra['house_type_id'];
                }
                if (isset($extra['description'])) {
                    $description = trim($extra['description']);
                }
                if (isset($extra['house_name'])) {
                    $house_name = trim($extra['house_name']);
                }
            }
        }

        if (function_exists('mb_substr')) {
            $house_name = mb_substr($house_name, 0, 255);
        } else {
            $house_name = substr($house_name, 0, 255);
        }

        // Ensure house_type_id is present and valid
        if (!$house_type_id) {
            $this->db->rejectAction($action_id, $admin_id, 'Missing house type');
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_type_not_found')];
        }

        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            $this->db->rejectAction($action_id, $admin_id, 'Invalid house type');
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_house_type_not_found')];
        }

        // Create house with requested max_occupants, selected type, and description
        $house_data = [
            'plot_id' => $plot_id,
            'type_id' => $house_type_id,
            'status' => 'active',
            'max_occupants' => $max_occupants,
            'description' => $db->escape_string($description),
            'house_name' => $db->escape_string($house_name),
            'created_by' => $user_id,
            'approved_at' => TIME_NOW,
        ];
        
        $house_id = $this->db->insertHouse($house_data);
        
        // Add creator as owner
        $this->db->addOccupant($house_id, $user_id, 'owner');
        
        // Update plot status
        $this->db->updatePlot($plot_id, ['status' => 'built']);
        
        // Approve action
        $this->db->approveAction($action_id, $admin_id, $admin_note);

        // Send MyAlerts notification to the requesting user
        $this->sendBuildApprovedAlert($user_id, $plot_id, $house_id);

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_build_approved'),
            'house_id' => $house_id,
        ];
    }
    
    /**
     * Reject an action
     * @param int $action_id
     * @param int $admin_id
     * @param string $admin_note
     * @return array ['success' => bool, 'message' => string]
     */
    public function rejectAction($action_id, $admin_id, $admin_note = '')
    {
        $action = $this->db->getActionById($action_id);
        if (!$action || $action['status'] !== 'pending') {
            return ['success' => false, 'message' => rpgmaps_lang('rpgmaps_msg_invalid_action')];
        }
        
        $this->db->rejectAction($action_id, $admin_id, $admin_note);

        return [
            'success' => true,
            'message' => rpgmaps_lang('rpgmaps_msg_action_rejected'),
        ];
    }
    
    /**
     * Handle user deletion - remove from houses and clean up empty houses
     * @param int $user_id
     */
    public function handleUserDeletion($user_id)
    {
        // Get all houses where user is an occupant
        $query = $GLOBALS['db']->simple_select(
            'rpgmaps_house_occupants',
            'house_id',
            'uid = ' . (int)$user_id . " AND left_at IS NULL",
            ['group_by' => 'house_id']
        );
        
        $houses_to_check = [];
        while ($occupant = $GLOBALS['db']->fetch_array($query)) {
            $houses_to_check[] = $occupant['house_id'];
        }
        
        // Offene Bauanträge des Accounts verwerfen
        $GLOBALS['db']->delete_query('rpgmaps_actions', 'user_id = ' . (int)$user_id . " AND status = 'pending'");

        // Mark all occupants as left
        $GLOBALS['db']->update_query(
            'rpgmaps_house_occupants',
            ['left_at' => TIME_NOW],
            'uid = ' . (int)$user_id . " AND left_at IS NULL"
        );
        
        // Clean up empty houses
        foreach ($houses_to_check as $house_id) {
            $remaining_count = $this->db->countActiveOccupants($house_id);
            
            if ($remaining_count === 0) {
                $house = $this->db->getHouseById($house_id);
                
                // Delete house
                $this->db->deleteHouse($house_id);
                
                // Mark plot as free
                $this->db->updatePlot($house['plot_id'], ['status' => 'free']);
                
            }
        }
    }
    
    /**
     * Send a MyAlerts notification when a build request is approved.
     * Silently skips if MyAlerts is not installed or not active.
     *
     * @param int $user_id  The user who submitted the build request
     * @param int $plot_id  The plot that was approved
     * @param int $house_id The newly created house
     */
    protected function sendBuildApprovedAlert($user_id, $plot_id, $house_id)
    {
        if (!function_exists('myalerts_create_instances')) {
            return;
        }

        $alertManager     = MybbStuff_MyAlerts_AlertManager::getInstance();
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertManager || !$alertTypeManager) {
            return;
        }

        $alertType = $alertTypeManager->getByCode('rpgmaps_build_approved');
        if (!$alertType) {
            return;
        }

        // Gather extra details for the formatter (plot key + map title + map id)
        $plot      = $this->db->getPlotById($plot_id);
        $map       = ($plot && isset($plot['map_id'])) ? $this->db->getMapById($plot['map_id']) : null;
        $plot_key  = $plot  ? $plot['plot_key']   : '';
        $map_title = $map   ? $map['title']        : '';
        $map_id    = $map   ? (int)$map['id']      : 0;

        $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$user_id, $alertType, (int)$house_id);
        $alert->setExtraDetails(array(
            'plot_key'  => $plot_key,
            'map_title' => $map_title,
            'map_id'    => $map_id,
        ));

        $alertManager->addAlert($alert);
    }
}
