<html>
<head>
<title>{$mybb->settings['bbname']} - {$rpgmaps_map_title}</title>
{$headerinclude}
</head>
<body>
{$header}
{$rpgmaps_notification}
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
<td class="thead"><strong>{$rpgmaps_map_title}</strong></td>
</tr>
<tr>
<td class="trow1">
<p class="smalltext">{$rpgmaps_map_description}</p>
<div class="rpgmaps-container" {$rpgmaps_container_attributes}>
    <div class="rpgmaps-map-wrapper" id="rpgmaps-map-{$rpgmaps_map_id}">
        <img src="{$rpgmaps_map_image}" alt="{$rpgmaps_map_title}" class="rpgmaps-map-image">
        <div id="rpgmaps-overlay-{$rpgmaps_map_id}" class="rpgmaps-overlay">
            {$rpgmaps_plot_overlays}
        </div>
    </div>
</div>
</td>
</tr>
</table>
<br />

<div id="rpgmaps-modal-build" class="rpgmaps-modal">
    <div class="modal-content tborder trow1">
        <div class="rpgmaps-modal-header thead">
            <strong>{$lang->rpgmaps_build_house}</strong>
            <span class="close">&times;</span>
        </div>
        <div class="rpgmaps-modal-body">
            <form id="form-build-house" method="post">
                <input type="hidden" name="plot_id" id="build-plot-id" value="">
                <div class="form-group">
                    <label>{$lang->rpgmaps_select_house_type_label}</label>
                    <select name="type_id" id="house-type-select" required>
                        {$rpgmaps_house_type_options}
                    </select>
                </div>
                <div class="form-group">
                    <label>{$lang->rpgmaps_maximum_occupants}</label>
                    <input type="number" name="max_occupants" id="max-occupants-input" min="1" max="20" value="5" required>
                </div>
                <div class="form-group">
                    <label>{$lang->rpgmaps_description_optional}</label>
                    <textarea name="description" id="house-description-input" rows="3" placeholder="{$lang->rpgmaps_description_placeholder}"></textarea>
                </div>
                <div class="form-group">
                    <label>{$lang->rpgmaps_house_name_optional}</label>
                    <input type="text" name="house_name" id="house-name-input" maxlength="255" placeholder="{$lang->rpgmaps_house_name_placeholder}">
                </div>
                <input type="submit" class="button" value="{$lang->rpgmaps_submit_build_request}" />
            </form>
        </div>
    </div>
</div>

{$footer}
</body>
</html>