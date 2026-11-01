<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->modcp} - {$lang->rpgmaps_modcp_nav}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
<td class="thead" colspan="5"><strong>{$lang->rpgmaps_modcp_title}</strong></td>
</tr>
<tr>
<td class="tcat" width="15%"><strong>{$lang->rpgmaps_type}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->rpgmaps_modcp_member}</strong></td>
<td class="tcat" width="30%"><strong>{$lang->rpgmaps_modcp_location}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->rpgmaps_modcp_created}</strong></td>
<td class="tcat rpgmaps-action-buttons" width="15%"><strong>{$lang->rpgmaps_modcp_actions}</strong></td>
</tr>
{$action_rows}
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>