<?php
/**
 * RPG Maps - Security Helper Class
 * Handles validation, CSRF tokens, and security checks
 * 
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

class RPGMapsSecurity
{
    /**
     * CSRF-Token prüfen (MyBB my_post_key).
     * Früher wurde die Session-ID als Token genutzt – die gehört nicht ins HTML.
     * @param string $token
     * @return bool
     */
    public static function verifyCSRFToken($token)
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return verify_post_check($token, true) === true;
    }

    /**
     * CSRF-Token für Formulare/AJAX erzeugen (MyBB my_post_key).
     * @return string
     */
    public static function generateCSRFToken()
    {
        global $mybb;

        return isset($mybb->post_code) ? (string)$mybb->post_code : '';
    }
    
    /**
     * Check if user is logged in
     * @return bool
     */
    public static function isUserLoggedIn()
    {
        global $mybb;
        
        return isset($mybb->user['uid']) && (int)$mybb->user['uid'] > 0;
    }
    
    /**
     * Get current user ID (safe)
     * @return int|null
     */
    public static function getCurrentUserID()
    {
        global $mybb;
        
        if (self::isUserLoggedIn()) {
            return (int)$mybb->user['uid'];
        }
        
        return null;
    }
    
    /**
     * Verify file upload
     * @param array $file $_FILES array element
     * @param array $allowed_extensions
     * @param int $max_size bytes
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function verifyFileUpload($file, $allowed_extensions, $max_size = 524288)
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        // Get file extension
        $filename = basename($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($ext, $allowed_extensions, true)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // MIME-Typ muss ein Bild sein UND zur Dateiendung passen
        // (verhindert z.B. image.php mit PNG-Inhalt, falls die Endungsliste falsch gepflegt ist)
        $allowed_mimes = [
            'image/png'  => ['png'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/gif'  => ['gif'],
            'image/webp' => ['webp'],
        ];

        if (!isset($allowed_mimes[$mime]) || !in_array($ext, $allowed_mimes[$mime], true)) {
            return ['valid' => false, 'error' => 'Invalid MIME type'];
        }

        return ['valid' => true];
    }
    
    /**
     * Get safe filename for upload
     * @param string $original_filename
     * @return string
     */
    public static function getSafeFilename($original_filename)
    {
        // Remove any path components
        $filename = basename($original_filename);
        
        // Keep only alphanumeric, dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        // Remove leading/trailing dots
        $filename = trim($filename, '.');
        
        // Prepend timestamp to ensure uniqueness
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        return time() . '_' . md5($name) . '.' . $ext;
    }
}

/**
 * Asset Helper Class
 * Manages file uploads and assets
 * 
 * @package rpgmaps
 */
class RPGMapsAsset
{
    // Can be customized in settings
    protected static $mapPath = 'inc/plugins/rpgmaps/assets/maps/';
    protected static $housePath = 'inc/plugins/rpgmaps/assets/houses/';
    
    /**
     * Get absolute path to maps directory
     * @return string
     */
    public static function getMapDirectory()
    {
        return MYBB_ROOT . self::$mapPath;
    }
    
    /**
     * Get absolute path to houses directory
     * @return string
     */
    public static function getHouseDirectory()
    {
        return MYBB_ROOT . self::$housePath;
    }
    
    /**
     * Upload map image
     * @param array $file
     * @return array ['success' => bool, 'filename' => string, 'error' => string]
     */
    public static function uploadMapImage($file)
    {
        global $mybb;
        
        $max_kb = (int)($mybb->settings['rpgmaps_max_upload_size'] ?? 0);
        $max_size = ($max_kb > 0 ? $max_kb : 512) * 1024; // KB -> Bytes
        $allowed_extensions = array_filter(array_map('trim', explode(',', strtolower((string)($mybb->settings['rpgmaps_allowed_extensions'] ?? 'png,jpg,jpeg,gif')))));
        
        // Verify upload
        $verify = RPGMapsSecurity::verifyFileUpload($file, $allowed_extensions, $max_size);
        
        if (!$verify['valid']) {
            return ['success' => false, 'error' => $verify['error']];
        }
        
        // Generate safe filename
        $filename = RPGMapsSecurity::getSafeFilename($file['name']);
        $path = self::getMapDirectory() . $filename;
        
        // Ensure directory exists
        if (!is_dir(self::getMapDirectory())) {
            mkdir(self::getMapDirectory(), 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
        // Set proper permissions
        @chmod($path, 0644);
        
        return ['success' => true, 'filename' => $filename];
    }
    
    /**
     * Upload house image
     * @param array $file
     * @return array ['success' => bool, 'filename' => string, 'error' => string]
     */
    public static function uploadHouseImage($file)
    {
        global $mybb;
        
        $max_kb = (int)($mybb->settings['rpgmaps_max_upload_size'] ?? 0);
        $max_size = ($max_kb > 0 ? $max_kb : 512) * 1024; // KB -> Bytes
        $allowed_extensions = array_filter(array_map('trim', explode(',', strtolower((string)($mybb->settings['rpgmaps_allowed_extensions'] ?? 'png,jpg,jpeg,gif')))));
        
        // Verify upload
        $verify = RPGMapsSecurity::verifyFileUpload($file, $allowed_extensions, $max_size);
        
        if (!$verify['valid']) {
            return ['success' => false, 'error' => $verify['error']];
        }
        
        // Generate safe filename
        $filename = RPGMapsSecurity::getSafeFilename($file['name']);
        $path = self::getHouseDirectory() . $filename;
        
        // Ensure directory exists
        if (!is_dir(self::getHouseDirectory())) {
            mkdir(self::getHouseDirectory(), 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
        // Set proper permissions
        @chmod($path, 0644);
        
        return ['success' => true, 'filename' => $filename];
    }
    
    /**
     * Delete a file
     * @param string $filename
     * @param string $type 'map' or 'house'
     * @return bool
     */
    public static function deleteFile($filename, $type = 'map')
    {
        if ($type === 'house') {
            $path = self::getHouseDirectory() . basename($filename);
        } else {
            $path = self::getMapDirectory() . basename($filename);
        }
        
        if (file_exists($path) && is_file($path)) {
            return @unlink($path);
        }
        
        return false;
    }
}
