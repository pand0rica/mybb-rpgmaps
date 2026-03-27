# RPG Maps Plugin for myBB 1.8.x

A comprehensive plugin for managing fantasy city maps, building plots, and house ownership with administrative approval workflow.

## Features

- **Map Management**: Create and manage multiple fantasy city maps
- **Build Plots**: Define clickable building plots on each map with customizable positions and sizes
- **House Types**: Create different house types with different capacity limits
- **Interactive Frontend**: Click on plots to view information, build houses, or move in/out
- **Approval Workflow**: All building/moving actions require admin approval before being finalized
- **User Occupancy**: Track who lives in each house with owner/resident roles
- **Automatic Cleanup**: Houses are automatically deleted when all occupants leave
- **Security**: CSRF protection, input validation, file upload verification, prepared statements

## Requirements

- myBB 1.8.x
- PHP 7.4+
- MySQL/MariaDB
- GD Library (for image handling)

## Installation

1. **Upload Files**
   - Extract the plugin files to `inc/plugins/rpgmaps/`
   - Ensure the directory structure is preserved

2. **Create Asset Directories**
   - Create `inc/plugins/rpgmaps/assets/maps/` for map images
   - Create `inc/plugins/rpgmaps/assets/houses/` for house graphics
   - Set proper permissions (755)

3. **Install via Admin Panel**
   - Log in to Admin Control Panel
   - Go to Plugins
   - Find "RPG Maps Plugin" and click Install
   - The plugin will create necessary database tables

4. **Activate the Plugin**
   - Click "Activate" next to the RPG Maps Plugin

5. **Configure Settings** (Optional)
   - Go to Settings → RPG Maps to adjust:
     - Maximum upload size (default 512 KB)
     - Allowed file extensions (default: png, jpg, jpeg, gif)
     - Maximum plot size

## Database Schema

The plugin creates 6 tables:

### rpgmaps_maps
- `id`: Map ID (Primary Key)
- `title`: Map title
- `description`: Map description
- `filename`: Image filename (relative to assets/maps/)
- `width`: Map width in pixels
- `height`: Map height in pixels
- `scale_factor`: Display scale factor (default 1.0)
- `created_by`: User ID of creator
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

### rpgmaps_buildplots
- `id`: Plot ID (Primary Key)
- `map_id`: Map ID (Foreign Key)
- `plot_key`: Unique plot identifier
- `x`, `y`: Coordinates on map
- `w`, `h`: Width and height of plot
- `tooltip_text`: Hover text
- `status`: free, pending, or built
- `created_at`: Creation timestamp

### rpgmaps_house_types
- `id`: Type ID (Primary Key)
- `name`: House type name
- `description`: Description
- `asset_filename`: Image filename (relative to assets/houses/)
- `max_occupants`: Maximum residents allowed
- `icon_scale`: Display scale for house icon
- `created_at`: Creation timestamp

### rpgmaps_houses
- `id`: House ID (Primary Key)
- `plot_id`: Building plot ID (Foreign Key)
- `type_id`: House type ID (Foreign Key)
- `status`: active, inactive, or pending
- `created_by`: User ID of builder
- `created_at`: Creation timestamp
- `approved_at`: Approval timestamp

### rpgmaps_house_occupants
- `id`: Occupant record ID (Primary Key)
- `house_id`: House ID (Foreign Key)
- `uid`: User ID (Foreign Key)
- `role`: owner or resident
- `joined_at`: Move-in timestamp
- `left_at`: Move-out timestamp (NULL if still occupant)

### rpgmaps_actions
- `id`: Action ID (Primary Key)
- `action_type`: build, move_in, move_out, or delete_house
- `target_id`: House ID or plot ID
- `user_id`: User who initiated action (Foreign Key)
- `status`: pending, approved, or rejected
- `created_at`: Action creation timestamp
- `reviewed_by`: Admin user ID (Foreign Key)
- `reviewed_at`: Review timestamp
- `admin_note`: Admin comment

## Usage

### Frontend (rpgmaps.php)

1. **View Maps**
   - Visit `rpgmaps.php` to see available maps
   - Click a map to view detailed plot information

2. **View Plots**
   - Hover over plots to see information
   - Free plots show in green, built plots show house graphics

3. **Build House** (Logged-in users)
   - Click on a free plot
   - Select a house type
   - Submit build request
   - Wait for admin approval

4. **Move In** (Logged-in users)
   - Click on a built house
   - Click "Move In" button
   - Wait for admin approval

5. **Move Out**
   - Click on your house
   - Click "Move Out" button
   - Wait for admin approval
   - If you're the last occupant, house is deleted and plot becomes free

### Admin Panel

Access RPG Maps management via **Admin CP → Tools → RPG Maps**

**Maps Management**
- Add new maps with image upload
- Edit map properties (title, description, dimensions)
- Delete maps (removes all related plots and houses)

**Build Plots Management**
- Add/edit/delete building plots per map
- Adjust plot position and size
- Set tooltip text

**House Types Management**
- Create house types with capacity limits
- Upload house graphics
- Adjust display scale

**Pending Actions**
- Review all pending build/move-in/move-out requests
- Approve requests to apply changes
- Reject requests with optional admin notes

## File Structure

```
inc/plugins/rpgmaps/
├── rpgmaps.php                      # Main plugin file
├── core/
│   ├── installer.php               # Install/uninstall routines
│   ├── database.php                # Database helper class
│   ├── security.php                # Security and asset helpers
│   ├── rpgmaps.class.php          # Main plugin logic
│   ├── hooks.php                   # Event hooks
│   └── ajax.php                    # AJAX endpoints
├── modules/
│   └── admin/
│       └── rpgmaps_admin.php       # ACP module
├── frontend.php                    # Frontend page handler
├── languages/
│   ├── english.lang.php            # English strings
│   └── german.lang.php             # German strings
├── assets/
│   ├── rpgmaps.css                # Frontend styles
│   ├── rpgmaps.js                 # Frontend JavaScript
│   ├── maps/                       # Map images (user uploaded)
│   └── houses/                     # House images (user uploaded)
└── templates/
    └── [template files]            # Template definitions
```

## Testing

### Manual Testing Checklist

#### 1. Installation
- [ ] Plugin installs without errors
- [ ] Database tables created successfully
- [ ] Admin panel accessible
- [ ] Asset directories created with proper permissions

#### 2. Map Management (ACP)
- [ ] Create a map with title, description, image
- [ ] Edit map properties
- [ ] Delete map (confirms all related data is removed)
- [ ] Multiple maps can coexist

#### 3. Build Plots (ACP)
- [ ] Add build plot to map with coordinates
- [ ] Edit plot position/size
- [ ] Delete plot
- [ ] Multiple plots per map work correctly

#### 4. House Types (ACP)
- [ ] Create house type with name, description, capacity
- [ ] Edit house type
- [ ] Cannot delete type if houses depend on it
- [ ] House types appear in build request form

#### 5. Frontend Display
- [ ] Maps list displays correctly
- [ ] Map loads with proper dimensions
- [ ] All plots visible and positioned correctly
- [ ] Free plots highlight on hover
- [ ] Built plots show house graphics
- [ ] Tooltips display on hover

#### 6. Building Workflow
- [ ] Guest user cannot submit build request
- [ ] Logged-in user can submit build request
- [ ] Request appears in pending actions
- [ ] Admin can approve request
- [ ] House created on plot after approval
- [ ] Plot status changes to "built"
- [ ] House graphic displays on plot

#### 7. Move In/Out
- [ ] User cannot move in without approvals
- [ ] Move-in request created
- [ ] Admin approves move-in
- [ ] User appears as occupant
- [ ] Cannot move in if house is full
- [ ] Move-out request created
- [ ] Admin approves move-out
- [ ] User removed from occupants
- [ ] House deleted if no occupants remain

#### 8. Security
- [ ] CSRF token validation works
- [ ] File uploads restricted to allowed types
- [ ] File uploads reject files exceeding size limit
- [ ] SQL injection attempts are prevented (prepared statements)
- [ ] Input validation prevents invalid data

#### 9. User Deletion
- [ ] When user is deleted, occupant records removed
- [ ] Empty houses deleted after user deletion
- [ ] Plots marked as free correctly

### Automated Testing Notes

While this plugin doesn't include full unit tests, here's a structure for adding them:

```
tests/
├── TestDatabase.php          # Test database operations
├── TestSecurity.php          # Test validation/security
├── TestWorkflow.php          # Test build/move-in/move-out workflow
└── README.md                 # Testing guide
```

#### Testing Commands (to be implemented)

```bash
# Run all tests
phpunit tests/

# Run specific test
phpunit tests/TestWorkflow.php

# Test with code coverage
phpunit --coverage-text tests/
```

## Security Considerations

1. **CSRF Protection**: All forms include CSRF tokens validated server-side
2. **Input Validation**: All inputs validated for type, length, and format
3. **SQL Injection**: All database queries use prepared statements
4. **File Uploads**: 
   - File type validation (MIME type + extension)
   - File size limits enforced
   - Files stored outside webroot when possible
5. **Permissions**: Admin actions require proper ACP permissions
6. **XSS Prevention**: Output properly escaped with `htmlspecialchars_uni()`

## Troubleshooting

### Plugin doesn't install
- Check PHP version (requires 7.4+)
- Verify database user has CREATE TABLE permissions
- Check error logs for specific errors

### Maps not displaying
- Verify asset directories exist and are readable
- Check that map image files are in `assets/maps/` folder
- Verify file permissions (644 for files)
- Check browser console for JavaScript errors

### AJAX requests failing
- Verify CSRF token is being sent correctly
- Check server error logs for PHP errors
- Ensure `index.php` is accessible

### Build requests not working
- Ensure user is logged in
- Check that house types are created
- Verify plot status is "free"
- Check pending actions in ACP

## Performance Optimization

For large installations with many maps/plots:

1. **Database Indexing**: Ensure these columns are indexed:
   - `rpgmaps_buildplots.map_id`
   - `rpgmaps_house_occupants.house_id`
   - `rpgmaps_actions.status`

2. **Caching**: Consider caching map/plot data:
   ```php
   $cache->update(
       'rpgmaps_map_' . $map_id,
       $map_data,
       3600 // 1 hour
   );
   ```

3. **Limiting**: Restrict number of plots per map display

## Configuration

Default settings in `Settings → RPG Maps`:

```
rpgmaps_enabled = 1
rpgmaps_max_plot_size = 200 (pixels)
rpgmaps_max_upload_size = 512 (KB)
rpgmaps_allowed_extensions = png,jpg,jpeg,gif
```

## Sample Data for Testing

See `assets/SAMPLE_DATA.sql` for SQL to load test maps and plots.

## Support & Reporting Issues

For bugs or feature requests, please provide:
- Error message and location
- Steps to reproduce
- myBB version
- PHP version
- Database type/version

## License

MIT License - Feel free to use and modify

## Credits

Developed for myBB 1.8.x

---

**Last Updated**: January 2026
**Version**: 1.0.0
**Compatibility**: myBB 1.8.x, PHP 7.4+
