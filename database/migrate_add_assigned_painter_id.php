<?php
// Run this script once to add assigned_painter_id to leads table
require_once __DIR__ . '/../config/db.php';

// Check if column exists
$colExists = $conn->query("SHOW COLUMNS FROM leads LIKE 'assigned_painter_id'");
if ($colExists && $colExists->num_rows > 0) {
    echo "Column 'assigned_painter_id' already exists.\n";
    exit;
}
// Add the column
$sql = "ALTER TABLE leads ADD COLUMN assigned_painter_id INT DEFAULT NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column 'assigned_painter_id' added successfully.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
} 