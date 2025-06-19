<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Handle notification actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notificationId = intval($_POST['notification_id']);
        // In real implementation, this would update the notification status in database
        $actionMsg = 'Notification marked as read.';
    } elseif (isset($_POST['mark_all_read'])) {
        // Mark all notifications as read
        $actionMsg = 'All notifications marked as read.';
    } elseif (isset($_POST['delete_notification'])) {
        $notificationId = intval($_POST['notification_id']);
        // Delete notification
        $actionMsg = 'Notification deleted.';
    }
}

// Get notifications data
function getNotifications($dataAccess) {
    $notifications = [];
    
    // Get recent activities to generate notifications
    $recentLeads = $dataAccess->getRecentLeads(10);
    $recentBids = $dataAccess->getRecentBids(10);
    $stats = $dataAccess->getDashboardStats();
    
    // Generate notifications based on recent activity
    foreach ($recentLeads as $lead) {
        $notifications[] = [
            'id' => 'lead_' . $lead['id'],
            'type' => 'new_lead',
            'title' => 'New Lead Posted',
            'message' => "New lead '{$lead['job_title']}' from {$lead['customer_name']} in {$lead['location']}",
            'timestamp' => $lead['created_at'],
            'priority' => 'medium',
            'icon' => 'person-plus',
            'color' => 'blue',
            'action_url' => 'admin-manage-leads.php',
            'is_read' => false
        ];
    }
    
    foreach ($recentBids as $bid) {
        $urgency = $bid['bid_amount'] > 2000 ? 'high' : 'medium';
        $notifications[] = [
            'id' => 'bid_' . $bid['id'],
            'type' => 'new_bid',
            'title' => 'New Bid Received',
            'message' => "£" . number_format($bid['bid_amount'], 2) . " bid from {$bid['company_name']} on '{$bid['job_title']}'",
            'timestamp' => $bid['created_at'],
            'priority' => $urgency,
            'icon' => 'currency-pound',
            'color' => 'green',
            'action_url' => 'admin-manage-bids.php',
            'is_read' => false
        ];
    }
    
    // Add system notifications
    if ($stats['open_leads'] > 10) {
        $notifications[] = [
            'id' => 'system_high_leads',
            'type' => 'system_alert',
            'title' => 'High Lead Volume',
            'message' => "You have {$stats['open_leads']} open leads requiring attention",
            'timestamp' => date('Y-m-d H:i:s'),
            'priority' => 'high',
            'icon' => 'exclamation-triangle',
            'color' => 'orange',
            'action_url' => 'admin-manage-leads.php',
            'is_read' => false
        ];
    }
    
    // Sort by timestamp (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return $notifications;
}

function getNotificationStats($notifications) {
    $stats = [
        'total' => count($notifications),
        'unread' => count(array_filter($notifications, fn($n) => !$n['is_read'])),
        'high_priority' => count(array_filter($notifications, fn($n) => $n['priority'] === 'high')),
        'by_type' => []
    ];
    
    foreach ($notifications as $notification) {
        $type = $notification['type'];
        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
    }
    
    return $stats;
}

$notifications = getNotifications($dataAccess);
$notificationStats = getNotificationStats($notifications);

include 'templates/header.php';
?>
<head>
    <title>Notifications | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Real-time notifications and activity feed for Painter Near Me marketplace." />
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
        <section class="notifications-hero admin-card">
            <div class="notifications-header">
                <div>
                    <h1 class="hero__title">Notifications</h1>
                    <p class="hero__subtitle">Real-time activity feed and system alerts</p>
                </div>
                <div class="notifications-actions">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-success">
                            <i class="bi bi-check-all"></i> Mark All Read
                        </button>
                    </form>
                    <a href="admin-leads.php" class="btn btn-success">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if ($actionMsg): ?>
                <div class="alert alert-success notifications-alert" role="alert">
                    <?php echo htmlspecialchars($actionMsg); ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Notification Stats -->
        <section class="notifications-stats admin-card">
            <h2 class="notifications-section-title">Notification Overview</h2>
            <div class="notifications-stats-grid">
                <div class="notifications-stat">
                    <div class="notifications-stat-icon">🔔</div>
                    <div class="notifications-stat-value"><?php echo $notificationStats['total']; ?></div>
                    <div class="notifications-stat-label">Total Notifications</div>
                </div>
                <div class="notifications-stat">
                    <div class="notifications-stat-icon">📬</div>
                    <div class="notifications-stat-value"><?php echo $notificationStats['unread']; ?></div>
                    <div class="notifications-stat-label">Unread</div>
                </div>
                <div class="notifications-stat">
                    <div class="notifications-stat-icon">⚠️</div>
                    <div class="notifications-stat-value"><?php echo $notificationStats['high_priority']; ?></div>
                    <div class="notifications-stat-label">High Priority</div>
                </div>
                <div class="notifications-stat">
                    <div class="notifications-stat-icon">📊</div>
                    <div class="notifications-stat-value"><?php echo count($notificationStats['by_type']); ?></div>
                    <div class="notifications-stat-label">Types</div>
                </div>
            </div>
        </section>

        <!-- Notification Filters -->
        <section class="notifications-filters admin-card">
            <h2 class="notifications-section-title">Filter Notifications</h2>
            <div class="notifications-filter-buttons">
                <button class="notifications-filter-btn active" data-filter="all">
                    <i class="bi bi-list"></i> All Notifications
                </button>
                <button class="notifications-filter-btn" data-filter="new_lead">
                    <i class="bi bi-person-plus"></i> New Leads
                </button>
                <button class="notifications-filter-btn" data-filter="new_bid">
                    <i class="bi bi-currency-pound"></i> New Bids
                </button>
                <button class="notifications-filter-btn" data-filter="system_alert">
                    <i class="bi bi-exclamation-triangle"></i> System Alerts
                </button>
                <button class="notifications-filter-btn" data-filter="high">
                    <i class="bi bi-exclamation-circle"></i> High Priority
                </button>
            </div>
        </section>

        <!-- Notifications List -->
        <section class="notifications-list admin-card">
            <h2 class="notifications-section-title">Activity Feed</h2>
            
            <?php if (empty($notifications)): ?>
                <div class="notifications-empty">
                    <i class="bi bi-bell-slash notifications-empty-icon"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! No new notifications at this time.</p>
                </div>
            <?php else: ?>
                <div class="notifications-feed">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                         data-type="<?php echo $notification['type']; ?>" 
                         data-priority="<?php echo $notification['priority']; ?>">
                        <div class="notification-icon notification-icon--<?php echo $notification['color']; ?>">
                            <i class="bi bi-<?php echo $notification['icon']; ?>"></i>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <div class="notification-meta">
                                    <span class="notification-priority notification-priority--<?php echo $notification['priority']; ?>">
                                        <?php echo ucfirst($notification['priority']); ?>
                                    </span>
                                    <span class="notification-time">
                                        <?php echo $dataAccess->formatDateTime($notification['timestamp']); ?>
                                    </span>
                                </div>
                            </div>
                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                        </div>
                        
                        <div class="notification-actions">
                            <a href="<?php echo $notification['action_url']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <?php if (!$notification['is_read']): ?>
                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check"></i> Mark Read
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Real-time Settings -->
        <section class="notifications-settings admin-card">
            <h2 class="notifications-section-title">Notification Settings</h2>
            <div class="notifications-settings-grid">
                <div class="notifications-setting">
                    <h4>Email Notifications</h4>
                    <label class="notifications-toggle">
                        <input type="checkbox" checked>
                        <span class="notifications-slider"></span>
                    </label>
                    <p>Receive email alerts for high-priority notifications</p>
                </div>
                <div class="notifications-setting">
                    <h4>Browser Notifications</h4>
                    <label class="notifications-toggle">
                        <input type="checkbox" checked>
                        <span class="notifications-slider"></span>
                    </label>
                    <p>Show browser notifications for new activities</p>
                </div>
                <div class="notifications-setting">
                    <h4>Auto-refresh</h4>
                    <label class="notifications-toggle">
                        <input type="checkbox" checked>
                        <span class="notifications-slider"></span>
                    </label>
                    <p>Automatically refresh notifications every 30 seconds</p>
                </div>
            </div>
        </section>
    </main>
</div>

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

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.notifications-actions {
    display: flex;
    gap: 1rem;
}

.notifications-section-title {
    color: #00b050;
    margin-bottom: 1.5rem;
    font-size: 1.4rem;
    font-weight: 700;
}

.notifications-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.notifications-stat {
    background: linear-gradient(135deg, #f8fffe 0%, #e6f7ea 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid #e6f7ea;
}

.notifications-stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.notifications-stat-value {
    font-size: 1.8rem;
    font-weight: 900;
    color: #00b050;
    margin-bottom: 0.25rem;
}

.notifications-stat-label {
    color: #666;
    font-weight: 600;
    font-size: 0.9rem;
}

.notifications-filter-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.notifications-filter-btn {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    color: #666;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notifications-filter-btn:hover,
.notifications-filter-btn.active {
    background: #00b050;
    border-color: #00b050;
    color: white;
}

.notifications-empty {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.notifications-empty-icon {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 1rem;
}

.notifications-feed {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border: 2px solid #e9ecef;
    border-radius: 0.8rem;
    transition: border-color 0.3s ease;
}

.notification-item.unread {
    border-color: #00b050;
    background: #f8fffe;
}

.notification-item:hover {
    border-color: #00b050;
}

.notification-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.notification-icon--blue { background: #007bff; }
.notification-icon--green { background: #28a745; }
.notification-icon--orange { background: #fd7e14; }
.notification-icon--red { background: #dc3545; }

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.notification-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #333;
}

.notification-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.notification-priority {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.notification-priority--high { background: #f8d7da; color: #721c24; }
.notification-priority--medium { background: #fff3cd; color: #856404; }
.notification-priority--low { background: #d4edda; color: #155724; }

.notification-time {
    color: #666;
    font-size: 0.9rem;
}

.notification-message {
    margin: 0;
    color: #666;
    line-height: 1.5;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
}

.notifications-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.notifications-setting {
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notifications-toggle {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.notifications-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.notifications-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.notifications-slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .notifications-slider {
    background-color: #00b050;
}

input:checked + .notifications-slider:before {
    transform: translateX(26px);
}

.btn {
    padding: 0.5rem 1rem;
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

.btn-success {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-outline-success {
    background: transparent;
    color: #00b050;
    border-color: #00b050;
}

.btn-outline-primary {
    background: transparent;
    color: #007bff;
    border-color: #007bff;
}

.btn-outline-danger {
    background: transparent;
    color: #dc3545;
    border-color: #dc3545;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .notifications-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .notification-item {
        flex-direction: column;
        align-items: stretch;
    }
    
    .notification-actions {
        justify-content: flex-end;
    }
}
</style>

<script>
// Notification filtering
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.notifications-filter-btn');
    const notificationItems = document.querySelectorAll('.notification-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const filterType = this.dataset.filter;
            
            notificationItems.forEach(item => {
                if (filterType === 'all') {
                    item.style.display = 'flex';
                } else if (filterType === 'high') {
                    item.style.display = item.dataset.priority === 'high' ? 'flex' : 'none';
                } else {
                    item.style.display = item.dataset.type === filterType ? 'flex' : 'none';
                }
            });
        });
    });
    
    // Auto-refresh notifications (if enabled)
    const autoRefreshEnabled = true; // This would come from user settings
    if (autoRefreshEnabled) {
        setInterval(() => {
            // In a real implementation, this would fetch new notifications via AJAX
            console.log('Auto-refreshing notifications...');
        }, 30000); // 30 seconds
    }
    
    // Request browser notification permissions
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
});
</script>

<?php include 'templates/footer.php'; ?> 