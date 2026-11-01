# RPG Maps – Project Context

## Identität
- **Name:** RPG Maps
- **Codename / Dateiname:** `rpgmaps` (Präfix: `rpgmaps_`, Klassen `RPGMaps*`)
- **Version:** 1.2.0 (unreleased)
- **Entwickelt von:** pand0rica
- **Kurzbeschreibung:** Interaktive Stadtkarten mit Bauplätzen, Bauanträgen und Hausbewohnenden für RPG-Foren.
- **MyBB:** 1.8.x · **PHP:** 7.4 + 8.x · **DB:** MySQL/MariaDB (InnoDB, Foreign Keys)

## Zweck & Zielgruppe
Das Team legt Karten (Bilder) mit Bauplätzen an. Mitglieder stellen für freie Bauplätze
Bauanträge; nach Freigabe durch das Team (ModCP oder ACP) erscheint das Haus auf der Karte.
Mitglieder können sofort ein- und ausziehen und Name/Beschreibung/Einstellungen ihres Hauses
pflegen. Zieht die letzte Person aus, wird der Bauplatz wieder frei.

## Architektur
- **Variante:** Klassen-/Core-Split (Uploads, AJAX, mehrere Tabellen)
- **Dateilayout:**
  ```
  rpgmaps.php                                 Frontend-Seite (Kartenliste, Karte, AJAX-Einstieg action=ajax_rpgmaps)
  inc/plugins/rpgmaps.php                     info/install/activate/deactivate/uninstall + Hook-Registrierung
  inc/plugins/rpgmaps/core/
      installer.php    Tabellen, Migrationen, Settings, Templates (.tpl), CSS, Header-Link, MyAlerts-Typ
      database.php     RPGMapsDatabase – alle Queries
      rpgmaps.class.php RPGMapsManager – Geschäftslogik + rpgmaps_lang()
      security.php     RPGMapsSecurity (CSRF = my_post_key, Upload-Prüfung), RPGMapsAsset (Uploads)
      ajax.php         AJAX-Dispatcher rpgmaps_handle_ajax()
      hooks.php        Header-Link, Index-Hinweis, ModCP-Menü, Account-Löschung, MyAlerts-Formatter
  inc/plugins/rpgmaps/modules/admin/rpgmaps_admin.php   ACP-Modul (geladen über admin/modules/tools/rpgmaps.php)
  inc/plugins/rpgmaps/modules/modcp/rpgmaps_modcp.php   ModCP-Seite modcp.php?action=rpgmaps
  inc/plugins/rpgmaps/templates/*.tpl         Templates (Dateiname = Templatetitel)
  inc/plugins/rpgmaps/src/Formatter/          MyAlerts-Formatter
  inc/plugins/rpgmaps/assets/                 rpgmaps.css, rpgmaps.js, maps/, houses/ (Uploads)
  inc/languages/{english,deutsch_du,deutsch_sie}/rpgmaps.lang.php        Frontend
  inc/languages/{english,deutsch_du,deutsch_sie}/admin/rpgmaps.lang.php  ACP
  ```
  Meldungen von Manager/AJAX (`rpgmaps_msg_*`) stehen in **beiden** Sprachdateien, weil der
  Manager auch im ACP läuft.

## Datenbank-Tabellen
| Tabelle (`TABLE_PREFIX .`) | Zweck | Wichtige Spalten |
|---|---|---|
| `rpgmaps_maps` | Karten | title, description, filename, width, height (Originalgröße in px), scale_factor |
| `rpgmaps_buildplots` | Bauplätze | map_id, plot_key (je Karte eindeutig), x, y, w, h (px), rotation, tooltip_text, status (free/pending/built) |
| `rpgmaps_house_types` | Haustypen | name, description, asset_filename, icon_scale |
| `rpgmaps_houses` | Häuser | plot_id, type_id, status, max_occupants, description (MyCode), house_name, created_by |
| `rpgmaps_house_occupants` | Bewohnende | house_id, uid, role (owner/resident), joined_at, left_at (NULL = wohnt dort) |
| `rpgmaps_actions` | Bauanträge | action_type (build), target_id (plot), user_id, status (pending/approved/rejected), extra_data (JSON) |

Migrationen: `rpgmaps_update_tables()` (idempotent, `field_exists()`), läuft in `activate()`.

## Settings (settinggroup `rpgmaps`)
| Setting | optionscode | Zweck |
|---|---|---|
| `rpgmaps_enabled` | yesno | Kartenseiten + Header-Link an/aus |
| `rpgmaps_max_plot_size` | numeric | Obergrenze Bauplatz-Breite/-Höhe in px (0 = unbegrenzt) |
| `rpgmaps_max_upload_size` | numeric | Max. Bildgröße in KB |
| `rpgmaps_allowed_extensions` | text | Erlaubte Endungen; MIME muss zur Endung passen |

## Hooks
| Hook | Handler | Zweck |
|---|---|---|
| `global_intermediate` | `rpgmaps_build_nav` | `{$rpgmaps_nav}` für den Header |
| `global_intermediate` (20) | `rpgmaps_register_myalerts_formatter` | MyAlerts-Formatter (nach MyAlerts, Prio 10) |
| `datahandler_user_delete_start` | `rpgmaps_user_delete_hook` | Aufräumen bei Account-Löschung (alle Löschpfade) |
| `admin_tools_menu` / `admin_tools_action_handler` / `admin_tools_permissions` | `rpgmaps_admin_*` | ACP-Modul + Berechtigung |
| `index_end` | `rpgmaps_index_notification` | Hinweis auf offene Bauanträge fürs Team |
| `modcp_start` | `rpgmaps_modcp_handler` | ModCP-Menüpunkt + Seite |

## Eigene Hooks / API
Keine.

## Abhängigkeiten
Optional **MyAlerts** (Alert-Typ `rpgmaps_build_approved`). Erkennung über
`function_exists('myalerts_create_instances')` bzw. Tabelle `alert_types`.

## Berechtigungen
- Kartenansicht: alle, inkl. Gäste (solange `rpgmaps_enabled`).
- Bauantrag, Ein-/Auszug: eingeloggte Mitglieder. Haus bearbeiten: nur Bewohnende.
- Anträge bearbeiten: Gruppen mit `canmodcp` (ModCP) bzw. ACP-Berechtigung „Can manage RPG Maps?".
- Schreibende AJAX-Aktionen: POST + `my_post_key` (`RPGMapsSecurity::verifyCSRFToken()`).

## What NOT to change
- **Koordinatensystem:** Bauplätze speichern **Pixel** bezogen auf `width`/`height` der Karte;
  `rpgmaps.js` skaliert anhand `data-orig-*`. Ein Umbau bricht bestehende Karten.
- DB-Struktur nur mit idempotenter Migration in `rpgmaps_update_tables()`.
- Lifecycle-Aufteilung: Tabellen/Settings/MyAlerts-Typ in install/uninstall; Templates, CSS,
  Header-Link in activate/deactivate.
- Upload-Ordner `inc/plugins/rpgmaps/assets/maps|houses/` (Pfade stehen in der DB als Dateiname).
