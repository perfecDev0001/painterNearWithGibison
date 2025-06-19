<?php
// Asset serving script for when static files aren't served properly
// Usage: serve-asset.php?file=css/style.css or serve-asset.php?file=images/save-money.svg

if (!isset($_GET['file'])) {
    http_response_code(400);
    die('No file specified');
}

$file = $_GET['file'];
// Sanitize the file path to prevent directory traversal
$file = str_replace(['../', '..\\', '\\'], '', $file);

$basePath = __DIR__ . '/assets/';
$fullPath = $basePath . $file;

// Check if file exists and is within assets directory
if (!file_exists($fullPath) || strpos(realpath($fullPath), realpath($basePath)) !== 0) {
    http_response_code(404);
    die('File not found');
}

// Get file extension and set appropriate content type
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$contentTypes = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'ico' => 'image/x-icon'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000'); // 1 year cache

// Output the file
readfile($fullPath);
?> 