<?php
/**
 * Cache Clearing Utility
 * Run this script to clear all cached data for performance optimization
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

$cacheDir = __DIR__ . '/cache';

if (!is_dir($cacheDir)) {
    echo "Cache directory does not exist.\n";
    exit(0);
}

$files = glob($cacheDir . '/*');
$cleared = 0;

foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        $cleared++;
    }
}

echo "Cleared $cleared cache files.\n";
echo "Cache cleared successfully!\n";
?>