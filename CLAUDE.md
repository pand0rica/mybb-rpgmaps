# RPG Maps – Plugin Context

## What This Plugin Does
RPG Maps is a MyBB 1.8 plugin that allows forum admins to create interactive maps
for RPG forums. Users can view maps with named plots/locations, click on them to
see details, and admins can manage maps and plots via the ACP.

## File Structure
```
rpgmaps.php                              # Entry point: hooks, activate/deactivate
inc/plugins/rpgmaps/
    core/
        ajax.php                         # AJAX request handler
        database.php                     # DB queries (maps, plots, uploads)
        hooks.php                        # Hook callback functions
        installer.php                    # Install/uninstall DB tables & settings
        rpgmaps.class.php                # Main class
        security.php                     # Permission checks, input validation
    assets/
        rpgmaps.css                      # Frontend styles
        rpgmaps.js                       # Interactive map (click handlers, overlays)
    templates/
        frontend.php                     # Template output helpers
    modules/
        admin/rpgmaps_admin.php          # ACP logic
        modcp/rpgmaps_modcp.php          # ModCP logic
    languages/
        english.lang.php
        german.lang.php
admin/modules/tools/rpgmaps.php          # ACP page entry
inc/languages/english/rpgmaps.lang.php
inc/languages/deutsch_du/rpgmaps.lang.php
```

## Database Tables
- `mybb_rpgmaps` – map definitions (id, name, image, settings)
- `mybb_rpgmaps_plots` – plot/location overlays per map (id, map_id, name, coords, link)

## Key Technical Details
- Maps are image-based with CSS/JS overlay positioning for plots
- Plot coordinates are stored as percentage values (responsive)
- Image uploads handled via `inc/plugins/rpgmaps/core/database.php`
- AJAX calls go through `ajax.php` with nonce verification
- Permissions: configurable per usergroup via MyBB settings

## Settings (mybb_settings)
- `rpgmaps_enabled` – master on/off switch
- `rpgmaps_max_upload_size` – max image size in KB
- `rpgmaps_max_plot_size` – max plot overlay size in px
- `rpgmaps_allowed_extensions` – comma-separated allowed image types

## Current Status
- Core functionality complete
- ACP management working
- Frontend display working
- ModCP view working

## Known Constraints
- No external JS libraries – vanilla JS only
- Images stored in uploads/ directory (excluded from Git)
- Compatible with MyBB default theme and custom themes

## What NOT to change
- The coordinate system (percentage-based) – changing breaks existing maps
- The DB table structure without providing a migration
- Core hook registration in rpgmaps.php
