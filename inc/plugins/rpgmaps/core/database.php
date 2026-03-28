<?php
/**
 * RPG Maps - Database Helper Class
 * Handles all database operations with prepared statements
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

class RPGMapsDatabase
{
    protected $db;
    protected $prefix;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
        // Note: myBB functions automatically add TABLE_PREFIX, so we just use table names
    }
    
    // ============ MAPS ============
    
    /**
     * Get all maps
     * @return array List of maps
     */
    public function getMaps()
    {
        $query = $this->db->simple_select(
            'rpgmaps_maps',
            '*',
            '',
            ['order_by' => 'title', 'order_dir' => 'ASC']
        );
        
        $maps = [];
        while ($map = $this->db->fetch_array($query)) {
            $maps[] = $map;
        }
        return $maps;
    }
    
    /**
     * Get map by ID
     * @param int $map_id
     * @return array|null
     */
    public function getMapById($map_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_maps',
                '*',
                'id = ' . (int)$map_id
            )
        );
    }
    
    /**
     * Insert a new map
     * @param array $data
     * @return int Map ID
     */
    public function insertMap(array $data)
    {
        $data['created_at'] = TIME_NOW;
        $data['updated_at'] = TIME_NOW;
        
        $this->db->insert_query('rpgmaps_maps', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Create a new map (alias for insertMap)
     * @param array $data
     * @return int Map ID
     */
    public function createMap(array $data)
    {
        return $this->insertMap($data);
    }
    
    /**
     * Update a map
     * @param int $map_id
     * @param array $data
     */
    public function updateMap($map_id, array $data)
    {
        $data['updated_at'] = TIME_NOW;
        
        $this->db->update_query(
            'rpgmaps_maps',
            $data,
            'id = ' . (int)$map_id
        );
    }
    
    /**
     * Delete a map and all related data
     * @param int $map_id
     */
    public function deleteMap($map_id)
    {
        // Delete all plots and their houses
        $query = $this->db->simple_select(
            'rpgmaps_buildplots',
            'id',
            'map_id = ' . (int)$map_id
        );
        
        while ($plot = $this->db->fetch_array($query)) {
            $this->deletePlot($plot['id']);
        }
        
        // Delete map
        $this->db->delete_query(
            'rpgmaps_maps',
            'id = ' . (int)$map_id
        );
    }
    
    // ============ BUILD PLOTS ============
    
    /**
     * Get plots for a map
     * @param int $map_id
     * @return array
     */
    public function getPlotsByMap($map_id)
    {
        $query = $this->db->simple_select(
            'rpgmaps_buildplots',
            '*',
            'map_id = ' . (int)$map_id,
            ['order_by' => 'plot_key']
        );
        
        $plots = [];
        while ($plot = $this->db->fetch_array($query)) {
            $plots[] = $plot;
        }
        return $plots;
    }
    
    /**
     * Get plot by ID
     * @param int $plot_id
     * @return array|null
     */
    public function getPlotById($plot_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_buildplots',
                '*',
                'id = ' . (int)$plot_id
            )
        );
    }
    
    /**
     * Insert a new plot
     * @param array $data
     * @return int Plot ID
     */
    public function insertPlot(array $data)
    {
        $data['created_at'] = TIME_NOW;
        
        $this->db->insert_query('rpgmaps_buildplots', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Update a plot
     * @param int $plot_id
     * @param array $data
     */
    public function updatePlot($plot_id, array $data)
    {
        $this->db->update_query(
            'rpgmaps_buildplots',
            $data,
            'id = ' . (int)$plot_id
        );
    }
    
    /**
     * Delete a plot and related houses
     * @param int $plot_id
     */
    public function deletePlot($plot_id)
    {
        // Delete houses on this plot
        $query = $this->db->simple_select(
            'rpgmaps_houses',
            'id',
            'plot_id = ' . (int)$plot_id
        );
        
        while ($house = $this->db->fetch_array($query)) {
            $this->deleteHouse($house['id']);
        }
        
        // Delete plot
        $this->db->delete_query(
            'rpgmaps_buildplots',
            'id = ' . (int)$plot_id
        );
    }
    
    // ============ HOUSE TYPES ============
    
    /**
     * Get all house types
     * @return array
     */
    public function getHouseTypes()
    {
        $query = $this->db->simple_select(
            'rpgmaps_house_types',
            '*',
            '',
            ['order_by' => 'name']
        );
        
        $types = [];
        while ($type = $this->db->fetch_array($query)) {
            $types[] = $type;
        }
        return $types;
    }
    
    /**
     * Get house type by ID
     * @param int $type_id
     * @return array|null
     */
    public function getHouseTypeById($type_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_house_types',
                '*',
                'id = ' . (int)$type_id
            )
        );
    }
    
    /**
     * Insert a new house type
     * @param array $data
     * @return int Type ID
     */
    public function insertHouseType(array $data)
    {
        $data['created_at'] = TIME_NOW;
        
        $this->db->insert_query('rpgmaps_house_types', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Create a new house type (alias for insertHouseType)
     * @param array $data
     * @return int Type ID
     */
    public function createHouseType(array $data)
    {
        return $this->insertHouseType($data);
    }
    
    /**
     * Update a house type
     * @param int $type_id
     * @param array $data
     */
    public function updateHouseType($type_id, array $data)
    {
        $this->db->update_query(
            'rpgmaps_house_types',
            $data,
            'id = ' . (int)$type_id
        );
    }
    
    /**
     * Delete a house type (if no houses use it)
     * @param int $type_id
     * @return bool Success
     */
    public function deleteHouseType($type_id)
    {
        // Check if any houses use this type
        $query = $this->db->simple_select(
            'rpgmaps_houses',
            'COUNT(*) as cnt',
            'type_id = ' . (int)$type_id
        );
        
        $row = $this->db->fetch_array($query);
        if ($row['cnt'] > 0) {
            return false; // Cannot delete - houses depend on it
        }
        
        $this->db->delete_query(
            'rpgmaps_house_types',
            'id = ' . (int)$type_id
        );
        return true;
    }
    
    // ============ HOUSES ============
    
    /**
     * Get house by ID with related info
     * @param int $house_id
     * @return array|null
     */
    public function getHouseById($house_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_houses',
                '*',
                'id = ' . (int)$house_id
            )
        );
    }
    
    /**
     * Get house on a plot
     * @param int $plot_id
     * @return array|null
     */
    public function getHouseByPlot($plot_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_houses',
                '*',
                'plot_id = ' . (int)$plot_id . " AND status = 'active'",
                ['limit' => 1]
            )
        );
    }
    
    /**
     * Insert a new house
     * @param array $data
     * @return int House ID
     */
    public function insertHouse(array $data)
    {
        $data['created_at'] = TIME_NOW;
        
        $this->db->insert_query('rpgmaps_houses', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Update a house
     * @param int $house_id
     * @param array $data
     */
    public function updateHouse($house_id, array $data)
    {
        $this->db->update_query(
            'rpgmaps_houses',
            $data,
            'id = ' . (int)$house_id
        );
    }
    
    /**
     * Update house description
     * @param int $house_id
     * @param string $description
     */
    public function updateHouseDescription($house_id, $description)
    {
        $this->db->update_query(
            'rpgmaps_houses',
            ['description' => $this->db->escape_string($description)],
            'id = ' . (int)$house_id
        );
    }
    
    /**
     * Delete a house and its occupants
     * @param int $house_id
     */
    public function deleteHouse($house_id)
    {
        // Delete occupants
        $this->db->delete_query(
            'rpgmaps_house_occupants',
            'house_id = ' . (int)$house_id
        );
        
        // Delete house
        $this->db->delete_query(
            'rpgmaps_houses',
            'id = ' . (int)$house_id
        );
    }
    
    // ============ HOUSE OCCUPANTS ============
    
    /**
     * Get occupants of a house
     * @param int $house_id
     * @return array
     */
    public function getHouseOccupants($house_id)
    {
        $query = $this->db->query("
            SELECT o.*, u.username, u.usergroup, u.displaygroup
            FROM " . TABLE_PREFIX . "rpgmaps_house_occupants o
            LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = o.uid
            WHERE o.house_id = " . (int)$house_id . " AND o.left_at IS NULL
            ORDER BY o.role DESC, o.joined_at ASC
        ");
        
        $occupants = [];
        while ($occupant = $this->db->fetch_array($query)) {
            $occupants[] = $occupant;
        }
        return $occupants;
    }
    
    /**
     * Add an occupant to a house
     * @param int $house_id
     * @param int $uid
     * @param string $role
     * @return int Occupant ID
     */
    public function addOccupant($house_id, $uid, $role = 'resident')
    {
        $data = [
            'house_id' => (int)$house_id,
            'uid' => (int)$uid,
            'role' => $role,
            'joined_at' => TIME_NOW,
        ];
        
        $this->db->insert_query('rpgmaps_house_occupants', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Remove an occupant from a house
     * @param int $occupant_id
     */
    public function removeOccupant($occupant_id)
    {
        $this->db->update_query(
            'rpgmaps_house_occupants',
            ['left_at' => TIME_NOW],
            'id = ' . (int)$occupant_id
        );
    }
    
    /**
     * Count active occupants in a house
     * @param int $house_id
     * @return int
     */
    public function countActiveOccupants($house_id)
    {
        $query = $this->db->simple_select(
            'rpgmaps_house_occupants',
            'COUNT(*) as cnt',
            'house_id = ' . (int)$house_id . " AND left_at IS NULL"
        );
        
        $row = $this->db->fetch_array($query);
        return (int)$row['cnt'];
    }
    
    // ============ ACTIONS / REQUESTS ============
    
    /**
     * Create a pending action
     * @param string $action_type (build, move_in, move_out, delete_house)
     * @param int $target_id
     * @param int $user_id
     * @param string $status Optional status
     * @param string $extra_data Optional JSON data
     * @return int Action ID
     */
    public function createAction($action_type, $target_id, $user_id, $status = 'pending', $extra_data = null)
    {
        $data = [
            'action_type' => $action_type,
            'target_id' => (int)$target_id,
            'user_id' => (int)$user_id,
            'status' => $status,
            'created_at' => TIME_NOW,
        ];
        
        if ($extra_data !== null) {
            // Escape the JSON data properly for database
            $data['extra_data'] = $this->db->escape_string($extra_data);
        }
        
        $this->db->insert_query('rpgmaps_actions', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Get pending actions
     * @param string $action_type Optional filter
     * @return array
     */
    public function getPendingActions($action_type = null)
    {
        $where = "status = 'pending'";
        if ($action_type) {
            $where .= " AND action_type = '" . $this->db->escape_string($action_type) . "'";
        }
        
        $query = $this->db->simple_select(
            'rpgmaps_actions',
            '*',
            $where,
            ['order_by' => 'created_at', 'order_dir' => 'DESC']
        );
        
        $actions = [];
        while ($action = $this->db->fetch_array($query)) {
            $actions[] = $action;
        }
        return $actions;
    }
    
    /**
     * Get action by ID
     * @param int $action_id
     * @return array|null
     */
    public function getActionById($action_id)
    {
        return $this->db->fetch_array(
            $this->db->simple_select(
                'rpgmaps_actions',
                '*',
                'id = ' . (int)$action_id
            )
        );
    }
    
    /**
     * Check if plot has pending build request
     * @param int $plot_id
     * @return bool
     */
    public function hasPendingBuildRequest($plot_id)
    {
        $query = $this->db->simple_select(
            'rpgmaps_actions',
            'COUNT(*) as count',
            "target_id = " . (int)$plot_id . " AND action_type = 'build' AND status = 'pending'"
        );
        $result = $this->db->fetch_array($query);
        return ($result['count'] > 0);
    }
    
    /**
     * Approve an action
     * @param int $action_id
     * @param int $reviewed_by
     * @param string $admin_note
     */
    public function approveAction($action_id, $reviewed_by, $admin_note = '')
    {
        $data = [
            'status' => 'approved',
            'reviewed_by' => (int)$reviewed_by,
            'reviewed_at' => TIME_NOW,
            'admin_note' => $this->db->escape_string($admin_note),
        ];
        
        $this->db->update_query(
            'rpgmaps_actions',
            $data,
            'id = ' . (int)$action_id
        );
    }
    
    /**
     * Reject an action
     * @param int $action_id
     * @param int $reviewed_by
     * @param string $admin_note
     */
    public function rejectAction($action_id, $reviewed_by, $admin_note = '')
    {
        $data = [
            'status' => 'rejected',
            'reviewed_by' => (int)$reviewed_by,
            'reviewed_at' => TIME_NOW,
            'admin_note' => $this->db->escape_string($admin_note),
        ];
        
        $this->db->update_query(
            'rpgmaps_actions',
            $data,
            'id = ' . (int)$action_id
        );
    }
}
