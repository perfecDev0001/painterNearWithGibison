<nav class="sidebar-painter" role="navigation" aria-label="Painter dashboard navigation">
  <ul class="sidebar-painter__list">
    <li class="sidebar-painter__item"><a class="sidebar-painter__link" href="dashboard.php">Dashboard</a></li>
    <li class="sidebar-painter__item"><a class="sidebar-painter__link" href="profile.php">Edit Profile</a></li>
    <li class="sidebar-painter__item"><a class="sidebar-painter__link" href="leads.php">Available Leads</a></li>
    <li class="sidebar-painter__item"><a class="sidebar-painter__link" href="my-bids.php">My Bids</a></li>
    <li class="sidebar-painter__item">
      <a class="sidebar-painter__link" href="messaging.php">
        Messages
        <?php
        // Get unread message count for painter
        if (isset($auth) && $auth->isLoggedIn()) {
            $painterId = $auth->getCurrentPainterId();
            $dataAccess = $dataAccess ?? new GibsonDataAccess();
            $unreadResult = $dataAccess->getUnreadMessageCount($painterId, 'painter');
            $unreadCount = $unreadResult['success'] ? ($unreadResult['data']['count'] ?? 0) : 0;
            if ($unreadCount > 0): ?>
              <span class="sidebar-painter__unread-badge"><?php echo $unreadCount; ?></span>
            <?php endif;
        }
        ?>
      </a>
    </li>
    <li class="sidebar-painter__item"><a class="sidebar-painter__link" href="logout.php">Logout</a></li>
  </ul>
</nav>
<style>
.sidebar-painter {
  background: #f6fef8;
  border-radius: 1.2rem;
  box-shadow: 0 2px 8px rgba(0,176,80,0.07);
  padding: 2rem 1.5rem;
  margin-bottom: 2.5rem;
  max-width: 220px;
}
.sidebar-painter__list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.sidebar-painter__item {
  margin-bottom: 1.1rem;
}
.sidebar-painter__item:last-child {
  margin-bottom: 0;
}
.sidebar-painter__link {
  color: #00b050;
  font-weight: 700;
  text-decoration: none;
  font-size: 1.08rem;
  display: block;
  padding: 0.4rem 0.7rem;
  border-radius: 0.7rem;
  transition: background 0.2s, color 0.2s;
  position: relative;
}
.sidebar-painter__link:hover, .sidebar-painter__link:focus {
  background: #e6f7ea;
  color: #009140;
}
.sidebar-painter__unread-badge {
  background: #ff4444;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.4rem;
  border-radius: 50%;
  position: absolute;
  top: 0.2rem;
  right: 0.2rem;
  min-width: 1.2rem;
  text-align: center;
  line-height: 1;
}
</style> 