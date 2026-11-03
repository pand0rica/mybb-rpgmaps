# Changelog

All notable changes to RPG Maps will be documented here.

## [Unreleased]

### Security
- **ACP actions without CSRF protection.** Deleting plots and house types and approving or
  rejecting pending actions ran on plain GET links without any post-key check. The links now
  carry `my_post_key`, and every ACP write handler checks it with
  `rpgmaps_acp_verify_key()` (in the ACP `verify_post_check()` does not abort on its own).
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
