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
    add_breadcrumb($lang->rpgmaps_name, 'modcp.php?action=rpgmaps');

    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();
    
    $actions = $db_helper->getPendingActions();
    
    $action_rows = '';
    
    if (empty($actions)) {
        $action_rows = '<tr><td colspan="5" class="trow1 rpgmaps-no-actions">' . $lang->rpgmaps_modcp_no_actions . '</td></tr>';
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
            
            $action_id = (int)$action['id'];
            $decision_form = function (string $sub, string $label) use ($action_id, $mybb): string {
                return '<form method="post" action="modcp.php" class="rpgmaps-inline-form">'
                    . '<input type="hidden" name="action" value="rpgmaps" />'
                    . '<input type="hidden" name="sub" value="' . $sub . '" />'
                    . '<input type="hidden" name="id" value="' . $action_id . '" />'
                    . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
                    . '<input type="submit" class="button" value="' . htmlspecialchars_uni($label) . '" /></form>';
            };
            
            $action_rows .= '<tr>
                <td class="trow1">' . $action_type . '</td>
                <td class="trow1">' . $user_link . '</td>
                <td class="trow1">' . htmlspecialchars_uni($map['title']) . ' - ' . $lang->sprintf($lang->rpgmaps_modcp_plot, htmlspecialchars_uni($plot['plot_key'])) . '</td>
                <td class="trow1">' . $created_date . '</td>
                <td class="trow1 rpgmaps-action-buttons">
                    ' . $decision_form('approve', $lang->rpgmaps_admin_approve) . '
                    ' . $decision_form('reject', $lang->rpgmaps_admin_reject) . '
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
    
    // Schreibend nur per POST (debugging.md #3); verify_post_check() bricht im Frontend selbst ab.
    if ($mybb->request_method !== 'post') {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));
    
    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $result = $manager->approveBuildRequest($action_id, $mybb->user['uid'], '');
    
    if ($result['success']) {
        redirect('modcp.php?action=rpgmaps', $lang->rpgmaps_modcp_approved);
    } else {
        error($lang->sprintf($lang->rpgmaps_modcp_error, htmlspecialchars_uni((string)$result['message'])));
    }
}

/**
 * Reject an action
 */
function rpgmaps_modcp_reject()
{
    global $mybb, $db, $lang;
    
    // Schreibend nur per POST (debugging.md #3); verify_post_check() bricht im Frontend selbst ab.
    if ($mybb->request_method !== 'post') {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));
    
    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    
    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();
    
    $result = $manager->rejectAction($action_id, $mybb->user['uid'], '');
    
    if ($result['success']) {
        redirect('modcp.php?action=rpgmaps', $lang->rpgmaps_modcp_rejected);
    } else {
        error($lang->sprintf($lang->rpgmaps_modcp_error, htmlspecialchars_uni((string)$result['message'])));
    }
}
