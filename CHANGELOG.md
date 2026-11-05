# Changelog

All notable changes to RPG Maps will be documented here.

## [Unreleased]

### Fixed
- **Account deletion never cleaned up.** The plugin hooked into `user_delete`, which MyBB
  does not have. It now uses `datahandler_user_delete_start` and removes houses, occupancies
  and requests of every deleted account (ACP single/bulk, merge, front end).
- **Admin permission was never registered** (`admin_permissions` → `admin_tools_permissions`).
  Removed the non-existent hooks `action_start`, `ajax_rpgmaps` and `admin_action_handler`
  (the first one pointed to a missing file).
- **No `deactivate()`.** Templates, the header link and the stylesheet stayed in place after
  deactivating. They now belong to `activate()`/`deactivate()`; tables, settings and the alert
  type to `install()`/`uninstall()`. `uninstall()` also removes the ModCP template.

### Changed
- **Front end fully translatable:** the build modal ("Haus bauen", "Bauantrag einreichen",
  labels, placeholders), house-settings messages in `rpgmaps.js` and all AJAX error messages
  (`rpgmaps_ajax_error()`) now come from the language files (English, German du/Sie). Internal
  exception texts are no longer shown to visitors.
- **Theme buttons:** all front-end buttons use the theme's `button` class (`input.button` in
  forms, `<button type="button" class="button">` for JS actions); the own `btn-small`,
  `btn-primary`/`btn-secondary` styles were removed. "View map" in the map list is a GET form
  instead of a link styled as a button.
- **One template source:** `rpgmaps_get_templates()` replaces the duplicated markup in
  `rpgmaps_install_templates()`/`rpgmaps_update_templates()`. `activate()` reinstalls the
  master templates, so updates always ship the current markup. Unused templates
  `rpgmaps_tooltip` and `rpgmaps_modal_build` removed; `modcp_nav_rpgmaps` is now also removed
  on deactivate and uses `{$lang->rpgmaps_name}`.
- **ACP and ModCP fully translatable:** about 75 hard-coded English/German texts (tabs,
  table headers, form labels, messages) moved to the language files (English, German du/Sie).
  The tab definition exists once (`rpgmaps_acp_sub_tabs()`) instead of six copies.
- ModCP: approve/reject are POST forms with `input.button` instead of GET links.
- Header link is inserted/removed with `find_replace_templatesets()` in every template set
  (previously only the master template was edited directly).
- German: “Benutzer” → “Account”.

### Removed
- Dead files `inc/plugins/rpgmaps/templates/frontend.php` and
  `inc/plugins/rpgmaps/languages/*.lang.php` (never loaded).

**Update note:** deactivate and reactivate the plugin once.

### Security
- **ACP actions without CSRF protection.** Deleting plots and house types and approving or
  rejecting pending actions ran on plain GET links without any post-key check. They now use
  MyBB's native pattern (dev kit `debugging.md` #23): the popup link carries `my_post_key`,
  `AdminCP.deleteConfirmation()` asks for confirmation and sends it as POST, and the handlers
  only act on POST – `admin/index.php` validates the key itself. No own key helper.
  The four handlers now answer with `flash_message()` + `admin_redirect()` instead of bare
  `echo` output without page header.
- **AJAX token was the session ID.** The frontend CSRF token exposed the MyBB session ID in
  the page HTML. It now uses MyBB's post key (`$mybb->post_code` / `verify_post_check()`).
- ACP delete confirmations use `json_encode()` for the JavaScript string.

### Fixed
- `inc/plugins/rpgmaps/templates/frontend.php` did not parse (unescaped quote in a template
  constant); the file is not included anywhere at the moment.
- “Delete map” confirmation page crashed because `$page` was not declared global.
- Direct `$mybb->input[...]` reads replaced by `get_input()`; `global` declarations moved to
  the start of their functions.

### Changed
- Plugin website set; README install step reads “Install & Activate”.

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
