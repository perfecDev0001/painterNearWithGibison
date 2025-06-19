<?php
// Debug 500 Error - Simple Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Debug 500 Error</title></head><body>";
echo "<h1>500 Error Debug</h1>";

// Test 1: Basic PHP
echo "<h2>✅ PHP is working</h2>";

// Test 2: Check if bootstrap.php exists and loads
echo "<h2>Test 2: Bootstrap File</h2>";
if (file_exists('bootstrap.php')) {
    echo "✅ bootstrap.php exists<br>";
    try {
        include_once 'bootstrap.php';
        echo "✅ bootstrap.php loaded successfully<br>";
    } catch (Exception $e) {
        echo "❌ bootstrap.php error: " . $e->getMessage() . "<br>";
    } catch (Error $e) {
        echo "❌ bootstrap.php fatal error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ bootstrap.php not found<br>";
}

// Test 3: Check project.env
echo "<h2>Test 3: Environment File</h2>";
if (file_exists('project.env')) {
    echo "✅ project.env exists<br>";
} else {
    echo "❌ project.env not found<br>";
}

// Test 4: Check core directory
echo "<h2>Test 4: Core Directory</h2>";
if (is_dir('core')) {
    echo "✅ core directory exists<br>";
    $files = scandir('core');
    echo "Core files: " . implode(', ', array_diff($files, ['.', '..'])) . "<br>";
} else {
    echo "❌ core directory not found<br>";
}

// Test 5: Check .htaccess
echo "<h2>Test 5: .htaccess File</h2>";
if (file_exists('.htaccess')) {
    echo "✅ .htaccess exists<br>";
    echo "Size: " . filesize('.htaccess') . " bytes<br>";
} else {
    echo "❌ .htaccess not found<br>";
}

// Test 6: Check index.php
echo "<h2>Test 6: Index File</h2>";
if (file_exists('index.php')) {
    echo "✅ index.php exists<br>";
} else {
    echo "❌ index.php not found<br>";
}

echo "</body></html>";
?> 