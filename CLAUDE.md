# RPG Maps – Plugin Context

Allgemeine MyBB-Konventionen stehen im MyBB Plugin Dev Kit (`AGENTS.md`, `standards/`); bei
Konflikten haben das Dev-Kit und diese Datei Vorrang. Grundsatz: native MyBB-Mittel vor
eigenem Code (`conventions.md` §11).

## Was das Plugin tut
Interaktive, bildbasierte Karten für RPG-Foren. Auf jeder Karte liegen Bauplätze
(prozentuale Koordinaten, responsiv). Mitglieder beantragen dort Häuser, ziehen ein oder aus;
das Team gibt die Anträge im ACP oder ModCP frei. Owner pflegen Hausname, Beschreibung
(MyCode), Haustyp und Bewohnerzahl. Optional MyAlerts beim genehmigten Bauantrag.

## Dateien
```
rpgmaps.php                                   # Frontend: Kartenliste, Kartenansicht, AJAX-Endpunkt (action=ajax_rpgmaps)
inc/plugins/rpgmaps.php                       # info, install/activate/deactivate/uninstall, Hook-Registrierung, ACP-Menü/-Recht
inc/plugins/rpgmaps/core/
    installer.php                             # Tabellen, Settings, Templates, Header-Link, Stylesheet, Alert-Typ, Migrationen
    database.php                              # RPGMapsDatabase – alle Queries (Karten, Bauplätze, Haustypen, Häuser, Anträge)
    rpgmaps.class.php                         # RPGMapsManager – Geschäftslogik (Anträge stellen/genehmigen/ablehnen)
    security.php                              # RPGMapsSecurity – Login-/Rechteprüfung, Validierung, CSRF-Token = $mybb->post_code
    ajax.php                                  # rpgmaps_handle_ajax() – JSON-Aktionen (siehe unten)
    hooks.php                                 # Header-Navigation, MyAlerts-Formatter, Index-Hinweis, ModCP-Router, Account-Löschung
inc/plugins/rpgmaps/modules/admin/rpgmaps_admin.php   # ACP: Karten, Bauplätze, Haustypen, offene Anträge
inc/plugins/rpgmaps/modules/modcp/rpgmaps_modcp.php   # ModCP: offene Bauanträge freigeben/ablehnen
inc/plugins/rpgmaps/src/Formatter/BuildApprovedFormatter.php  # MyAlerts-Formatter
inc/plugins/rpgmaps/assets/                   # rpgmaps.css, rpgmaps.js (Vanilla JS), Bilder in maps/ und houses/
admin/modules/tools/rpgmaps.php               # ACP-Einstieg (lädt das Admin-Modul)
inc/languages/{english,deutsch_du,deutsch_sie}/rpgmaps.lang.php        # Frontend + ModCP
inc/languages/{english,deutsch_du,deutsch_sie}/admin/rpgmaps.lang.php  # ACP
```

## Tabellen (`TABLE_PREFIX . …`)
`rpgmaps_maps`, `rpgmaps_buildplots`, `rpgmaps_house_types`, `rpgmaps_houses`,
`rpgmaps_house_occupants`, `rpgmaps_actions` (Bau-/Einzugs-/Auszugsanträge).

## Settings (settinggroup `rpgmaps`)
`rpgmaps_enabled`, `rpgmaps_max_plot_size`, `rpgmaps_max_upload_size`,
`rpgmaps_allowed_extensions` – angelegt in `install()`, entfernt in `uninstall()`.

## Lifecycle
- `install()`: Tabellen, Settings, Alert-Typ. `uninstall()`: alles davon + Templates/CSS.
- `activate()`: Tabellen-Migration, Templates (`rpgmaps_*`, `modcp_rpgmaps_pending`),
  Header-Link `{$rpgmaps_nav}` per `find_replace_templatesets()`, Stylesheet in allen Themes.
- `deactivate()`: exaktes Spiegelbild – Templates, Header-Link, Stylesheet weg.

## Hooks
`global_intermediate` (Header-Navigation, MyAlerts-Formatter), `index_end` (Hinweis auf offene
Anträge), `modcp_start` (ModCP-Seite), `datahandler_user_delete_start` (Häuser/Anträge
gelöschter Accounts aufräumen – deckt alle Löschwege ab), `admin_tools_menu`,
`admin_tools_action_handler`, `admin_tools_permissions`.

## AJAX (`rpgmaps.php?action=ajax_rpgmaps&sub=…`)
`rpgmaps_get_map`, `rpgmaps_get_house_info` (lesend, ohne Token) sowie
`rpgmaps_build_request`, `rpgmaps_move_in_request`, `rpgmaps_move_out_request`,
`rpgmaps_update_description`, `rpgmaps_update_house_settings` (schreibend; Token =
`$mybb->post_code`, geprüft mit `verify_post_check()`).

## Security
- ACP: Schreibende Handler nur unter `request_method === 'post'`; den `my_post_key` prüft
  `admin/index.php` selbst. Popup-Aktionen (Löschen, Freigeben, Ablehnen) nutzen Link mit
  `my_post_key` + `AdminCP.deleteConfirmation()` (Dev-Kit `debugging.md` #23).
- ModCP: Freigeben/Ablehnen nur per POST-Formular + `verify_post_check()`.
- Alle Texte aus den Sprachdateien (ACP: Admin-Sprachdatei), keine harten Strings.

## What NOT to change
- Koordinatensystem (Prozentwerte) – sonst verschieben sich bestehende Karten.
- Tabellenstruktur nur mit Migration in `rpgmaps_update_tables()`.
- Bilder liegen in `inc/plugins/rpgmaps/assets/maps|houses/` (nicht in Git).
