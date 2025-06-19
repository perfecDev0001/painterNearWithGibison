<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';

$auth = new GibsonAuth();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Cleanup execution flag
$runCleanup = isset($_POST['run_cleanup']) && $_POST['run_cleanup'] === '1';
$cleanupOptions = $_POST['cleanup_options'] ?? [];
$analysisResults = [];
$cleanupResults = [];

// Disk analysis
$analysisResults = analyzeDiskUsage();

if ($runCleanup && !empty($cleanupOptions)) {
    $cleanupResults = performCleanup($cleanupOptions);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function analyzeDiskUsage() {
    $analysis = [
        'total_space' => disk_total_space(__DIR__),
        'free_space' => disk_free_space(__DIR__),
        'used_space' => 0,
        'directories' => [],
        'cleanable_items' => []
    ];
    
    $analysis['used_space'] = $analysis['total_space'] - $analysis['free_space'];
    $analysis['usage_percent'] = ($analysis['used_space'] / $analysis['total_space']) * 100;
    
    // Analyze key directories
    $dirsToAnalyze = [
        'logs' => __DIR__ . '/logs',
        'vendor' => __DIR__ . '/vendor',
        'assets' => __DIR__ . '/assets',
        'uploads' => __DIR__ . '/uploads',
        'cache' => __DIR__ . '/cache',
        'temp' => sys_get_temp_dir(),
        'sessions' => session_save_path() ?: '/tmp'
    ];
    
    foreach ($dirsToAnalyze as $name => $path) {
        if (is_dir($path)) {
            $size = getDirSize($path);
            $fileCount = countFiles($path);
            $analysis['directories'][$name] = [
                'path' => $path,
                'size' => $size,
                'size_formatted' => formatBytes($size),
                'file_count' => $fileCount,
                'exists' => true
            ];
        } else {
            $analysis['directories'][$name] = [
                'path' => $path,
                'size' => 0,
                'size_formatted' => '0 B',
                'file_count' => 0,
                'exists' => false
            ];
        }
    }
    
    // Identify cleanable items
    $analysis['cleanable_items'] = identifyCleanableItems();
    
    return $analysis;
}

function getDirSize($dir) {
    $size = 0;
    try {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $size += $file->getSize();
                }
            }
        }
    } catch (Exception $e) {
        // Directory not accessible
    }
    return $size;
}

function countFiles($dir) {
    $count = 0;
    try {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
    } catch (Exception $e) {
        // Directory not accessible
    }
    return $count;
}

function identifyCleanableItems() {
    $items = [];
    
    // Log files older than 30 days
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/*.log');
        $oldLogSize = 0;
        $oldLogCount = 0;
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < strtotime('-30 days')) {
                $oldLogSize += filesize($logFile);
                $oldLogCount++;
            }
        }
        
        if ($oldLogCount > 0) {
            $items['old_logs'] = [
                'name' => 'Log files older than 30 days',
                'count' => $oldLogCount,
                'size' => $oldLogSize,
                'size_formatted' => formatBytes($oldLogSize),
                'safe' => true,
                'description' => 'Email logs, error logs, and system logs older than 30 days'
            ];
        }
    }
    
    // Large log files (>10MB)
    if (is_dir($logDir)) {
        $largeLogFiles = [];
        $largeLogSize = 0;
        
        foreach (glob($logDir . '/*.log') as $logFile) {
            $fileSize = filesize($logFile);
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                $largeLogFiles[] = basename($logFile);
                $largeLogSize += $fileSize;
            }
        }
        
        if (!empty($largeLogFiles)) {
            $items['large_logs'] = [
                'name' => 'Large log files (>10MB)',
                'count' => count($largeLogFiles),
                'size' => $largeLogSize,
                'size_formatted' => formatBytes($largeLogSize),
                'safe' => false,
                'description' => 'Large log files that can be truncated: ' . implode(', ', $largeLogFiles)
            ];
        }
    }
    
    // Temporary PHP session files
    $sessionPath = session_save_path() ?: '/tmp';
    if (is_dir($sessionPath)) {
        $sessionFiles = glob($sessionPath . '/sess_*');
        $oldSessionSize = 0;
        $oldSessionCount = 0;
        
        foreach ($sessionFiles as $sessionFile) {
            if (filemtime($sessionFile) < strtotime('-7 days')) {
                $oldSessionSize += filesize($sessionFile);
                $oldSessionCount++;
            }
        }
        
        if ($oldSessionCount > 0) {
            $items['old_sessions'] = [
                'name' => 'Old PHP session files (>7 days)',
                'count' => $oldSessionCount,
                'size' => $oldSessionSize,
                'size_formatted' => formatBytes($oldSessionSize),
                'safe' => true,
                'description' => 'Expired PHP session files older than 7 days'
            ];
        }
    }
    
    // Cache files
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        $cacheSize = getDirSize($cacheDir);
        $cacheCount = countFiles($cacheDir);
        
        if ($cacheSize > 0) {
            $items['cache_files'] = [
                'name' => 'Cache files',
                'count' => $cacheCount,
                'size' => $cacheSize,
                'size_formatted' => formatBytes($cacheSize),
                'safe' => true,
                'description' => 'Application cache files that can be regenerated'
            ];
        }
    }
    
    // Error logs
    $errorLogs = [
        __DIR__ . '/error.log',
        __DIR__ . '/php_errors.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log'
    ];
    
    $errorLogSize = 0;
    $errorLogCount = 0;
    $errorLogFiles = [];
    
    foreach ($errorLogs as $errorLog) {
        if (file_exists($errorLog) && is_writable($errorLog)) {
            $fileSize = filesize($errorLog);
            if ($fileSize > 1024 * 1024) { // > 1MB
                $errorLogSize += $fileSize;
                $errorLogCount++;
                $errorLogFiles[] = basename($errorLog);
            }
        }
    }
    
    if ($errorLogCount > 0) {
        $items['error_logs'] = [
            'name' => 'Large error log files',
            'count' => $errorLogCount,
            'size' => $errorLogSize,
            'size_formatted' => formatBytes($errorLogSize),
            'safe' => false,
            'description' => 'Error log files that can be truncated: ' . implode(', ', $errorLogFiles)
        ];
    }
    
    return $items;
}

function performCleanup($options) {
    $results = [];
    $totalCleaned = 0;
    $totalFiles = 0;
    
    foreach ($options as $option) {
        switch ($option) {
            case 'old_logs':
                $result = cleanOldLogs();
                $results['old_logs'] = $result;
                $totalCleaned += $result['size_cleaned'];
                $totalFiles += $result['files_cleaned'];
                break;
                
            case 'large_logs':
                $result = truncateLargeLogs();
                $results['large_logs'] = $result;
                $totalCleaned += $result['size_cleaned'];
                $totalFiles += $result['files_cleaned'];
                break;
                
            case 'old_sessions':
                $result = cleanOldSessions();
                $results['old_sessions'] = $result;
                $totalCleaned += $result['size_cleaned'];
                $totalFiles += $result['files_cleaned'];
                break;
                
            case 'cache_files':
                $result = cleanCacheFiles();
                $results['cache_files'] = $result;
                $totalCleaned += $result['size_cleaned'];
                $totalFiles += $result['files_cleaned'];
                break;
                
            case 'error_logs':
                $result = truncateErrorLogs();
                $results['error_logs'] = $result;
                $totalCleaned += $result['size_cleaned'];
                $totalFiles += $result['files_cleaned'];
                break;
        }
    }
    
    $results['summary'] = [
        'total_size_cleaned' => $totalCleaned,
        'total_files_cleaned' => $totalFiles,
        'total_size_formatted' => formatBytes($totalCleaned)
    ];
    
    return $results;
}

function cleanOldLogs() {
    $logDir = __DIR__ . '/logs';
    $sizeFreed = 0;
    $filesRemoved = 0;
    $errors = [];
    
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/*.log');
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < strtotime('-30 days')) {
                $fileSize = filesize($logFile);
                if (unlink($logFile)) {
                    $sizeFreed += $fileSize;
                    $filesRemoved++;
                } else {
                    $errors[] = 'Failed to delete: ' . basename($logFile);
                }
            }
        }
    }
    
    return [
        'files_cleaned' => $filesRemoved,
        'size_cleaned' => $sizeFreed,
        'size_formatted' => formatBytes($sizeFreed),
        'errors' => $errors,
        'success' => empty($errors)
    ];
}

function truncateLargeLogs() {
    $logDir = __DIR__ . '/logs';
    $sizeFreed = 0;
    $filesProcessed = 0;
    $errors = [];
    
    if (is_dir($logDir)) {
        foreach (glob($logDir . '/*.log') as $logFile) {
            $fileSize = filesize($logFile);
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                $lines = file($logFile);
                if ($lines !== false) {
                    // Keep only the last 1000 lines
                    $keepLines = array_slice($lines, -1000);
                    if (file_put_contents($logFile, implode('', $keepLines))) {
                        $newSize = filesize($logFile);
                        $sizeFreed += ($fileSize - $newSize);
                        $filesProcessed++;
                    } else {
                        $errors[] = 'Failed to truncate: ' . basename($logFile);
                    }
                } else {
                    $errors[] = 'Failed to read: ' . basename($logFile);
                }
            }
        }
    }
    
    return [
        'files_cleaned' => $filesProcessed,
        'size_cleaned' => $sizeFreed,
        'size_formatted' => formatBytes($sizeFreed),
        'errors' => $errors,
        'success' => empty($errors)
    ];
}

function cleanOldSessions() {
    $sessionPath = session_save_path() ?: '/tmp';
    $sizeFreed = 0;
    $filesRemoved = 0;
    $errors = [];
    
    if (is_dir($sessionPath)) {
        $sessionFiles = glob($sessionPath . '/sess_*');
        
        foreach ($sessionFiles as $sessionFile) {
            if (filemtime($sessionFile) < strtotime('-7 days')) {
                $fileSize = filesize($sessionFile);
                if (unlink($sessionFile)) {
                    $sizeFreed += $fileSize;
                    $filesRemoved++;
                } else {
                    $errors[] = 'Failed to delete session: ' . basename($sessionFile);
                }
            }
        }
    }
    
    return [
        'files_cleaned' => $filesRemoved,
        'size_cleaned' => $sizeFreed,
        'size_formatted' => formatBytes($sizeFreed),
        'errors' => $errors,
        'success' => empty($errors)
    ];
}

function cleanCacheFiles() {
    $cacheDir = __DIR__ . '/cache';
    $sizeFreed = 0;
    $filesRemoved = 0;
    $errors = [];
    
    if (is_dir($cacheDir)) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $fileSize = $file->getSize();
                    if (unlink($file->getPathname())) {
                        $sizeFreed += $fileSize;
                        $filesRemoved++;
                    }
                } elseif ($file->isDir()) {
                    @rmdir($file->getPathname());
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Cache cleanup error: ' . $e->getMessage();
        }
    }
    
    return [
        'files_cleaned' => $filesRemoved,
        'size_cleaned' => $sizeFreed,
        'size_formatted' => formatBytes($sizeFreed),
        'errors' => $errors,
        'success' => empty($errors)
    ];
}

function truncateErrorLogs() {
    $errorLogs = [
        __DIR__ . '/error.log',
        __DIR__ . '/php_errors.log'
    ];
    
    $sizeFreed = 0;
    $filesProcessed = 0;
    $errors = [];
    
    foreach ($errorLogs as $errorLog) {
        if (file_exists($errorLog) && is_writable($errorLog)) {
            $fileSize = filesize($errorLog);
            if ($fileSize > 1024 * 1024) { // > 1MB
                if (file_put_contents($errorLog, '')) {
                    $sizeFreed += $fileSize;
                    $filesProcessed++;
                } else {
                    $errors[] = 'Failed to truncate: ' . basename($errorLog);
                }
            }
        }
    }
    
    return [
        'files_cleaned' => $filesProcessed,
        'size_cleaned' => $sizeFreed,
        'size_formatted' => formatBytes($sizeFreed),
        'errors' => $errors,
        'success' => empty($errors)
    ];
}

include 'templates/header.php';
?>
<head>
    <title>Disk Cleanup | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Disk cleanup utility for managing log files and temporary data on Painter Near Me." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>
<div class="admin-layout">
    <?php include 'templates/sidebar-admin.php'; ?>
    <main class="admin-main" role="main">
        <section class="admin-dashboard hero admin-card">
            <h1 class="hero__title">Disk Cleanup Utility</h1>
            <p class="hero__subtitle">Manage disk space by cleaning log files and temporary data</p>
        </section>

        <!-- Disk Usage Overview -->
        <section class="admin-card">
            <h2 class="cleanup__section-title">
                <i class="bi bi-pie-chart-fill"></i> Disk Usage Overview
            </h2>
            <div class="cleanup__overview">
                <div class="cleanup__overview-stats">
                    <div class="cleanup__stat">
                        <div class="cleanup__stat-value"><?php echo formatBytes($analysisResults['total_space']); ?></div>
                        <div class="cleanup__stat-label">Total Space</div>
                    </div>
                    <div class="cleanup__stat cleanup__stat--used">
                        <div class="cleanup__stat-value"><?php echo formatBytes($analysisResults['used_space']); ?></div>
                        <div class="cleanup__stat-label">Used Space</div>
                    </div>
                    <div class="cleanup__stat cleanup__stat--free">
                        <div class="cleanup__stat-value"><?php echo formatBytes($analysisResults['free_space']); ?></div>
                        <div class="cleanup__stat-label">Free Space</div>
                    </div>
                    <div class="cleanup__stat cleanup__stat--percent">
                        <div class="cleanup__stat-value"><?php echo number_format($analysisResults['usage_percent'], 1); ?>%</div>
                        <div class="cleanup__stat-label">Usage</div>
                    </div>
                </div>
                
                <div class="cleanup__usage-bar">
                    <div class="cleanup__usage-fill" style="width: <?php echo min($analysisResults['usage_percent'], 100); ?>%"></div>
                </div>
            </div>
        </section>

        <!-- Directory Analysis -->
        <section class="admin-card">
            <h2 class="cleanup__section-title">
                <i class="bi bi-folder-fill"></i> Directory Analysis
            </h2>
            <div class="cleanup__directories">
                <?php foreach ($analysisResults['directories'] as $name => $dir): ?>
                <div class="cleanup__directory">
                    <div class="cleanup__directory-info">
                        <div class="cleanup__directory-name">
                            <i class="bi bi-folder2-open"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $name)); ?>
                        </div>
                        <div class="cleanup__directory-path"><?php echo htmlspecialchars($dir['path']); ?></div>
                    </div>
                    <div class="cleanup__directory-stats">
                        <?php if ($dir['exists']): ?>
                            <span class="cleanup__directory-size"><?php echo $dir['size_formatted']; ?></span>
                            <span class="cleanup__directory-files"><?php echo number_format($dir['file_count']); ?> files</span>
                        <?php else: ?>
                            <span class="cleanup__directory-missing">Not found</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Cleanup Options -->
        <section class="admin-card">
            <h2 class="cleanup__section-title">
                <i class="bi bi-trash-fill"></i> Cleanup Options
            </h2>
            
            <?php if (!empty($analysisResults['cleanable_items'])): ?>
                <form method="post" class="cleanup__form" id="cleanupForm">
                    <input type="hidden" name="run_cleanup" value="1">
                    
                    <div class="cleanup__items">
                        <?php foreach ($analysisResults['cleanable_items'] as $key => $item): ?>
                        <div class="cleanup__item">
                            <div class="cleanup__item-checkbox">
                                <input type="checkbox" id="cleanup_<?php echo $key; ?>" name="cleanup_options[]" value="<?php echo $key; ?>" class="cleanup__checkbox">
                                <label for="cleanup_<?php echo $key; ?>" class="cleanup__checkbox-label">
                                    <div class="cleanup__item-info">
                                        <div class="cleanup__item-title">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <?php if (!$item['safe']): ?>
                                                <span class="cleanup__warning-badge">⚠️ Caution</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cleanup__item-description">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                        </div>
                                    </div>
                                    <div class="cleanup__item-stats">
                                        <span class="cleanup__item-size"><?php echo $item['size_formatted']; ?></span>
                                        <span class="cleanup__item-count"><?php echo number_format($item['count']); ?> items</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cleanup__actions">
                        <button type="submit" class="btn btn-warning cleanup__run-btn" onclick="return confirmCleanup()">
                            <i class="bi bi-trash-fill"></i> Run Selected Cleanup
                        </button>
                        <a href="admin-leads.php" class="btn btn-outline-success">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="cleanup__no-items">
                    <i class="bi bi-check-circle-fill cleanup__no-items-icon"></i>
                    <h3>No Cleanup Needed</h3>
                    <p>Your system is clean! No large files or old temporary data found.</p>
                    <a href="admin-leads.php" class="btn btn-outline-success">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Cleanup Results -->
        <?php if ($runCleanup && !empty($cleanupResults)): ?>
        <section class="admin-card">
            <h2 class="cleanup__section-title">
                <i class="bi bi-check-circle-fill"></i> Cleanup Results
            </h2>
            
            <div class="cleanup__results-summary">
                <div class="cleanup__results-stat">
                    <div class="cleanup__results-value"><?php echo $cleanupResults['summary']['total_size_formatted']; ?></div>
                    <div class="cleanup__results-label">Space Freed</div>
                </div>
                <div class="cleanup__results-stat">
                    <div class="cleanup__results-value"><?php echo number_format($cleanupResults['summary']['total_files_cleaned']); ?></div>
                    <div class="cleanup__results-label">Files Processed</div>
                </div>
            </div>
            
            <div class="cleanup__results-details">
                <?php foreach ($cleanupResults as $key => $result): ?>
                    <?php if ($key !== 'summary'): ?>
                    <div class="cleanup__result-item cleanup__result-item--<?php echo $result['success'] ? 'success' : 'error'; ?>">
                        <div class="cleanup__result-header">
                            <i class="bi bi-<?php echo $result['success'] ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                            <span class="cleanup__result-title"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></span>
                        </div>
                        <div class="cleanup__result-stats">
                            <span><?php echo $result['size_formatted']; ?> freed</span>
                            <span><?php echo $result['files_cleaned']; ?> files processed</span>
                        </div>
                        <?php if (!empty($result['errors'])): ?>
                        <div class="cleanup__result-errors">
                            <strong>Errors:</strong>
                            <ul>
                                <?php foreach ($result['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="cleanup__results-actions">
                <a href="admin-disk-cleanup.php" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Analysis
                </a>
                <a href="admin-system-test.php" class="btn btn-outline-success">
                    <i class="bi bi-gear-fill"></i> Run System Tests
                </a>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<script>
function confirmCleanup() {
    const selectedItems = document.querySelectorAll('input[name="cleanup_options[]"]:checked');
    if (selectedItems.length === 0) {
        alert('Please select at least one cleanup option.');
        return false;
    }
    
    const hasRiskyOptions = Array.from(selectedItems).some(item => {
        const label = item.parentNode.querySelector('.cleanup__warning-badge');
        return label !== null;
    });
    
    let message = `Are you sure you want to clean up ${selectedItems.length} selected item(s)?`;
    if (hasRiskyOptions) {
        message += '\n\n⚠️ WARNING: Some selected options may affect system functionality. Make sure you have backups!';
    }
    message += '\n\nThis action cannot be undone.';
    
    return confirm(message);
}

// Auto-refresh disk usage every 30 seconds if no cleanup is running
<?php if (!$runCleanup): ?>
setTimeout(function() {
    if (!document.querySelector('.cleanup__results-summary')) {
        window.location.reload();
    }
}, 30000);
<?php endif; ?>
</script>

<style>
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: #f7fafc;
}

.admin-main {
    flex: 1;
    padding: 2.5rem 2rem 2rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
    background: #f7fafc;
}

.admin-card {
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08);
    padding: 2rem 1.5rem;
    margin-bottom: 2rem;
}

.cleanup__section-title {
    color: #00b050;
    margin-bottom: 1.5rem;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cleanup__overview {
    background: #f8f9fa;
    border-radius: 1rem;
    padding: 1.5rem;
}

.cleanup__overview-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.cleanup__stat {
    text-align: center;
    background: white;
    padding: 1rem;
    border-radius: 0.8rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cleanup__stat--used { border-top: 4px solid #dc3545; }
.cleanup__stat--free { border-top: 4px solid #28a745; }
.cleanup__stat--percent { border-top: 4px solid #ffc107; }

.cleanup__stat-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #222;
}

.cleanup__stat-label {
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.25rem;
}

.cleanup__usage-bar {
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.cleanup__usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #ffc107 70%, #dc3545 90%);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.cleanup__directories {
    display: grid;
    gap: 1rem;
}

.cleanup__directory {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.8rem;
    border-left: 4px solid #00b050;
}

.cleanup__directory-name {
    font-weight: 600;
    color: #00b050;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cleanup__directory-path {
    font-size: 0.9rem;
    color: #666;
    font-family: monospace;
}

.cleanup__directory-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.cleanup__directory-size {
    font-weight: 700;
    color: #222;
}

.cleanup__directory-files {
    font-size: 0.9rem;
    color: #666;
}

.cleanup__directory-missing {
    color: #999;
    font-style: italic;
}

.cleanup__form {
    display: grid;
    gap: 1.5rem;
}

.cleanup__items {
    display: grid;
    gap: 1rem;
}

.cleanup__item {
    border: 2px solid #e9ecef;
    border-radius: 0.8rem;
    overflow: hidden;
    transition: border-color 0.2s;
}

.cleanup__item:hover {
    border-color: #00b050;
}

.cleanup__checkbox {
    display: none;
}

.cleanup__checkbox:checked + .cleanup__checkbox-label {
    background: #e6f7ea;
    border-color: #00b050;
}

.cleanup__checkbox-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    cursor: pointer;
    background: white;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.cleanup__checkbox-label:hover {
    background: #f8f9fa;
}

.cleanup__item-title {
    font-weight: 600;
    color: #222;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cleanup__warning-badge {
    font-size: 0.8rem;
    background: #fff3cd;
    color: #856404;
    padding: 0.2rem 0.5rem;
    border-radius: 0.3rem;
    border: 1px solid #ffeaa7;
}

.cleanup__item-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

.cleanup__item-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.cleanup__item-size {
    font-weight: 700;
    color: #dc3545;
    font-size: 1.1rem;
}

.cleanup__item-count {
    color: #666;
    font-size: 0.9rem;
}

.cleanup__actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.cleanup__run-btn {
    background: #ffc107;
    color: #212529;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: background 0.3s ease;
}

.cleanup__run-btn:hover {
    background: #ffca2c;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-outline-success {
    background: transparent;
    color: #00b050;
    border-color: #00b050;
}

.btn-outline-success:hover {
    background: #00b050;
    color: white;
}

.cleanup__no-items {
    text-align: center;
    padding: 3rem 2rem;
    color: #666;
}

.cleanup__no-items-icon {
    font-size: 3rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.cleanup__results-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #d4edda;
    border-radius: 1rem;
    border: 2px solid #c3e6cb;
}

.cleanup__results-stat {
    text-align: center;
}

.cleanup__results-value {
    font-size: 2rem;
    font-weight: 900;
    color: #155724;
}

.cleanup__results-label {
    color: #155724;
    font-weight: 600;
}

.cleanup__results-details {
    display: grid;
    gap: 1rem;
    margin-bottom: 2rem;
}

.cleanup__result-item {
    padding: 1rem;
    border-radius: 0.8rem;
    border-left: 4px solid;
}

.cleanup__result-item--success {
    background: #d4edda;
    border-color: #28a745;
}

.cleanup__result-item--error {
    background: #f8d7da;
    border-color: #dc3545;
}

.cleanup__result-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.cleanup__result-title {
    font-weight: 600;
}

.cleanup__result-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #666;
}

.cleanup__result-errors {
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.cleanup__result-errors ul {
    margin: 0.5rem 0 0 1rem;
}

.cleanup__results-actions {
    display: flex;
    gap: 1rem;
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .cleanup__overview-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .cleanup__checkbox-label {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .cleanup__item-stats {
        align-items: flex-start;
    }
    
    .cleanup__actions {
        flex-direction: column;
    }
    
    .cleanup__results-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'templates/footer.php'; ?>
</code_block_to_apply_changes_from>