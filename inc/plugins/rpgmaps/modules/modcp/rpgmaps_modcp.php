<?php
/**
 * RPG Maps – ModCP-Modul
 * Offene Bauanträge im Moderations-CP genehmigen oder ablehnen.
 *
 * @package rpgmaps
 */

if (!defined('IN_MYBB')) {
    exit;
}

/**
 * Einstieg: modcp.php?action=rpgmaps
 */
function rpgmaps_modcp_main()
{
    global $mybb, $lang;

    if ((int)($mybb->usergroup['canmodcp'] ?? 0) !== 1) {
        error_no_permission();
    }

    $lang->load('rpgmaps');
    $lang->load('modcp');

    $sub = $mybb->get_input('sub');
    if ($sub === 'approve' || $sub === 'reject') {
        rpgmaps_modcp_decide($sub === 'approve');
        return;
    }

    rpgmaps_modcp_pending_list();
}

/**
 * Liste der offenen Bauanträge
 */
function rpgmaps_modcp_pending_list()
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;

    add_breadcrumb($lang->nav_modcp, 'modcp.php');
    add_breadcrumb($lang->rpgmaps_modcp_nav, 'modcp.php?action=rpgmaps');

    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
    $db_helper = new RPGMapsDatabase();

    $actions = $db_helper->getPendingActions('build');
    $action_rows = '';

    if (empty($actions)) {
        $action_rows = '<tr><td colspan="5" class="trow1 rpgmaps-no-actions">' . $lang->rpgmaps_modcp_none . '</td></tr>';
    }

    $post_key = htmlspecialchars_uni($mybb->post_code);
    $approve_label = htmlspecialchars_uni($lang->rpgmaps_modcp_approve);
    $reject_label = htmlspecialchars_uni($lang->rpgmaps_modcp_reject);
    $alt = 'trow1';

    foreach ($actions as $action) {
        $id = (int)$action['id'];

        $user = get_user((int)$action['user_id']);
        $user_link = $user
            ? build_profile_link(format_name(htmlspecialchars_uni($user['username']), (int)$user['usergroup'], (int)$user['displaygroup']), (int)$user['uid'])
            : $lang->rpgmaps_deleted_account;

        $plot = $db_helper->getPlotById((int)$action['target_id']);
        $map = $plot ? $db_helper->getMapById((int)$plot['map_id']) : null;
        $location = ($plot && $map)
            ? $lang->sprintf($lang->rpgmaps_modcp_location_value, htmlspecialchars_uni($map['title']), htmlspecialchars_uni($plot['plot_key']))
            : '&ndash;';

        $type_key = 'rpgmaps_action_' . $action['action_type'];
        $type = isset($lang->$type_key) ? $lang->$type_key : htmlspecialchars_uni($action['action_type']);

        $action_rows .= '<tr>
            <td class="' . $alt . '">' . $type . '</td>
            <td class="' . $alt . '">' . $user_link . '</td>
            <td class="' . $alt . '">' . $location . '</td>
            <td class="' . $alt . '">' . my_date('relative', (int)$action['created_at']) . '</td>
            <td class="' . $alt . ' rpgmaps-action-buttons">
                <form action="modcp.php" method="post" class="rpgmaps_inline_form">
                    <input type="hidden" name="action" value="rpgmaps" />
                    <input type="hidden" name="sub" value="approve" />
                    <input type="hidden" name="id" value="' . $id . '" />
                    <input type="hidden" name="my_post_key" value="' . $post_key . '" />
                    <input type="submit" class="button" value="' . $approve_label . '" />
                </form>
                <form action="modcp.php" method="post" class="rpgmaps_inline_form">
                    <input type="hidden" name="action" value="rpgmaps" />
                    <input type="hidden" name="sub" value="reject" />
                    <input type="hidden" name="id" value="' . $id . '" />
                    <input type="hidden" name="my_post_key" value="' . $post_key . '" />
                    <input type="submit" class="button" value="' . $reject_label . '" />
                </form>
            </td>
        </tr>';

        $alt = alt_trow();
    }

    eval('$content = "' . $templates->get('rpgmaps_modcp_pending') . '";');
    output_page($content);
}

/**
 * Antrag genehmigen oder ablehnen (nur POST mit my_post_key)
 *
 * @param bool $approve
 */
function rpgmaps_modcp_decide($approve)
{
    global $mybb;

    if ($mybb->request_method !== 'post') {
        error_no_permission();
    }

    verify_post_check($mybb->get_input('my_post_key'));

    require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';
    $manager = new RPGMapsManager();

    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $result = $approve
        ? $manager->approveBuildRequest($action_id, (int)$mybb->user['uid'], '')
        : $manager->rejectAction($action_id, (int)$mybb->user['uid'], '');

    if (empty($result['success'])) {
        error(htmlspecialchars_uni((string)$result['message']));
    }

    redirect('modcp.php?action=rpgmaps', htmlspecialchars_uni((string)$result['message']));
}
