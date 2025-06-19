<?php
namespace Core;

class UploadConfig {
    
    /**
     * Initialize upload configuration
     * Attempts to increase PHP limits programmatically where possible
     */
    public static function initialize() {
        // Only attempt to modify settings if we have permission
        if (function_exists('ini_set')) {
            // Try to increase limits (may be restricted by hosting provider)
            @ini_set('upload_max_filesize', '10M');
            @ini_set('post_max_size', '25M');
            @ini_set('max_file_uploads', '5');
            @ini_set('max_execution_time', '300');
            @ini_set('max_input_time', '300');
            @ini_set('memory_limit', '256M');
        }
    }
    
    /**
     * Get current upload limits
     * @return array Current PHP upload configuration
     */
    public static function getCurrentLimits() {
        return [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
    }
    
    /**
     * Get maximum upload size in bytes
     * @return int Maximum upload size in bytes
     */
    public static function getMaxUploadSize() {
        $upload_max = self::convertToBytes(ini_get('upload_max_filesize'));
        $post_max = self::convertToBytes(ini_get('post_max_size'));
        
        // Return the smaller of the two limits
        return min($upload_max, $post_max);
    }
    
    /**
     * Get maximum upload size in MB
     * @return float Maximum upload size in MB
     */
    public static function getMaxUploadSizeMB() {
        return round(self::getMaxUploadSize() / 1024 / 1024, 1);
    }
    
    /**
     * Convert PHP size notation to bytes
     * @param string $val Size string (e.g., "10M", "256K")
     * @return int Size in bytes
     */
    private static function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (float) $val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return (int) $val;
    }
    
    /**
     * Check if upload size is acceptable
     * @param int $fileSize File size in bytes
     * @return bool True if size is acceptable
     */
    public static function isFileSizeAcceptable($fileSize) {
        return $fileSize <= self::getMaxUploadSize();
    }
} 