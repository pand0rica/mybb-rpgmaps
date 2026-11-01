<?php
/**
 * RPG Maps – ACP-Modul
 * Verwaltung von Karten, Bauplätzen, Haustypen und offenen Bauanträgen.
 *
 * @package rpgmaps
 */

if (!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Direct initialization of this file is not allowed.');
}

global $mybb, $lang;

$lang->load('rpgmaps');

require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/database.php';
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/security.php';
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/core/rpgmaps.class.php';

define('RPGMAPS_ACP_URL', 'index.php?module=tools-rpgmaps');

switch ($mybb->get_input('sub')) {
    case 'maps':               rpgmaps_admin_maps(); break;
    case 'maps_add':           rpgmaps_admin_maps_form(false); break;
    case 'maps_edit':          rpgmaps_admin_maps_form(true); break;
    case 'maps_delete':        rpgmaps_admin_maps_delete(); break;
    case 'buildplots':         rpgmaps_admin_buildplots(); break;
    case 'buildplots_add':     rpgmaps_admin_buildplots_form(false); break;
    case 'buildplots_edit':    rpgmaps_admin_buildplots_form(true); break;
    case 'buildplots_delete':  rpgmaps_admin_buildplots_delete(); break;
    case 'house_types':        rpgmaps_admin_house_types(); break;
    case 'house_types_add':    rpgmaps_admin_house_types_form(false); break;
    case 'house_types_edit':   rpgmaps_admin_house_types_form(true); break;
    case 'house_types_delete': rpgmaps_admin_house_types_delete(); break;
    case 'pending_actions':    rpgmaps_admin_pending_actions(); break;
    case 'action_approve':     rpgmaps_admin_action_decide(true); break;
    case 'action_reject':      rpgmaps_admin_action_decide(false); break;
    default:                   rpgmaps_admin_overview();
}

// ============ HELPER ============

/**
 * URL zu einer Unterseite des Moduls.
 *
 * @param string $sub
 * @param array  $params
 * @return string
 */
function rpgmaps_acp_url($sub = '', array $params = [])
{
    $url = RPGMAPS_ACP_URL;
    if ($sub !== '') {
        $url .= '&action=rpgmaps&sub=' . $sub;
    }
    foreach ($params as $key => $value) {
        $url .= '&' . $key . '=' . urlencode((string)$value);
    }

    return $url;
}

/**
 * Prüft den my_post_key im ACP. verify_post_check() bricht im ACP nicht selbst ab
 * (gibt nur false zurück), daher hier explizit umleiten (debugging.md #23).
 */
function rpgmaps_acp_verify_key()
{
    global $mybb, $lang;

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message($lang->rpgmaps_invalid_key, 'error');
        admin_redirect(rpgmaps_acp_url());
    }
}

/**
 * Seitenkopf mit Breadcrumb und Navigations-Tabs ausgeben.
 *
 * @param string $title       Seitentitel
 * @param string $active_tab  overview|maps|house_types|pending_actions
 * @param array  $breadcrumbs [Titel => URL]; letzte URL darf leer sein
 */
function rpgmaps_acp_header($title, $active_tab, array $breadcrumbs = [])
{
    global $page, $lang;

    $page->add_breadcrumb_item($lang->rpgmaps, RPGMAPS_ACP_URL);
    foreach ($breadcrumbs as $crumb => $url) {
        $page->add_breadcrumb_item($crumb, $url);
    }
    $page->output_header($lang->rpgmaps . ' – ' . $title);

    $page->output_nav_tabs([
        'overview' => [
            'title'       => $lang->rpgmaps_tab_overview,
            'link'        => rpgmaps_acp_url(),
            'description' => $lang->rpgmaps_tab_overview_desc,
        ],
        'maps' => [
            'title'       => $lang->rpgmaps_manage_maps,
            'link'        => rpgmaps_acp_url('maps'),
            'description' => $lang->rpgmaps_tab_maps_desc,
        ],
        'house_types' => [
            'title'       => $lang->rpgmaps_manage_house_types,
            'link'        => rpgmaps_acp_url('house_types'),
            'description' => $lang->rpgmaps_tab_house_types_desc,
        ],
        'pending_actions' => [
            'title'       => $lang->rpgmaps_manage_actions,
            'link'        => rpgmaps_acp_url('pending_actions'),
            'description' => $lang->rpgmaps_tab_actions_desc,
        ],
    ], $active_tab);
}

/**
 * Bild-Upload über RPGMapsAsset (prüft Endung, MIME-Typ und Größe laut Settings).
 * Ohne Upload bleibt der bisherige Dateiname erhalten. Ein ersetztes Bild wird gelöscht.
 *
 * @param string $field     Name des Dateifelds
 * @param string $type      'map' oder 'house'
 * @param string $current   bisheriger Dateiname (bzw. placeholder.png)
 * @param string $error_url Ziel bei Fehler
 * @return string Dateiname
 */
function rpgmaps_acp_handle_upload($field, $type, $current, $error_url)
{
    global $lang;

    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || (int)$_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    if ((int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        flash_message($lang->rpgmaps_upload_failed, 'error');
        admin_redirect($error_url);
    }

    $result = $type === 'house'
        ? RPGMapsAsset::uploadHouseImage($_FILES[$field])
        : RPGMapsAsset::uploadMapImage($_FILES[$field]);

    if (empty($result['success'])) {
        flash_message($lang->sprintf($lang->rpgmaps_upload_invalid, htmlspecialchars_uni((string)$result['error'])), 'error');
        admin_redirect($error_url);
    }

    if ($current !== '' && $current !== 'placeholder.png') {
        RPGMapsAsset::deleteFile($current, $type);
    }

    return $result['filename'];
}

/**
 * Plot-Breite/-Höhe auf 1 … rpgmaps_max_plot_size begrenzen (0 = unbegrenzt).
 *
 * @param int $value
 * @return int
 */
function rpgmaps_acp_plot_size($value)
{
    global $mybb;

    $max = (int)($mybb->settings['rpgmaps_max_plot_size'] ?? 0);
    $value = max(1, (int)$value);

    return $max > 0 ? min($value, $max) : $value;
}

/**
 * Rotation auf 0–359 Grad normalisieren.
 *
 * @param int $rotation
 * @return int
 */
function rpgmaps_acp_rotation($rotation)
{
    $rotation = (int)$rotation % 360;

    return $rotation < 0 ? $rotation + 360 : $rotation;
}

/**
 * Popup-Menü „Optionen“ bauen.
 *
 * @param string $id
 * @param array  $items [Titel => URL] oder [Titel => [URL, onclick]]
 * @return string
 */
function rpgmaps_acp_popup($id, array $items)
{
    global $lang;

    $popup = new PopupMenu($id, $lang->rpgmaps_options);
    foreach ($items as $title => $target) {
        if (is_array($target)) {
            $popup->add_item($title, $target[0], $target[1]);
        } else {
            $popup->add_item($title, $target);
        }
    }

    return $popup->fetch();
}

/**
 * Text für ein JavaScript-confirm() in einem onclick-Attribut absichern.
 *
 * @param string $text
 * @return string
 */
function rpgmaps_acp_confirm_js($text)
{
    return 'return confirm(' . htmlspecialchars_uni(json_encode((string)$text)) . ');';
}

// ============ OVERVIEW ============

/**
 * Übersicht
 */
function rpgmaps_admin_overview()
{
    global $page, $lang;

    $db_helper = new RPGMapsDatabase();

    rpgmaps_acp_header($lang->rpgmaps_tab_overview, 'overview');

    $table = new Table;
    $table->construct_cell('<strong>' . $lang->rpgmaps_overview_maps . '</strong>', ['width' => '30%']);
    $table->construct_cell(count($db_helper->getMaps()));
    $table->construct_row();
    $table->construct_cell('<strong>' . $lang->rpgmaps_overview_house_types . '</strong>');
    $table->construct_cell(count($db_helper->getHouseTypes()));
    $table->construct_row();
    $table->construct_cell('<strong>' . $lang->rpgmaps_overview_pending . '</strong>');
    $table->construct_cell('<a href="' . rpgmaps_acp_url('pending_actions') . '">' . count($db_helper->getPendingActions()) . '</a>');
    $table->construct_row();
    $table->output($lang->rpgmaps_tab_overview);

    $page->output_footer();
}

// ============ MAPS ============

/**
 * Kartenliste
 */
function rpgmaps_admin_maps()
{
    global $page, $lang;

    $db_helper = new RPGMapsDatabase();

    rpgmaps_acp_header($lang->rpgmaps_manage_maps, 'maps', [$lang->rpgmaps_manage_maps => '']);

    $table = new Table;
    $table->construct_header($lang->rpgmaps_map_name);
    $table->construct_header($lang->rpgmaps_map_size, ['width' => '15%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_created, ['width' => '15%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_controls, ['width' => '10%', 'class' => 'align_center']);

    $maps = $db_helper->getMaps();
    if (empty($maps)) {
        $table->construct_cell($lang->rpgmaps_no_maps, ['colspan' => 4]);
        $table->construct_row();
    }

    foreach ($maps as $map) {
        $id = (int)$map['id'];
        $table->construct_cell('<a href="' . rpgmaps_acp_url('buildplots', ['map_id' => $id]) . '"><strong>' . htmlspecialchars_uni($map['title']) . '</strong></a>');
        $table->construct_cell((int)$map['width'] . ' × ' . (int)$map['height'], ['class' => 'align_center']);
        $table->construct_cell(my_date('relative', (int)$map['created_at']), ['class' => 'align_center']);
        $table->construct_cell(rpgmaps_acp_popup('map_' . $id, [
            $lang->rpgmaps_edit          => rpgmaps_acp_url('maps_edit', ['id' => $id]),
            $lang->rpgmaps_manage_plots  => rpgmaps_acp_url('buildplots', ['map_id' => $id]),
            $lang->rpgmaps_delete        => rpgmaps_acp_url('maps_delete', ['id' => $id]),
        ]), ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($lang->rpgmaps_manage_maps . ' &mdash; <a href="' . rpgmaps_acp_url('maps_add') . '">' . $lang->rpgmaps_add_map . '</a>');

    $page->output_footer();
}

/**
 * Karte anlegen/bearbeiten
 *
 * @param bool $edit
 */
function rpgmaps_admin_maps_form($edit)
{
    global $mybb, $db, $page, $lang;

    $db_helper = new RPGMapsDatabase();
    $map_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $map = [
        'title' => '', 'description' => '', 'width' => 800, 'height' => 600,
        'scale_factor' => 1.0, 'filename' => 'placeholder.png',
    ];

    if ($edit) {
        $map = $db_helper->getMapById($map_id);
        if (!$map) {
            flash_message($lang->rpgmaps_map_not_found, 'error');
            admin_redirect(rpgmaps_acp_url('maps'));
        }
    }

    $self_url = $edit ? rpgmaps_acp_url('maps_edit', ['id' => $map_id]) : rpgmaps_acp_url('maps_add');

    if ($mybb->request_method === 'post') {
        rpgmaps_acp_verify_key();

        $title = trim($mybb->get_input('title'));
        if ($title === '') {
            flash_message($lang->rpgmaps_error_title_required, 'error');
            admin_redirect($self_url);
        }

        $filename = rpgmaps_acp_handle_upload('map_image', 'map', (string)$map['filename'], $self_url);

        $data = [
            'title'        => $db->escape_string($title),
            'description'  => $db->escape_string($mybb->get_input('description')),
            'width'        => max(1, $mybb->get_input('width', MyBB::INPUT_INT)),
            'height'       => max(1, $mybb->get_input('height', MyBB::INPUT_INT)),
            'scale_factor' => $mybb->get_input('scale_factor', MyBB::INPUT_FLOAT) ?: 1.0,
            'filename'     => $db->escape_string($filename),
        ];

        if ($edit) {
            $db_helper->updateMap($map_id, $data);
            flash_message($lang->rpgmaps_map_updated, 'success');
        } else {
            $data['created_by'] = (int)$mybb->user['uid'];
            $db_helper->createMap($data);
            flash_message($lang->rpgmaps_map_added, 'success');
        }
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    $title = $edit ? $lang->rpgmaps_edit_map : $lang->rpgmaps_add_map;
    rpgmaps_acp_header($title, 'maps', [$lang->rpgmaps_manage_maps => rpgmaps_acp_url('maps'), $title => '']);

    $form = new Form($self_url, 'post', '', 1);
    $form_container = new FormContainer($title);

    if ($edit && $map['filename'] !== '' && $map['filename'] !== 'placeholder.png') {
        $form_container->output_row($lang->rpgmaps_current_image, '', '<img src="' . htmlspecialchars_uni($mybb->settings['bburl']) . '/inc/plugins/rpgmaps/assets/maps/' . htmlspecialchars_uni($map['filename']) . '" alt="" style="max-width: 400px; max-height: 300px;" />');
    }

    $form_container->output_row($lang->rpgmaps_map_name . ' <em>*</em>', '', $form->generate_text_box('title', $map['title'], ['id' => 'title']), 'title');
    $form_container->output_row($lang->rpgmaps_map_description, '', $form->generate_text_area('description', $map['description'], ['id' => 'description']), 'description');
    $form_container->output_row($lang->rpgmaps_map_width, $lang->rpgmaps_map_size_desc, $form->generate_numeric_field('width', (int)$map['width'], ['id' => 'width', 'min' => 1]), 'width');
    $form_container->output_row($lang->rpgmaps_map_height, $lang->rpgmaps_map_size_desc, $form->generate_numeric_field('height', (int)$map['height'], ['id' => 'height', 'min' => 1]), 'height');
    $form_container->output_row($lang->rpgmaps_map_scale, '', $form->generate_numeric_field('scale_factor', (float)$map['scale_factor'], ['id' => 'scale_factor', 'step' => '0.1', 'min' => '0.1']), 'scale_factor');
    $form_container->output_row(
        $edit ? $lang->rpgmaps_new_image : $lang->rpgmaps_map_image,
        $edit ? $lang->rpgmaps_new_image_desc : $lang->rpgmaps_image_desc,
        $form->generate_file_upload_box('map_image', ['id' => 'map_image']),
        'map_image'
    );
    $form_container->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgmaps_save)]);
    $form->end();

    $page->output_footer();
}

/**
 * Karte löschen (Bestätigungsseite → POST)
 */
function rpgmaps_admin_maps_delete()
{
    global $mybb, $page, $lang;

    $db_helper = new RPGMapsDatabase();
    $map_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $map = $db_helper->getMapById($map_id);

    if (!$map) {
        flash_message($lang->rpgmaps_map_not_found, 'error');
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    if ($mybb->get_input('no')) {
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    if ($mybb->request_method === 'post') {
        rpgmaps_acp_verify_key();

        $db_helper->deleteMap($map_id);
        if ($map['filename'] !== 'placeholder.png') {
            RPGMapsAsset::deleteFile($map['filename'], 'map');
        }

        flash_message($lang->rpgmaps_map_deleted, 'success');
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    $page->output_confirm_action(rpgmaps_acp_url('maps_delete', ['id' => $map_id]), $lang->sprintf($lang->rpgmaps_confirm_delete_map, htmlspecialchars_uni($map['title'])));
}

// ============ BUILD PLOTS ============

/**
 * Bauplätze einer Karte
 */
function rpgmaps_admin_buildplots()
{
    global $mybb, $page, $lang;

    $db_helper = new RPGMapsDatabase();
    $map_id = $mybb->get_input('map_id', MyBB::INPUT_INT);
    $map = $db_helper->getMapById($map_id);

    if (!$map) {
        flash_message($lang->rpgmaps_map_not_found, 'error');
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    $map_title = htmlspecialchars_uni($map['title']);
    $heading = $lang->sprintf($lang->rpgmaps_plots_of_map, $map_title);
    rpgmaps_acp_header($lang->rpgmaps_manage_plots, 'maps', [
        $lang->rpgmaps_manage_maps => rpgmaps_acp_url('maps'),
        $heading => '',
    ]);

    $plots = $db_helper->getPlotsByMap($map_id);
    usort($plots, function ($a, $b) {
        $cmp = (int)$b['created_at'] <=> (int)$a['created_at'];

        return $cmp !== 0 ? $cmp : (int)$b['id'] <=> (int)$a['id'];
    });

    $table = new Table;
    $table->construct_header($lang->rpgmaps_plot_key);
    $table->construct_header($lang->rpgmaps_plot_position, ['width' => '25%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_status, ['width' => '15%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_controls, ['width' => '10%', 'class' => 'align_center']);

    if (empty($plots)) {
        $table->construct_cell($lang->rpgmaps_no_plots, ['colspan' => 4]);
        $table->construct_row();
    }

    foreach ($plots as $plot) {
        $id = (int)$plot['id'];
        $status_key = 'rpgmaps_plot_status_' . $plot['status'];
        $label = trim((string)$plot['tooltip_text']) !== '' ? ' <span class="smalltext">(' . htmlspecialchars_uni($plot['tooltip_text']) . ')</span>' : '';

        $table->construct_cell(htmlspecialchars_uni($plot['plot_key']) . $label);
        $table->construct_cell('X ' . (int)$plot['x'] . ' / Y ' . (int)$plot['y'] . ' &middot; ' . (int)$plot['w'] . ' × ' . (int)$plot['h'], ['class' => 'align_center']);
        $table->construct_cell(isset($lang->$status_key) ? $lang->$status_key : htmlspecialchars_uni($plot['status']), ['class' => 'align_center']);
        $table->construct_cell(rpgmaps_acp_popup('plot_' . $id, [
            $lang->rpgmaps_edit   => rpgmaps_acp_url('buildplots_edit', ['id' => $id]),
            $lang->rpgmaps_delete => [
                rpgmaps_acp_url('buildplots_delete', ['id' => $id, 'my_post_key' => $mybb->post_code]),
                rpgmaps_acp_confirm_js($lang->rpgmaps_confirm_delete_plot),
            ],
        ]), ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($heading . ' &mdash; <a href="' . rpgmaps_acp_url('buildplots_add', ['map_id' => $map_id]) . '">' . $lang->rpgmaps_add_plot . '</a>');

    $page->output_footer();
}

/**
 * Bauplatz anlegen/bearbeiten
 *
 * @param bool $edit
 */
function rpgmaps_admin_buildplots_form($edit)
{
    global $mybb, $db, $page, $lang;

    $db_helper = new RPGMapsDatabase();

    if ($edit) {
        $plot_id = $mybb->get_input('id', MyBB::INPUT_INT);
        $plot = $db_helper->getPlotById($plot_id);
        if (!$plot) {
            flash_message($lang->rpgmaps_plot_not_found, 'error');
            admin_redirect(rpgmaps_acp_url('maps'));
        }
        $map_id = (int)$plot['map_id'];
        $self_url = rpgmaps_acp_url('buildplots_edit', ['id' => $plot_id]);
    } else {
        $plot_id = 0;
        $map_id = $mybb->get_input('map_id', MyBB::INPUT_INT);
        $plot = ['plot_key' => '', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 50, 'rotation' => 0, 'tooltip_text' => ''];
        $self_url = rpgmaps_acp_url('buildplots_add', ['map_id' => $map_id]);
    }

    $map = $db_helper->getMapById($map_id);
    if (!$map) {
        flash_message($lang->rpgmaps_map_not_found, 'error');
        admin_redirect(rpgmaps_acp_url('maps'));
    }
    $list_url = rpgmaps_acp_url('buildplots', ['map_id' => $map_id]);

    if ($mybb->request_method === 'post') {
        rpgmaps_acp_verify_key();

        $plot_key = trim($mybb->get_input('plot_key'));
        if ($plot_key === '') {
            $plot_key = $edit ? (string)$plot['plot_key'] : 'plot_' . TIME_NOW . '_' . my_rand(1000, 9999);
        }

        $data = [
            'plot_key'     => $db->escape_string(my_substr($plot_key, 0, 100)),
            'x'            => max(0, $mybb->get_input('x', MyBB::INPUT_INT)),
            'y'            => max(0, $mybb->get_input('y', MyBB::INPUT_INT)),
            'w'            => rpgmaps_acp_plot_size($mybb->get_input('w', MyBB::INPUT_INT)),
            'h'            => rpgmaps_acp_plot_size($mybb->get_input('h', MyBB::INPUT_INT)),
            'rotation'     => rpgmaps_acp_rotation($mybb->get_input('rotation', MyBB::INPUT_INT)),
            'tooltip_text' => $db->escape_string(my_substr($mybb->get_input('tooltip_text'), 0, 255)),
        ];

        if ($edit) {
            $db_helper->updatePlot($plot_id, $data);
            flash_message($lang->rpgmaps_plot_updated, 'success');
        } else {
            $data['map_id'] = $map_id;
            $data['status'] = 'free';
            $db_helper->insertPlot($data);
            flash_message($lang->rpgmaps_plot_added, 'success');
        }
        admin_redirect($list_url);
    }

    $title = $edit ? $lang->rpgmaps_edit_plot : $lang->rpgmaps_add_plot;
    rpgmaps_acp_header($title, 'maps', [
        $lang->rpgmaps_manage_maps => rpgmaps_acp_url('maps'),
        $lang->sprintf($lang->rpgmaps_plots_of_map, htmlspecialchars_uni($map['title'])) => $list_url,
        $title => '',
    ]);

    $form = new Form($self_url, 'post');
    $form_container = new FormContainer($title);
    $form_container->output_row($lang->rpgmaps_plot_key, $edit ? '' : $lang->rpgmaps_plot_key_desc, $form->generate_text_box('plot_key', $plot['plot_key'], ['id' => 'plot_key']), 'plot_key');
    $form_container->output_row($lang->rpgmaps_plot_x, $lang->rpgmaps_plot_coords_desc, $form->generate_numeric_field('x', (int)$plot['x'], ['id' => 'x', 'min' => 0]), 'x');
    $form_container->output_row($lang->rpgmaps_plot_y, $lang->rpgmaps_plot_coords_desc, $form->generate_numeric_field('y', (int)$plot['y'], ['id' => 'y', 'min' => 0]), 'y');
    $form_container->output_row($lang->rpgmaps_plot_w, '', $form->generate_numeric_field('w', (int)$plot['w'], ['id' => 'w', 'min' => 1]), 'w');
    $form_container->output_row($lang->rpgmaps_plot_h, '', $form->generate_numeric_field('h', (int)$plot['h'], ['id' => 'h', 'min' => 1]), 'h');
    $form_container->output_row($lang->rpgmaps_plot_rotation, $lang->rpgmaps_plot_rotation_desc, $form->generate_numeric_field('rotation', (int)($plot['rotation'] ?? 0), ['id' => 'rotation', 'min' => 0, 'max' => 359]), 'rotation');
    $form_container->output_row($lang->rpgmaps_plot_tooltip, $lang->rpgmaps_plot_tooltip_desc, $form->generate_text_box('tooltip_text', $plot['tooltip_text'], ['id' => 'tooltip_text']), 'tooltip_text');
    $form_container->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgmaps_save)]);
    $form->end();

    $page->output_footer();
}

/**
 * Bauplatz löschen (Popup-Link mit my_post_key)
 */
function rpgmaps_admin_buildplots_delete()
{
    global $mybb, $lang;

    rpgmaps_acp_verify_key();

    $db_helper = new RPGMapsDatabase();
    $plot = $db_helper->getPlotById($mybb->get_input('id', MyBB::INPUT_INT));

    if (!$plot) {
        flash_message($lang->rpgmaps_plot_not_found, 'error');
        admin_redirect(rpgmaps_acp_url('maps'));
    }

    $db_helper->deletePlot((int)$plot['id']);

    flash_message($lang->rpgmaps_plot_deleted, 'success');
    admin_redirect(rpgmaps_acp_url('buildplots', ['map_id' => (int)$plot['map_id']]));
}

// ============ HOUSE TYPES ============

/**
 * Haustypen
 */
function rpgmaps_admin_house_types()
{
    global $mybb, $page, $lang;

    $db_helper = new RPGMapsDatabase();

    rpgmaps_acp_header($lang->rpgmaps_manage_house_types, 'house_types', [$lang->rpgmaps_manage_house_types => '']);

    $table = new Table;
    $table->construct_header($lang->rpgmaps_house_type_image, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_house_type_name);
    $table->construct_header($lang->rpgmaps_created, ['width' => '15%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_controls, ['width' => '10%', 'class' => 'align_center']);

    $types = $db_helper->getHouseTypes();
    if (empty($types)) {
        $table->construct_cell($lang->rpgmaps_no_house_types, ['colspan' => 4]);
        $table->construct_row();
    }

    foreach ($types as $type) {
        $id = (int)$type['id'];
        $image = $type['asset_filename'] !== '' && $type['asset_filename'] !== 'placeholder.png'
            ? '<img src="' . htmlspecialchars_uni($mybb->settings['bburl']) . '/inc/plugins/rpgmaps/assets/houses/' . htmlspecialchars_uni($type['asset_filename']) . '" alt="" style="max-width: 48px; max-height: 48px;" />'
            : '&ndash;';

        $table->construct_cell($image, ['class' => 'align_center']);
        $table->construct_cell('<strong>' . htmlspecialchars_uni($type['name']) . '</strong>');
        $table->construct_cell(my_date('relative', (int)$type['created_at']), ['class' => 'align_center']);
        $table->construct_cell(rpgmaps_acp_popup('type_' . $id, [
            $lang->rpgmaps_edit   => rpgmaps_acp_url('house_types_edit', ['id' => $id]),
            $lang->rpgmaps_delete => [
                rpgmaps_acp_url('house_types_delete', ['id' => $id, 'my_post_key' => $mybb->post_code]),
                rpgmaps_acp_confirm_js($lang->rpgmaps_confirm_delete_house_type),
            ],
        ]), ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($lang->rpgmaps_manage_house_types . ' &mdash; <a href="' . rpgmaps_acp_url('house_types_add') . '">' . $lang->rpgmaps_add_house_type . '</a>');

    $page->output_footer();
}

/**
 * Haustyp anlegen/bearbeiten
 *
 * @param bool $edit
 */
function rpgmaps_admin_house_types_form($edit)
{
    global $mybb, $db, $page, $lang;

    $db_helper = new RPGMapsDatabase();
    $type_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $type = ['name' => '', 'description' => '', 'icon_scale' => 1.0, 'asset_filename' => 'placeholder.png'];

    if ($edit) {
        $type = $db_helper->getHouseTypeById($type_id);
        if (!$type) {
            flash_message($lang->rpgmaps_house_type_not_found, 'error');
            admin_redirect(rpgmaps_acp_url('house_types'));
        }
    }

    $self_url = $edit ? rpgmaps_acp_url('house_types_edit', ['id' => $type_id]) : rpgmaps_acp_url('house_types_add');

    if ($mybb->request_method === 'post') {
        rpgmaps_acp_verify_key();

        $name = trim($mybb->get_input('name'));
        if ($name === '') {
            flash_message($lang->rpgmaps_error_name_required, 'error');
            admin_redirect($self_url);
        }

        $filename = rpgmaps_acp_handle_upload('house_image', 'house', (string)$type['asset_filename'], $self_url);

        $data = [
            'name'           => $db->escape_string(my_substr($name, 0, 100)),
            'description'    => $db->escape_string($mybb->get_input('description')),
            'icon_scale'     => $mybb->get_input('icon_scale', MyBB::INPUT_FLOAT) ?: 1.0,
            'asset_filename' => $db->escape_string($filename),
        ];

        if ($edit) {
            $db_helper->updateHouseType($type_id, $data);
            flash_message($lang->rpgmaps_house_type_updated, 'success');
        } else {
            $db_helper->createHouseType($data);
            flash_message($lang->rpgmaps_house_type_added, 'success');
        }
        admin_redirect(rpgmaps_acp_url('house_types'));
    }

    $title = $edit ? $lang->rpgmaps_edit_house_type : $lang->rpgmaps_add_house_type;
    rpgmaps_acp_header($title, 'house_types', [$lang->rpgmaps_manage_house_types => rpgmaps_acp_url('house_types'), $title => '']);

    $form = new Form($self_url, 'post', '', 1);
    $form_container = new FormContainer($title);

    if ($edit && $type['asset_filename'] !== '' && $type['asset_filename'] !== 'placeholder.png') {
        $form_container->output_row($lang->rpgmaps_current_image, '', '<img src="' . htmlspecialchars_uni($mybb->settings['bburl']) . '/inc/plugins/rpgmaps/assets/houses/' . htmlspecialchars_uni($type['asset_filename']) . '" alt="" style="max-width: 150px; max-height: 150px;" />');
    }

    $form_container->output_row($lang->rpgmaps_house_type_name . ' <em>*</em>', '', $form->generate_text_box('name', $type['name'], ['id' => 'name']), 'name');
    $form_container->output_row($lang->rpgmaps_house_type_description, '', $form->generate_text_area('description', $type['description'], ['id' => 'description']), 'description');
    $form_container->output_row(
        $edit ? $lang->rpgmaps_new_image : $lang->rpgmaps_house_type_image,
        $edit ? $lang->rpgmaps_new_image_desc : $lang->rpgmaps_image_desc,
        $form->generate_file_upload_box('house_image', ['id' => 'house_image']),
        'house_image'
    );
    $form_container->output_row($lang->rpgmaps_house_type_icon_scale, '', $form->generate_numeric_field('icon_scale', (float)$type['icon_scale'], ['id' => 'icon_scale', 'step' => '0.1', 'min' => '0.1']), 'icon_scale');
    $form_container->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgmaps_save)]);
    $form->end();

    $page->output_footer();
}

/**
 * Haustyp löschen (Popup-Link mit my_post_key)
 */
function rpgmaps_admin_house_types_delete()
{
    global $mybb, $lang;

    rpgmaps_acp_verify_key();

    $db_helper = new RPGMapsDatabase();
    $type = $db_helper->getHouseTypeById($mybb->get_input('id', MyBB::INPUT_INT));

    if (!$type) {
        flash_message($lang->rpgmaps_house_type_not_found, 'error');
        admin_redirect(rpgmaps_acp_url('house_types'));
    }

    if (!$db_helper->deleteHouseType((int)$type['id'])) {
        flash_message($lang->rpgmaps_house_type_in_use, 'error');
        admin_redirect(rpgmaps_acp_url('house_types'));
    }

    if ($type['asset_filename'] !== 'placeholder.png') {
        RPGMapsAsset::deleteFile($type['asset_filename'], 'house');
    }

    flash_message($lang->rpgmaps_house_type_deleted, 'success');
    admin_redirect(rpgmaps_acp_url('house_types'));
}

// ============ PENDING ACTIONS ============

/**
 * Offene Bauanträge
 */
function rpgmaps_admin_pending_actions()
{
    global $mybb, $page, $lang;

    $db_helper = new RPGMapsDatabase();

    rpgmaps_acp_header($lang->rpgmaps_manage_actions, 'pending_actions', [$lang->rpgmaps_manage_actions => '']);

    $table = new Table;
    $table->construct_header($lang->rpgmaps_action_type, ['width' => '15%']);
    $table->construct_header($lang->rpgmaps_action_user, ['width' => '20%']);
    $table->construct_header($lang->rpgmaps_action_target);
    $table->construct_header($lang->rpgmaps_action_date, ['width' => '15%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgmaps_controls, ['width' => '10%', 'class' => 'align_center']);

    $actions = $db_helper->getPendingActions();
    if (empty($actions)) {
        $table->construct_cell($lang->rpgmaps_no_actions, ['colspan' => 5]);
        $table->construct_row();
    }

    foreach ($actions as $action) {
        $id = (int)$action['id'];
        $user = get_user((int)$action['user_id']);
        $username = $user ? build_profile_link(htmlspecialchars_uni($user['username']), (int)$user['uid'], '_blank') : $lang->rpgmaps_deleted_account;

        $type_key = 'rpgmaps_action_' . $action['action_type'];
        $target = '#' . (int)$action['target_id'];
        if ($action['action_type'] === 'build') {
            $plot = $db_helper->getPlotById((int)$action['target_id']);
            $map = $plot ? $db_helper->getMapById((int)$plot['map_id']) : null;
            if ($plot && $map) {
                $target = $lang->sprintf($lang->rpgmaps_action_target_plot, htmlspecialchars_uni($map['title']), htmlspecialchars_uni($plot['plot_key']));
            }
        }

        $table->construct_cell(isset($lang->$type_key) ? $lang->$type_key : htmlspecialchars_uni($action['action_type']));
        $table->construct_cell($username);
        $table->construct_cell($target);
        $table->construct_cell(my_date('relative', (int)$action['created_at']), ['class' => 'align_center']);
        $table->construct_cell(rpgmaps_acp_popup('action_' . $id, [
            $lang->rpgmaps_action_approve => rpgmaps_acp_url('action_approve', ['id' => $id, 'my_post_key' => $mybb->post_code]),
            $lang->rpgmaps_action_reject  => [
                rpgmaps_acp_url('action_reject', ['id' => $id, 'my_post_key' => $mybb->post_code]),
                rpgmaps_acp_confirm_js($lang->rpgmaps_confirm_reject),
            ],
        ]), ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($lang->rpgmaps_manage_actions);

    $page->output_footer();
}

/**
 * Antrag genehmigen oder ablehnen (Popup-Link mit my_post_key)
 *
 * @param bool $approve
 */
function rpgmaps_admin_action_decide($approve)
{
    global $mybb, $lang;

    rpgmaps_acp_verify_key();

    $manager = new RPGMapsManager();
    $action_id = $mybb->get_input('id', MyBB::INPUT_INT);
    $note = $mybb->get_input('note');

    $result = $approve
        ? $manager->approveBuildRequest($action_id, (int)$mybb->user['uid'], $note)
        : $manager->rejectAction($action_id, (int)$mybb->user['uid'], $note);

    if (!empty($result['success'])) {
        flash_message($approve ? $lang->rpgmaps_action_approved : $lang->rpgmaps_action_rejected, 'success');
    } else {
        flash_message(htmlspecialchars_uni((string)$result['message']), 'error');
    }

    admin_redirect(rpgmaps_acp_url('pending_actions'));
}
