# Changelog

All notable changes to RPG Maps will be documented here.

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
