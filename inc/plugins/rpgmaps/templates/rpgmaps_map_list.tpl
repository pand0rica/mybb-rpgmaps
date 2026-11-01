<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->rpgmaps_maps}</title>
{$headerinclude}
</head>
<body>
{$header}
{$rpgmaps_notification}
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->rpgmaps_maps}</strong></td>
</tr>
{$rpgmaps_map_list}
</table>
<br />
{$footer}
</body>
</html>