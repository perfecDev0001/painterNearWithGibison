<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonDataAccess.php';
require_once 'core/EmailNotificationService.php';

$dataAccess = new GibsonDataAccess();
$emailService = new Core\EmailNotificationService();

// Customer authentication system
session_start();

// Handle customer login/registration and actions
$errors = [];
$success = '';
$customer = null;
$customerLeads = [];

// Handle various POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required.';
            }
            
            if (empty($errors)) {
                // Find leads by customer email
                $leads = $dataAccess->getLeads();
                $customerLeads = array_filter($leads, function($lead) use ($email) {
                    return $lead['customer_email'] === $email;
                });
                
                if (!empty($customerLeads)) {
                    $_SESSION['customer_email'] = $email;
                    $_SESSION['customer_authenticated'] = true;
                    $customer = [
                        'email' => $email,
                        'name' => $customerLeads[0]['customer_name'] ?? 'Customer'
                    ];
                    $success = 'Welcome back! Access your project dashboard below.';
                } else {
                    $errors[] = 'No projects found for this email address.';
                }
            }
            break;
            

        case 'mark_milestone':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $milestone = $_POST['milestone'] ?? '';
                
                if ($leadId && $milestone) {
                    // Store milestone completion in session
                    if (!isset($_SESSION['project_milestones'])) {
                        $_SESSION['project_milestones'] = [];
                    }
                    
                    $_SESSION['project_milestones'][$leadId][$milestone] = [
                        'completed' => true,
                        'date' => date('Y-m-d H:i:s'),
                        'notes' => $_POST['milestone_notes'] ?? ''
                    ];
                    
                    $success = 'Milestone marked as complete!';
                }
            }
            break;
            
        case 'submit_review':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $painterId = $_POST['painter_id'] ?? '';
                $rating = intval($_POST['rating'] ?? 0);
                $review = trim($_POST['review'] ?? '');
                
                if ($leadId && $painterId && $rating >= 1 && $rating <= 5) {
                    // Store review in session
                    if (!isset($_SESSION['customer_reviews'])) {
                        $_SESSION['customer_reviews'] = [];
                    }
                    
                    $_SESSION['customer_reviews'][$leadId] = [
                        'painter_id' => $painterId,
                        'rating' => $rating,
                        'review' => $review,
                        'date' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Review submitted successfully!';
                }
            }
            break;
            
        case 'upload_document':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $documentType = $_POST['document_type'] ?? '';
                
                if ($leadId && isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    // Store document info in session (in production, save to database/storage)
                    if (!isset($_SESSION['customer_documents'])) {
                        $_SESSION['customer_documents'] = [];
                    }
                    
                    $fileName = $_FILES['document']['name'];
                    $fileSize = $_FILES['document']['size'];
                    
                    $_SESSION['customer_documents'][$leadId][] = [
                        'type' => $documentType,
                        'name' => $fileName,
                        'size' => $fileSize,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Document uploaded successfully!';
                }
            }
            break;
            
        case 'save_project_notes':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $notes = trim($_POST['notes'] ?? '');
                
                if ($leadId) {
                    if (!isset($_SESSION['customer_project_notes'])) {
                        $_SESSION['customer_project_notes'] = [];
                    }
                    
                    $_SESSION['customer_project_notes'][$leadId] = [
                        'notes' => $notes,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Project notes saved successfully!';
                }
            }
            break;
            
        case 'schedule_appointment':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $painterId = $_POST['painter_id'] ?? '';
                $appointmentDate = $_POST['appointment_date'] ?? '';
                $appointmentTime = $_POST['appointment_time'] ?? '';
                $appointmentType = $_POST['appointment_type'] ?? '';
                
                if ($leadId && $painterId && $appointmentDate && $appointmentTime) {
                    if (!isset($_SESSION['customer_appointments'])) {
                        $_SESSION['customer_appointments'] = [];
                    }
                    
                    $_SESSION['customer_appointments'][$leadId][] = [
                        'painter_id' => $painterId,
                        'date' => $appointmentDate,
                        'time' => $appointmentTime,
                        'type' => $appointmentType,
                        'status' => 'scheduled',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Send email notification
                    $emailService->sendAppointmentScheduledNotification(
                        $customerEmail,
                        $customer['name'],
                        $appointmentDate,
                        $appointmentTime,
                        $appointmentType
                    );
                    
                    $success = 'Appointment scheduled successfully!';
                }
            }
            break;
            
        case 'request_quote_modification':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $bidId = $_POST['bid_id'] ?? '';
                $modificationType = $_POST['modification_type'] ?? '';
                $modificationDetails = trim($_POST['modification_details'] ?? '');
                
                if ($leadId && $bidId && $modificationType) {
                    if (!isset($_SESSION['quote_modifications'])) {
                        $_SESSION['quote_modifications'] = [];
                    }
                    
                    $_SESSION['quote_modifications'][$bidId] = [
                        'lead_id' => $leadId,
                        'type' => $modificationType,
                        'details' => $modificationDetails,
                        'status' => 'pending',
                        'requested_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Quote modification request sent successfully!';
                }
            }
            break;
            
        case 'upload_document':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $documentType = $_POST['document_type'] ?? '';
                
                if ($leadId && isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    // Store document info in session (in production, save to database/storage)
                    if (!isset($_SESSION['customer_documents'])) {
                        $_SESSION['customer_documents'] = [];
                    }
                    
                    $fileName = $_FILES['document']['name'];
                    $fileSize = $_FILES['document']['size'];
                    
                    $_SESSION['customer_documents'][$leadId][] = [
                        'type' => $documentType,
                        'name' => $fileName,
                        'size' => $fileSize,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Document uploaded successfully!';
                }
            }
            break;
            
        case 'save_project_notes':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $notes = trim($_POST['notes'] ?? '');
                
                if ($leadId) {
                    if (!isset($_SESSION['customer_project_notes'])) {
                        $_SESSION['customer_project_notes'] = [];
                    }
                    
                    $_SESSION['customer_project_notes'][$leadId] = [
                        'notes' => $notes,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Project notes saved successfully!';
                }
            }
            break;
            
        case 'schedule_appointment':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $painterId = $_POST['painter_id'] ?? '';
                $appointmentDate = $_POST['appointment_date'] ?? '';
                $appointmentTime = $_POST['appointment_time'] ?? '';
                $appointmentType = $_POST['appointment_type'] ?? '';
                
                if ($leadId && $painterId && $appointmentDate && $appointmentTime) {
                    if (!isset($_SESSION['customer_appointments'])) {
                        $_SESSION['customer_appointments'] = [];
                    }
                    
                    $_SESSION['customer_appointments'][$leadId][] = [
                        'painter_id' => $painterId,
                        'date' => $appointmentDate,
                        'time' => $appointmentTime,
                        'type' => $appointmentType,
                        'status' => 'scheduled',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Send email notification
                    $emailService->sendAppointmentScheduledNotification(
                        $customerEmail,
                        $customer['name'],
                        $appointmentDate,
                        $appointmentTime,
                        $appointmentType
                    );
                    
                    $success = 'Appointment scheduled successfully!';
                }
            }
            break;
            
        case 'request_quote_modification':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $bidId = $_POST['bid_id'] ?? '';
                $modificationType = $_POST['modification_type'] ?? '';
                $modificationDetails = trim($_POST['modification_details'] ?? '');
                
                if ($leadId && $bidId && $modificationType) {
                    if (!isset($_SESSION['quote_modifications'])) {
                        $_SESSION['quote_modifications'] = [];
                    }
                    
                    $_SESSION['quote_modifications'][$bidId] = [
                        'lead_id' => $leadId,
                        'type' => $modificationType,
                        'details' => $modificationDetails,
                        'status' => 'pending',
                        'requested_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Quote modification request sent successfully!';
                }
            }
            break;
    }
}

// Check if customer is logged in
if (isset($_SESSION['customer_authenticated']) && $_SESSION['customer_authenticated']) {
    $customerEmail = $_SESSION['customer_email'];
    $leads = $dataAccess->getLeads();
    $customerLeads = array_filter($leads, function($lead) use ($customerEmail) {
        return $lead['customer_email'] === $customerEmail;
    });
    
    $customer = [
        'email' => $customerEmail,
        'name' => !empty($customerLeads) ? array_values($customerLeads)[0]['customer_name'] : 'Customer'
    ];
}

// Enhanced dashboard metrics
$totalProjects = count($customerLeads);
$activeProjects = count(array_filter($customerLeads, function($lead) {
    return $lead['status'] === 'open';
}));
$completedProjects = count(array_filter($customerLeads, function($lead) {
    return $lead['status'] === 'closed';
}));
$inProgressProjects = count(array_filter($customerLeads, function($lead) {
    return $lead['status'] === 'assigned';
}));

// Get messaging metrics for authenticated customer
$unreadMessageCount = 0;
$totalConversations = 0;
if (isset($_SESSION['customer_authenticated']) && $_SESSION['customer_authenticated']) {
    $customerId = $_SESSION['customer_id'] ?? 'customer_' . session_id();
    $unreadResult = $dataAccess->getUnreadMessageCount($customerId, 'customer');
    $unreadMessageCount = $unreadResult['success'] ? ($unreadResult['data']['count'] ?? 0) : 0;
    
    $conversationsResult = $dataAccess->getConversationsByUser($customerId, 'customer');
    $totalConversations = $conversationsResult['success'] ? count($conversationsResult['data']) : 0;
}

$totalBids = 0;
$averageBid = 0;
$totalSpent = 0;

if (!empty($customerLeads)) {
    foreach ($customerLeads as $lead) {
        $leadBids = $dataAccess->getLeadBids($lead['id']);
        $totalBids += count($leadBids);
        
        // Calculate spending for completed projects
        if ($lead['status'] === 'closed') {
            $acceptedBids = array_filter($leadBids, function($bid) {
                return $bid['status'] === 'accepted';
            });
            if (!empty($acceptedBids)) {
                $totalSpent += floatval(array_values($acceptedBids)[0]['bid_amount']);
            }
        }
    }
    
    // Calculate average bid amount
    $allBidAmounts = [];
    foreach ($customerLeads as $lead) {
        $leadBids = $dataAccess->getLeadBids($lead['id']);
        foreach ($leadBids as $bid) {
            $allBidAmounts[] = floatval($bid['bid_amount']);
        }
    }
    
    if (!empty($allBidAmounts)) {
        $averageBid = array_sum($allBidAmounts) / count($allBidAmounts);
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';

// Apply filters
$filteredLeads = $customerLeads;
if ($statusFilter !== 'all') {
    $filteredLeads = array_filter($customerLeads, function($lead) use ($statusFilter) {
        return $lead['status'] === $statusFilter;
    });
}

// Apply sorting
usort($filteredLeads, function($a, $b) use ($sortBy) {
    switch ($sortBy) {
        case 'oldest':
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        case 'title':
            return strcmp($a['job_title'], $b['job_title']);
        case 'location':
            return strcmp($a['location'], $b['location']);
        default: // newest
            return strtotime($b['created_at']) - strtotime($a['created_at']);
    }
});

include 'templates/header.php';
?>

<head>
    <title>Customer Dashboard | Painter Near Me</title>
    <meta name="description" content="Manage your painting projects, view bids, and track progress on Painter Near Me." />
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<main class="customer-dashboard" role="main">
    <div class="customer-dashboard__container">
        
        <?php if (!$customer): ?>
        <!-- Customer Login Section -->
        <section class="customer-dashboard__login">
            <div class="customer-dashboard__login-container">
                <header class="customer-dashboard__login-header">
                    <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me logo" loading="lazy" class="customer-dashboard__logo" />
                    <h1 class="customer-dashboard__login-title">Customer Project Dashboard</h1>
                    <p class="customer-dashboard__login-subtitle">Access your painting projects and manage bids</p>
                </header>

                <?php if (!empty($errors)): ?>
                    <div class="customer-dashboard__alert customer-dashboard__alert--error" role="alert">
                        <ul class="customer-dashboard__error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="customer-dashboard__login-form" method="post" action="">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="customer-dashboard__form-group">
                        <label class="customer-dashboard__label" for="customer-email">Email Address</label>
                        <input 
                            class="customer-dashboard__input" 
                            type="email" 
                            id="customer-email" 
                            name="email" 
                            required 
                            placeholder="Enter the email used when posting your job"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        />
                        <small class="customer-dashboard__help-text">
                            Use the same email address you provided when posting your painting job
                        </small>
                    </div>

                    <button class="customer-dashboard__submit" type="submit">
                        Access My Projects
                    </button>
                </form>

                <div class="customer-dashboard__login-help">
                    <h3>Need Help?</h3>
                    <p>Having trouble accessing your projects? Contact us at <a href="mailto:info@painter-near-me.co.uk">info@painter-near-me.co.uk</a></p>
                    <p>Don't have a project yet? <a href="/">Post a new painting job</a></p>
                </div>
            </div>
        </section>

        <?php else: ?>
        <!-- Customer Dashboard -->
        <section class="customer-dashboard__main">
            
            <!-- Dashboard Header -->
            <header class="customer-dashboard__header">
                <div class="customer-dashboard__welcome">
                    <h1 class="customer-dashboard__title">Welcome back, <?php echo htmlspecialchars($customer['name']); ?>!</h1>
                    <p class="customer-dashboard__subtitle">
                        Manage your painting projects and track progress
                        <?php if ($unreadMessageCount > 0): ?>
                            <span style="background:#ff4444;color:white;padding:0.3rem 0.6rem;border-radius:1rem;font-size:0.85rem;margin-left:1rem;">
                                <?php echo $unreadMessageCount; ?> new message<?php echo $unreadMessageCount > 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="customer-dashboard__header-actions">
                    <?php if ($unreadMessageCount > 0): ?>
                        <a href="messaging.php" class="customer-dashboard__btn customer-dashboard__btn--primary">
                            <i class="bi bi-chat-dots"></i> Messages (<?php echo $unreadMessageCount; ?>)
                        </a>
                    <?php endif; ?>
                    <a href="/" class="customer-dashboard__btn customer-dashboard__btn--secondary">
                        <i class="bi bi-plus-circle"></i> Post New Job
                    </a>
                    <button onclick="location.href='?logout=1'" class="customer-dashboard__btn customer-dashboard__btn--outline">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="customer-dashboard__alert customer-dashboard__alert--success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Enhanced Dashboard Metrics -->
            <section class="customer-dashboard__metrics">
                <div class="customer-dashboard__metrics-grid">
                    <div class="customer-dashboard__metric">
                        <div class="customer-dashboard__metric-icon customer-dashboard__metric-icon--projects">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>
                        <div class="customer-dashboard__metric-content">
                            <div class="customer-dashboard__metric-value"><?php echo $totalProjects; ?></div>
                            <div class="customer-dashboard__metric-label">Total Projects</div>
                            <div class="customer-dashboard__metric-trend">
                                <?php if ($completedProjects > 0): ?>
                                    <small><?php echo $completedProjects; ?> completed</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-dashboard__metric">
                        <div class="customer-dashboard__metric-icon customer-dashboard__metric-icon--active">
                            <i class="bi bi-play-circle-fill"></i>
                        </div>
                        <div class="customer-dashboard__metric-content">
                            <div class="customer-dashboard__metric-value"><?php echo $activeProjects; ?></div>
                            <div class="customer-dashboard__metric-label">Open Projects</div>
                            <div class="customer-dashboard__metric-trend">
                                <?php if ($inProgressProjects > 0): ?>
                                    <small><?php echo $inProgressProjects; ?> in progress</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-dashboard__metric">
                        <div class="customer-dashboard__metric-icon customer-dashboard__metric-icon--bids">
                            <i class="bi bi-person-raised-hand"></i>
                        </div>
                        <div class="customer-dashboard__metric-content">
                            <div class="customer-dashboard__metric-value"><?php echo $totalBids; ?></div>
                            <div class="customer-dashboard__metric-label">Total Bids Received</div>
                            <div class="customer-dashboard__metric-trend">
                                <?php if ($totalBids > 0): ?>
                                    <small>Avg: £<?php echo number_format($averageBid, 0); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-dashboard__metric">
                        <div class="customer-dashboard__metric-icon customer-dashboard__metric-icon--spending">
                            <i class="bi bi-currency-pound"></i>
                        </div>
                        <div class="customer-dashboard__metric-content">
                            <div class="customer-dashboard__metric-value">£<?php echo number_format($totalSpent, 0); ?></div>
                            <div class="customer-dashboard__metric-label">Total Spent</div>
                            <div class="customer-dashboard__metric-trend">
                                <?php if ($completedProjects > 0): ?>
                                    <small>Avg: £<?php echo number_format($totalSpent / $completedProjects, 0); ?> per project</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-dashboard__metric">
                        <div class="customer-dashboard__metric-icon customer-dashboard__metric-icon--messages">
                            <i class="bi bi-chat-dots-fill"></i>
                        </div>
                        <div class="customer-dashboard__metric-content">
                            <div class="customer-dashboard__metric-value"><?php echo $unreadMessageCount; ?></div>
                            <div class="customer-dashboard__metric-label">Unread Messages</div>
                            <div class="customer-dashboard__metric-trend">
                                <?php if ($totalConversations > 0): ?>
                                    <small><?php echo $totalConversations; ?> conversation<?php echo $totalConversations > 1 ? 's' : ''; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Project Status Overview -->
                <?php if ($totalProjects > 0): ?>
                <div class="customer-dashboard__status-overview">
                    <h3 class="customer-dashboard__status-title">Project Status Overview</h3>
                    <div class="customer-dashboard__status-bar">
                        <?php 
                        $openPercentage = ($activeProjects / $totalProjects) * 100;
                        $progressPercentage = ($inProgressProjects / $totalProjects) * 100;
                        $completedPercentage = ($completedProjects / $totalProjects) * 100;
                        ?>
                        <div class="customer-dashboard__status-segment customer-dashboard__status-segment--open" 
                             style="width: <?php echo $openPercentage; ?>%"
                             title="<?php echo $activeProjects; ?> Open Projects">
                        </div>
                        <div class="customer-dashboard__status-segment customer-dashboard__status-segment--progress" 
                             style="width: <?php echo $progressPercentage; ?>%"
                             title="<?php echo $inProgressProjects; ?> In Progress">
                        </div>
                        <div class="customer-dashboard__status-segment customer-dashboard__status-segment--completed" 
                             style="width: <?php echo $completedPercentage; ?>%"
                             title="<?php echo $completedProjects; ?> Completed">
                        </div>
                    </div>
                    <div class="customer-dashboard__status-legend">
                        <span class="customer-dashboard__legend-item">
                            <span class="customer-dashboard__legend-color customer-dashboard__legend-color--open"></span>
                            Open (<?php echo $activeProjects; ?>)
                        </span>
                        <span class="customer-dashboard__legend-item">
                            <span class="customer-dashboard__legend-color customer-dashboard__legend-color--progress"></span>
                            In Progress (<?php echo $inProgressProjects; ?>)
                        </span>
                        <span class="customer-dashboard__legend-item">
                            <span class="customer-dashboard__legend-color customer-dashboard__legend-color--completed"></span>
                            Completed (<?php echo $completedProjects; ?>)
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <!-- Projects List -->
            <section class="customer-dashboard__projects">
                <div class="customer-dashboard__projects-header">
                    <h2 class="customer-dashboard__section-title">Your Projects</h2>
                    
                    <!-- Advanced Filters and Search -->
                    <div class="customer-dashboard__filters">
                        <div class="customer-dashboard__search-box">
                            <input type="text" 
                                   id="project-search" 
                                   placeholder="Search projects..." 
                                   class="customer-dashboard__search-input"
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <i class="bi bi-search customer-dashboard__search-icon"></i>
                        </div>
                        
                        <div class="customer-dashboard__filter-group">
                            <select id="status-filter" class="customer-dashboard__filter-select">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="assigned" <?php echo $statusFilter === 'assigned' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            
                            <select id="sort-filter" class="customer-dashboard__filter-select">
                                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>By Title</option>
                                <option value="location" <?php echo $sortBy === 'location' ? 'selected' : ''; ?>>By Location</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($filteredLeads)): ?>
                    <div class="customer-dashboard__empty-state">
                        <i class="bi bi-inbox customer-dashboard__empty-icon"></i>
                        <h3 class="customer-dashboard__empty-title">No projects found</h3>
                        <p class="customer-dashboard__empty-text">Start by posting your first painting job</p>
                        <a href="/" class="customer-dashboard__btn customer-dashboard__btn--primary">Post a Job</a>
                    </div>
                <?php else: ?>
                    <div class="customer-dashboard__projects-grid">
                        <?php foreach ($filteredLeads as $lead): ?>
                            <?php 
                            $leadBids = $dataAccess->getLeadBids($lead['id']);
                            $bidCount = count($leadBids);
                            $lowestBid = null;
                            $highestBid = null;
                            
                            if (!empty($leadBids)) {
                                $bidAmounts = array_map(function($bid) { return floatval($bid['bid_amount']); }, $leadBids);
                                $lowestBid = min($bidAmounts);
                                $highestBid = max($bidAmounts);
                            }
                            ?>
                            
                            <div class="customer-dashboard__project-card" data-project-id="<?php echo $lead['id']; ?>">
                                <div class="customer-dashboard__project-header">
                                    <h3 class="customer-dashboard__project-title"><?php echo htmlspecialchars($lead['job_title']); ?></h3>
                                    <div class="customer-dashboard__project-status-group">
                                        <span class="customer-dashboard__project-status customer-dashboard__project-status--<?php echo $lead['status']; ?>">
                                            <?php 
                                            switch($lead['status']) {
                                                case 'open': echo '<i class="bi bi-circle"></i> Open'; break;
                                                case 'assigned': echo '<i class="bi bi-play-circle"></i> In Progress'; break;
                                                case 'closed': echo '<i class="bi bi-check-circle"></i> Completed'; break;
                                                default: echo ucfirst($lead['status']);
                                            }
                                            ?>
                                        </span>
                                        <?php if ($lead['status'] === 'assigned'): ?>
                                            <span class="customer-dashboard__project-priority">
                                                <i class="bi bi-clock"></i> Active
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="customer-dashboard__project-details">
                                    <div class="customer-dashboard__project-detail">
                                        <i class="bi bi-geo-alt"></i>
                                        <span><?php echo htmlspecialchars($lead['location']); ?></span>
                                    </div>
                                    <div class="customer-dashboard__project-detail">
                                        <i class="bi bi-calendar3"></i>
                                        <span><?php echo $dataAccess->formatDate($lead['created_at']); ?></span>
                                    </div>
                                    <div class="customer-dashboard__project-detail">
                                        <i class="bi bi-person-raised-hand"></i>
                                        <span><?php echo $bidCount; ?> bid<?php echo $bidCount !== 1 ? 's' : ''; ?> received</span>
                                    </div>
                                </div>
                                
                                <div class="customer-dashboard__project-description">
                                    <?php echo nl2br(htmlspecialchars(substr($lead['job_description'], 0, 150) . (strlen($lead['job_description']) > 150 ? '...' : ''))); ?>
                                </div>
                                
                                <!-- Project Timeline & Progress -->
                                <?php if ($lead['status'] === 'assigned' || $lead['status'] === 'closed'): ?>
                                <div class="customer-dashboard__project-timeline">
                                    <h5 class="customer-dashboard__timeline-title">
                                        <i class="bi bi-list-check"></i> Project Progress
                                    </h5>
                                    <?php 
                                    $milestones = [
                                        'project_started' => 'Project Started',
                                        'materials_ordered' => 'Materials Ordered',
                                        'prep_work_complete' => 'Prep Work Complete',
                                        'painting_started' => 'Painting Started',
                                        'first_coat_complete' => 'First Coat Complete',
                                        'final_coat_complete' => 'Final Coat Complete',
                                        'cleanup_complete' => 'Cleanup Complete',
                                        'project_complete' => 'Project Complete'
                                    ];
                                    
                                    $completedMilestones = $_SESSION['project_milestones'][$lead['id']] ?? [];
                                    $completedCount = count($completedMilestones);
                                    $totalMilestones = count($milestones);
                                    $progressPercentage = ($completedCount / $totalMilestones) * 100;
                                    ?>
                                    
                                    <div class="customer-dashboard__progress-bar">
                                        <div class="customer-dashboard__progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                                        <span class="customer-dashboard__progress-text"><?php echo round($progressPercentage); ?>% Complete</span>
                                    </div>
                                    
                                    <div class="customer-dashboard__milestones">
                                        <?php foreach (array_slice($milestones, 0, 4) as $key => $milestone): ?>
                                            <div class="customer-dashboard__milestone <?php echo isset($completedMilestones[$key]) ? 'customer-dashboard__milestone--completed' : ''; ?>">
                                                <i class="bi bi-<?php echo isset($completedMilestones[$key]) ? 'check-circle-fill' : 'circle'; ?>"></i>
                                                <span><?php echo $milestone; ?></span>
                                                <?php if (isset($completedMilestones[$key])): ?>
                                                    <small><?php echo date('M j', strtotime($completedMilestones[$key]['date'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($totalMilestones > 4): ?>
                                            <button class="customer-dashboard__milestone-toggle" onclick="toggleMilestones(<?php echo $lead['id']; ?>)">
                                                <i class="bi bi-chevron-down"></i> View All Milestones
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Communication Section -->
                                <?php if ($lead['status'] === 'assigned'): ?>
                                <div class="customer-dashboard__communication">
                                    <h5 class="customer-dashboard__communication-title">
                                        <i class="bi bi-chat-dots"></i> Communication
                                    </h5>
                                    
                                    <?php 
                                    // Get conversation for this lead using Gibson AI messaging system
                                    $customerId = $_SESSION['customer_id'] ?? 'customer_' . session_id();
                                    $painterId = $lead['assigned_painter_id'] ?? null;
                                    $leadConversation = null;
                                    $leadMessages = [];
                                    $leadUnreadCount = 0;
                                    
                                    if ($painterId) {
                                        $conversationResult = $dataAccess->getOrCreateConversation($lead['id'], $customerId, $painterId);
                                        if ($conversationResult['success']) {
                                            $leadConversation = $conversationResult['data'];
                                            $messagesResult = $dataAccess->getConversationMessages($leadConversation['id']);
                                            if ($messagesResult['success']) {
                                                $leadMessages = $messagesResult['data'];
                                                // Count unread messages from painter
                                                $leadUnreadCount = count(array_filter($leadMessages, function($msg) {
                                                    return !$msg['is_read'] && $msg['sender_type'] !== 'customer';
                                                }));
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <div class="customer-dashboard__message-summary">
                                        <span class="customer-dashboard__message-count">
                                            <?php echo count($leadMessages); ?> messages
                                        </span>
                                        <?php if ($leadUnreadCount > 0): ?>
                                            <span class="customer-dashboard__unread-badge"><?php echo $leadUnreadCount; ?> unread</span>
                                        <?php endif; ?>
                                        <?php if ($leadConversation): ?>
                                            <a href="messaging.php?conversation_id=<?php echo $leadConversation['id']; ?>" class="customer-dashboard__message-btn">
                                                <i class="bi bi-chat-dots"></i> View Messages
                                            </a>
                                        <?php else: ?>
                                            <a href="messaging.php?lead_id=<?php echo $lead['id']; ?>&painter_id=<?php echo $painterId; ?>" class="customer-dashboard__message-btn">
                                                <i class="bi bi-plus"></i> Start Conversation
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($leadMessages)): ?>
                                        <div class="customer-dashboard__recent-message">
                                            <?php $lastMessage = end($leadMessages); ?>
                                            <small>
                                                <strong><?php echo $lastMessage['sender_type'] === 'customer' ? 'You' : 'Painter'; ?>:</strong>
                                                <?php echo htmlspecialchars(substr($lastMessage['message_text'], 0, 50) . '...'); ?>
                                                <em><?php echo date('M j, g:i A', strtotime($lastMessage['sent_at'])); ?></em>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($bidCount > 0): ?>
                                    <div class="customer-dashboard__project-bids">
                                        <h4 class="customer-dashboard__bids-title">Bid Summary</h4>
                                        <div class="customer-dashboard__bid-summary">
                                            <div class="customer-dashboard__bid-range">
                                                <span>Range: £<?php echo number_format($lowestBid, 0); ?> - £<?php echo number_format($highestBid, 0); ?></span>
                                            </div>
                                        </div>
                                        
                                        <a href="quote-bids.php?lead_id=<?php echo $lead['id']; ?>" class="customer-dashboard__btn customer-dashboard__btn--primary customer-dashboard__btn--small">
                                            View All Bids
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="customer-dashboard__no-bids">
                                        <p>No bids received yet. Painters will be notified about your project.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Review Section for Completed Projects -->
                                <?php if ($lead['status'] === 'closed'): ?>
                                    <?php $existingReview = $_SESSION['customer_reviews'][$lead['id']] ?? null; ?>
                                    <div class="customer-dashboard__review-section">
                                        <h5 class="customer-dashboard__review-title">
                                            <i class="bi bi-star"></i> Project Review
                                        </h5>
                                        
                                        <?php if ($existingReview): ?>
                                            <div class="customer-dashboard__existing-review">
                                                <div class="customer-dashboard__rating-display">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?php echo $i <= $existingReview['rating'] ? '-fill' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span><?php echo $existingReview['rating']; ?>/5</span>
                                                </div>
                                                <p class="customer-dashboard__review-text">
                                                    "<?php echo htmlspecialchars($existingReview['review']); ?>"
                                                </p>
                                                <small>Reviewed on <?php echo date('M j, Y', strtotime($existingReview['date'])); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <button class="customer-dashboard__review-btn" onclick="openReviewModal(<?php echo $lead['id']; ?>, <?php echo $lead['assigned_painter_id'] ?? 0; ?>)">
                                                <i class="bi bi-star"></i> Leave Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="customer-dashboard__project-actions">
                                    <a href="quote-bids.php?lead_id=<?php echo $lead['id']; ?>" class="customer-dashboard__btn customer-dashboard__btn--outline customer-dashboard__btn--small">
                                        <i class="bi bi-eye"></i> Manage Project
                                    </a>
                                    
                                    <?php if ($lead['status'] === 'assigned' && $lead['assigned_painter_id']): ?>
                                        <?php
                                        // Check if conversation exists for messaging link
                                        $customerId = $_SESSION['customer_id'] ?? 'customer_' . session_id();
                                        $painterId = $lead['assigned_painter_id'];
                                        $conversationResult = $dataAccess->getOrCreateConversation($lead['id'], $customerId, $painterId);
                                        if ($conversationResult['success']):
                                        ?>
                                            <a href="messaging.php?conversation_id=<?php echo $conversationResult['data']['id']; ?>" class="customer-dashboard__btn customer-dashboard__btn--primary customer-dashboard__btn--small">
                                                <i class="bi bi-chat"></i> Message Painter
                                            </a>
                                        <?php else: ?>
                                            <a href="messaging.php?lead_id=<?php echo $lead['id']; ?>&painter_id=<?php echo $painterId; ?>" class="customer-dashboard__btn customer-dashboard__btn--primary customer-dashboard__btn--small">
                                                <i class="bi bi-chat"></i> Contact Painter
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($lead['status'] === 'open' && $bidCount > 0): ?>
                                        <span class="customer-dashboard__action-hint">
                                            <i class="bi bi-info-circle"></i> <?php echo $bidCount; ?> bid<?php echo $bidCount > 1 ? 's' : ''; ?> waiting for review
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Quick Actions -->
            <section class="customer-dashboard__quick-actions">
                <h2 class="customer-dashboard__section-title">Quick Actions</h2>
                <div class="customer-dashboard__actions-grid">
                    <a href="/" class="customer-dashboard__action-card">
                        <i class="bi bi-plus-circle-fill"></i>
                        <h3>Post New Job</h3>
                        <p>Get quotes for a new painting project</p>
                    </a>
                    <a href="messaging.php" class="customer-dashboard__action-card">
                        <i class="bi bi-chat-dots-fill"></i>
                        <h3>Messages</h3>
                        <p>Communicate with your painters</p>
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="customer-dashboard__action-badge"><?php echo $unreadMessageCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="mailto:info@painter-near-me.co.uk" class="customer-dashboard__action-card">
                        <i class="bi bi-headset"></i>
                        <h3>Get Support</h3>
                        <p>Contact our team for help</p>
                    </a>
                    <a href="/" class="customer-dashboard__action-card">
                        <i class="bi bi-star-fill"></i>
                        <h3>Leave Review</h3>
                        <p>Review completed projects</p>
                    </a>
                </div>
            </section>
        </section>
        <?php endif; ?>
    </div>
    

    
    <!-- Review Modal -->
    <div id="reviewModal" class="customer-dashboard__modal" style="display: none;">
        <div class="customer-dashboard__modal-content">
            <div class="customer-dashboard__modal-header">
                <h3><i class="bi bi-star"></i> Leave a Review</h3>
                <button class="customer-dashboard__modal-close" onclick="closeModal('reviewModal')">&times;</button>
            </div>
            <form method="post" class="customer-dashboard__modal-form">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="lead_id" id="reviewLeadId">
                <input type="hidden" name="painter_id" id="reviewPainterId">
                
                <div class="customer-dashboard__form-group">
                    <label>Rating:</label>
                    <div class="customer-dashboard__rating-input">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                            <label for="rating<?php echo $i; ?>" class="customer-dashboard__star-label">
                                <i class="bi bi-star"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="customer-dashboard__form-group">
                    <label for="reviewText">Your Review:</label>
                    <textarea name="review" id="reviewText" rows="4" placeholder="Share your experience with this painter..." required></textarea>
                </div>
                
                <div class="customer-dashboard__modal-actions">
                    <button type="button" class="customer-dashboard__btn customer-dashboard__btn--outline" onclick="closeModal('reviewModal')">Cancel</button>
                    <button type="submit" class="customer-dashboard__btn customer-dashboard__btn--primary">
                        <i class="bi bi-star"></i> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php 
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: customer-dashboard.php');
    exit();
}

include 'templates/footer.php'; 
?>

<script>
// Enhanced Customer Dashboard JavaScript

// Filter and Search Functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('project-search');
    const statusFilter = document.getElementById('status-filter');
    const sortFilter = document.getElementById('sort-filter');
    
    // Real-time search
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterProjects();
        });
    }
    
    // Filter change handlers
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            updateURL();
        });
    }
    
    if (sortFilter) {
        sortFilter.addEventListener('change', function() {
            updateURL();
        });
    }
});

function filterProjects() {
    const searchTerm = document.getElementById('project-search').value.toLowerCase();
    const projectCards = document.querySelectorAll('.customer-dashboard__project-card');
    
    projectCards.forEach(card => {
        const title = card.querySelector('.customer-dashboard__project-title').textContent.toLowerCase();
        const description = card.querySelector('.customer-dashboard__project-description').textContent.toLowerCase();
        const location = card.querySelector('.customer-dashboard__project-detail span').textContent.toLowerCase();
        
        const matches = title.includes(searchTerm) || 
                       description.includes(searchTerm) || 
                       location.includes(searchTerm);
        
        card.style.display = matches ? 'block' : 'none';
    });
}

function updateURL() {
    const status = document.getElementById('status-filter').value;
    const sort = document.getElementById('sort-filter').value;
    const search = document.getElementById('project-search').value;
    
    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (sort !== 'newest') params.set('sort', sort);
    if (search) params.set('search', search);
    
    const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = newURL;
}

// Modal Functions
function openReviewModal(leadId, painterId) {
    document.getElementById('reviewLeadId').value = leadId;
    document.getElementById('reviewPainterId').value = painterId;
    document.getElementById('reviewText').value = '';
    
    // Reset rating
    const ratingInputs = document.querySelectorAll('input[name="rating"]');
    ratingInputs.forEach(input => input.checked = false);
    
    document.getElementById('reviewModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Phase 2: Open appointment modal
function openAppointmentModal(leadId, painterId) {
    document.getElementById('appointmentLeadId').value = leadId;
    document.getElementById('appointmentPainterId').value = painterId;
    document.getElementById('appointmentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Phase 2: Open quote modification modal
function openQuoteModificationModal(leadId, bidId) {
    document.getElementById('modificationLeadId').value = leadId;
    document.getElementById('modificationBidId').value = bidId;
    document.getElementById('quoteModificationModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Phase 2: Open gallery modal
function openGalleryModal(leadId) {
    document.getElementById('galleryModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    initializeGallery(leadId);
}

// Phase 2: Initialize gallery functionality
function initializeGallery(leadId) {
    const uploadInput = document.getElementById('galleryUpload');
    const newUploadInput = uploadInput.cloneNode(true);
    uploadInput.parentNode.replaceChild(newUploadInput, uploadInput);
    
    newUploadInput.addEventListener('change', function(e) {
        handleGalleryUpload(e.target.files, leadId);
    });
}

// Phase 2: Handle gallery photo upload
function handleGalleryUpload(files, leadId) {
    const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
    const placeholder = galleryGrid.querySelector('.gallery-placeholder');
    
    if (placeholder) {
        placeholder.style.display = 'none';
    }
    
    Array.from(files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imagePreview = document.createElement('div');
                imagePreview.className = 'gallery-image-preview';
                imagePreview.innerHTML = `
                    <img src="${e.target.result}" alt="Project photo">
                    <div class="gallery-image-overlay">
                        <button onclick="removeGalleryImage(this)" class="gallery-remove-btn">×</button>
                        <span class="gallery-image-name">${file.name}</span>
                    </div>
                `;
                galleryGrid.appendChild(imagePreview);
            };
            reader.readAsDataURL(file);
        }
    });
    
    showNotification(`${files.length} photo(s) uploaded successfully`, 'success');
}

// Phase 2: Remove gallery image
function removeGalleryImage(button) {
    button.closest('.gallery-image-preview').remove();
    showNotification('Photo removed', 'info');
}

// Phase 2: Enhanced notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `customer-dashboard__notification customer-dashboard__notification--${type}`;
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentNode.remove()">×</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Milestone Toggle
function toggleMilestones(leadId) {
    const button = event.target;
    const milestones = button.parentElement;
    const hiddenMilestones = milestones.querySelectorAll('.customer-dashboard__milestone:nth-child(n+5)');
    
    if (button.textContent.includes('View All')) {
        hiddenMilestones.forEach(milestone => milestone.style.display = 'flex');
        button.innerHTML = '<i class="bi bi-chevron-up"></i> Show Less';
    } else {
        hiddenMilestones.forEach(milestone => milestone.style.display = 'none');
        button.innerHTML = '<i class="bi bi-chevron-down"></i> View All Milestones';
    }
}

// Star Rating Interaction
document.addEventListener('DOMContentLoaded', function() {
    const starLabels = document.querySelectorAll('.customer-dashboard__star-label');
    
    starLabels.forEach((label, index) => {
        label.addEventListener('mouseenter', function() {
            highlightStars(index + 1);
        });
        
        label.addEventListener('click', function() {
            selectStars(index + 1);
        });
    });
    
    // Reset on mouse leave
    const ratingContainer = document.querySelector('.customer-dashboard__rating-input');
    if (ratingContainer) {
        ratingContainer.addEventListener('mouseleave', function() {
            const checkedRating = document.querySelector('input[name="rating"]:checked');
            if (checkedRating) {
                highlightStars(parseInt(checkedRating.value));
            } else {
                highlightStars(0);
            }
        });
    }
});

function highlightStars(rating) {
    const starLabels = document.querySelectorAll('.customer-dashboard__star-label');
    starLabels.forEach((label, index) => {
        const star = label.querySelector('i');
        if (index < rating) {
            star.className = 'bi bi-star-fill';
            label.style.color = '#fbbf24';
        } else {
            star.className = 'bi bi-star';
            label.style.color = '#d1d5db';
        }
    });
}

function selectStars(rating) {
    const ratingInput = document.querySelector(`input[name="rating"][value="${rating}"]`);
    if (ratingInput) {
        ratingInput.checked = true;
    }
    highlightStars(rating);
}

// Close modals on outside click
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.customer-dashboard__modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Auto-refresh for real-time updates (every 30 seconds)
setInterval(function() {
    // Check for new messages or updates
    const unreadBadges = document.querySelectorAll('.customer-dashboard__unread-badge');
    if (unreadBadges.length > 0) {
        // In a real implementation, this would make an AJAX call to check for updates
        console.log('Checking for updates...');
    }
}, 30000);
</script>

<style>
/* Customer Dashboard Styles */
.customer-dashboard {
    min-height: 100vh;
    background: #f8fafc;
    padding: 2rem 1rem;
}

.customer-dashboard__container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Login Section */
.customer-dashboard__login {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 80vh;
}

.customer-dashboard__login-container {
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
    padding: 3rem 2.5rem;
    max-width: 500px;
    width: 100%;
}

.customer-dashboard__login-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.customer-dashboard__logo {
    height: 3.5rem;
    width: auto;
    margin-bottom: 1.5rem;
}

.customer-dashboard__login-title {
    color: #00b050;
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
}

.customer-dashboard__login-subtitle {
    color: #6b7280;
    font-size: 1.1rem;
    margin: 0;
}

.customer-dashboard__login-form {
    margin-bottom: 2rem;
}

.customer-dashboard__form-group {
    margin-bottom: 1.5rem;
}

.customer-dashboard__label {
    display: block;
    font-weight: 700;
    color: #00b050;
    margin-bottom: 0.5rem;
}

.customer-dashboard__input {
    width: 100%;
    padding: 1rem 1.2rem;
    border: 2px solid #e5e7eb;
    border-radius: 1.2rem;
    font-size: 1.1rem;
    transition: border-color 0.2s;
}

.customer-dashboard__input:focus {
    border-color: #00b050;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
}

.customer-dashboard__help-text {
    color: #666;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.customer-dashboard__submit {
    width: 100%;
    background: #00b050;
    color: #fff;
    font-weight: 700;
    border: none;
    border-radius: 1.2rem;
    padding: 1rem 0;
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.2s;
}

.customer-dashboard__submit:hover {
    background: #00913d;
}

.customer-dashboard__login-help {
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 1rem;
    text-align: center;
}

.customer-dashboard__login-help h3 {
    color: #00b050;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.customer-dashboard__login-help p {
    margin: 0.5rem 0;
    color: #666;
    line-height: 1.5;
}

.customer-dashboard__login-help a {
    color: #00b050;
    text-decoration: underline;
}

/* Main Dashboard */
.customer-dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2.5rem;
    padding: 2rem;
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
}

.customer-dashboard__welcome h1 {
    color: #00b050;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
}

.customer-dashboard__welcome p {
    color: #6b7280;
    font-size: 1.2rem;
    margin: 0;
}

.customer-dashboard__header-actions {
    display: flex;
    gap: 1rem;
}

/* Buttons */
.customer-dashboard__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.customer-dashboard__btn--primary {
    background: #00b050;
    color: #fff;
}

.customer-dashboard__btn--primary:hover {
    background: #00913d;
    color: #fff;
}

.customer-dashboard__btn--secondary {
    background: #f1f5f9;
    color: #475569;
}

.customer-dashboard__btn--secondary:hover {
    background: #e2e8f0;
    color: #334155;
}

.customer-dashboard__btn--outline {
    background: transparent;
    color: #00b050;
    border: 2px solid #00b050;
}

.customer-dashboard__btn--outline:hover {
    background: #00b050;
    color: #fff;
}

.customer-dashboard__btn--small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

/* Metrics */
.customer-dashboard__metrics {
    margin-bottom: 3rem;
}

.customer-dashboard__metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.customer-dashboard__metric {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #fff;
    padding: 1.5rem;
    border-radius: 1.2rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
}

.customer-dashboard__metric-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
}

.customer-dashboard__metric-icon--projects {
    background: #3b82f6;
}

.customer-dashboard__metric-icon--active {
    background: #10b981;
}

.customer-dashboard__metric-icon--bids {
    background: #f59e0b;
}

.customer-dashboard__metric-icon--spending {
    background: #8b5cf6;
}

.customer-dashboard__metric-trend {
    margin-top: 0.25rem;
}

.customer-dashboard__metric-trend small {
    color: #6b7280;
    font-size: 0.8rem;
}

.customer-dashboard__metric-value {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    line-height: 1;
}

.customer-dashboard__metric-label {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Status Overview */
.customer-dashboard__status-overview {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
}

.customer-dashboard__status-title {
    color: #1f2937;
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
}

.customer-dashboard__status-bar {
    height: 1rem;
    background: #f1f5f9;
    border-radius: 0.5rem;
    overflow: hidden;
    display: flex;
    margin-bottom: 1rem;
}

.customer-dashboard__status-segment {
    height: 100%;
    transition: width 0.3s ease;
}

.customer-dashboard__status-segment--open {
    background: #3b82f6;
}

.customer-dashboard__status-segment--progress {
    background: #f59e0b;
}

.customer-dashboard__status-segment--completed {
    background: #10b981;
}

.customer-dashboard__status-legend {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.customer-dashboard__legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.customer-dashboard__legend-color {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 0.25rem;
}

.customer-dashboard__legend-color--open {
    background: #3b82f6;
}

.customer-dashboard__legend-color--progress {
    background: #f59e0b;
}

.customer-dashboard__legend-color--completed {
    background: #10b981;
}

/* Projects */
.customer-dashboard__projects-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    gap: 2rem;
}

.customer-dashboard__section-title {
    color: #1f2937;
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
}

/* Filters and Search */
.customer-dashboard__filters {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.customer-dashboard__search-box {
    position: relative;
    min-width: 250px;
}

.customer-dashboard__search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid #e5e7eb;
    border-radius: 1rem;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.customer-dashboard__search-input:focus {
    border-color: #00b050;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
}

.customer-dashboard__search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    pointer-events: none;
}

.customer-dashboard__filter-group {
    display: flex;
    gap: 0.5rem;
}

.customer-dashboard__filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 1rem;
    font-size: 0.9rem;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s;
}

.customer-dashboard__filter-select:focus {
    border-color: #00b050;
    outline: none;
}

.customer-dashboard__projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}

.customer-dashboard__project-card {
    background: #fff;
    border-radius: 1.2rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
    border: 1px solid #f1f5f9;
}

.customer-dashboard__project-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.customer-dashboard__project-status-group {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.customer-dashboard__project-priority {
    padding: 0.25rem 0.5rem;
    background: #fef3c7;
    color: #92400e;
    border-radius: 0.5rem;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.customer-dashboard__project-title {
    color: #1f2937;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    flex: 1;
}

.customer-dashboard__project-status {
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.customer-dashboard__project-status--open {
    background: #dbeafe;
    color: #1d4ed8;
}

.customer-dashboard__project-status--assigned {
    background: #fef3c7;
    color: #92400e;
}

.customer-dashboard__project-status--closed {
    background: #dcfce7;
    color: #166534;
}

.customer-dashboard__project-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.customer-dashboard__project-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.customer-dashboard__project-detail i {
    color: #00b050;
    width: 1.2rem;
}

.customer-dashboard__project-description {
    color: #4b5563;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.customer-dashboard__project-bids {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 0.8rem;
}

.customer-dashboard__bids-title {
    color: #374151;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.customer-dashboard__bid-range {
    color: #4b5563;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.customer-dashboard__no-bids {
    color: #6b7280;
    font-style: italic;
    text-align: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 0.8rem;
    margin-bottom: 1rem;
}

.customer-dashboard__project-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Quick Actions */
.customer-dashboard__quick-actions {
    margin-top: 3rem;
}

.customer-dashboard__actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.customer-dashboard__action-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 1.2rem;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
    transition: transform 0.2s ease;
    text-align: center;
}

.customer-dashboard__action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,176,80,0.12);
    color: inherit;
    text-decoration: none;
}

.customer-dashboard__action-card i {
    font-size: 2rem;
    color: #00b050;
    margin-bottom: 1rem;
}

.customer-dashboard__action-card h3 {
    color: #1f2937;
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.customer-dashboard__action-card p {
    color: #6b7280;
    margin: 0;
}

/* Empty State */
.customer-dashboard__empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
}

.customer-dashboard__empty-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.customer-dashboard__empty-title {
    color: #374151;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.customer-dashboard__empty-text {
    color: #6b7280;
    margin: 0 0 2rem 0;
}

/* Alerts */
.customer-dashboard__alert {
    padding: 1rem 1.5rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.customer-dashboard__alert--success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.customer-dashboard__alert--error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.customer-dashboard__error-list {
    margin: 0;
    padding-left: 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .customer-dashboard {
        padding: 1rem;
    }
    
    .customer-dashboard__header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .customer-dashboard__header-actions {
        justify-content: center;
    }
    
    .customer-dashboard__projects-grid {
        grid-template-columns: 1fr;
    }
    
    .customer-dashboard__metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .customer-dashboard__actions-grid {
        grid-template-columns: 1fr;
    }
    
    .customer-dashboard__project-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .customer-dashboard__login-container {
        padding: 2rem 1.5rem;
    }
    
    .customer-dashboard__metrics-grid {
        grid-template-columns: 1fr;
    }
    
    .customer-dashboard__metric {
        flex-direction: column;
        text-align: center;
    }
}

/* Phase 2: Enhanced Features Styles */

/* Document Upload Styles */
.customer-dashboard__project-documents {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8fffe;
    border-radius: 0.5rem;
    border: 1px solid #e5f5f0;
}

.customer-dashboard__upload-form {
    margin-bottom: 1rem;
}

.customer-dashboard__upload-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.customer-dashboard__document-type {
    flex: 1;
    min-width: 120px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
}

.customer-dashboard__file-input {
    flex: 2;
    min-width: 150px;
}

.customer-dashboard__upload-btn {
    background: #00b050;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
    white-space: nowrap;
}

.customer-dashboard__upload-btn:hover {
    background: #00913d;
}

.customer-dashboard__document-list {
    max-height: 200px;
    overflow-y: auto;
}

.customer-dashboard__document-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 0.3rem;
    margin-bottom: 0.3rem;
    border: 1px solid #e5e5e5;
}

.customer-dashboard__document-item i {
    color: #00b050;
}

.document-name {
    flex: 1;
    font-weight: 500;
}

.document-type {
    background: #e8f5e8;
    color: #00b050;
    padding: 0.2rem 0.5rem;
    border-radius: 0.3rem;
    font-size: 0.85rem;
    text-transform: capitalize;
}

.document-date {
    color: #666;
    font-size: 0.85rem;
}

/* Project Notes Styles */
.customer-dashboard__project-notes {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #fdf9f0;
    border-radius: 0.5rem;
    border: 1px solid #f0e5a5;
}

.customer-dashboard__notes-textarea {
    width: 100%;
    min-height: 80px;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    font-family: inherit;
    resize: vertical;
}

.customer-dashboard__notes-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
}

.customer-dashboard__save-notes-btn {
    background: #ffa500;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
}

.customer-dashboard__save-notes-btn:hover {
    background: #e6940a;
}

.customer-dashboard__notes-saved {
    color: #666;
    font-size: 0.85rem;
}

/* Appointment Scheduling Styles */
.customer-dashboard__appointment-section {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f0f8ff;
    border-radius: 0.5rem;
    border: 1px solid #b5d4f0;
}

.customer-dashboard__schedule-btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 500;
}

.customer-dashboard__schedule-btn:hover {
    background: #0056b3;
}

.customer-dashboard__appointments-list {
    margin-top: 1rem;
}

.customer-dashboard__appointment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    border: 1px solid #e5e5e5;
}

.appointment-info strong {
    display: block;
    color: #333;
}

.appointment-info span {
    color: #666;
    font-size: 0.9rem;
}

.appointment-status {
    padding: 0.3rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.85rem;
    font-weight: 500;
}

.appointment-status.status-scheduled {
    background: #e8f5e8;
    color: #00b050;
}

.appointment-status.status-confirmed {
    background: #e3f2fd;
    color: #1976d2;
}

.appointment-status.status-completed {
    background: #f3e5f5;
    color: #7b1fa2;
}

/* Project Gallery Styles */
.customer-dashboard__project-gallery {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 0.5rem;
    border: 1px solid #ddd;
}

.customer-dashboard__gallery-btn {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    cursor: pointer;
    margin-bottom: 1rem;
}

.customer-dashboard__gallery-btn:hover {
    background: #545b62;
}

.customer-dashboard__gallery-preview .gallery-placeholder {
    text-align: center;
    padding: 2rem;
    color: #666;
    border: 2px dashed #ccc;
    border-radius: 0.5rem;
}

.customer-dashboard__gallery-preview .gallery-placeholder i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

/* Modal Enhancements */
.customer-dashboard__modal--large .customer-dashboard__modal-content {
    max-width: 800px;
    width: 90%;
}

.customer-dashboard__gallery-content {
    padding: 1rem 0;
}

.customer-dashboard__gallery-upload-zone {
    border: 2px dashed #00b050;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.customer-dashboard__gallery-upload-zone:hover {
    background: #f8fff8;
    border-color: #00913d;
}

.customer-dashboard__gallery-upload-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #00b050;
    font-size: 1.1rem;
}

.customer-dashboard__gallery-upload-btn i {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.customer-dashboard__gallery-upload-btn small {
    display: block;
    color: #666;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.customer-dashboard__gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.gallery-image-preview {
    position: relative;
    aspect-ratio: 1;
    border-radius: 0.5rem;
    overflow: hidden;
    border: 1px solid #ddd;
}

.gallery-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.5rem;
}

.gallery-image-preview:hover .gallery-image-overlay {
    opacity: 1;
}

.gallery-remove-btn {
    background: #dc3545;
    color: white;
    border: none;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    cursor: pointer;
    align-self: flex-end;
}

.gallery-image-name {
    font-size: 0.8rem;
    text-align: center;
    word-break: break-word;
}

/* Notification System */
.customer-dashboard__notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    padding: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    max-width: 350px;
    animation: slideInRight 0.3s ease;
}

.customer-dashboard__notification--success {
    border-left: 4px solid #28a745;
}

.customer-dashboard__notification--error {
    border-left: 4px solid #dc3545;
}

.customer-dashboard__notification--info {
    border-left: 4px solid #17a2b8;
}

.customer-dashboard__notification i {
    font-size: 1.2rem;
}

.customer-dashboard__notification--success i {
    color: #28a745;
}

.customer-dashboard__notification--error i {
    color: #dc3545;
}

.customer-dashboard__notification--info i {
    color: #17a2b8;
}

.customer-dashboard__notification button {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #666;
    margin-left: auto;
}

.customer-dashboard__action-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 0.2rem 0.4rem;
    font-size: 0.75rem;
    font-weight: bold;
    min-width: 1.2rem;
    text-align: center;
    z-index: 10;
}

.customer-dashboard__action-card {
    position: relative;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive Enhancements for Phase 2 */
@media (max-width: 768px) {
    .customer-dashboard__upload-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .customer-dashboard__document-type,
    .customer-dashboard__file-input {
        min-width: auto;
    }
    
    .customer-dashboard__notes-actions {
        flex-direction: column;
        gap: 0.5rem;
        align-items: stretch;
    }
    
    .customer-dashboard__appointment-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .customer-dashboard__gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    
    .customer-dashboard__notification {
        left: 10px;
        right: 10px;
        max-width: none;
    }
}
</style>

<!-- Phase 2: Appointment Scheduling Modal -->
<div id="appointmentModal" class="customer-dashboard__modal">
    <div class="customer-dashboard__modal-content">
        <div class="customer-dashboard__modal-header">
            <h3><i class="bi bi-calendar-check"></i> Schedule Appointment</h3>
            <button class="customer-dashboard__modal-close" onclick="closeModal('appointmentModal')">&times;</button>
        </div>
        <form method="post" class="customer-dashboard__modal-form">
            <input type="hidden" name="action" value="schedule_appointment">
            <input type="hidden" name="lead_id" id="appointmentLeadId">
            <input type="hidden" name="painter_id" id="appointmentPainterId">
            
            <div class="customer-dashboard__form-group">
                <label for="appointmentType">Appointment Type:</label>
                <select name="appointment_type" id="appointmentType" required>
                    <option value="">Select type</option>
                    <option value="initial_consultation">Initial Consultation</option>
                    <option value="site_measurement">Site Measurement</option>
                    <option value="color_consultation">Color Consultation</option>
                    <option value="progress_check">Progress Check</option>
                    <option value="final_inspection">Final Inspection</option>
                </select>
            </div>
            
            <div class="customer-dashboard__form-group">
                <label for="appointmentDate">Date:</label>
                <input type="date" name="appointment_date" id="appointmentDate" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="customer-dashboard__form-group">
                <label for="appointmentTime">Time:</label>
                <select name="appointment_time" id="appointmentTime" required>
                    <option value="">Select time</option>
                    <option value="09:00">9:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">1:00 PM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="16:00">4:00 PM</option>
                    <option value="17:00">5:00 PM</option>
                </select>
            </div>
            
            <div class="customer-dashboard__modal-actions">
                <button type="button" onclick="closeModal('appointmentModal')" class="customer-dashboard__btn customer-dashboard__btn--outline">Cancel</button>
                <button type="submit" class="customer-dashboard__btn customer-dashboard__btn--primary">Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Phase 2: Quote Modification Modal -->
<div id="quoteModificationModal" class="customer-dashboard__modal">
    <div class="customer-dashboard__modal-content">
        <div class="customer-dashboard__modal-header">
            <h3><i class="bi bi-pencil-square"></i> Request Quote Modification</h3>
            <button class="customer-dashboard__modal-close" onclick="closeModal('quoteModificationModal')">&times;</button>
        </div>
        <form method="post" class="customer-dashboard__modal-form">
            <input type="hidden" name="action" value="request_quote_modification">
            <input type="hidden" name="lead_id" id="modificationLeadId">
            <input type="hidden" name="bid_id" id="modificationBidId">
            
            <div class="customer-dashboard__form-group">
                <label for="modificationType">Modification Type:</label>
                <select name="modification_type" id="modificationType" required>
                    <option value="">Select modification type</option>
                    <option value="scope_change">Scope Change</option>
                    <option value="material_upgrade">Material Upgrade</option>
                    <option value="timeline_adjustment">Timeline Adjustment</option>
                    <option value="pricing_query">Pricing Query</option>
                    <option value="specification_change">Specification Change</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="customer-dashboard__form-group">
                <label for="modificationDetails">Details:</label>
                <textarea name="modification_details" id="modificationDetails" rows="4" 
                          placeholder="Please describe your requested changes or questions in detail..."></textarea>
            </div>
            
            <div class="customer-dashboard__modal-actions">
                <button type="button" onclick="closeModal('quoteModificationModal')" class="customer-dashboard__btn customer-dashboard__btn--outline">Cancel</button>
                <button type="submit" class="customer-dashboard__btn customer-dashboard__btn--primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Phase 2: Project Gallery Modal -->
<div id="galleryModal" class="customer-dashboard__modal customer-dashboard__modal--large">
    <div class="customer-dashboard__modal-content">
        <div class="customer-dashboard__modal-header">
            <h3><i class="bi bi-images"></i> Project Gallery</h3>
            <button class="customer-dashboard__modal-close" onclick="closeModal('galleryModal')">&times;</button>
        </div>
        <div class="customer-dashboard__gallery-content">
            <div class="customer-dashboard__gallery-upload-zone">
                <input type="file" id="galleryUpload" multiple accept="image/*" style="display: none;">
                <button onclick="document.getElementById('galleryUpload').click()" class="customer-dashboard__gallery-upload-btn">
                    <i class="bi bi-cloud-upload"></i>
                    <span>Upload Photos</span>
                    <small>Drag & drop or click to select</small>
                </button>
            </div>
            <div class="customer-dashboard__gallery-grid">
                <!-- Gallery images would be displayed here -->
                <div class="gallery-placeholder">
                    <i class="bi bi-image"></i>
                    <p>No photos uploaded yet</p>
                </div>
            </div>
        </div>
    </div>
</div>

</main>
</body>
</html>
</style> 