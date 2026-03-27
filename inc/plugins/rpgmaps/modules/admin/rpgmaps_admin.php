<?php
/**
 * RPG Maps Plugin - Admin Control Panel Module
 * Manages maps, build plots, house types, and pending actions
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

// Load language files
global $lang;
$lang->load('rpgmaps');

// Load helpers
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/security.php';
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';

// Initialize
global $mybb, $admin, $db;

// Create instances
$db_helper = new RPGMapsDatabase();
$manager = new RPGMapsManager();

// Get action
$action = isset($mybb->input['sub']) ? $mybb->input['sub'] : 'overview';

// Check admin permission - simplified check
// In production, implement proper permission checking
if (!defined('IN_ADMINCP') || !IN_ADMINCP) {
    die('Access Denied');
}

// Handle different sub-actions
switch ($action) {
    // ============ MAPS MANAGEMENT ============
    case 'maps':
        rpgmaps_admin_maps();
        break;
        
    case 'maps_add':
        rpgmaps_admin_maps_add();
        break;
        
    case 'maps_edit':
        rpgmaps_admin_maps_edit();
        break;
        
    case 'maps_delete':
        rpgmaps_admin_maps_delete();
        break;
        
    // ============ BUILD PLOTS MANAGEMENT ============
    case 'buildplots':
        rpgmaps_admin_buildplots();
        break;
        
    case 'buildplots_add':
        rpgmaps_admin_buildplots_add();
        break;
        
    case 'buildplots_edit':
        rpgmaps_admin_buildplots_edit();
        break;
        
    case 'buildplots_delete':
        rpgmaps_admin_buildplots_delete();
        break;
        
    // ============ HOUSE TYPES MANAGEMENT ============
    case 'house_types':
        rpgmaps_admin_house_types();
        break;
        
    case 'house_types_add':
        rpgmaps_admin_house_types_add();
        break;
        
    case 'house_types_edit':
        rpgmaps_admin_house_types_edit();
        break;
        
    case 'house_types_delete':
        rpgmaps_admin_house_types_delete();
        break;
        
    // ============ PENDING ACTIONS ============
    case 'pending_actions':
        rpgmaps_admin_pending_actions();
        break;
        
    case 'action_approve':
        rpgmaps_admin_action_approve();
        break;
        
    case 'action_reject':
        rpgmaps_admin_action_reject();
        break;
        
    // ============ OVERVIEW ============
    default:
        rpgmaps_admin_overview();
}

/**
 * Overview page
 */
function rpgmaps_admin_overview()
{
    global $mybb, $db, $page, $lang;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->output_header($lang->rpgmaps . ' - Overview');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $map_count = count($db_helper->getMaps());
    $pending_count = count($db_helper->getPendingActions());
    
    $sub_tabs = array(
        'overview' => array(
            'title' => 'Overview',
            'link' => 'index.php?module=tools-rpgmaps',
            'description' => 'Overview of RPG Maps statistics'
        ),
        'maps' => array(
            'title' => 'Manage Maps',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps',
            'description' => 'Manage city maps'
        ),
        'house_types' => array(
            'title' => 'Manage House Types',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types',
            'description' => 'Manage house types'
        ),
        'pending_actions' => array(
            'title' => 'View Pending Actions',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions',
            'description' => 'Review pending actions'
        )
    );
    
    $page->output_nav_tabs($sub_tabs, 'overview');
    
    $table = new Table;
    $table->construct_cell('<strong>Maps:</strong> ' . $map_count);
    $table->construct_row();
    $table->construct_cell('<strong>Pending Actions:</strong> ' . $pending_count);
    $table->construct_row();
    $table->output('RPG Maps - Overview');
    
    $table = new Table;
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps">Manage Maps</a>');
    $table->construct_row();
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types">Manage House Types</a>');
    $table->construct_row();
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions">View Pending Actions</a>');
    $table->construct_row();
    $table->output('Quick Links');
    
    $page->output_footer();
}

/**
 * Maps list and management
 */
function rpgmaps_admin_maps()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_manage_maps);
    
    $sub_tabs = array(
        'overview' => array(
            'title' => 'Overview',
            'link' => 'index.php?module=tools-rpgmaps',
        ),
        'maps' => array(
            'title' => 'Manage Maps',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps',
        ),
        'house_types' => array(
            'title' => 'Manage House Types',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types',
        ),
        'pending_actions' => array(
            'title' => 'View Pending Actions',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions',
        )
    );
    
    $page->output_nav_tabs($sub_tabs, 'maps');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $maps = $db_helper->getMaps();
    
    $form_container = new FormContainer($lang->rpgmaps_manage_maps);
    $form_container->output_row_header('Map Title');
    $form_container->output_row_header('Width/Height');
    $form_container->output_row_header('Created');
    $form_container->output_row_header('Actions');
    
    if (empty($maps)) {
        $form_container->output_cell($lang->rpgmaps_no_maps, array('colspan' => 4));
        $form_container->construct_row();
    } else {
        foreach ($maps as $map) {
            $form_container->output_cell(htmlspecialchars_uni($map['title']));
            $form_container->output_cell((int)$map['width'] . 'x' . (int)$map['height']);
            $form_container->output_cell(date('Y-m-d', $map['created_at']));
            
            $popup = new PopupMenu('map_' . $map['id'], 'Options');
            $popup->add_item('Edit', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_edit&id=' . (int)$map['id']);
            $popup->add_item('Manage Plots', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . (int)$map['id']);
            $popup->add_item('Delete', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_delete&id=' . (int)$map['id'], 'return confirm(\'' . $lang->rpgmaps_confirm_delete . '\');');
            
            $form_container->output_cell($popup->fetch());
            $form_container->construct_row();
        }
    }
    
    $form_container->end();
    
    $table = new Table;
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_add">' . $lang->rpgmaps_add_map . '</a>');
    $table->construct_row();
    $table->output('Actions');
    
    $page->output_footer();
}

/**
 * Add map form
 */
function rpgmaps_admin_maps_add()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    $page->add_breadcrumb_item($lang->rpgmaps_add_map);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_add_map);
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'maps');
    
    if ($mybb->request_method == 'post') {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
        $db_helper = new RPGMapsDatabase();
        
        // Handle file upload
        $filename = '';
        if (isset($_FILES['map_image']) && $_FILES['map_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/maps/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['map_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = array('png', 'jpg', 'jpeg', 'gif');
            
            if (in_array($file_ext, $allowed_exts)) {
                $filename = 'map_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['map_image']['tmp_name'], $target_path)) {
                    // File uploaded successfully
                } else {
                    flash_message('Fehler beim Hochladen der Datei', 'error');
                    admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_add');
                }
            } else {
                flash_message('Ungültiges Dateiformat. Nur PNG, JPG und GIF erlaubt.', 'error');
                admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_add');
            }
        } else {
            // No file uploaded, use placeholder
            $filename = 'placeholder.png';
        }
        
        $map_data = array(
            'title' => $mybb->get_input('title'),
            'description' => $mybb->get_input('description'),
            'width' => $mybb->get_input('width', MyBB::INPUT_INT),
            'height' => $mybb->get_input('height', MyBB::INPUT_INT),
            'filename' => $filename
        );
        
        $map_id = $db_helper->createMap($map_data);
        
        flash_message($lang->rpgmaps_map_added, 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps', 'post', '', 1);
    echo $form->generate_hidden_field('sub', 'maps_add');
    
    $form_container = new FormContainer($lang->rpgmaps_add_map);
    $form_container->output_row($lang->rpgmaps_map_name, '', $form->generate_text_box('title', '', array('id' => 'title')), 'title');
    $form_container->output_row($lang->rpgmaps_map_description, '', $form->generate_text_area('description', '', array('id' => 'description')), 'description');
    $form_container->output_row($lang->rpgmaps_map_width . ' (px)', '', $form->generate_numeric_field('width', '800', array('id' => 'width', 'min' => 100)), 'width');
    $form_container->output_row($lang->rpgmaps_map_height . ' (px)', '', $form->generate_numeric_field('height', '600', array('id' => 'height', 'min' => 100)), 'height');
    $form_container->output_row('Kartendatei', '', $form->generate_file_upload_box('map_image', array('id' => 'map_image')), 'map_image');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->rpgmaps_save);
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Edit map form
 */
function rpgmaps_admin_maps_edit()
{
    global $mybb, $db, $lang, $page;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $map_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $map = $db_helper->getMapById($map_id);
    
    if (!$map) {
        flash_message('Map not found', 'error');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    $page->add_breadcrumb_item($lang->rpgmaps_edit_map);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_edit_map);
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'maps');
    
    if ($mybb->request_method == 'post') {
        // Handle file upload if provided
        $filename = $map['filename']; // Keep existing filename by default
        
        if (isset($_FILES['map_image']) && $_FILES['map_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/maps/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['map_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = array('png', 'jpg', 'jpeg', 'gif');
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'map_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['map_image']['tmp_name'], $target_path)) {
                    // Delete old file if it exists and is not placeholder
                    if ($filename != 'placeholder.png' && file_exists($upload_dir . $filename)) {
                        @unlink($upload_dir . $filename);
                    }
                    $filename = $new_filename;
                } else {
                    flash_message('Fehler beim Hochladen der Datei', 'error');
                    admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_edit&id=' . $map_id);
                }
            } else {
                flash_message('Ungültiges Dateiformat. Nur PNG, JPG und GIF erlaubt.', 'error');
                admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_edit&id=' . $map_id);
            }
        }
        
        $map_data = array(
            'title' => $mybb->get_input('title'),
            'description' => $mybb->get_input('description'),
            'width' => $mybb->get_input('width', MyBB::INPUT_INT),
            'height' => $mybb->get_input('height', MyBB::INPUT_INT),
            'scale_factor' => $mybb->get_input('scale_factor', MyBB::INPUT_FLOAT),
            'filename' => $filename
        );
        
        $db_helper->updateMap($map_id, $map_data);
        
        flash_message($lang->rpgmaps_map_updated, 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_edit&id=' . $map_id, 'post', '', 1);
    
    $form_container = new FormContainer($lang->rpgmaps_edit_map);
    
    // Show current map image if exists
    if (!empty($map['filename']) && $map['filename'] != 'placeholder.png') {
        $current_image = '<img src="' . $mybb->settings['bburl'] . '/inc/plugins/rpgmaps/assets/maps/' . htmlspecialchars_uni($map['filename']) . '" alt="Current Map" class="rpgmaps-admin-image">';
        $form_container->output_row('Aktuelles Kartenbild', '', $current_image);
    }
    
    $form_container->output_row($lang->rpgmaps_map_name, '', $form->generate_text_box('title', $map['title'], array('id' => 'title')), 'title');
    $form_container->output_row($lang->rpgmaps_map_description, '', $form->generate_text_area('description', $map['description'], array('id' => 'description')), 'description');
    $form_container->output_row($lang->rpgmaps_map_width . ' (px)', '', $form->generate_numeric_field('width', $map['width'], array('id' => 'width', 'min' => 100)), 'width');
    $form_container->output_row($lang->rpgmaps_map_height . ' (px)', '', $form->generate_numeric_field('height', $map['height'], array('id' => 'height', 'min' => 100)), 'height');
    $form_container->output_row('Skalierungsfaktor', '', $form->generate_numeric_field('scale_factor', $map['scale_factor'], array('id' => 'scale_factor', 'step' => '0.1')), 'scale_factor');
    $form_container->output_row('Neues Kartenbild (optional)', 'Lasse dies leer, um das aktuelle Bild beizubehalten', $form->generate_file_upload_box('map_image', array('id' => 'map_image')), 'map_image');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->rpgmaps_save);
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Delete map
 */
function rpgmaps_admin_maps_delete()
{
    global $mybb, $db, $lang;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $map_id = $mybb->get_input('id', MyBB::INPUT_INT);
    
    if ($mybb->request_method == 'post') {
        $db_helper->deleteMap($map_id);
        flash_message($lang->rpgmaps_map_deleted, 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    } else {
        $page->output_confirm_action('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps_delete&id=' . $map_id, $lang->rpgmaps_confirm_delete);
    }
}

/**
 * Build plots management
 */
function rpgmaps_admin_buildplots()
{
    global $mybb, $db, $lang, $page;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $map_id = $mybb->get_input('map_id', MyBB::INPUT_INT);
    $map = $db_helper->getMapById($map_id);
    
    if (!$map) {
        flash_message('Map not found', 'error');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    $page->add_breadcrumb_item('Bauplätze - ' . htmlspecialchars_uni($map['title']));
    $page->output_header($lang->rpgmaps . ' - Bauplätze');
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'maps');

    $table = new Table;
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots_add&map_id=' . (int)$map['id'] . '">Neuen Bauplatz hinzufügen</a>');
    $table->construct_row();
    $table->output('Actions');
    
    $plots = $db_helper->getPlotsByMap($map_id);

    if (!empty($plots)) {
        usort($plots, function ($a, $b) {
            $a_created = isset($a['created_at']) ? (int)$a['created_at'] : 0;
            $b_created = isset($b['created_at']) ? (int)$b['created_at'] : 0;

            if ($a_created !== $b_created) {
                return ($a_created < $b_created) ? 1 : -1;
            }

            $a_id = isset($a['id']) ? (int)$a['id'] : 0;
            $b_id = isset($b['id']) ? (int)$b['id'] : 0;

            if ($a_id === $b_id) {
                return 0;
            }

            return ($a_id < $b_id) ? 1 : -1;
        });
    }
    
    $form_container = new FormContainer('Bauplätze - ' . htmlspecialchars_uni($map['title']));
    $form_container->output_row_header('Key');
    $form_container->output_row_header('Position');
    $form_container->output_row_header('Status');
    $form_container->output_row_header('Actions');
    
    if (empty($plots)) {
        $form_container->output_cell('Keine Bauplätze vorhanden', array('colspan' => 4));
        $form_container->construct_row();
    } else {
        foreach ($plots as $plot) {
            $form_container->output_cell(htmlspecialchars_uni($plot['plot_key']));
            $form_container->output_cell('(' . (int)$plot['x'] . ',' . (int)$plot['y'] . ') ' . (int)$plot['w'] . 'x' . (int)$plot['h']);
            $form_container->output_cell(htmlspecialchars_uni($plot['status']));
            
            $popup = new PopupMenu('plot_' . $plot['id'], 'Options');
            $popup->add_item('Edit', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots_edit&id=' . (int)$plot['id']);
            $popup->add_item('Delete', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots_delete&id=' . (int)$plot['id'], 'return confirm(\'' . $lang->rpgmaps_confirm_delete . '\');');
            
            $form_container->output_cell($popup->fetch());
            $form_container->construct_row();
        }
    }
    
    $form_container->end();
    
    $page->output_footer();
}

/**
 * Add build plot
 */
function rpgmaps_admin_buildplots_add()
{
    global $mybb, $db, $lang, $page;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $map_id = $mybb->get_input('map_id', MyBB::INPUT_INT);
    $map = $db_helper->getMapById($map_id);
    
    if (!$map) {
        flash_message('Map not found', 'error');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    $page->add_breadcrumb_item('Bauplätze', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . $map_id);
    $page->add_breadcrumb_item('Neuen Bauplatz hinzufügen');
    $page->output_header($lang->rpgmaps . ' - Neuen Bauplatz hinzufügen');
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'maps');
    
    if ($mybb->request_method == 'post') {
        $plot_key = trim($mybb->get_input('plot_key'));
        
        // Auto-generate plot_key if not provided
        if (empty($plot_key)) {
            // Generate unique plot_key: use timestamp + random number
            $plot_key = 'plot_' . time() . '_' . mt_rand(1000, 9999);
        }
        
        // Get and normalize rotation value (0-360)
        $rotation = $mybb->get_input('rotation', MyBB::INPUT_INT);
        $rotation = $rotation % 360;
        if ($rotation < 0) {
            $rotation += 360;
        }
        
        $plot_data = array(
            'map_id' => $map_id,
            'plot_key' => $plot_key,
            'x' => $mybb->get_input('x', MyBB::INPUT_INT),
            'y' => $mybb->get_input('y', MyBB::INPUT_INT),
            'w' => $mybb->get_input('w', MyBB::INPUT_INT),
            'h' => $mybb->get_input('h', MyBB::INPUT_INT),
            'rotation' => $rotation,
            'tooltip_text' => $mybb->get_input('tooltip_text'),
            'status' => 'free'
        );
        
        $plot_id = $db_helper->insertPlot($plot_data);
        
        flash_message('Bauplatz wurde erfolgreich hinzugefügt', 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . $map_id);
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps', 'post');
    echo $form->generate_hidden_field('sub', 'buildplots_add');
    echo $form->generate_hidden_field('map_id', $map_id);
    
    $form_container = new FormContainer('Neuen Bauplatz hinzufügen');
    $form_container->output_row('Plot Key <em>(Optional)</em>', 'Wird automatisch generiert, wenn leer gelassen', $form->generate_text_box('plot_key', '', array('id' => 'plot_key', 'placeholder' => 'z.B. plot_1 oder leer lassen für Auto-Generierung')), 'plot_key');
    $form_container->output_row('X-Koordinate', '', $form->generate_numeric_field('x', '0', array('id' => 'x', 'min' => 0)), 'x');
    $form_container->output_row('Y-Koordinate', '', $form->generate_numeric_field('y', '0', array('id' => 'y', 'min' => 0)), 'y');
    $form_container->output_row('Breite', '', $form->generate_numeric_field('w', '50', array('id' => 'w', 'min' => 10)), 'w');
    $form_container->output_row('Höhe', '', $form->generate_numeric_field('h', '50', array('id' => 'h', 'min' => 10)), 'h');
    $form_container->output_row('Rotation (Grad)', 'Winkel zwischen 0 und 360 Grad', $form->generate_numeric_field('rotation', '0', array('id' => 'rotation', 'min' => 0, 'max' => 360)), 'rotation');
    $form_container->output_row('Tooltip-Text', '', $form->generate_text_box('tooltip_text', '', array('id' => 'tooltip_text')), 'tooltip_text');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button('Speichern');
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Edit build plot
 */
function rpgmaps_admin_buildplots_edit()
{
    global $mybb, $db, $lang, $page;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $plot_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $plot = $db_helper->getPlotById($plot_id);
    
    if (!$plot) {
        flash_message('Plot not found', 'error');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    }
    
    $map = $db_helper->getMapById($plot['map_id']);
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_maps, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps');
    $page->add_breadcrumb_item('Bauplätze', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . $plot['map_id']);
    $page->add_breadcrumb_item('Bauplatz bearbeiten');
    $page->output_header($lang->rpgmaps . ' - Bauplatz bearbeiten');
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'maps');
    
    if ($mybb->request_method == 'post') {
        // Get and normalize rotation value (0-360)
        $rotation = $mybb->get_input('rotation', MyBB::INPUT_INT);
        $rotation = $rotation % 360;
        if ($rotation < 0) {
            $rotation += 360;
        }
        
        $plot_data = array(
            'plot_key' => $mybb->get_input('plot_key'),
            'x' => $mybb->get_input('x', MyBB::INPUT_INT),
            'y' => $mybb->get_input('y', MyBB::INPUT_INT),
            'w' => $mybb->get_input('w', MyBB::INPUT_INT),
            'h' => $mybb->get_input('h', MyBB::INPUT_INT),
            'rotation' => $rotation,
            'tooltip_text' => $mybb->get_input('tooltip_text')
        );
        
        $db_helper->updatePlot($plot_id, $plot_data);
        
        flash_message('Bauplatz wurde erfolgreich aktualisiert', 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . $plot['map_id']);
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots_edit&id=' . $plot_id, 'post');
    
    $form_container = new FormContainer('Bauplatz bearbeiten');
    $form_container->output_row('Plot Key', '', $form->generate_text_box('plot_key', $plot['plot_key'], array('id' => 'plot_key')), 'plot_key');
    $form_container->output_row('X-Koordinate', '', $form->generate_numeric_field('x', $plot['x'], array('id' => 'x', 'min' => 0)), 'x');
    $form_container->output_row('Y-Koordinate', '', $form->generate_numeric_field('y', $plot['y'], array('id' => 'y', 'min' => 0)), 'y');
    $form_container->output_row('Breite', '', $form->generate_numeric_field('w', $plot['w'], array('id' => 'w', 'min' => 10)), 'w');
    $form_container->output_row('Höhe', '', $form->generate_numeric_field('h', $plot['h'], array('id' => 'h', 'min' => 10)), 'h');
    $form_container->output_row('Rotation (Grad)', 'Winkel zwischen 0 und 360 Grad', $form->generate_numeric_field('rotation', isset($plot['rotation']) ? $plot['rotation'] : 0, array('id' => 'rotation', 'min' => 0, 'max' => 360)), 'rotation');
    $form_container->output_row('Tooltip-Text', '', $form->generate_text_box('tooltip_text', $plot['tooltip_text'], array('id' => 'tooltip_text')), 'tooltip_text');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button('Speichern');
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Delete build plot
 */
function rpgmaps_admin_buildplots_delete()
{
    global $mybb, $db, $lang;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $plot_id = (int)$mybb->input['id'];
    $plot = $db_helper->getPlotById($plot_id);
    
    if (!$plot) {
        echo 'Plot not found';
        return;
    }
    
    $db_helper->deletePlot($plot_id);
    
    echo '<p>' . $lang->rpgmaps_success . '</p>';
    echo '<p><a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=buildplots&map_id=' . (int)$plot['map_id'] . '">Back to Plots</a></p>';
}

/**
 * House types management
 */
function rpgmaps_admin_house_types()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_house_types);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_manage_house_types);
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'house_types');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $house_types = $db_helper->getHouseTypes();
    
    $form_container = new FormContainer($lang->rpgmaps_manage_house_types);
    $form_container->output_row_header('Name');
    $form_container->output_row_header('Created');
    $form_container->output_row_header('Actions');
    
    if (empty($house_types)) {
        $form_container->output_cell($lang->rpgmaps_no_house_types, array('colspan' => 3));
        $form_container->construct_row();
    } else {
        foreach ($house_types as $type) {
            $form_container->output_cell(htmlspecialchars_uni($type['name']));
            $form_container->output_cell(date('Y-m-d', $type['created_at']));
            
            $popup = new PopupMenu('type_' . $type['id'], 'Options');
            $popup->add_item('Edit', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_edit&id=' . (int)$type['id']);
            $popup->add_item('Delete', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_delete&id=' . (int)$type['id'], 'return confirm(\'' . $lang->rpgmaps_confirm_delete . '\');');
            
            $form_container->output_cell($popup->fetch());
            $form_container->construct_row();
        }
    }
    
    $form_container->end();
    
    $table = new Table;
    $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_add">' . $lang->rpgmaps_add_house_type . '</a>');
    $table->construct_row();
    $table->output('Actions');
    
    $page->output_footer();
}

/**
 * Add house type
 */
function rpgmaps_admin_house_types_add()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_house_types, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types');
    $page->add_breadcrumb_item($lang->rpgmaps_add_house_type);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_add_house_type);
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'house_types');
    
    if ($mybb->request_method == 'post') {
        require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
        $db_helper = new RPGMapsDatabase();
        
        // Handle file upload
        $filename = '';
        if (isset($_FILES['house_image']) && $_FILES['house_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/houses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['house_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = array('png', 'jpg', 'jpeg', 'gif');
            
            if (in_array($file_ext, $allowed_exts)) {
                $filename = 'house_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['house_image']['tmp_name'], $target_path)) {
                    // File uploaded successfully
                } else {
                    flash_message('Fehler beim Hochladen der Datei', 'error');
                    admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_add');
                }
            } else {
                flash_message('Ungültiges Dateiformat. Nur PNG, JPG und GIF erlaubt.', 'error');
                admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_add');
            }
        } else {
            // No file uploaded, use placeholder
            $filename = 'placeholder.png';
        }
        
        $type_data = array(
            'name' => $mybb->get_input('name'),
            'description' => $mybb->get_input('description'),
            'asset_filename' => $filename,
            'icon_scale' => $mybb->get_input('icon_scale', MyBB::INPUT_FLOAT)
        );
        
        $type_id = $db_helper->createHouseType($type_data);
        
        flash_message($lang->rpgmaps_house_type_added, 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types');
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps', 'post', '', 1);
    echo $form->generate_hidden_field('sub', 'house_types_add');
    
    $form_container = new FormContainer($lang->rpgmaps_add_house_type);
    $form_container->output_row('Name', '', $form->generate_text_box('name', '', array('id' => 'name')), 'name');
    $form_container->output_row($lang->rpgmaps_house_type_description, '', $form->generate_text_area('description', '', array('id' => 'description')), 'description');
    $form_container->output_row('Bilddatei', '', $form->generate_file_upload_box('house_image', array('id' => 'house_image')), 'house_image');
    $form_container->output_row('Icon-Skalierung', '', $form->generate_numeric_field('icon_scale', '1.0', array('id' => 'icon_scale', 'step' => '0.1')), 'icon_scale');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->rpgmaps_save);
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Edit house type
 */
function rpgmaps_admin_house_types_edit()
{
    global $mybb, $db, $lang, $page;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $type_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $type = $db_helper->getHouseTypeById($type_id);
    
    if (!$type) {
        flash_message('House type not found', 'error');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types');
    }
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item($lang->rpgmaps_manage_house_types, 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types');
    $page->add_breadcrumb_item($lang->rpgmaps_edit_house_type);
    $page->output_header($lang->rpgmaps . ' - ' . $lang->rpgmaps_edit_house_type);
    
    $sub_tabs = array(
        'overview' => array('title' => 'Overview', 'link' => 'index.php?module=tools-rpgmaps'),
        'maps' => array('title' => 'Manage Maps', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps'),
        'house_types' => array('title' => 'Manage House Types', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types'),
        'pending_actions' => array('title' => 'View Pending Actions', 'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions')
    );
    $page->output_nav_tabs($sub_tabs, 'house_types');
    
    if ($mybb->request_method == 'post') {
        // Handle file upload if provided
        $filename = $type['asset_filename']; // Keep existing filename by default
        
        if (isset($_FILES['house_image']) && $_FILES['house_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = MYBB_ROOT . 'inc/plugins/rpgmaps/assets/houses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['house_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = array('png', 'jpg', 'jpeg', 'gif');
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'house_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['house_image']['tmp_name'], $target_path)) {
                    // Delete old file if it exists and is not placeholder
                    if ($filename != 'placeholder.png' && file_exists($upload_dir . $filename)) {
                        @unlink($upload_dir . $filename);
                    }
                    $filename = $new_filename;
                } else {
                    flash_message('Fehler beim Hochladen der Datei', 'error');
                    admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_edit&id=' . $type_id);
                }
            } else {
                flash_message('Ungültiges Dateiformat. Nur PNG, JPG und GIF erlaubt.', 'error');
                admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_edit&id=' . $type_id);
            }
        }
        
        $type_data = array(
            'name' => $mybb->get_input('name'),
            'description' => $mybb->get_input('description'),
            'icon_scale' => $mybb->get_input('icon_scale', MyBB::INPUT_FLOAT),
            'asset_filename' => $filename
        );
        
        $db_helper->updateHouseType($type_id, $type_data);
        
        flash_message($lang->rpgmaps_house_type_updated, 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types');
    }
    
    $form = new Form('index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types_edit&id=' . $type_id, 'post', '', 1);
    
    $form_container = new FormContainer($lang->rpgmaps_edit_house_type);
    
    // Show current house image if exists
    if (!empty($type['asset_filename']) && $type['asset_filename'] != 'placeholder.png') {
        $current_image = '<img src="' . $mybb->settings['bburl'] . '/inc/plugins/rpgmaps/assets/houses/' . htmlspecialchars_uni($type['asset_filename']) . '" alt="Current House" class="rpgmaps-house-type-image">';
        $form_container->output_row('Aktuelles Hausbild', '', $current_image);
    }
    
    $form_container->output_row('Name', '', $form->generate_text_box('name', $type['name'], array('id' => 'name')), 'name');
    $form_container->output_row($lang->rpgmaps_house_type_description, '', $form->generate_text_area('description', $type['description'], array('id' => 'description')), 'description');
    $form_container->output_row('Icon-Skalierung', '', $form->generate_numeric_field('icon_scale', $type['icon_scale'], array('id' => 'icon_scale', 'step' => '0.1')), 'icon_scale');
    $form_container->output_row('Neues Hausbild (optional)', 'Lasse dies leer, um das aktuelle Bild beizubehalten', $form->generate_file_upload_box('house_image', array('id' => 'house_image')), 'house_image');
    $form_container->end();
    
    $buttons[] = $form->generate_submit_button($lang->rpgmaps_save);
    $form->output_submit_wrapper($buttons);
    $form->end();
    
    $page->output_footer();
}

/**
 * Delete house type
 */
function rpgmaps_admin_house_types_delete()
{
    global $mybb, $db, $lang;
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $type_id = (int)$mybb->input['id'];
    
    $success = $db_helper->deleteHouseType($type_id);
    
    if ($success) {
        echo '<p>' . $lang->rpgmaps_success . '</p>';
    } else {
        echo '<p>Cannot delete: Houses depend on this type.</p>';
    }
    
    echo '<p><a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types">Back to House Types</a></p>';
}

/**
 * Pending actions management
 */
function rpgmaps_admin_pending_actions()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item('Pending Actions');
    $page->output_header($lang->rpgmaps . ' - Pending Actions');
    
    $sub_tabs = array(
        'overview' => array(
            'title' => 'Overview',
            'link' => 'index.php?module=tools-rpgmaps',
        ),
        'maps' => array(
            'title' => 'Manage Maps',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=maps',
        ),
        'house_types' => array(
            'title' => 'Manage House Types',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=house_types',
        ),
        'pending_actions' => array(
            'title' => 'View Pending Actions',
            'link' => 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions',
        )
    );
    
    $page->output_nav_tabs($sub_tabs, 'pending_actions');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $actions = $db_helper->getPendingActions();
    
    if (empty($actions)) {
        $table = new Table;
        $table->construct_cell('No pending actions.');
        $table->construct_row();
        $table->output('Pending Actions');
    } else {
        $table = new Table;
        $table->construct_header('Type');
        $table->construct_header('User');
        $table->construct_header('Target');
        $table->construct_header('Created');
        $table->construct_header('Actions', array('class' => 'align_center', 'width' => '20%'));
        
        foreach ($actions as $action) {
            // Get user info
            $user_query = $db->simple_select('users', 'username', 'uid = ' . (int)$action['user_id']);
            $user = $db->fetch_array($user_query);
            
            $table->construct_cell(htmlspecialchars_uni($action['action_type']));
            $table->construct_cell(htmlspecialchars_uni($user['username']));
            $table->construct_cell((int)$action['target_id']);
            $table->construct_cell(date('Y-m-d H:i', $action['created_at']));
            
            $popup = new PopupMenu('action_' . $action['id'], 'Options');
            $popup->add_item('Approve', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=action_approve&id=' . (int)$action['id']);
            $popup->add_item('Reject', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=action_reject&id=' . (int)$action['id']);
            $table->construct_cell($popup->fetch(), array('class' => 'align_center'));
            
            $table->construct_row();
        }
        
        $table->output('Pending Actions');
    }
    
    $page->output_footer();
}

/**
 * Approve action
 */
function rpgmaps_admin_action_approve()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item('Pending Actions', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions');
    $page->add_breadcrumb_item('Approve Action');
    $page->output_header($lang->rpgmaps . ' - Approve Action');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $action_id = (int)$mybb->input['id'];
    $admin_note = isset($mybb->input['note']) ? $mybb->input['note'] : '';
    
    $result = $manager->approveBuildRequest($action_id, $mybb->user['uid'], $admin_note);
    
    if ($result['success']) {
        flash_message('Action approved successfully!', 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions');
    } else {
        $table = new Table;
        $table->construct_cell('<strong>Error:</strong> ' . htmlspecialchars_uni($result['message']));
        $table->construct_row();
        $table->output('Error');
        
        $table = new Table;
        $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions">Back to Pending Actions</a>');
        $table->construct_row();
        $table->output('Actions');
    }
    
    $page->output_footer();
}

/**
 * Reject action
 */
function rpgmaps_admin_action_reject()
{
    global $mybb, $db, $lang, $page;
    
    $page->add_breadcrumb_item($lang->rpgmaps, 'index.php?module=tools-rpgmaps');
    $page->add_breadcrumb_item('Pending Actions', 'index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions');
    $page->add_breadcrumb_item('Reject Action');
    $page->output_header($lang->rpgmaps . ' - Reject Action');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $action_id = (int)$mybb->input['id'];
    $admin_note = isset($mybb->input['note']) ? $mybb->input['note'] : '';
    
    $result = $manager->rejectAction($action_id, $mybb->user['uid'], $admin_note);
    
    if ($result['success']) {
        flash_message('Action rejected successfully!', 'success');
        admin_redirect('index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions');
    } else {
        $table = new Table;
        $table->construct_cell('<strong>Error:</strong> ' . htmlspecialchars_uni($result['message']));
        $table->construct_row();
        $table->output('Error');
        
        $table = new Table;
        $table->construct_cell('<a href="index.php?module=tools-rpgmaps&action=rpgmaps&sub=pending_actions">Back to Pending Actions</a>');
        $table->construct_row();
        $table->output('Actions');
    }
    
    $page->output_footer();
}
