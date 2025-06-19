<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Require login
$auth->requireLogin();

$user = $auth->getCurrentUser();
$painterId = $auth->getCurrentPainterId();

// Get dashboard stats
$stats = $dataAccess->getDashboardStats();
$myBids = $dataAccess->getBidsByPainter($painterId);
$availableLeads = $dataAccess->getOpenLeads($painterId);

// Get unread message count for header display
$unreadResult = $dataAccess->getUnreadMessageCount($painterId, 'painter');
$unreadMessageCount = $unreadResult['success'] ? ($unreadResult['data']['count'] ?? 0) : 0;

include 'templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
      <title>Dashboard | Painter Near Me</title>
    <meta name="description" content="Painter dashboard to manage leads and bids on Painter Near Me." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
  </script>
</head>
<body>
<main role="main">
  <div style="display:flex;gap:2.5rem;align-items:flex-start;max-width:1100px;margin:0 auto;">
    <div>
      <?php include 'templates/sidebar-painter.php'; ?>
    </div>
    <div style="flex:1;min-width:0;">
      <section class="dashboard-hero hero">
        <h1 class="hero__title">Welcome, <?php echo htmlspecialchars($user['company_name']); ?></h1>
        <p class="hero__subtitle">
          Manage your painting leads and bids
          <?php if ($unreadMessageCount > 0): ?>
            <span style="background:#ff4444;color:white;padding:0.3rem 0.6rem;border-radius:1rem;font-size:0.85rem;margin-left:1rem;">
              <?php echo $unreadMessageCount; ?> new message<?php echo $unreadMessageCount > 1 ? 's' : ''; ?>
            </span>
          <?php endif; ?>
        </p>
      </section>

      <section class="dashboard-stats" style="margin:2rem 0;">
        <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;">
          <div class="stat-card" style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);">
            <h3 style="color:#00b050;margin:0 0 0.5rem 0;">My Bids</h3>
            <p style="font-size:2rem;font-weight:900;margin:0;color:#222;"><?php echo count($myBids); ?></p>
          </div>
          <div class="stat-card" style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);">
            <h3 style="color:#00b050;margin:0 0 0.5rem 0;">Available Leads</h3>
            <p style="font-size:2rem;font-weight:900;margin:0;color:#222;"><?php echo count($availableLeads); ?></p>
          </div>
          <div class="stat-card" style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);">
            <h3 style="color:#00b050;margin:0 0 0.5rem 0;">Total Leads</h3>
            <p style="font-size:2rem;font-weight:900;margin:0;color:#222;"><?php echo $stats['total_leads'] ?? 0; ?></p>
          </div>
          <div class="stat-card" style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);">
            <h3 style="color:#00b050;margin:0 0 0.5rem 0;">Messages</h3>
            <p style="font-size:2rem;font-weight:900;margin:0;color:#222;"><?php echo $unreadMessageCount; ?></p>
            <small style="color:#666;">Unread</small>
          </div>
        </div>
      </section>

      <section class="dashboard-recent" style="margin:2rem 0;">
        <h2 style="color:#00b050;margin-bottom:1rem;">Recent Available Leads</h2>
        <?php if (empty($availableLeads)): ?>
          <div style="background:#f0f9ff;padding:1.5rem;border-radius:1rem;text-align:center;color:#666;">
            No leads available at the moment. Check back later!
          </div>
        <?php else: ?>
          <div class="leads-grid" style="display:grid;gap:1rem;">
            <?php foreach (array_slice($availableLeads, 0, 5) as $lead): ?>
              <div class="lead-card" style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);border-left:4px solid #00b050;">
                <h3 style="margin:0 0 0.5rem 0;color:#222;"><?php echo htmlspecialchars($lead['job_title']); ?></h3>
                <p style="color:#666;margin:0 0 1rem 0;"><?php echo htmlspecialchars(substr($lead['job_description'], 0, 100)) . '...'; ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span style="color:#00b050;font-weight:700;"><?php echo htmlspecialchars($lead['location']); ?></span>
                  <a href="bid.php?lead_id=<?php echo $lead['id']; ?>" class="btn-primary" style="background:#00b050;color:#fff;padding:0.5rem 1rem;border-radius:0.5rem;text-decoration:none;">View & Bid</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="text-align:center;margin-top:1.5rem;">
            <a href="leads.php" style="color:#00b050;font-weight:700;text-decoration:none;">View All Available Leads →</a>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($unreadMessageCount > 0): ?>
      <section class="dashboard-messages" style="margin:2rem 0;">
        <h2 style="color:#00b050;margin-bottom:1rem;">Recent Messages</h2>
        <div style="background:#fff;padding:1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(0,176,80,0.1);border-left:4px solid #ff4444;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <h3 style="margin:0 0 0.5rem 0;color:#222;">You have <?php echo $unreadMessageCount; ?> unread message<?php echo $unreadMessageCount > 1 ? 's' : ''; ?></h3>
              <p style="color:#666;margin:0;">Stay in touch with your customers about their painting projects.</p>
            </div>
            <a href="messaging.php" class="btn-primary" style="background:#00b050;color:#fff;padding:0.75rem 1.5rem;border-radius:0.5rem;text-decoration:none;">View Messages</a>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</main>

<style>
body {
    font-family: 'Segoe UI', Roboto, Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #f8f9fa;
    color: #333;
    line-height: 1.6;
}

.dashboard-hero {
    background: linear-gradient(120deg, #00b050 0%, #009140 100%);
    color: white;
    padding: 2rem 1rem;
    border-radius: 1rem;
    margin-bottom: 2rem;
}

.hero__title {
    font-size: 2rem;
    font-weight: 900;
    margin: 0 0 0.5rem 0;
}

.hero__subtitle {
    font-size: 1.1rem;
    margin: 0;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dashboard-hero {
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

.dashboard-main__logout-link:hover { 
    background: #009140; 
    color: #fff; 
}
</style>

<?php include 'templates/footer.php'; ?>
</body>
</html> 