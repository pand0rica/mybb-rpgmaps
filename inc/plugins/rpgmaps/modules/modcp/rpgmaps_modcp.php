<?php
/**
 * RPG Maps Plugin - ModCP Module
 * Handles pending actions in the Moderator Control Panel
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Main ModCP handler for RPG Maps
 */
function rpgmaps_modcp_main()
{
    global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;
    
    // Check if user can access ModCP
    if ($mybb->usergroup['canmodcp'] != 1) {
        error_no_permission();
    }
    
    $lang->load('rpgmaps');
    $lang->load('modcp');
    
    // Get sub-action
    $sub = $mybb->get_input('sub', MyBB::INPUT_STRING);
    
    switch ($sub) {
        case 'approve':
            rpgmaps_modcp_approve();
            break;
        case 'reject':
            rpgmaps_modcp_reject();
            break;
        default:
            rpgmaps_modcp_pending_list();
            break;
    }
}

/**
 * Show pending actions list
 */
function rpgmaps_modcp_pending_list()
{
    global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;
    
    add_breadcrumb($lang->modcp, 'modcp.php');
    add_breadcrumb('RPG Maps', 'modcp.php?action=rpgmaps');
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $actions = $db_helper->getPendingActions();
    
    $action_rows = '';
    
    if (empty($actions)) {
        $action_rows = '<tr><td colspan="5" class="trow1 rpgmaps-no-actions">Keine ausstehenden Anfragen.</td></tr>';
    } else {
        foreach ($actions as $action) {
            // Get user info
            $user = get_user($action['user_id']);
            $username = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
            $user_link = build_profile_link($username, $user['uid']);
            
            // Get plot info
            $plot = $db_helper->getPlotById($action['target_id']);
            $map = $db_helper->getMapById($plot['map_id']);
            
            $action_type = htmlspecialchars_uni($action['action_type']);
            $created_date = my_date('relative', $action['created_at']);
            
            $approve_link = 'modcp.php?action=rpgmaps&sub=approve&id=' . (int)$action['id'] . '&my_post_key=' . $mybb->post_code;
            $reject_link = 'modcp.php?action=rpgmaps&sub=reject&id=' . (int)$action['id'] . '&my_post_key=' . $mybb->post_code;
            
            $action_rows .= '<tr>
                <td class="trow1">' . $action_type . '</td>
                <td class="trow1">' . $user_link . '</td>
                <td class="trow1">' . htmlspecialchars_uni($map['title']) . ' - Plot ' . htmlspecialchars_uni($plot['plot_key']) . '</td>
                <td class="trow1">' . $created_date . '</td>
                <td class="trow1 rpgmaps-action-buttons">
                    <a href="' . $approve_link . '" class="button rpgmaps-approve-btn">Genehmigen</a> 
                    <a href="' . $reject_link . '" class="button rpgmaps-reject-btn">Ablehnen</a>
                </td>
            </tr>';
        }
    }
    
    eval('$content = "' . $templates->get('modcp_rpgmaps_pending') . '";');
    
    output_page($content);
}

/**
 * Approve an action
 */
function rpgmaps_modcp_approve()
{
    global $mybb, $db, $lang;
    
    verify_post_check($mybb->get_input('my_post_key'));
    
    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $result = $manager->approveBuildRequest($action_id, $mybb->user['uid'], '');
    
    if ($result['success']) {
        redirect('modcp.php?action=rpgmaps', 'Bauantrag wurde erfolgreich genehmigt!');
    } else {
        error('Fehler: ' . $result['message']);
    }
}

/**
 * Reject an action
 */
function rpgmaps_modcp_reject()
{
    global $mybb, $db, $lang;
    
    verify_post_check($mybb->get_input('my_post_key'));
    
    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $result = $manager->rejectAction($action_id, $mybb->user['uid'], '');
    
    if ($result['success']) {
        redirect('modcp.php?action=rpgmaps', 'Bauantrag wurde abgelehnt.');
    } else {
        error('Fehler: ' . $result['message']);
    }
}
