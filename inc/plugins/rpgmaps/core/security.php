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
     * Verify CSRF token
     * @param string $token
     * @return bool
     */
    public static function verifyCSRFToken($token)
    {
        global $mybb;
        
        if (empty($token)) {
            return false;
        }
        
        // Get the session token from the session
        if (!isset($mybb->session) || empty($mybb->session->sid)) {
            return false;
        }
        
        // Verify token matches
        return $token === $mybb->session->sid;
    }
    
    /**
     * Generate CSRF token for forms
     * @return string
     */
    public static function generateCSRFToken()
    {
        global $mybb;
        
        if (!isset($mybb->session) || empty($mybb->session->sid)) {
            return '';
        }
        
        return $mybb->session->sid;
    }
    
    /**
     * Validate input - string
     * @param string $input
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function validateString($input, $min = 1, $max = 255)
    {
        if (!is_string($input)) {
            return false;
        }
        
        $len = mb_strlen($input);
        return $len >= $min && $len <= $max;
    }
    
    /**
     * Validate input - integer
     * @param mixed $input
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function validateInteger($input, $min = 0, $max = PHP_INT_MAX)
    {
        $int = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return false;
        }
        
        return $int >= $min && $int <= $max;
    }
    
    /**
     * Validate coordinate
     * @param int $value
     * @param int $max
     * @return bool
     */
    public static function validateCoordinate($value, $max = 10000)
    {
        return self::validateInteger($value, 0, $max);
    }
    
    /**
     * Validate email
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Sanitize string for database
     * @param string $str
     * @return string
     */
    public static function sanitizeString($str)
    {
        global $db;
        return $db->escape_string($str);
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
     * Check admin permission
     * @param string $permission
     * @return bool
     */
    public static function checkAdminPermission($permission = 'rpgmaps')
    {
        global $admin;
        
        if (!isset($admin)) {
            return false;
        }
        
        // Check if admin has can_manage_rpgmaps permission
        if (!isset($admin->permissions[$permission])) {
            return false;
        }
        
        return (bool)$admin->permissions[$permission];
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
        
        $allowed_mimes = [
            'image/png' => 'png',
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/gif' => 'gif',
        ];
        
        $valid_mime = false;
        foreach ($allowed_mimes as $mime_type => $exts) {
            if ($mime === $mime_type) {
                $valid_mime = true;
                break;
            }
        }
        
        if (!$valid_mime) {
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
     * Get relative URL to maps directory
     * @return string
     */
    public static function getMapURL()
    {
        return $GLOBALS['mybb']->settings['bburl'] . '/' . self::$mapPath;
    }
    
    /**
     * Get relative URL to houses directory
     * @return string
     */
    public static function getHouseURL()
    {
        return $GLOBALS['mybb']->settings['bburl'] . '/' . self::$housePath;
    }
    
    /**
     * Upload map image
     * @param array $file
     * @return array ['success' => bool, 'filename' => string, 'error' => string]
     */
    public static function uploadMapImage($file)
    {
        global $mybb;
        
        $max_size = (int)$mybb->settings['rpgmaps_max_upload_size'] * 1024; // Convert KB to bytes
        $allowed_extensions = explode(',', $mybb->settings['rpgmaps_allowed_extensions']);
        $allowed_extensions = array_map('trim', $allowed_extensions);
        
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
            @mkdir(self::getMapDirectory(), 0755, true);
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
        
        $max_size = (int)$mybb->settings['rpgmaps_max_upload_size'] * 1024; // Convert KB to bytes
        $allowed_extensions = explode(',', $mybb->settings['rpgmaps_allowed_extensions']);
        $allowed_extensions = array_map('trim', $allowed_extensions);
        
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
            @mkdir(self::getHouseDirectory(), 0755, true);
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
    
    /**
     * Check if map file exists
     * @param string $filename
     * @return bool
     */
    public static function mapFileExists($filename)
    {
        $path = self::getMapDirectory() . basename($filename);
        return file_exists($path) && is_file($path);
    }
    
    /**
     * Check if house file exists
     * @param string $filename
     * @return bool
     */
    public static function houseFileExists($filename)
    {
        $path = self::getHouseDirectory() . basename($filename);
        return file_exists($path) && is_file($path);
    }
}
