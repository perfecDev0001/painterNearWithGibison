<?php
require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Restrict to admin
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Handle POST actions (edit, assign, status, delete)
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_lead_id'])) {
        // Edit lead
        $id = intval($_POST['edit_lead_id']);
        $updateData = [
            'customer_name' => trim($_POST['customer_name']),
            'customer_email' => trim($_POST['customer_email']),
            'customer_phone' => trim($_POST['customer_phone']),
            'job_title' => trim($_POST['job_title']),
            'job_description' => trim($_POST['job_description']),
            'location' => trim($_POST['location'])
        ];
        
        $result = $dataAccess->updateLead($id, $updateData);
        if ($result['success']) {
            $actionMsg = 'Lead updated successfully.';
        } else {
            $actionMsg = 'Error updating lead: ' . ($result['error'] ?? 'Unknown error');
        }
        
    } elseif (isset($_POST['assign_lead_id'])) {
        // Assign painter
        $id = intval($_POST['assign_lead_id']);
        $painter_id = intval($_POST['assigned_painter_id']);
        
        $result = $dataAccess->assignLeadToPainter($id, $painter_id);
        if ($result['success']) {
            $actionMsg = 'Painter assigned successfully.';
        } else {
            $actionMsg = 'Error assigning painter: ' . ($result['error'] ?? 'Unknown error');
        }
        
    } elseif (isset($_POST['status_lead_id'])) {
        // Change status
        $id = intval($_POST['status_lead_id']);
        $status = $_POST['status'];
        
        $result = $dataAccess->updateLeadStatus($id, $status);
        if ($result['success']) {
            $actionMsg = 'Status updated successfully.';
        } else {
            $actionMsg = 'Error updating status: ' . ($result['error'] ?? 'Unknown error');
        }
        
    } elseif (isset($_POST['delete_lead_id'])) {
        // Delete lead
        $id = intval($_POST['delete_lead_id']);
        
        $result = $dataAccess->deleteLead($id);
        if ($result['success']) {
            $actionMsg = 'Lead deleted successfully.';
        } else {
            $actionMsg = 'Error deleting lead: ' . ($result['error'] ?? 'Unknown error');
        }
    }
}

// Fetch all painters for assign dropdown
$paintersData = $dataAccess->getAllPainters();
$painters = [];
if (is_array($paintersData)) {
    foreach ($paintersData as $painter) {
        if (is_array($painter) && isset($painter['id'])) {
            $painters[$painter['id']] = $painter['company_name'] ?? $painter['name'] ?? 'Unknown';
        }
    }
}

// Fetch all leads with filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search) {
    $filters['search'] = $search;
}
if ($status) {
    $filters['status'] = $status;
}

$leads = $dataAccess->getFilteredLeads($filters);
// getFilteredLeads now returns an array directly, or empty array if failed

include 'templates/header.php';
?>
<head>
      <title>Manage Leads | Painter Near Me Admin</title>
    <meta name="description" content="Manage leads and assignments in the Painter Near Me marketplace." />
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
      <div class="hero__header">
        <div class="hero__content">
          <h1 class="hero__title">Manage Leads</h1>
          <p class="hero__subtitle">View and manage all painting job leads with enhanced controls</p>
        </div>
        <div class="hero__actions">
          <a href="admin-leads.php" class="btn btn-outline-success">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
          </a>
        </div>
      </div>
      <?php if ($actionMsg): ?>
        <div class="alert alert-success alert--enhanced" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <?php echo htmlspecialchars($actionMsg); ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <div class="filters-section">
        <div class="filters-header">
          <h2 class="filters-title">
            <i class="bi bi-funnel"></i>
            Search & Filter
          </h2>
          <div class="filters-meta">
            <?php echo count($leads); ?> leads found
          </div>
        </div>
        <form class="filters-form" method="get">
          <div class="filters-grid">
            <div class="filter-group">
              <label for="search" class="filter-label">Search Leads</label>
              <div class="filter-input-wrapper">
                <i class="bi bi-search filter-input-icon"></i>
                <input type="text" class="filter-input" id="search" name="search" 
                       placeholder="Search by name, email, location..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       aria-label="Search leads">
              </div>
            </div>
            <div class="filter-group">
              <label for="status" class="filter-label">Status Filter</label>
              <select class="filter-select" id="status" name="status" aria-label="Filter by status">
                <option value="">All Statuses</option>
                <option value="open" <?php if($status==='open') echo 'selected'; ?>>
                  <i class="bi bi-circle-fill text-success"></i> Open
                </option>
                <option value="assigned" <?php if($status==='assigned') echo 'selected'; ?>>
                  <i class="bi bi-circle-fill text-warning"></i> Assigned
                </option>
                <option value="closed" <?php if($status==='closed') echo 'selected'; ?>>
                  <i class="bi bi-circle-fill text-secondary"></i> Closed
                </option>
              </select>
            </div>
            <div class="filter-actions">
              <button type="submit" class="btn btn-success filter-btn">
                <i class="bi bi-search"></i> Apply Filters
              </button>
              <?php if ($search || $status): ?>
                <a href="admin-manage-leads.php" class="btn btn-outline-secondary filter-btn">
                  <i class="bi bi-x-circle"></i> Clear
                </a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </div>
    </section>

    <section class="admin-card">
      <div class="table-section">
        <div class="table-header">
          <h2 class="table-title">
            <i class="bi bi-list-ul"></i>
            Leads Management
          </h2>
          <div class="table-actions">
            <button class="btn btn-outline-success btn-sm" onclick="refreshTable()">
              <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="table-id">ID</th>
                <th class="table-customer">Customer Info</th>
                <th class="table-job">Job Details</th>
                <th class="table-location">Location</th>
                <th class="table-status">Status</th>
                <th class="table-assigned">Assigned</th>
                <th class="table-date">Created</th>
                <th class="table-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($leads)): ?>
                <tr>
                  <td colspan="8" class="empty-state">
                    <div class="empty-state-content">
                      <i class="bi bi-inbox empty-state-icon"></i>
                      <h3 class="empty-state-title">No leads found</h3>
                      <p class="empty-state-text">Try adjusting your search criteria or filters</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($leads as $lead): ?>
                <tr class="table-row" data-lead-id="<?php echo $lead['id']; ?>">
                  <td class="table-id">
                    <span class="lead-id">#<?php echo $lead['id']; ?></span>
                  </td>
                  <td class="table-customer">
                    <div class="customer-info">
                      <div class="customer-name"><?php echo htmlspecialchars($lead['customer_name']); ?></div>
                      <div class="customer-email"><?php echo htmlspecialchars($lead['customer_email']); ?></div>
                      <?php if (!empty($lead['customer_phone'])): ?>
                        <div class="customer-phone"><?php echo htmlspecialchars($lead['customer_phone']); ?></div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="table-job">
                    <div class="job-info">
                      <div class="job-title"><?php echo htmlspecialchars($lead['job_title']); ?></div>
                      <div class="job-description"><?php echo htmlspecialchars(substr($lead['job_description'], 0, 60) . (strlen($lead['job_description']) > 60 ? '...' : '')); ?></div>
                    </div>
                  </td>
                  <td class="table-location">
                    <div class="location-info">
                      <i class="bi bi-geo-alt location-icon"></i>
                      <?php echo htmlspecialchars($lead['location']); ?>
                    </div>
                  </td>
                  <td class="table-status">
                    <span class="status-badge status-<?php echo $lead['status']; ?>">
                      <i class="bi bi-circle-fill status-indicator"></i>
                      <?php echo ucfirst($lead['status']); ?>
                    </span>
                  </td>
                  <td class="table-assigned">
                    <?php 
                    if (isset($lead['assigned_painter_id']) && isset($painters[$lead['assigned_painter_id']])) {
                        echo '<div class="painter-assigned">';
                        echo '<i class="bi bi-person-check painter-icon"></i>';
                        echo '<span>' . htmlspecialchars($painters[$lead['assigned_painter_id']]) . '</span>';
                        echo '</div>';
                    } else {
                        echo '<span class="no-assignment">Not assigned</span>';
                    }
                    ?>
                  </td>
                  <td class="table-date">
                    <div class="date-info">
                      <div class="date-main"><?php echo $dataAccess->formatDate($lead['created_at']); ?></div>
                    </div>
                  </td>
                  <td class="table-actions">
                    <div class="dropdown">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle action-btn" 
                              type="button" 
                              data-bs-toggle="dropdown" 
                              aria-expanded="false">
                        <i class="bi bi-three-dots"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewLeadModal<?php echo $lead['id']; ?>">
                            <i class="bi bi-eye"></i> View Details
                          </button>
                        </li>
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editLeadModal<?php echo $lead['id']; ?>">
                            <i class="bi bi-pencil"></i> Edit Lead
                          </button>
                        </li>
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#assignLeadModal<?php echo $lead['id']; ?>">
                            <i class="bi bi-person-plus"></i> Assign Painter
                          </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusLeadModal<?php echo $lead['id']; ?>">
                            <i class="bi bi-arrow-repeat"></i> Change Status
                          </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteLeadModal<?php echo $lead['id']; ?>">
                            <i class="bi bi-trash"></i> Delete Lead
                          </button>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

      <!-- Modals will be generated by JavaScript or included here for each lead -->
      <?php foreach ($leads as $lead): ?>
        <!-- View Modal -->
        <div class="modal fade" id="viewLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-labelledby="viewLeadModalLabel<?php echo $lead['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-success-subtle">
                <h5 class="modal-title d-flex align-items-center gap-2" id="viewLeadModalLabel<?php echo $lead['id']; ?>">
                  <i class="bi bi-eye-fill text-success"></i> Lead Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="row">
                  <div class="col-md-6">
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($lead['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($lead['customer_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($lead['customer_phone']); ?></p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Status:</strong> <span class="badge bg-secondary"><?php echo ucfirst($lead['status']); ?></span></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?></p>
                    <p><strong>Created:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?></p>
                  </div>
                </div>
                <div class="mt-3">
                  <p><strong>Job Title:</strong> <?php echo htmlspecialchars($lead['job_title']); ?></p>
                  <p><strong>Job Description:</strong></p>
                  <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($lead['job_description'])); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-labelledby="editLeadModalLabel<?php echo $lead['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center gap-2" id="editLeadModalLabel<?php echo $lead['id']; ?>">
                  <i class="bi bi-pencil-fill"></i> Edit Lead
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" action="">
                <div class="modal-body">
                  <input type="hidden" name="edit_lead_id" value="<?php echo $lead['id']; ?>">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label for="customer_name_<?php echo $lead['id']; ?>" class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="customer_name_<?php echo $lead['id']; ?>" name="customer_name" value="<?php echo htmlspecialchars($lead['customer_name']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <label for="customer_email_<?php echo $lead['id']; ?>" class="form-label">Email</label>
                        <input type="email" class="form-control" id="customer_email_<?php echo $lead['id']; ?>" name="customer_email" value="<?php echo htmlspecialchars($lead['customer_email']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <label for="customer_phone_<?php echo $lead['id']; ?>" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="customer_phone_<?php echo $lead['id']; ?>" name="customer_phone" value="<?php echo htmlspecialchars($lead['customer_phone']); ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label for="job_title_<?php echo $lead['id']; ?>" class="form-label">Job Title</label>
                        <input type="text" class="form-control" id="job_title_<?php echo $lead['id']; ?>" name="job_title" value="<?php echo htmlspecialchars($lead['job_title']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <label for="location_<?php echo $lead['id']; ?>" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location_<?php echo $lead['id']; ?>" name="location" value="<?php echo htmlspecialchars($lead['location']); ?>" required>
                      </div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="job_description_<?php echo $lead['id']; ?>" class="form-label">Job Description</label>
                    <textarea class="form-control" id="job_description_<?php echo $lead['id']; ?>" name="job_description" rows="4" required><?php echo htmlspecialchars($lead['job_description']); ?></textarea>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Update Lead</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Assign Modal -->
        <div class="modal fade" id="assignLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-labelledby="assignLeadModalLabel<?php echo $lead['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title d-flex align-items-center gap-2" id="assignLeadModalLabel<?php echo $lead['id']; ?>">
                  <i class="bi bi-person-plus-fill"></i> Assign Painter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" action="">
                <div class="modal-body">
                  <input type="hidden" name="assign_lead_id" value="<?php echo $lead['id']; ?>">
                  <div class="mb-3">
                    <label for="assigned_painter_id_<?php echo $lead['id']; ?>" class="form-label">Select Painter</label>
                    <select class="form-select" id="assigned_painter_id_<?php echo $lead['id']; ?>" name="assigned_painter_id" required>
                      <option value="">Choose a painter...</option>
                      <?php foreach ($painters as $painterId => $painterName): ?>
                        <option value="<?php echo $painterId; ?>" <?php echo ($lead['assigned_painter_id'] == $painterId) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($painterName); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i>
                    This will assign the lead to the selected painter and change status to "Assigned".
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success">Assign Painter</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Status Modal -->
        <div class="modal fade" id="statusLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-labelledby="statusLeadModalLabel<?php echo $lead['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-info text-white">
                <h5 class="modal-title d-flex align-items-center gap-2" id="statusLeadModalLabel<?php echo $lead['id']; ?>">
                  <i class="bi bi-arrow-repeat"></i> Change Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" action="">
                <div class="modal-body">
                  <input type="hidden" name="status_lead_id" value="<?php echo $lead['id']; ?>">
                  <div class="mb-3">
                    <label for="status_<?php echo $lead['id']; ?>" class="form-label">New Status</label>
                    <select class="form-select" id="status_<?php echo $lead['id']; ?>" name="status" required>
                      <option value="open" <?php echo ($lead['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                      <option value="assigned" <?php echo ($lead['status'] === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                      <option value="closed" <?php echo ($lead['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-info text-white">Update Status</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-labelledby="deleteLeadModalLabel<?php echo $lead['id']; ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title d-flex align-items-center gap-2" id="deleteLeadModalLabel<?php echo $lead['id']; ?>">
                  <i class="bi bi-exclamation-triangle-fill"></i> Delete Lead
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" action="">
                <div class="modal-body">
                  <input type="hidden" name="delete_lead_id" value="<?php echo $lead['id']; ?>">
                  <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Warning:</strong> This action cannot be undone. Are you sure you want to delete this lead?
                  </div>
                  <p><strong>Lead:</strong> <?php echo htmlspecialchars($lead['job_title']); ?> - <?php echo htmlspecialchars($lead['customer_name']); ?></p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-danger">Delete Lead</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </main>
</div>

<script>
function refreshTable() {
    const refreshBtn = event.target;
    const originalHTML = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Add loading class to table
    document.querySelector('.data-table').classList.add('loading');
    
    setTimeout(() => {
        location.reload();
    }, 800);
}

// Auto-refresh functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add subtle animations to status badges
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.style.animation = 'fadeIn 0.5s ease-in-out';
    });
    
    // Enhanced dropdown interactions
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    });
});

// CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .spin {
        animation: spin 1s linear infinite;
    }
    
    .dropdown-toggle.active {
        background: #f8fffe !important;
        border-color: #00b050 !important;
    }
`;
document.head.appendChild(style);
</script>

<style>
/* Admin Layout */
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: #f7fafc;
}

.admin-main {
    flex: 1;
    padding: 2.5rem 2rem 2rem 2rem;
    max-width: 1400px;
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

/* Hero Section */
.hero__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.hero__title {
    color: #00b050;
    margin: 0 0 0.5rem 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.hero__subtitle {
    color: #666;
    margin: 0;
    font-size: 1.1rem;
}

.hero__actions .btn {
    border-radius: 0.8rem;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Alert Enhancement */
.alert--enhanced {
    border-radius: 1rem;
    border: none;
    box-shadow: 0 2px 8px rgba(0,176,80,0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    margin-top: 1.5rem;
}

/* Filters Section */
.filters-section {
    background: #f8fffe;
    border-radius: 1rem;
    padding: 0;
    border: 1px solid rgba(0,176,80,0.1);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem 1rem 2rem;
    border-bottom: 1px solid rgba(0,176,80,0.1);
}

.filters-title {
    color: #00b050;
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filters-meta {
    color: #666;
    font-size: 0.9rem;
    background: #e8f8f0;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
}

.filters-form {
    padding: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: 1fr 300px auto;
    gap: 1.5rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.filter-input-wrapper {
    position: relative;
}

.filter-input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    font-size: 1rem;
}

.filter-input,
.filter-select {
    border: 2px solid #e5e7eb;
    border-radius: 0.8rem;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-input {
    padding-left: 2.5rem;
}

.filter-input:focus,
.filter-select:focus {
    border-color: #00b050;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
}

.filter-btn {
    border-radius: 0.8rem;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

/* Table Section */
.table-section {
    background: #fff;
    border-radius: 1rem;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: #f8fffe;
    border-bottom: 1px solid rgba(0,176,80,0.1);
}

.table-title {
    color: #00b050;
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-actions .btn {
    border-radius: 0.6rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}

.data-table thead th {
    background: #00b050;
    color: white;
    padding: 1.25rem 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr {
    border-bottom: 1px solid #f0f4f8;
    transition: background-color 0.2s ease;
}

.data-table tbody tr:hover {
    background: #f8fffe;
}

.data-table tbody td {
    padding: 1.25rem 1rem;
    vertical-align: top;
    border: none;
}

/* Table Cell Styles */
.lead-id {
    font-weight: 700;
    color: #00b050;
    font-size: 0.9rem;
}

.customer-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.customer-name {
    font-weight: 600;
    color: #333;
    font-size: 1rem;
}

.customer-email {
    color: #666;
    font-size: 0.9rem;
}

.customer-phone {
    color: #888;
    font-size: 0.85rem;
}

.job-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.job-title {
    font-weight: 600;
    color: #333;
    font-size: 1rem;
}

.job-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

.location-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
}

.location-icon {
    color: #00b050;
    font-size: 1rem;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.6rem;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.status-open {
    background: #d4edda;
    color: #155724;
}

.status-assigned {
    background: #fff3cd;
    color: #856404;
}

.status-closed {
    background: #f8d7da;
    color: #721c24;
}

.status-indicator {
    font-size: 0.6rem;
}

/* Painter Assignment */
.painter-assigned {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #00b050;
    font-weight: 600;
}

.painter-icon {
    font-size: 1rem;
}

.no-assignment {
    color: #999;
    font-style: italic;
    font-size: 0.9rem;
}

/* Date Info */
.date-info {
    color: #666;
    font-size: 0.9rem;
}

.date-main {
    font-weight: 600;
}

/* Action Button */
.action-btn {
    border-radius: 0.6rem;
    border: 2px solid #e5e7eb;
    background: white;
    padding: 0.5rem 1rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.action-btn:hover {
    border-color: #00b050;
    background: #f8fffe;
}

/* Dropdown Menu */
.dropdown-menu {
    border-radius: 0.8rem;
    border: 1px solid rgba(0,176,80,0.1);
    box-shadow: 0 8px 32px rgba(0,176,80,0.15);
    padding: 0.5rem 0;
    margin-top: 0.5rem;
}

.dropdown-item {
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: #f8fffe;
    color: #00b050;
}

.dropdown-item.text-danger:hover {
    background: #fff5f5;
    color: #dc3545;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.empty-state-icon {
    font-size: 4rem;
    color: #ccc;
}

.empty-state-title {
    color: #666;
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.empty-state-text {
    color: #999;
    margin: 0;
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filters-grid {
        grid-template-columns: 1fr 250px auto;
    }
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.5rem 1rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
    
    .hero__header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .hero__title {
        font-size: 2rem;
    }
    
    .data-table {
        font-size: 0.9rem;
    }
    
    .data-table tbody td {
        padding: 1rem 0.75rem;
    }
    
    .customer-info,
    .job-info {
        gap: 0.15rem;
    }
}

/* Loading States */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Utility Classes */
.btn {
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-success {
    background: #00b050;
    color: white;
}

.btn-success:hover {
    background: #009140;
    color: white;
}

.btn-outline-success {
    background: transparent;
    color: #00b050;
    border: 2px solid #00b050;
}

.btn-outline-success:hover {
    background: #00b050;
    color: white;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
}
</style>

<?php include 'templates/footer.php'; ?>