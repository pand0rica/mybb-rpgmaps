# Changelog

All notable changes to RPG Maps will be documented here.

## [1.2.0] – unreleased

### Security
- SQL injection via the house name in the house settings (AJAX `rpgmaps_update_house_settings`): the value is now escaped before `update_query()`
- ACP: deleting plots and house types and approving/rejecting requests could be triggered by a plain GET link without `my_post_key` (CSRF). All write handlers now use `rpgmaps_acp_verify_key()`, which checks the return value of `verify_post_check()`
- AJAX requests used the session ID as CSRF token and exposed it in the page source. They now use `my_post_key` (`verify_post_check()`), and write actions require POST
- ModCP approve/reject were GET links; they are now POST forms with `my_post_key`
- ACP image uploads were only checked by file extension. They now go through `RPGMapsAsset`: the MIME type must match the extension, size and allowed extensions come from the plugin settings

### Fixed
- Account deletion never cleaned up houses: the plugin listened to the non-existent hook `user_delete`; it now uses `datahandler_user_delete_start` (all deletion paths) and also drops the member's pending build requests
- The ACP permission "Can manage RPG Maps?" never appeared (`admin_permissions` → `admin_tools_permissions`)
- ACP: deleting a map crashed with a fatal error (`$page` not declared global)
- ACP: deleting plots/house types printed bare text without the ACP page frame
- Double table prefix (`mybb_mybb_…`) in occupant queries
- Deactivating the plugin left templates, the stylesheet and the `{$rpgmaps_nav}` header link behind (no `rpgmaps_deactivate()` existed); the ModCP templates were not removed on uninstall because they lacked the `rpgmaps_` prefix
- The header link was only added to the master template and often ended up before the first `</ul>` on MyBB 1.8 themes (`{$menu_search}`); it is now inserted into all template sets via `find_replace_templatesets()`
- Uninstall left `rpgmaps.css` in the theme stylesheet lists and cache
- The settings *Maximum Upload Size*, *Allowed File Extensions* and *Maximum Plot Size* had no effect
- House tooltip title was HTML-escaped twice when no custom house name was set
- `templates/frontend.php` contained a PHP syntax error (file removed, it was unused)

### Changed
- Templates are maintained as `.tpl` files in `inc/plugins/rpgmaps/templates/` and loaded on activation; ModCP templates renamed to `rpgmaps_modcp_nav` / `rpgmaps_modcp_pending`
- Settings and data are created on install and kept on deactivate; templates, stylesheet and header link follow activate/deactivate
- All texts (ACP, ModCP, index notice, AJAX messages, JavaScript) come from the language files; language files rebuilt with consistent keys and gender-fair wording (English, German du/Sie)
- ACP module rewritten on shared helpers; consistent tabs, breadcrumbs and confirmation dialogs
- Frontend action buttons are `input.button` forms (map list, build form, ModCP)
- Removed all `console.*` output from the JavaScript
- Removed dead code: unused hooks (`action_start`, `ajax_rpgmaps`, `admin_action_handler`), duplicate language files in `inc/plugins/rpgmaps/languages/`, unused templates and helper methods

### Upgrade notes
- Upload the files, then deactivate and activate the plugin once. Theme-specific copies of the RPG Maps templates are replaced.

## [1.1.0] – 2026-03-28

### Added
- House ownership system: users can submit build requests for free plots
- Move-in / move-out requests with admin approval workflow
- House types with configurable max occupants and custom images
- House name and description (BBCode supported) set by the owner
- Owner can update house settings (type, max occupants, name) after approval
- House info tooltip redesigned to match MyBB theme (tborder/thead/trow classes)
- Optional MyAlerts integration: owner receives an alert when a build request is approved
- ModCP read-only view for moderators
- Admin "Pending Actions" panel for approving/rejecting build and move-in/out requests
- `deutsch_sie` (formal German) language file

### Changed
- Plot overlays now show occupancy status (free / pending / built)
- AJAX requests use `$mybb->get_input()` instead of `$_REQUEST` throughout
- All `console.log()` debug output removed from JavaScript

### Fixed
- MyAlerts lang placeholders corrected from `%s` to `{1}` / `{2}` (MyBB lang->sprintf format)

## [1.0.0] – 2026-03-27

### Added
- Initial release
- ACP map management (create, edit, delete maps with image upload)
- ACP plot management (add/edit/delete clickable plot overlays per map)
- Percentage-based plot coordinates for responsive display
- Frontend map view with interactive plot overlays
- ModCP read-only map view
- AJAX-based plot detail popups with nonce verification
- Security: input validation, output escaping, file upload verification
- English and German language files
