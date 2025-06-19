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

$actionMsg = '';

// Handle POST actions (edit, accept, status, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_bid_id'])) {
        $id = intval($_POST['edit_bid_id']);
        $updateData = [
            'bid_amount' => floatval($_POST['bid_amount']),
            'message' => trim($_POST['message'])
        ];
        
        $result = $dataAccess->updateBid($id, $updateData);
        if ($result['success']) {
            $actionMsg = 'Bid updated successfully.';
        } else {
            $actionMsg = 'Error updating bid: ' . ($result['error'] ?? 'Unknown error');
        }
        
    } elseif (isset($_POST['accept_bid_id'])) {
        $bid_id = intval($_POST['accept_bid_id']);
        
        // Get bid information
        $bidResult = $dataAccess->getBid($bid_id);
        if ($bidResult['success'] && isset($bidResult['data'])) {
            $bid = $bidResult['data'];
            
            // Assign painter to lead
            $assignResult = $dataAccess->assignLeadToPainter($bid['lead_id'], $bid['painter_id']);
            if ($assignResult['success']) {
                // Update bid status to accepted
                $updateResult = $dataAccess->updateBid($bid_id, ['status' => 'accepted']);
                if ($updateResult['success']) {
                    $actionMsg = 'Bid accepted and painter assigned successfully.';
                } else {
                    $actionMsg = 'Painter assigned but failed to update bid status.';
                }
            } else {
                $actionMsg = 'Error accepting bid: ' . ($assignResult['error'] ?? 'Unknown error');
            }
        } else {
            $actionMsg = 'Error: Bid not found.';
        }
        
    } elseif (isset($_POST['status_bid_id'])) {
        $id = intval($_POST['status_bid_id']);
        $status = $_POST['status'];
        
        $result = $dataAccess->updateBid($id, ['status' => $status]);
        if ($result['success']) {
            $actionMsg = 'Bid status updated successfully.';
        } else {
            $actionMsg = 'Error updating bid status: ' . ($result['error'] ?? 'Unknown error');
        }
        
    } elseif (isset($_POST['delete_bid_id'])) {
        $id = intval($_POST['delete_bid_id']);
        
        $result = $dataAccess->deleteBid($id);
        if ($result['success']) {
            $actionMsg = 'Bid deleted successfully.';
        } else {
            $actionMsg = 'Error deleting bid: ' . ($result['error'] ?? 'Unknown error');
        }
    }
}

// Fetch all bids with filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search) {
    $filters['search'] = $search;
}
if ($status) {
    $filters['status'] = $status;
}

$bids = $dataAccess->getFilteredBids($filters);

include 'templates/header.php';
?>
<head>
    <title>Manage Bids | Painter Near Me Admin</title>
    <meta name="description" content="Manage bids and assignments in the Painter Near Me marketplace." />
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
          <h1 class="hero__title">Manage Bids</h1>
          <p class="hero__subtitle">View and manage all painter bids on leads with enhanced controls</p>
        </div>
        <div class="admin-header__actions">
          <a href="admin-leads.php" class="btn btn--outline">
            <i class="bi bi-arrow-left"></i> Back to Leads
          </a>
          <a href="admin-analytics.php" class="btn btn--primary">
            <i class="bi bi-graph-up"></i> Analytics
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
            Search & Filter Bids
          </h2>
          <div class="filters-meta">
            <?php echo count($bids); ?> bids found
          </div>
        </div>
        <form class="filters-form" method="get">
          <div class="filters-grid">
            <div class="filter-group">
              <label for="search" class="filter-label">Search Bids</label>
              <div class="filter-input-wrapper">
                <i class="bi bi-search filter-input-icon"></i>
                <input type="text" class="filter-input" id="search" name="search" 
                       placeholder="Search by painter, lead, amount..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       aria-label="Search bids">
              </div>
            </div>
            <div class="filter-group">
              <label for="status" class="filter-label">Status Filter</label>
              <select class="filter-select" id="status" name="status" aria-label="Filter by status">
                <option value="">All Statuses</option>
                <option value="pending" <?php if($status==='pending') echo 'selected'; ?>>Pending</option>
                <option value="accepted" <?php if($status==='accepted') echo 'selected'; ?>>Accepted</option>
                <option value="rejected" <?php if($status==='rejected') echo 'selected'; ?>>Rejected</option>
                <option value="withdrawn" <?php if($status==='withdrawn') echo 'selected'; ?>>Withdrawn</option>
              </select>
            </div>
            <div class="filter-actions">
              <button type="submit" class="btn btn--success filter-btn">
                <i class="bi bi-search"></i> Apply Filters
              </button>
              <?php if ($search || $status): ?>
                <a href="admin-manage-bids.php" class="btn btn--outline filter-btn">
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
            <i class="bi bi-card-list"></i>
            Bids Management
          </h2>
          <div class="table-actions">
            <button class="btn btn--outline btn--small" onclick="refreshTable()">
              <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="table-id">ID</th>
                <th class="table-lead">Lead Info</th>
                <th class="table-painter">Painter</th>
                <th class="table-amount">Bid Amount</th>
                <th class="table-message">Message</th>
                <th class="table-status">Status</th>
                <th class="table-date">Created</th>
                <th class="table-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($bids)): ?>
                <tr>
                  <td colspan="8" class="empty-state">
                    <div class="empty-state-content">
                      <i class="bi bi-clipboard-x empty-state-icon"></i>
                      <h3 class="empty-state-title">No bids found</h3>
                      <p class="empty-state-text">Try adjusting your search criteria or filters</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($bids as $bid): ?>
                <tr class="table-row" data-bid-id="<?php echo $bid['id']; ?>">
                  <td class="table-id">
                    <span class="bid-id">#<?php echo $bid['id']; ?></span>
                  </td>
                  <td class="table-lead">
                    <div class="lead-info">
                      <div class="lead-title"><?php echo htmlspecialchars($bid['job_title'] ?? 'Unknown Job'); ?></div>
                      <?php if (!empty($bid['customer_name'])): ?>
                        <div class="lead-customer"><?php echo htmlspecialchars($bid['customer_name']); ?></div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="table-painter">
                    <div class="painter-info">
                      <i class="bi bi-person-circle painter-icon"></i>
                      <span class="painter-name"><?php echo htmlspecialchars($bid['company_name'] ?? 'Unknown Company'); ?></span>
                    </div>
                  </td>
                  <td class="table-amount">
                    <div class="amount-info">
                      <span class="amount-value"><?php echo $dataAccess->formatMoney($bid['bid_amount']); ?></span>
                    </div>
                  </td>
                  <td class="table-message">
                    <div class="message-preview">
                      <?php 
                      $message = $bid['message'] ?? '';
                      if (strlen($message) > 40) {
                          echo htmlspecialchars(substr($message, 0, 40)) . '...';
                      } else {
                          echo htmlspecialchars($message);
                      }
                      ?>
                    </div>
                  </td>
                  <td class="table-status">
                    <?php 
                    $status = $bid['status'] ?? 'pending';
                    $statusClass = '';
                    $statusIcon = '';
                    switch($status) {
                        case 'accepted':
                            $statusClass = 'status-accepted';
                            $statusIcon = 'bi-check-circle-fill';
                            break;
                        case 'rejected':
                            $statusClass = 'status-rejected';
                            $statusIcon = 'bi-x-circle-fill';
                            break;
                        case 'withdrawn':
                            $statusClass = 'status-withdrawn';
                            $statusIcon = 'bi-arrow-left-circle-fill';
                            break;
                        default:
                            $statusClass = 'status-pending';
                            $statusIcon = 'bi-clock-fill';
                    }
                    ?>
                    <span class="status-badge <?php echo $statusClass; ?>">
                      <i class="bi <?php echo $statusIcon; ?> status-indicator"></i>
                      <?php echo ucfirst($status); ?>
                    </span>
                  </td>
                  <td class="table-date">
                    <div class="date-info">
                      <div class="date-main"><?php echo $dataAccess->formatDate($bid['created_at']); ?></div>
                    </div>
                  </td>
                  <td class="table-actions">
                    <div class="dropdown">
                      <button class="btn btn--outline btn--small dropdown-toggle action-btn" 
                              type="button" 
                              data-bs-toggle="dropdown" 
                              aria-expanded="false">
                        <i class="bi bi-three-dots"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewBidModal<?php echo $bid['id']; ?>">
                            <i class="bi bi-eye"></i> View Details
                          </button>
                        </li>
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editBidModal<?php echo $bid['id']; ?>">
                            <i class="bi bi-pencil"></i> Edit Bid
                          </button>
                        </li>
                        <?php if (($bid['status'] ?? 'pending') !== 'accepted'): ?>
                        <li>
                          <button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#acceptBidModal<?php echo $bid['id']; ?>">
                            <i class="bi bi-check-circle"></i> Accept Bid
                          </button>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusBidModal<?php echo $bid['id']; ?>">
                            <i class="bi bi-arrow-repeat"></i> Change Status
                          </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteBidModal<?php echo $bid['id']; ?>">
                            <i class="bi bi-trash"></i> Delete Bid
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

    <!-- Modals for each bid -->
    <?php foreach ($bids as $bid): ?>
      <!-- View Modal -->
      <div class="modal fade" id="viewBidModal<?php echo $bid['id']; ?>" tabindex="-1" aria-labelledby="viewBidModalLabel<?php echo $bid['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title d-flex align-items-center gap-2" id="viewBidModalLabel<?php echo $bid['id']; ?>">
                <i class="bi bi-eye-fill"></i> Bid Details
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Lead:</strong> <?php echo htmlspecialchars($bid['job_title'] ?? 'Unknown Job'); ?></p>
                  <p><strong>Painter:</strong> <?php echo htmlspecialchars($bid['company_name'] ?? 'Unknown Company'); ?></p>
                  <p><strong>Amount:</strong> <?php echo $dataAccess->formatMoney($bid['bid_amount']); ?></p>
                </div>
                <div class="col-md-6">
                  <p><strong>Status:</strong> <span class="badge bg-secondary"><?php echo ucfirst($bid['status'] ?? 'pending'); ?></span></p>
                  <p><strong>Created:</strong> <?php echo $dataAccess->formatDateTime($bid['created_at']); ?></p>
                  <p><strong>Customer:</strong> <?php echo htmlspecialchars($bid['customer_name'] ?? 'Unknown'); ?></p>
                </div>
              </div>
              <?php if (!empty($bid['message'])): ?>
                <div class="mt-3">
                  <p><strong>Message:</strong></p>
                  <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($bid['message'])); ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div class="modal fade" id="editBidModal<?php echo $bid['id']; ?>" tabindex="-1" aria-labelledby="editBidModalLabel<?php echo $bid['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title d-flex align-items-center gap-2" id="editBidModalLabel<?php echo $bid['id']; ?>">
                <i class="bi bi-pencil-fill"></i> Edit Bid
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
              <div class="modal-body">
                <input type="hidden" name="edit_bid_id" value="<?php echo $bid['id']; ?>">
                <div class="mb-3">
                  <label for="bid_amount_<?php echo $bid['id']; ?>" class="form-label">Amount (£)</label>
                  <input type="number" step="0.01" class="form-control" id="bid_amount_<?php echo $bid['id']; ?>" name="bid_amount" value="<?php echo htmlspecialchars($bid['bid_amount']); ?>" required>
                </div>
                <div class="mb-3">
                  <label for="message_<?php echo $bid['id']; ?>" class="form-label">Message</label>
                  <textarea class="form-control" id="message_<?php echo $bid['id']; ?>" name="message" rows="4"><?php echo htmlspecialchars($bid['message'] ?? ''); ?></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Accept Modal -->
      <div class="modal fade" id="acceptBidModal<?php echo $bid['id']; ?>" tabindex="-1" aria-labelledby="acceptBidModalLabel<?php echo $bid['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title d-flex align-items-center gap-2" id="acceptBidModalLabel<?php echo $bid['id']; ?>">
                <i class="bi bi-check-circle-fill"></i> Accept Bid
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
              <div class="modal-body">
                <input type="hidden" name="accept_bid_id" value="<?php echo $bid['id']; ?>">
                <div class="alert alert-info">
                  <i class="bi bi-info-circle-fill"></i>
                  <strong>Confirm:</strong> Accept this bid and assign the painter to the lead?
                </div>
                <p><strong>Painter:</strong> <?php echo htmlspecialchars($bid['company_name'] ?? 'Unknown Company'); ?></p>
                <p><strong>Amount:</strong> <?php echo $dataAccess->formatMoney($bid['bid_amount']); ?></p>
                <p><strong>Lead:</strong> <?php echo htmlspecialchars($bid['job_title'] ?? 'Unknown Job'); ?></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Accept Bid</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Status Modal -->
      <div class="modal fade" id="statusBidModal<?php echo $bid['id']; ?>" tabindex="-1" aria-labelledby="statusBidModalLabel<?php echo $bid['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title d-flex align-items-center gap-2" id="statusBidModalLabel<?php echo $bid['id']; ?>">
                <i class="bi bi-arrow-repeat"></i> Change Bid Status
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
              <div class="modal-body">
                <input type="hidden" name="status_bid_id" value="<?php echo $bid['id']; ?>">
                <div class="mb-3">
                  <label for="status_<?php echo $bid['id']; ?>" class="form-label">New Status</label>
                  <select class="form-select" id="status_<?php echo $bid['id']; ?>" name="status" required>
                    <option value="pending" <?php echo ($bid['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="accepted" <?php echo ($bid['status'] ?? 'pending') === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo ($bid['status'] ?? 'pending') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="withdrawn" <?php echo ($bid['status'] ?? 'pending') === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark">Update Status</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Delete Modal -->
      <div class="modal fade" id="deleteBidModal<?php echo $bid['id']; ?>" tabindex="-1" aria-labelledby="deleteBidModalLabel<?php echo $bid['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title d-flex align-items-center gap-2" id="deleteBidModalLabel<?php echo $bid['id']; ?>">
                <i class="bi bi-exclamation-triangle-fill"></i> Delete Bid
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
              <div class="modal-body">
                <input type="hidden" name="delete_bid_id" value="<?php echo $bid['id']; ?>">
                <div class="alert alert-danger">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  <strong>Warning:</strong> This action cannot be undone. Are you sure you want to delete this bid?
                </div>
                <p><strong>Bid:</strong> <?php echo $dataAccess->formatMoney($bid['bid_amount']); ?> by <?php echo htmlspecialchars($bid['company_name'] ?? 'Unknown Company'); ?></p>
                <p><strong>Lead:</strong> <?php echo htmlspecialchars($bid['job_title'] ?? 'Unknown Job'); ?></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Bid</button>
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

// Enhanced interactions and animations
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
    
    // Bid amount highlighting on hover
    const amountValues = document.querySelectorAll('.amount-value');
    amountValues.forEach(amount => {
        amount.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        amount.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});

// CSS animations and styles
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
    
    .amount-value {
        cursor: pointer;
        font-weight: 700;
        color: #00b050;
        font-size: 1.1rem;
    }
    
    .alert--enhanced {
        border-left: 4px solid #28a745;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .status-accepted { background: #d4edda; color: #155724; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    .status-withdrawn { background: #e2e3e5; color: #495057; }
    
    .bid-id {
        font-weight: 700;
        color: #00b050;
        font-size: 0.9rem;
    }
    
    .lead-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .lead-title {
        font-weight: 600;
        color: #333;
        font-size: 1rem;
    }
    
    .lead-customer {
        color: #666;
        font-size: 0.9rem;
    }
    
    .painter-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .painter-icon {
        color: #00b050;
        font-size: 1.2rem;
    }
    
    .painter-name {
        font-weight: 600;
        color: #333;
    }
    
    .message-preview {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.4;
        max-width: 200px;
    }
    
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
`;
document.head.appendChild(style);
</script>

<?php include 'templates/footer.php'; ?> 