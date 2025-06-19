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

// Handle actions
$action = $_GET['action'] ?? '';
$painterId = $_GET['id'] ?? '';
$message = '';
$messageType = '';

if ($_POST) {
    if ($action === 'update_status' && $painterId) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['active', 'suspended', 'pending'])) {
            $result = $dataAccess->updatePainter($painterId, ['status' => $newStatus]);
            if ($result['success']) {
                $message = 'Painter status updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update painter status.';
                $messageType = 'error';
            }
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build filters
$filters = [];
if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status_filter)) {
    $filters['status'] = $status_filter;
}

// Get all painters
$painters = $dataAccess->getPainters($filters);
// getPainters now returns an array directly, or empty array if failed

include 'templates/header.php';
?>

<head>
    <title>Manage Painters | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Manage painter profiles, verification status, and accounts in the Painter Near Me marketplace." />
    <link rel="stylesheet" href="serve-asset.php?file=css/admin-dashboard.css">
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
        <section class="admin-card">
            <div class="admin-header">
                <div class="admin-header__content">
                    <h1 class="hero__title">Manage Painters</h1>
                    <p class="hero__subtitle">View and manage painter profiles, verification status, and account details</p>
                </div>
                <div class="admin-header__actions">
                    <a href="admin-leads.php" class="btn btn--outline">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <button class="btn btn--primary" onclick="exportPainters()">
                        <i class="bi bi-download"></i> Export Data
                    </button>
                </div>
            </div>
        </section>

        <?php if ($message): ?>
        <section class="admin-card">
            <div class="admin-alert admin-alert--<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <section class="admin-card">
            <h2 class="admin-section-title">
                <i class="bi bi-search"></i>
                Search & Filter Painters
            </h2>
            <form method="GET" class="admin-form">
                <div class="admin-grid admin-grid--3">
                    <div class="admin-form-group">
                        <label for="search" class="admin-form-label">Search</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Company name, contact name, email..." 
                               class="admin-form-input">
                    </div>
                    <div class="admin-form-group">
                        <label for="status" class="admin-form-label">Status</label>
                        <select id="status" name="status" class="admin-form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn--primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="admin-manage-painters.php" class="btn btn--outline">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </section>

        <!-- Painters List -->
        <section class="admin-card">
            <div class="admin-section-header">
                <h2 class="admin-section-title">
                    Painters (<?php echo count($painters); ?> found)
                </h2>
            </div>

            <?php if (empty($painters)): ?>
                <div class="admin-empty-state">
                    <i class="bi bi-person-x"></i>
                    <h3>No painters found</h3>
                    <p>No painters match your current search criteria.</p>
                </div>
            <?php else: ?>
                <div class="admin-grid admin-grid--painters">
                    <?php foreach ($painters as $painter): ?>
                        <div class="admin-painter-card">
                            <div class="admin-painter-card__header">
                                <div class="admin-painter-card__basic">
                                    <h3 class="admin-painter-card__company">
                                        <?php echo htmlspecialchars($painter['company_name'] ?? 'No Company Name'); ?>
                                    </h3>
                                    <div class="admin-painter-card__contact">
                                        <?php echo htmlspecialchars($painter['contact_name'] ?? 'No Contact Name'); ?>
                                    </div>
                                </div>
                                <div class="admin-painter-card__status">
                                    <span class="admin-status admin-status--<?php echo $painter['status'] ?? 'unknown'; ?>">
                                        <?php echo ucfirst($painter['status'] ?? 'Unknown'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="admin-painter-card__details">
                                <div class="admin-painter-detail">
                                    <i class="bi bi-envelope"></i>
                                    <span><?php echo htmlspecialchars($painter['email'] ?? 'No email'); ?></span>
                                </div>
                                <div class="admin-painter-detail">
                                    <i class="bi bi-telephone"></i>
                                    <span><?php echo htmlspecialchars($painter['phone'] ?? 'No phone'); ?></span>
                                </div>
                                <?php if (!empty($painter['location'])): ?>
                                <div class="admin-painter-detail">
                                    <i class="bi bi-geo-alt"></i>
                                    <span><?php echo htmlspecialchars($painter['location']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($painter['specialities'])): ?>
                                <div class="admin-painter-detail">
                                    <i class="bi bi-tools"></i>
                                    <span><?php echo htmlspecialchars($painter['specialities']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($painter['experience_years'])): ?>
                                <div class="admin-painter-detail">
                                    <i class="bi bi-calendar-check"></i>
                                    <span><?php echo $painter['experience_years']; ?> years experience</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($painter['rating'])): ?>
                                <div class="admin-painter-detail">
                                    <i class="bi bi-star-fill"></i>
                                    <span><?php echo $painter['rating']; ?>/5.0 rating</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="admin-painter-card__meta">
                                <div class="admin-painter-meta">
                                    <small>Joined: <?php echo $dataAccess->formatDate($painter['created_at'] ?? ''); ?></small>
                                </div>
                                <?php if (!empty($painter['verification_status'])): ?>
                                <div class="admin-painter-verification">
                                    <span class="admin-verification-badge admin-verification-badge--<?php echo $painter['verification_status']; ?>">
                                        <i class="bi bi-<?php echo $painter['verification_status'] === 'verified' ? 'check-circle-fill' : 'clock'; ?>"></i>
                                        <?php echo ucfirst($painter['verification_status']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="admin-painter-card__actions">
                                <div class="admin-painter-actions">
                                    <!-- Status Update Form -->
                                    <form method="POST" action="?action=update_status&id=<?php echo $painter['id']; ?>" class="admin-status-form">
                                        <select name="status" class="admin-form-select admin-form-select--compact" onchange="this.form.submit()">
                                            <option value="">Change Status</option>
                                            <option value="active" <?php echo ($painter['status'] ?? '') === 'active' ? 'disabled' : ''; ?>>
                                                Set Active
                                            </option>
                                            <option value="pending" <?php echo ($painter['status'] ?? '') === 'pending' ? 'disabled' : ''; ?>>
                                                Set Pending
                                            </option>
                                            <option value="suspended" <?php echo ($painter['status'] ?? '') === 'suspended' ? 'disabled' : ''; ?>>
                                                Suspend
                                            </option>
                                        </select>
                                    </form>

                                    <a href="admin-manage-bids.php?painter_id=<?php echo $painter['id']; ?>" 
                                       class="btn btn--outline btn--small">
                                        <i class="bi bi-eye"></i> View Bids
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
function exportPainters() {
    // Show loading state
    const exportBtn = event.target;
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-download"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Simulate export (in real implementation, this would generate a CSV/Excel file)
    setTimeout(() => {
        exportBtn.innerHTML = '<i class="bi bi-check-circle"></i> Exported!';
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 2000);
    }, 1500);
}
</script>

<?php include 'templates/footer.php'; ?> 