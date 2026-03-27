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
     * Get database helper
     * @return RPGMapsDatabase
     */
    public function getDB()
    {
        return $this->db;
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
            return ['success' => false, 'message' => 'Plot not found'];
        }
        
        if ($plot['status'] !== 'free') {
            return ['success' => false, 'message' => 'Plot is not available'];
        }
        
        // Verify house type exists
        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            return ['success' => false, 'message' => 'House type not found'];
        }
        
        // Validate max_occupants
        if ($max_occupants < 1 || $max_occupants > 20) {
            return ['success' => false, 'message' => 'Invalid max occupants value'];
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
        
        // Log action
        $this->logAction('BUILD_REQUEST', $user_id, $plot_id, 'Created for max ' . $max_occupants . ' occupants');
        
        return [
            'success' => true,
            'message' => 'Build request created. Awaiting admin approval.',
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
            return ['success' => false, 'message' => 'House not available'];
        }
        
        // Use house's max_occupants instead of house type default
        $max_occupants = $house['max_occupants'];
        $occupant_count = $this->db->countActiveOccupants($house_id);
        
        if ($occupant_count >= $max_occupants) {
            return ['success' => false, 'message' => 'House is full'];
        }
        
        // Check if user is already an occupant
        $occupants = $this->db->getHouseOccupants($house_id);
        foreach ($occupants as $occ) {
            if ($occ['uid'] == $user_id) {
                return ['success' => false, 'message' => 'You are already living in this house'];
            }
        }
        
        // Add user as resident immediately (no admin approval needed)
        $this->db->addOccupant($house_id, $user_id, 'resident');
        
        // Log action
        $this->logAction('MOVE_IN', $user_id, $house_id, 'User moved in');
        
        return [
            'success' => true,
            'message' => 'You have successfully moved into the house!',
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
            return ['success' => false, 'message' => 'House not found'];
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
            return ['success' => false, 'message' => 'You are not living in this house'];
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
            
            // Log cleanup
            $this->logAction('HOUSE_DELETED', $user_id, $plot_id, 'House deleted (no occupants left)');
            
            $house_deleted = true;
        }
        
        // Log action
        $this->logAction('MOVE_OUT', $user_id, $house_id, 'User moved out');
        
        return [
            'success' => true,
            'message' => 'You have successfully moved out!',
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
            return ['success' => false, 'message' => 'House not found'];
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
            return ['success' => false, 'message' => 'Only occupants can edit the description'];
        }
        
        // Update description
        $this->db->updateHouseDescription($house_id, $description);
        
        // Log action
        $this->logAction('HOUSE_DESCRIPTION_UPDATED', $user_id, $house_id, 'Description updated');
        
        return [
            'success' => true,
            'message' => 'Description updated successfully',
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
        // Verify house exists
        $house = $this->db->getHouseById($house_id);
        if (!$house) {
            return ['success' => false, 'message' => 'House not found'];
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
            return ['success' => false, 'message' => 'Only occupants can edit house settings'];
        }

        // Validate house type
        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            return ['success' => false, 'message' => 'House type not found'];
        }

        // Validate max occupants
        if ($max_occupants < 1 || $max_occupants > 20) {
            return ['success' => false, 'message' => 'Invalid max occupants value'];
        }

        // Ensure max occupants cannot be lower than current residents
        $current_occupants = $this->db->countActiveOccupants($house_id);
        if ($max_occupants < $current_occupants) {
            return ['success' => false, 'message' => 'Max occupants cannot be lower than current occupants'];
        }

        $house_name = trim($house_name);
        if (function_exists('mb_substr')) {
            $house_name = mb_substr($house_name, 0, 255);
        } else {
            $house_name = substr($house_name, 0, 255);
        }

        // Update house settings
        $this->db->updateHouse($house_id, [
            'type_id' => (int)$house_type_id,
            'max_occupants' => (int)$max_occupants,
            'house_name' => $house_name,
        ]);

        // Log action
        $this->logAction('HOUSE_SETTINGS_UPDATED', $user_id, $house_id, 'Settings updated: type=' . (int)$house_type_id . ', max=' . (int)$max_occupants . ', name=' . $house_name);

        return [
            'success' => true,
            'message' => 'House settings updated successfully',
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
        // Get action
        $action = $this->db->getActionById($action_id);
        if (!$action || $action['action_type'] !== 'build' || $action['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invalid or already processed action'];
        }
        
        $plot_id = $action['target_id'];
        $user_id = $action['user_id'];
        
        // Get plot
        $plot = $this->db->getPlotById($plot_id);
        if (!$plot) {
            return ['success' => false, 'message' => 'Plot not found'];
        }
        
        // Start transaction-like behavior by checking again
        if ($plot['status'] !== 'free') {
            $this->db->rejectAction($action_id, $admin_id, 'Plot no longer available');
            return ['success' => false, 'message' => 'Plot is no longer available'];
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
            return ['success' => false, 'message' => 'House type missing in request'];
        }

        $house_type = $this->db->getHouseTypeById($house_type_id);
        if (!$house_type) {
            $this->db->rejectAction($action_id, $admin_id, 'Invalid house type');
            return ['success' => false, 'message' => 'House type not found'];
        }

        // Create house with requested max_occupants, selected type, and description
        $house_data = [
            'plot_id' => $plot_id,
            'type_id' => $house_type_id,
            'status' => 'active',
            'max_occupants' => $max_occupants,
            'description' => $description,
            'house_name' => $house_name,
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
        
        // Log action
        $this->logAction('BUILD_APPROVED', $admin_id, $plot_id, "House $house_id created (max " . $max_occupants . " occupants)");
        
        return [
            'success' => true,
            'message' => 'Build request approved. House created.',
            'house_id' => $house_id,
        ];
    }
    
    /**
     * Approve a move-in request - add user as occupant
     * @param int $action_id
     * @param int $admin_id
     * @param string $admin_note
     * @return array ['success' => bool, 'message' => string]
     */
    public function approveMoveInRequest($action_id, $admin_id, $admin_note = '')
    {
        // Get action
        $action = $this->db->getActionById($action_id);
        if (!$action || $action['action_type'] !== 'move_in' || $action['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invalid or already processed action'];
        }
        
        $house_id = $action['target_id'];
        $user_id = $action['user_id'];
        
        // Verify house still exists and has space
        $house = $this->db->getHouseById($house_id);
        if (!$house || $house['status'] !== 'active') {
            $this->db->rejectAction($action_id, $admin_id, 'House no longer available');
            return ['success' => false, 'message' => 'House is no longer available'];
        }
        
        // Use house's max_occupants instead of house type default
        $max_occupants = $house['max_occupants'];
        $occupant_count = $this->db->countActiveOccupants($house_id);
        
        if ($occupant_count >= $max_occupants) {
            $this->db->rejectAction($action_id, $admin_id, 'House is full');
            return ['success' => false, 'message' => 'House is full'];
        }
        
        // Add occupant
        $this->db->addOccupant($house_id, $user_id, 'resident');
        
        // Approve action
        $this->db->approveAction($action_id, $admin_id, $admin_note);
        
        // Log action
        $this->logAction('MOVE_IN_APPROVED', $admin_id, $house_id, "User $user_id added");
        
        return [
            'success' => true,
            'message' => 'Move-in request approved. User added to house.',
        ];
    }
    
    /**
     * Approve a move-out request - remove user from occupants
     * @param int $action_id
     * @param int $admin_id
     * @param string $admin_note
     * @return array ['success' => bool, 'message' => string]
     */
    public function approveMoveOutRequest($action_id, $admin_id, $admin_note = '')
    {
        // Get action
        $action = $this->db->getActionById($action_id);
        if (!$action || $action['action_type'] !== 'move_out' || $action['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invalid or already processed action'];
        }
        
        $house_id = $action['target_id'];
        $user_id = $action['user_id'];
        
        // Find occupant record
        $query = $GLOBALS['db']->simple_select(
            TABLE_PREFIX . 'rpgmaps_house_occupants',
            'id',
            'house_id = ' . (int)$house_id . " AND uid = " . (int)$user_id . " AND left_at IS NULL",
            ['limit' => 1]
        );
        
        $occupant = $GLOBALS['db']->fetch_array($query);
        if (!$occupant) {
            return ['success' => false, 'message' => 'Occupant record not found'];
        }
        
        // Remove occupant
        $this->db->removeOccupant($occupant['id']);
        
        // Check if house is now empty - if so, delete it and mark plot as free
        $remaining_count = $this->db->countActiveOccupants($house_id);
        if ($remaining_count === 0) {
            // Get plot info
            $house = $this->db->getHouseById($house_id);
            
            // Delete house
            $this->db->deleteHouse($house_id);
            
            // Mark plot as free
            $this->db->updatePlot($house['plot_id'], ['status' => 'free']);
            
            // Log cleanup
            $this->logAction('HOUSE_CLEANED_UP', 0, $house['plot_id'], 'House deleted (no occupants)');
        }
        
        // Approve action
        $this->db->approveAction($action_id, $admin_id, $admin_note);
        
        // Log action
        $this->logAction('MOVE_OUT_APPROVED', $admin_id, $house_id, "User $user_id removed");
        
        return [
            'success' => true,
            'message' => 'Move-out request approved. User removed from house.',
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
            return ['success' => false, 'message' => 'Invalid or already processed action'];
        }
        
        $this->db->rejectAction($action_id, $admin_id, $admin_note);
        
        // Log action
        $this->logAction('ACTION_REJECTED', $admin_id, $action['target_id'], $action['action_type']);
        
        return [
            'success' => true,
            'message' => 'Action rejected.',
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
            TABLE_PREFIX . 'rpgmaps_house_occupants',
            'house_id',
            'uid = ' . (int)$user_id . " AND left_at IS NULL",
            ['group_by' => 'house_id']
        );
        
        $houses_to_check = [];
        while ($occupant = $GLOBALS['db']->fetch_array($query)) {
            $houses_to_check[] = $occupant['house_id'];
        }
        
        // Mark all occupants as left
        $GLOBALS['db']->update_query(
            TABLE_PREFIX . 'rpgmaps_house_occupants',
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
                
                // Log cleanup
                $this->logAction('HOUSE_CLEANED_UP', 0, $house['plot_id'], 'User deleted - house removed');
            }
        }
    }
    
    /**
     * Log an action for administrative purposes
     * @param string $action_type
     * @param int $user_id
     * @param int $target_id
     * @param string $details
     */
    protected function logAction($action_type, $user_id, $target_id, $details)
    {
        // You could store these in a separate log table
        // For now, just document the structure
        // TODO: Implement logging to separate table or file
    }
}
