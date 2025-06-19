<?php
// Simple PHP Test - Check if basic PHP is working
echo "<h1>PHP Basic Test</h1>";
echo "<p>If you can see this, PHP is working!</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test if we can read files
if (file_exists('project.env')) {
    echo "<p>✅ project.env file found</p>";
} else {
    echo "<p>❌ project.env file not found</p>";
}

if (file_exists('bootstrap.php')) {
    echo "<p>✅ bootstrap.php file found</p>";
} else {
    echo "<p>❌ bootstrap.php file not found</p>";
}

if (file_exists('index.php')) {
    echo "<p>✅ index.php file found</p>";
} else {
    echo "<p>❌ index.php file not found</p>";
}

phpinfo();
?> 