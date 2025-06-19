<nav class="sidebar-admin" role="navigation" aria-label="Admin dashboard navigation">
  <a href="admin-leads.php" class="sidebar-admin__logo" aria-label="Admin Home">
    <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me logo" loading="lazy" class="sidebar-admin__logo-img" />
    <span class="sidebar-admin__logo-text">
      Painter Near Me 
      <span class="sidebar-admin__logo-admin">Admin</span>
    </span>
  </a>
  
  <div class="sidebar-admin__section">
    <h3 class="sidebar-admin__section-title">Overview</h3>
    <ul class="sidebar-admin__list">
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-leads.php" aria-current="page">
          <i class="bi bi-speedometer2 sidebar-admin__icon"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-analytics.php">
          <i class="bi bi-graph-up sidebar-admin__icon"></i>
          <span>Analytics</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="sidebar-admin__section">
    <h3 class="sidebar-admin__section-title">Management</h3>
    <ul class="sidebar-admin__list">
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-manage-leads.php">
          <i class="bi bi-person-lines-fill sidebar-admin__icon"></i>
          <span>Manage Leads</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-manage-bids.php">
          <i class="bi bi-currency-pound sidebar-admin__icon"></i>
          <span>Manage Bids</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-manage-painters.php">
          <i class="bi bi-people-fill sidebar-admin__icon"></i>
          <span>Manage Painters</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-quality-control.php">
          <i class="bi bi-shield-check sidebar-admin__icon"></i>
          <span>Quality Control</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="sidebar-admin__section">
    <h3 class="sidebar-admin__section-title">Operations</h3>
    <ul class="sidebar-admin__list">
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-notifications.php">
          <i class="bi bi-bell-fill sidebar-admin__icon"></i>
          <span>Notifications</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-financial.php">
          <i class="bi bi-currency-pound sidebar-admin__icon"></i>
          <span>Financial</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-payment-management.php">
          <i class="bi bi-credit-card sidebar-admin__icon"></i>
          <span>Payments</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="sidebar-admin__section">
    <h3 class="sidebar-admin__section-title">System</h3>
    <ul class="sidebar-admin__list">
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-system-monitor.php">
          <i class="bi bi-activity sidebar-admin__icon"></i>
          <span>System Monitor</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-system-test.php">
          <i class="bi bi-gear-fill sidebar-admin__icon"></i>
          <span>System Tests</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-backup-management.php">
          <i class="bi bi-cloud-arrow-up sidebar-admin__icon"></i>
          <span>Backup Management</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-disk-cleanup.php">
          <i class="bi bi-hdd-stack-fill sidebar-admin__icon"></i>
          <span>Disk Cleanup</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="sidebar-admin__section sidebar-admin__section--logout">
    <ul class="sidebar-admin__list">
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link" href="admin-change-password.php">
          <i class="bi bi-key sidebar-admin__icon"></i>
          <span>Change Password</span>
        </a>
      </li>
      <li class="sidebar-admin__item">
        <a class="sidebar-admin__link sidebar-admin__link--logout" href="admin-logout.php">
          <i class="bi bi-box-arrow-right sidebar-admin__icon"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </div>
</nav>
<style>
.sidebar-admin {
  background: #fff;
  border-right: 1px solid #e5e7eb;
  min-width: 280px;
  max-width: 320px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 1.5rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  overflow-y: auto;
}

.sidebar-admin__logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-family: 'Montserrat', Arial, sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: #00b050;
  text-decoration: none;
  margin-bottom: 2rem;
  padding: 1rem;
  border-radius: 0.75rem;
  transition: background-color 150ms ease;
}

.sidebar-admin__logo:hover {
  background: #e6f7ea;
}

.sidebar-admin__logo-img {
  height: 2rem;
  width: auto;
}

.sidebar-admin__logo-admin {
  color: #009140;
  font-size: 0.875rem;
  font-weight: 600;
  background: #e6f7ea;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
}

.sidebar-admin__section {
  margin-bottom: 1.5rem;
}

.sidebar-admin__section--logout {
  margin-top: auto;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.sidebar-admin__section-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
  padding: 0 1rem;
}

.sidebar-admin__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.sidebar-admin__item {
  width: 100%;
}

.sidebar-admin__link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #374151;
  font-weight: 600;
  font-size: 0.875rem;
  text-decoration: none;
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  transition: all 150ms ease;
  position: relative;
}

.sidebar-admin__link::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 0;
  background: #00b050;
  border-radius: 0 2px 2px 0;
  transition: height 150ms ease;
}

.sidebar-admin__link[aria-current="page"],
.sidebar-admin__link--active {
  background: #e6f7ea;
  color: #009140;
}

.sidebar-admin__link[aria-current="page"]::before,
.sidebar-admin__link--active::before {
  height: 60%;
}

.sidebar-admin__link:hover,
.sidebar-admin__link:focus {
  background: #f3f4f6;
  color: #00b050;
  outline: none;
}

.sidebar-admin__link:hover::before,
.sidebar-admin__link:focus::before {
  height: 40%;
}

.sidebar-admin__link--logout {
  color: #ef4444;
}

.sidebar-admin__link--logout:hover,
.sidebar-admin__link--logout:focus {
  background: #fecaca;
  color: #ef4444;
}

.sidebar-admin__icon {
  font-size: 1.125rem;
  width: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

@media (max-width: 1024px) {
  .sidebar-admin {
    min-width: 240px;
    max-width: 240px;
  }
}

@media (max-width: 768px) {
  .sidebar-admin {
    min-width: 100%;
    max-width: 100%;
    height: auto;
    position: static;
    border-right: none;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem;
  }
  
  .sidebar-admin__section {
    margin-bottom: 1rem;
  }
  
  .sidebar-admin__section--logout {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
  }
  
  .sidebar-admin__list {
    flex-direction: row;
    overflow-x: auto;
    gap: 0.5rem;
  }
  
  .sidebar-admin__item {
    flex-shrink: 0;
  }
  
  .sidebar-admin__link {
    white-space: nowrap;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
  }
  
  .sidebar-admin__logo-text {
    display: none;
  }
  
  .sidebar-admin__section-title {
    display: none;
  }
}
</style>