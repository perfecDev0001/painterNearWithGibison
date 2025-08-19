<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonDataAccess.php';
require_once 'core/EmailNotificationService.php';

$dataAccess = new GibsonDataAccess();
$emailService = new Core\EmailNotificationService();

// Helper function to get project status (considering customer updates)
function getProjectStatus($leadId, $originalStatus) {
    if (isset($_SESSION['project_status_updates'][$leadId])) {
        return $_SESSION['project_status_updates'][$leadId]['status'];
    }
    return $originalStatus;
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Customer authentication system
session_start();

// Handle logout first
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: customer-dashboard.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle customer login/registration and actions
$errors = [];
$success = '';
$customer = null;
$customerLeads = [];

// Handle various POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
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
                    // Generate a unique customer ID for messaging system
                    $_SESSION['customer_id'] = 'customer_' . md5($email);
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
                    
                    // Send email notification with error handling
                    try {
                        $customerEmail = $_SESSION['customer_email'];
                        $customerName = $customer['name'] ?? 'Customer';
                        $emailService->sendAppointmentScheduledNotification(
                            $customerEmail,
                            $customerName,
                            $appointmentDate,
                            $appointmentTime,
                            $appointmentType
                        );
                    } catch (Exception $e) {
                        error_log("Failed to send appointment notification: " . $e->getMessage());
                    }
                    
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
                    // Validate file type and size
                    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    $fileType = mime_content_type($_FILES['document']['tmp_name']);
                    $fileSize = $_FILES['document']['size'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $errors[] = 'Invalid file type. Please upload PDF or image files only.';
                    } elseif ($fileSize > $maxSize) {
                        $errors[] = 'File too large. Maximum size is 5MB.';
                    } else {
                        // Store document info in session (in production, save to database/storage)
                        if (!isset($_SESSION['customer_documents'])) {
                            $_SESSION['customer_documents'] = [];
                        }
                        
                        $fileName = $_FILES['document']['name'];
                        
                        $_SESSION['customer_documents'][$leadId][] = [
                            'type' => $documentType,
                            'name' => $fileName,
                            'size' => $fileSize,
                            'uploaded_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $success = 'Document uploaded successfully!';
                    }
                }
            }
            break;
            
        case 'update_project_status':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $newStatus = $_POST['new_status'] ?? '';
                
                if ($leadId && in_array($newStatus, ['open', 'assigned', 'closed'])) {
                    // In a real application, this would update the database
                    // For now, we'll store it in session
                    if (!isset($_SESSION['project_status_updates'])) {
                        $_SESSION['project_status_updates'] = [];
                    }
                    
                    $_SESSION['project_status_updates'][$leadId] = [
                        'status' => $newStatus,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $success = 'Project status updated successfully!';
                }
            }
            break;
            
        case 'add_project_note':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $noteText = trim($_POST['note_text'] ?? '');
                
                if ($leadId && $noteText) {
                    if (!isset($_SESSION['project_notes'])) {
                        $_SESSION['project_notes'] = [];
                    }
                    
                    if (!isset($_SESSION['project_notes'][$leadId])) {
                        $_SESSION['project_notes'][$leadId] = [];
                    }
                    
                    $_SESSION['project_notes'][$leadId][] = [
                        'text' => $noteText,
                        'created_at' => date('Y-m-d H:i:s'),
                        'author' => 'Customer'
                    ];
                    
                    $success = 'Note added successfully!';
                }
            }
            break;
            
        case 'upload_project_photo':
            if (isset($_SESSION['customer_authenticated'])) {
                $leadId = $_POST['lead_id'] ?? '';
                $photoDescription = trim($_POST['photo_description'] ?? '');
                
                if ($leadId && isset($_FILES['project_photo']) && $_FILES['project_photo']['error'] === UPLOAD_ERR_OK) {
                    // Validate image file
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $maxSize = 10 * 1024 * 1024; // 10MB
                    
                    $fileType = mime_content_type($_FILES['project_photo']['tmp_name']);
                    $fileSize = $_FILES['project_photo']['size'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $errors[] = 'Invalid file type. Please upload image files only.';
                    } elseif ($fileSize > $maxSize) {
                        $errors[] = 'File too large. Maximum size is 10MB.';
                    } else {
                        // Store photo info in session
                        if (!isset($_SESSION['project_photos'])) {
                            $_SESSION['project_photos'] = [];
                        }
                        
                        if (!isset($_SESSION['project_photos'][$leadId])) {
                            $_SESSION['project_photos'][$leadId] = [];
                        }
                        
                        $fileName = $_FILES['project_photo']['name'];
                        
                        $_SESSION['project_photos'][$leadId][] = [
                            'name' => $fileName,
                            'description' => $photoDescription,
                            'size' => $fileSize,
                            'uploaded_at' => date('Y-m-d H:i:s'),
                            'uploaded_by' => 'Customer'
                        ];
                        
                        $success = 'Photo uploaded successfully!';
                    }
                }
            }
            break;
        }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
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
                                        
                                        <!-- Appointment Scheduling Button -->
                                        <button onclick="openAppointmentModal(<?php echo $lead['id']; ?>, <?php echo $painterId; ?>)" class="customer-dashboard__btn customer-dashboard__btn--secondary customer-dashboard__btn--small">
                                            <i class="bi bi-calendar-check"></i> Schedule Meeting
                                        </button>
                                        
                                        <!-- Project Gallery Button -->
                                        <button onclick="openGalleryModal(<?php echo $lead['id']; ?>)" class="customer-dashboard__btn customer-dashboard__btn--outline customer-dashboard__btn--small">
                                            <i class="bi bi-images"></i> Project Photos
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($lead['status'] === 'open' && $bidCount > 0): ?>
                                        <?php 
                                        // Get the first bid for quote modification
                                        $firstBid = !empty($leadBids) ? $leadBids[0] : null;
                                        if ($firstBid): ?>
                                            <button onclick="openQuoteModificationModal(<?php echo $lead['id']; ?>, <?php echo $firstBid['id']; ?>)" class="customer-dashboard__btn customer-dashboard__btn--secondary customer-dashboard__btn--small">
                                                <i class="bi bi-pencil-square"></i> Request Changes
                                            </button>
                                        <?php endif; ?>
                                        
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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

<?php include 'templates/footer.php'; ?>

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
    setTimeout(() => focusModal('reviewModal'), 100);
}

// Unified Modal Management System
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn(`Modal with ID ${modalId} not found`);
        return false;
    }
    
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    document.body.classList.remove('modal-open');
    
    // Return focus to the trigger element if available
    const triggerElement = modal.getAttribute('data-trigger');
    if (triggerElement) {
        const trigger = document.getElementById(triggerElement);
        if (trigger) trigger.focus();
    }
    
    // Clear any form data if needed
    const form = modal.querySelector('form');
    if (form && form.hasAttribute('data-reset-on-close')) {
        form.reset();
    }
    
    return true;
}

// Enhanced appointment modal
function openAppointmentModal(leadId, painterId, painterName = '') {
    const modal = document.getElementById('appointmentModal');
    if (!modal) {
        console.error('Appointment modal not found');
        return false;
    }
    
    // Set form values
    const leadIdInput = document.getElementById('appointmentLeadId');
    const painterIdInput = document.getElementById('appointmentPainterId');
    
    if (leadIdInput) leadIdInput.value = leadId;
    if (painterIdInput) painterIdInput.value = painterId;
    
    // Reset form
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
        // Re-set hidden values after reset
        if (leadIdInput) leadIdInput.value = leadId;
        if (painterIdInput) painterIdInput.value = painterId;
    }
    
    // Update modal title if painter name is provided
    if (painterName) {
        const title = modal.querySelector('h3');
        if (title) {
            title.innerHTML = `<i class="bi bi-calendar-check"></i> Schedule Appointment with ${painterName}`;
        }
    }
    
    // Set minimum date to today
    const dateInput = document.getElementById('appointmentDate');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        
        // Remove existing event listeners to prevent duplicates
        const newDateInput = dateInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);
        
        // Add fresh event listener
        newDateInput.addEventListener('change', function() {
            checkTimeSlotAvailability(this.value, leadId, painterId);
        });
    }
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');
    
    // Focus management
    setTimeout(() => focusModal('appointmentModal'), 100);
    
    return true;
}

// Enhanced quote modification modal
function openQuoteModificationModal(leadId, bidId, projectTitle = '') {
    const modal = document.getElementById('quoteModificationModal');
    if (!modal) {
        console.error('Quote modification modal not found');
        return false;
    }
    
    // Set form values
    const leadIdInput = document.getElementById('modificationLeadId');
    const bidIdInput = document.getElementById('modificationBidId');
    
    if (leadIdInput) leadIdInput.value = leadId;
    if (bidIdInput) bidIdInput.value = bidId;
    
    // Reset form
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
        // Re-set hidden values after reset
        if (leadIdInput) leadIdInput.value = leadId;
        if (bidIdInput) bidIdInput.value = bidId;
    }
    
    // Update modal title if project title is provided
    if (projectTitle) {
        const title = modal.querySelector('h3');
        if (title) {
            title.innerHTML = `<i class="bi bi-pencil-square"></i> Request Quote Modification - ${projectTitle}`;
        }
    }
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');
    
    // Focus management
    setTimeout(() => focusModal('quoteModificationModal'), 100);
    
    return true;
}

// Enhanced gallery modal
function openGalleryModal(leadId, projectTitle = '') {
    const modal = document.getElementById('galleryModal');
    if (!modal) {
        console.error('Gallery modal not found');
        return false;
    }
    
    // Update modal title if project title is provided
    if (projectTitle) {
        const title = modal.querySelector('h3');
        if (title) {
            title.innerHTML = `<i class="bi bi-images"></i> Project Gallery - ${projectTitle}`;
        }
    }
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');
    
    // Initialize gallery functionality
    initializeGallery(leadId);
    loadExistingPhotos(leadId);
    
    // Focus management
    setTimeout(() => focusModal('galleryModal'), 100);
    
    return true;
}

// Initialize gallery functionality
function initializeGallery(leadId) {
    const galleryUpload = document.getElementById('galleryUpload');
    const uploadZone = document.querySelector('.customer-dashboard__gallery-upload-zone');
    
    if (!galleryUpload || !uploadZone) {
        console.warn('Gallery elements not found');
        return;
    }
    
    // Remove existing event listeners to prevent duplicates
    const newUpload = galleryUpload.cloneNode(true);
    galleryUpload.parentNode.replaceChild(newUpload, galleryUpload);
    
    // File upload handler
    newUpload.addEventListener('change', function(e) {
        handleGalleryUpload(e.target.files, leadId);
    });
    
    // Drag and drop functionality
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        handleGalleryUpload(e.dataTransfer.files, leadId);
    });
}

// Handle gallery file upload
function handleGalleryUpload(files, leadId) {
    if (!files || files.length === 0) return;
    
    const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
    const placeholder = galleryGrid.querySelector('.gallery-placeholder');
    
    Array.from(files).forEach(file => {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            showNotification('Please upload only image files', 'error');
            return;
        }
        
        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            showNotification('File size must be less than 5MB', 'error');
            return;
        }
        
        // Create file reader
        const reader = new FileReader();
        reader.onload = function(e) {
            const photo = {
                name: file.name,
                url: e.target.result,
                size: file.size,
                type: file.type
            };
            
            // Hide placeholder if this is the first image
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            displayPhotoPreview(photo, galleryGrid);
            showNotification('Photo uploaded successfully', 'success');
        };
        
        reader.readAsDataURL(file);
    });
}

// Load existing photos for the project
function loadExistingPhotos(leadId) {
    const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
    if (!galleryGrid) return;
    
    const placeholder = galleryGrid.querySelector('.gallery-placeholder');
    
    // In a real application, this would fetch photos from the server
    // For now, we'll check session storage or show placeholder
    const existingPhotos = getProjectPhotos(leadId);
    
    if (existingPhotos && existingPhotos.length > 0) {
        if (placeholder) placeholder.style.display = 'none';
        existingPhotos.forEach(photo => {
            displayPhotoPreview(photo, galleryGrid);
        });
    } else {
        if (placeholder) placeholder.style.display = 'block';
    }
}

// Get project photos from session (simulated)
function getProjectPhotos(leadId) {
    // This would normally fetch from server/database
    // For demo purposes, return empty array
    return [];
}

// Display photo preview in gallery
function displayPhotoPreview(photo, container) {
    if (!container) return;
    
    const imagePreview = document.createElement('div');
    imagePreview.className = 'gallery-image-preview';
    imagePreview.innerHTML = `
        <img src="${photo.url || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2VlZSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+SW1hZ2U8L3RleHQ+PC9zdmc+'}" alt="Project photo" loading="lazy">
        <div class="gallery-image-overlay">
            <button onclick="removeGalleryImage(this)" class="gallery-remove-btn" title="Remove image">×</button>
            <span class="gallery-image-name">${photo.name}</span>
        </div>
    `;
    container.appendChild(imagePreview);
}

// Remove gallery image
function removeGalleryImage(button) {
    const imagePreview = button.closest('.gallery-image-preview');
    if (imagePreview) {
        imagePreview.remove();
        
        // Show placeholder if no images left
        const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
        const remainingImages = galleryGrid.querySelectorAll('.gallery-image-preview');
        const placeholder = galleryGrid.querySelector('.gallery-placeholder');
        
        if (remainingImages.length === 0 && placeholder) {
            placeholder.style.display = 'block';
        }
        
        showNotification('Photo removed', 'info');
    }
}

// Time slot availability checker
function checkTimeSlotAvailability(date, leadId, painterId) {
    const timeSelect = document.getElementById('appointmentTime');
    if (!timeSelect || !date) return;
    
    // Clear existing options except the first one
    const firstOption = timeSelect.querySelector('option[value=""]');
    timeSelect.innerHTML = '';
    if (firstOption) {
        timeSelect.appendChild(firstOption);
    }
    
    // Simulate checking availability (in real app, this would be an AJAX call)
    const availableSlots = [
        { value: '09:00', text: '9:00 AM' },
        { value: '10:00', text: '10:00 AM' },
        { value: '11:00', text: '11:00 AM' },
        { value: '14:00', text: '2:00 PM' },
        { value: '15:00', text: '3:00 PM' },
        { value: '16:00', text: '4:00 PM' }
    ];
    
    // Add available slots
    availableSlots.forEach(slot => {
        const option = document.createElement('option');
        option.value = slot.value;
        option.textContent = slot.text;
        timeSelect.appendChild(option);
    });
    
    // Show loading state briefly
    timeSelect.disabled = true;
    setTimeout(() => {
        timeSelect.disabled = false;
    }, 500);
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.customer-dashboard__notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `customer-dashboard__notification customer-dashboard__notification--${type}`;
    
    const icon = type === 'success' ? 'bi-check-circle' : 
                 type === 'error' ? 'bi-exclamation-triangle' : 
                 'bi-info-circle';
    
    notification.innerHTML = `
        <i class="bi ${icon}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" title="Close">×</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
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

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const openModal = document.querySelector('.customer-dashboard__modal[style*="flex"]');
        if (openModal) {
            closeModal(openModal.id);
        }
    }
});

// Focus management for modals
function focusModal(modalId) {
    const modal = document.getElementById(modalId);
    const firstInput = modal.querySelector('input, select, textarea, button');
    if (firstInput) {
        firstInput.focus();
    }
}

// Enhanced Modal Management System
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.modalStack = [];
        this.focusedElementBeforeModal = null;
        this.init();
    }

    init() {
        // Initialize modal event listeners
        this.setupGlobalEventListeners();
        this.setupModalEventListeners();
    }

    setupGlobalEventListeners() {
        // Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.closeModal(this.activeModal);
            }
        });

        // Click outside handler
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('customer-dashboard__modal')) {
                this.closeModal(e.target.id);
            }
        });
    }

    setupModalEventListeners() {
        // Setup form submission handlers for all modal forms
        document.querySelectorAll('.customer-dashboard__modal-form').forEach(form => {
            form.addEventListener('submit', (e) => {
                this.handleFormSubmission(e, form);
            });
        });
    }

    openModal(modalId, data = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error(`Modal with ID ${modalId} not found`);
            return false;
        }

        // Store currently focused element
        this.focusedElementBeforeModal = document.activeElement;

        // Add to modal stack
        this.modalStack.push(modalId);
        this.activeModal = modalId;

        // Populate modal with data if provided
        this.populateModalData(modalId, data);

        // Show modal with animation
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Focus management
        setTimeout(() => {
            this.focusFirstElement(modal);
        }, 100);

        // Add modal-open class to body for additional styling
        document.body.classList.add('modal-open');

        // Trigger custom event
        this.triggerModalEvent('modalOpened', modalId, data);

        return true;
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return false;

        // Validate before closing if needed
        if (!this.validateModalClose(modalId)) {
            return false;
        }

        // Hide modal
        modal.style.display = 'none';

        // Remove from stack
        this.modalStack = this.modalStack.filter(id => id !== modalId);
        this.activeModal = this.modalStack.length > 0 ? this.modalStack[this.modalStack.length - 1] : null;

        // Restore body overflow if no modals are open
        if (this.modalStack.length === 0) {
            document.body.style.overflow = 'auto';
            document.body.classList.remove('modal-open');
        }

        // Restore focus
        if (this.focusedElementBeforeModal && this.modalStack.length === 0) {
            this.focusedElementBeforeModal.focus();
            this.focusedElementBeforeModal = null;
        }

        // Reset modal form if exists
        this.resetModalForm(modalId);

        // Trigger custom event
        this.triggerModalEvent('modalClosed', modalId);

        return true;
    }

    populateModalData(modalId, data) {
        const modal = document.getElementById(modalId);
        
        // Populate hidden fields
        Object.keys(data).forEach(key => {
            const input = modal.querySelector(`input[name="${key}"], #${key}`);
            if (input) {
                input.value = data[key];
            }
        });

        // Special handling for different modal types
        switch(modalId) {
            case 'reviewModal':
                this.populateReviewModal(data);
                break;
            case 'appointmentModal':
                this.populateAppointmentModal(data);
                break;
            case 'quoteModificationModal':
                this.populateQuoteModal(data);
                break;
            case 'galleryModal':
                this.populateGalleryModal(data);
                break;
        }
    }

    populateReviewModal(data) {
        // Reset rating
        document.querySelectorAll('input[name="rating"]').forEach(input => {
            input.checked = false;
        });
        
        // Clear review text
        const reviewText = document.getElementById('reviewText');
        if (reviewText) reviewText.value = '';

        // Update modal title if painter name is provided
        if (data.painterName) {
            const title = document.querySelector('#reviewModal h3');
            if (title) {
                title.innerHTML = `<i class="bi bi-star"></i> Review ${data.painterName}`;
            }
        }
    }

    populateAppointmentModal(data) {
        // Reset form fields
        ['appointmentType', 'appointmentDate', 'appointmentTime'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });

        // Set minimum date to today
        const dateField = document.getElementById('appointmentDate');
        if (dateField) {
            const today = new Date().toISOString().split('T')[0];
            dateField.min = today;
        }

        // Update modal title if painter name is provided
        if (data.painterName) {
            const title = document.querySelector('#appointmentModal h3');
            if (title) {
                title.innerHTML = `<i class="bi bi-calendar-check"></i> Schedule with ${data.painterName}`;
            }
        }
    }

    populateQuoteModal(data) {
        // Reset form fields
        ['modificationType', 'modificationDetails'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });

        // Update modal title with project info if provided
        if (data.projectTitle) {
            const title = document.querySelector('#quoteModificationModal h3');
            if (title) {
                title.innerHTML = `<i class="bi bi-pencil-square"></i> Modify Quote - ${data.projectTitle}`;
            }
        }
    }

    populateGalleryModal(data) {
        // Load existing photos for the project
        if (data.leadId) {
            this.loadProjectPhotos(data.leadId);
        }

        // Update modal title
        if (data.projectTitle) {
            const title = document.querySelector('#galleryModal h3');
            if (title) {
                title.innerHTML = `<i class="bi bi-images"></i> ${data.projectTitle} Gallery`;
            }
        }
    }

    loadProjectPhotos(leadId) {
        // This would typically fetch from server
        // For now, simulate with session storage or show placeholder
        const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
        const placeholder = galleryGrid.querySelector('.gallery-placeholder');
        
        // Show loading state
        if (placeholder) {
            placeholder.innerHTML = `
                <i class="bi bi-hourglass-split"></i>
                <p>Loading photos...</p>
            `;
        }

        // Simulate API call
        setTimeout(() => {
            const existingPhotos = this.getProjectPhotos(leadId);
            
            if (existingPhotos && existingPhotos.length > 0) {
                placeholder.style.display = 'none';
                existingPhotos.forEach(photo => {
                    this.displayPhotoPreview(photo, galleryGrid);
                });
            } else {
                placeholder.innerHTML = `
                    <i class="bi bi-image"></i>
                    <p>No photos uploaded yet</p>
                    <small>Upload photos to track project progress</small>
                `;
            }
        }, 500);
    }

    getProjectPhotos(leadId) {
        // Simulate getting photos from storage/server
        const storageKey = `project_photos_${leadId}`;
        const stored = sessionStorage.getItem(storageKey);
        return stored ? JSON.parse(stored) : [];
    }

    displayPhotoPreview(photo, container) {
        const imagePreview = document.createElement('div');
        imagePreview.className = 'gallery-image-preview';
        imagePreview.innerHTML = `
            <img src="${photo.url || this.getPlaceholderImage()}" alt="Project photo" loading="lazy">
            <div class="gallery-image-overlay">
                <button onclick="modalManager.removeGalleryImage(this, '${photo.id}')" class="gallery-remove-btn" title="Remove photo">×</button>
                <span class="gallery-image-name">${photo.name || 'Project Photo'}</span>
            </div>
        `;
        container.appendChild(imagePreview);
    }

    getPlaceholderImage() {
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y4ZmFmYyIvPjx0ZXh0IHg9IjEwMCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM2YjcyODAiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5Qcm9qZWN0IFBob3RvPC90ZXh0Pjwvc3ZnPg==';
    }

    removeGalleryImage(button, photoId) {
        if (confirm('Are you sure you want to remove this photo?')) {
            const imagePreview = button.closest('.gallery-image-preview');
            imagePreview.remove();
            
            // Remove from storage
            // This would typically send a delete request to server
            this.removePhotoFromStorage(photoId);
            
            // Show placeholder if no images left
            const galleryGrid = document.querySelector('.customer-dashboard__gallery-grid');
            const remainingImages = galleryGrid.querySelectorAll('.gallery-image-preview');
            if (remainingImages.length === 0) {
                const placeholder = galleryGrid.querySelector('.gallery-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'block';
                }
            }
            
            showNotification('Photo removed successfully', 'success');
        }
    }

    removePhotoFromStorage(photoId) {
        // Implementation would depend on storage method
        console.log(`Removing photo ${photoId} from storage`);
    }

    focusFirstElement(modal) {
        const focusableElements = modal.querySelectorAll(
            'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    }

    resetModalForm(modalId) {
        const modal = document.getElementById(modalId);
        const form = modal.querySelector('form');
        
        if (form) {
            form.reset();
            
            // Remove validation classes
            form.querySelectorAll('.error').forEach(element => {
                element.classList.remove('error');
            });
            
            // Reset custom elements
            form.querySelectorAll('input[name="rating"]').forEach(input => {
                input.checked = false;
            });
        }
    }

    validateModalClose(modalId) {
        const modal = document.getElementById(modalId);
        const form = modal.querySelector('form');
        
        if (form) {
            const hasChanges = this.formHasChanges(form);
            
            if (hasChanges) {
                return confirm('You have unsaved changes. Are you sure you want to close?');
            }
        }
        
        return true;
    }

    formHasChanges(form) {
        const formData = new FormData(form);
        const inputs = form.querySelectorAll('input, select, textarea');
        
        for (let input of inputs) {
            if (input.type === 'hidden' || input.name === 'csrf_token') continue;
            
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) return true;
            } else if (input.value.trim() !== '') {
                return true;
            }
        }
        
        return false;
    }

    handleFormSubmission(event, form) {
        // Add loading state
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            
            // Reset button after timeout if form doesn't redirect
            setTimeout(() => {
                if (submitButton.disabled) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            }, 5000);
        }

        // Validate form
        if (!this.validateModalForm(form)) {
            event.preventDefault();
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
            return false;
        }

        // Form is valid, let it submit
        return true;
    }

    validateModalForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.classList.remove('error');
            
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            }
        });

        // Special validation for rating
        const ratingInputs = form.querySelectorAll('input[name="rating"]');
        if (ratingInputs.length > 0) {
            const hasRating = Array.from(ratingInputs).some(input => input.checked);
            if (!hasRating) {
                showNotification('Please select a rating', 'error');
                isValid = false;
            }
        }

        if (!isValid) {
            showNotification('Please fill in all required fields', 'error');
        }

        return isValid;
    }

    triggerModalEvent(eventName, modalId, data = {}) {
        const event = new CustomEvent(eventName, {
            detail: { modalId, data }
        });
        document.dispatchEvent(event);
    }

    // Public API methods
    review(leadId, painterId, painterName = '') {
        return this.openModal('reviewModal', {
            lead_id: leadId,
            painter_id: painterId,
            painterName: painterName
        });
    }

    appointment(leadId, painterId, painterName = '') {
        return this.openModal('appointmentModal', {
            lead_id: leadId,
            painter_id: painterId,
            painterName: painterName
        });
    }

    quoteModification(leadId, bidId, projectTitle = '') {
        return this.openModal('quoteModificationModal', {
            lead_id: leadId,
            bid_id: bidId,
            projectTitle: projectTitle
        });
    }

    gallery(leadId, projectTitle = '') {
        return this.openModal('galleryModal', {
            leadId: leadId,
            projectTitle: projectTitle
        });
    }

    close(modalId = null) {
        const targetModal = modalId || this.activeModal;
        if (targetModal) {
            return this.closeModal(targetModal);
        }
        return false;
    }
}

// Initialize modal manager
const modalManager = new ModalManager();

// Add custom event listeners for modal interactions
document.addEventListener('modalOpened', function(e) {
    const { modalId, data } = e.detail;
    console.log(`Modal ${modalId} opened with data:`, data);
    
    // Track modal usage for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'modal_opened', {
            modal_type: modalId,
            project_id: data.lead_id || data.leadId
        });
    }
});

document.addEventListener('modalClosed', function(e) {
    const { modalId } = e.detail;
    console.log(`Modal ${modalId} closed`);
    
    // Track modal closure
    if (typeof gtag !== 'undefined') {
        gtag('event', 'modal_closed', {
            modal_type: modalId
        });
    }
});

// Enhanced modal keyboard navigation
document.addEventListener('keydown', function(e) {
    if (!modalManager.activeModal) return;
    
    const modal = document.getElementById(modalManager.activeModal);
    if (!modal) return;
    
    // Tab navigation within modal
    if (e.key === 'Tab') {
        const focusableElements = modal.querySelectorAll(
            'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey) {
            // Shift + Tab
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            // Tab
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }
});

// Legacy function support for backward compatibility
function openReviewModal(leadId, painterId, painterName = '') {
    return modalManager.review(leadId, painterId, painterName);
}

function openAppointmentModal(leadId, painterId, painterName = '') {
    return modalManager.appointment(leadId, painterId, painterName);
}

function openQuoteModificationModal(leadId, bidId, projectTitle = '') {
    return modalManager.quoteModification(leadId, bidId, projectTitle);
}

function openGalleryModal(leadId, projectTitle = '') {
    return modalManager.gallery(leadId, projectTitle);
}

function closeModal(modalId) {
    return modalManager.close(modalId);
}

// Enhanced close modal function (kept for compatibility)
function closeModalLegacy(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Return focus to the trigger button if possible
    const triggerButton = document.activeElement;
    if (triggerButton && triggerButton.tagName === 'BUTTON') {
        triggerButton.focus();
    }
}

// Auto-refresh for real-time updates (every 30 seconds)
setInterval(function() {
    // Check for new messages or updates
    const unreadBadges = document.querySelectorAll('.customer-dashboard__unread-badge');
    if (unreadBadges.length > 0) {
        // In a real implementation, this would make an AJAX call to check for updates
        console.log('Checking for updates...');
    }
}, 30000);

// Enhanced form validation
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Add form validation to all forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            } else {
                // Add loading state to submit button
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                    
                    // Reset button after 3 seconds if form doesn't redirect
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 3000);
                }
            }
        });
    });
});
</script>

<style>
/* Modern Customer Dashboard Styles */
* {
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.customer-dashboard {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1rem;
    position: relative;
}

.customer-dashboard::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
}

.customer-dashboard__container {
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

/* Login Section - Modern Glass Morphism */
.customer-dashboard__login {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
}

.customer-dashboard__login-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 24px;
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 8px 32px rgba(0, 0, 0, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    padding: 3rem 2.5rem;
    max-width: 480px;
    width: 100%;
    position: relative;
    overflow: hidden;
}

.customer-dashboard__login-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
    background-size: 200% 100%;
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% { background-position: 200% 0; }
    50% { background-position: -200% 0; }
}

.customer-dashboard__login-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.customer-dashboard__logo {
    height: 4rem;
    width: auto;
    margin-bottom: 1.5rem;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
}

.customer-dashboard__login-title {
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
}

.customer-dashboard__login-subtitle {
    color: #64748b;
    font-size: 1.1rem;
    margin: 0;
    font-weight: 500;
}

.customer-dashboard__login-form {
    margin-bottom: 2rem;
}

.customer-dashboard__form-group {
    margin-bottom: 1.5rem;
}

.customer-dashboard__label {
    display: block;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.customer-dashboard__input {
    width: 100%;
    padding: 1.2rem 1.5rem;
    border: 2px solid rgba(148, 163, 184, 0.2);
    border-radius: 16px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
}

.customer-dashboard__input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 
        0 0 0 4px rgba(102, 126, 234, 0.1),
        0 8px 24px rgba(102, 126, 234, 0.15);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.customer-dashboard__help-text {
    color: #64748b;
    font-size: 0.875rem;
    margin-top: 0.5rem;
    font-weight: 500;
}

.customer-dashboard__submit {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-weight: 600;
    border: none;
    border-radius: 16px;
    padding: 1.2rem 0;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.customer-dashboard__submit::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.customer-dashboard__submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
}

.customer-dashboard__submit:hover::before {
    left: 100%;
}

.customer-dashboard__submit:active {
    transform: translateY(0);
}

.customer-dashboard__login-help {
    padding: 2rem;
    background: rgba(248, 250, 252, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    text-align: center;
    border: 1px solid rgba(148, 163, 184, 0.1);
}

.customer-dashboard__login-help h3 {
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 1rem 0;
    font-size: 1.2rem;
    font-weight: 700;
}

.customer-dashboard__login-help p {
    margin: 0.75rem 0;
    color: #64748b;
    line-height: 1.6;
    font-weight: 500;
}

.customer-dashboard__login-help a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}

.customer-dashboard__login-help a:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Main Dashboard - Modern Card Design */
.customer-dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 8px 32px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.customer-dashboard__header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.customer-dashboard__welcome h1 {
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
}

.customer-dashboard__welcome p {
    color: #64748b;
    font-size: 1.2rem;
    margin: 0;
    font-weight: 500;
}

.customer-dashboard__header-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Modern Button Styles */
.customer-dashboard__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: 16px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    position: relative;
    overflow: hidden;
}

.customer-dashboard__btn--primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
}

.customer-dashboard__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
}

.customer-dashboard__btn--secondary {
    background: rgba(255, 255, 255, 0.9);
    color: #64748b;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(148, 163, 184, 0.2);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.customer-dashboard__btn--secondary:hover {
    background: rgba(255, 255, 255, 1);
    color: #475569;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.customer-dashboard__btn--outline {
    background: transparent;
    color: #667eea;
    border: 2px solid rgba(102, 126, 234, 0.3);
    backdrop-filter: blur(10px);
}

.customer-dashboard__btn--outline:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-color: transparent;
    transform: translateY(-1px);
}

.customer-dashboard__btn--small {
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
}

/* Modern Metrics Grid */
.customer-dashboard__metrics {
    margin-bottom: 3rem;
}

.customer-dashboard__metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.customer-dashboard__metric {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.08),
        0 4px 16px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.customer-dashboard__metric:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 16px 48px rgba(0, 0, 0, 0.12),
        0 8px 24px rgba(0, 0, 0, 0.08);
}

.customer-dashboard__metric::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--metric-color, #667eea), var(--metric-color-alt, #764ba2));
}

.customer-dashboard__metric-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
    position: relative;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.customer-dashboard__metric-icon--projects {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    --metric-color: #3b82f6;
    --metric-color-alt: #1d4ed8;
}

.customer-dashboard__metric-icon--active {
    background: linear-gradient(135deg, #10b981, #059669);
    --metric-color: #10b981;
    --metric-color-alt: #059669;
}

.customer-dashboard__metric-icon--bids {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    --metric-color: #f59e0b;
    --metric-color-alt: #d97706;
}

.customer-dashboard__metric-icon--spending {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    --metric-color: #8b5cf6;
    --metric-color-alt: #7c3aed;
}

.customer-dashboard__metric-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #1f2937, #374151);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.customer-dashboard__metric-content p {
    margin: 0;
    color: #64748b;
    font-weight: 600;
    font-size: 0.95rem;
}

.customer-dashboard__metric-trend {
    margin-top: 0.5rem;
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

/* Modern Status Overview */
.customer-dashboard__status-overview {
    margin-top: 2rem;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.08),
        0 4px 16px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.customer-dashboard__status-title {
    background: linear-gradient(135deg, #1f2937, #374151);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.customer-dashboard__status-bar {
    height: 12px;
    background: rgba(241, 245, 249, 0.8);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    margin-bottom: 1.5rem;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
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
    border: 2px dashed #e5e7eb;
    border-radius: 1.2rem;
    padding: 2.5rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.customer-dashboard__gallery-upload-zone:hover,
.customer-dashboard__gallery-upload-zone.dragover {
    background: rgba(0,176,80,0.05);
    border-color: #00b050;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,176,80,0.1);
}

.customer-dashboard__gallery-upload-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #00b050;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.2s ease;
    padding: 1rem;
    border-radius: 1rem;
}

.customer-dashboard__gallery-upload-btn:hover {
    color: #00913d;
    transform: translateY(-2px);
}

.customer-dashboard__gallery-upload-btn i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 0.75rem;
    color: #00b050;
}

.customer-dashboard__gallery-upload-btn span {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.customer-dashboard__gallery-upload-btn small {
    display: block;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 400;
}

.customer-dashboard__gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.gallery-placeholder {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2rem;
    color: #6b7280;
    background: #f8fafc;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
}

.gallery-placeholder i {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
    color: #d1d5db;
}

.gallery-image-preview {
    position: relative;
    aspect-ratio: 1;
    border-radius: 1rem;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
    transition: all 0.2s ease;
}

.gallery-image-preview:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,176,80,0.15);
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
    background: rgba(31, 41, 55, 0.8);
    color: white;
    opacity: 0;
    transition: opacity 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.75rem;
}

.gallery-image-preview:hover .gallery-image-overlay {
    opacity: 1;
}

.gallery-remove-btn {
    background: #ef4444;
    color: white;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    align-self: flex-end;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.gallery-remove-btn:hover {
    background: #dc2626;
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.gallery-image-name {
    font-size: 0.8rem;
    text-align: center;
    word-break: break-word;
    font-weight: 500;
}

/* Notification System - Matching Theme */
.customer-dashboard__notification {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 1.2rem;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0,176,80,0.15), 0 2px 8px rgba(0,0,0,0.08);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 1rem;
    max-width: 400px;
    animation: slideInRight 0.3s ease;
    backdrop-filter: blur(8px);
}

.customer-dashboard__notification--success {
    border-left: 4px solid #00b050;
}

.customer-dashboard__notification--success i {
    color: #00b050;
}

.customer-dashboard__notification--error {
    border-left: 4px solid #ef4444;
}

.customer-dashboard__notification--error i {
    color: #ef4444;
}

.customer-dashboard__notification--info {
    border-left: 4px solid #3b82f6;
}

.customer-dashboard__notification--info i {
    color: #3b82f6;
}

.customer-dashboard__notification i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.customer-dashboard__notification span {
    color: #1f2937;
    font-weight: 500;
    flex-grow: 1;
}

.customer-dashboard__notification button {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.customer-dashboard__notification button:hover {
    color: #374151;
    background: #f3f4f6;
}

/* Upload Progress Styles - Matching Theme */
.upload-progress-container {
    grid-column: 1 / -1;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,176,80,0.08);
}

.upload-progress {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.upload-progress-bar {
    width: 0%;
    height: 8px;
    background: linear-gradient(90deg, #00b050, #00913d);
    border-radius: 4px;
    transition: width 0.3s ease;
    position: relative;
    overflow: hidden;
}

.upload-progress-bar::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

.upload-progress-text {
    color: #1f2937;
    font-weight: 500;
    font-size: 0.9rem;
    text-align: center;
}

/* Modal body scroll enhancement */
.modal-open {
    overflow: hidden;
}

.modal-open .customer-dashboard__modal-content {
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

/* Enhanced focus styles for accessibility */
.customer-dashboard__modal-form input:focus,
.customer-dashboard__modal-form select:focus,
.customer-dashboard__modal-form textarea:focus {
    border-color: #00b050;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
    transform: translateY(-1px);
}

/* Loading state for time slots */
select:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

select option:disabled {
    color: #9ca3af;
    font-style: italic;
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

/* Form validation styles */
.customer-dashboard__input.error,
.customer-dashboard__filter-select.error,
textarea.error,
select.error {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
}

.customer-dashboard__input.error:focus,
.customer-dashboard__filter-select.error:focus,
textarea.error:focus,
select.error:focus {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.3) !important;
}

/* Modal Styles - Matching Project Theme */
.customer-dashboard__modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(31, 41, 55, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(4px);
    padding: 1rem;
}

.customer-dashboard__modal-content {
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 8px 32px rgba(0,176,80,0.15), 0 2px 8px rgba(0,0,0,0.08);
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
    border: 1px solid #f1f5f9;
}

.customer-dashboard__modal--large .customer-dashboard__modal-content {
    max-width: 700px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.customer-dashboard__modal-header {
    padding: 2rem 2rem 1rem 2rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.customer-dashboard__modal-header h3 {
    color: #00b050;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.customer-dashboard__modal-header h3 i {
    font-size: 1.2rem;
}

.customer-dashboard__modal-close {
    background: #f1f5f9;
    border: none;
    border-radius: 0.75rem;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    color: #6b7280;
    transition: all 0.2s ease;
}

.customer-dashboard__modal-close:hover {
    background: #e2e8f0;
    color: #475569;
    transform: scale(1.05);
}

.customer-dashboard__modal-form {
    padding: 1.5rem 2rem 2rem 2rem;
}

.customer-dashboard__modal-form .customer-dashboard__form-group {
    margin-bottom: 1.5rem;
}

.customer-dashboard__modal-form label {
    display: block;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.customer-dashboard__modal-form input,
.customer-dashboard__modal-form select,
.customer-dashboard__modal-form textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 1rem;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    background: #fff;
}

.customer-dashboard__modal-form input:focus,
.customer-dashboard__modal-form select:focus,
.customer-dashboard__modal-form textarea:focus {
    border-color: #00b050;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
}

.customer-dashboard__modal-form textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.customer-dashboard__modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
    margin-top: 1rem;
}

.customer-dashboard__modal-actions .customer-dashboard__btn {
    min-width: 120px;
    justify-content: center;
}

/* Star Rating Styles - Matching Theme */
.customer-dashboard__rating-input {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.customer-dashboard__rating-input input[type="radio"] {
    display: none;
}

.customer-dashboard__star-label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #e5e7eb;
    transition: all 0.2s ease;
    padding: 0.25rem;
    border-radius: 0.5rem;
}

.customer-dashboard__star-label:hover {
    color: #fbbf24;
    transform: scale(1.1);
}

.customer-dashboard__star-label i {
    transition: all 0.2s ease;
}

.customer-dashboard__rating-input input[type="radio"]:checked + .customer-dashboard__star-label,
.customer-dashboard__rating-input input[type="radio"]:checked ~ .customer-dashboard__star-label {
    color: #fbbf24;
}

.customer-dashboard__rating-input input[type="radio"]:checked + .customer-dashboard__star-label i {
    transform: scale(1.1);
}

/* Button loading state */
.customer-dashboard__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.customer-dashboard__btn:disabled i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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

/* Modern Enhancements & Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Smooth page load animations */
.customer-dashboard__header {
    animation: fadeInUp 0.6s ease-out;
}

.customer-dashboard__metric {
    animation: fadeInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.customer-dashboard__metric:nth-child(1) { animation-delay: 0.1s; }
.customer-dashboard__metric:nth-child(2) { animation-delay: 0.2s; }
.customer-dashboard__metric:nth-child(3) { animation-delay: 0.3s; }
.customer-dashboard__metric:nth-child(4) { animation-delay: 0.4s; }

.customer-dashboard__project-card {
    animation: slideInRight 0.5s ease-out;
    animation-fill-mode: both;
}

/* Loading states */
.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    animation: shimmer 1.5s infinite;
}

/* Improved focus states for accessibility */
.customer-dashboard__btn:focus-visible,
.customer-dashboard__input:focus-visible,
button:focus-visible {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Custom scrollbar for webkit browsers */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.5);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
}

/* Selection styling */
::selection {
    background: rgba(102, 126, 234, 0.2);
    color: #1f2937;
}

/* Improved notification styles */
.customer-dashboard__notification {
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* Modern card hover effects */
.customer-dashboard__project-card,
.customer-dashboard__metric,
.customer-dashboard__status-overview {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Improved mobile experience */
@media (max-width: 768px) {
    .customer-dashboard__login-title {
        font-size: 2rem;
    }
    
    .customer-dashboard__welcome h1 {
        font-size: 2rem;
    }
    
    .customer-dashboard__metric {
        padding: 1.5rem;
    }
    
    .customer-dashboard__metric-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.25rem;
    }
    
    .customer-dashboard__metric-content h3 {
        font-size: 1.5rem;
    }
}
</style>

<!-- Phase 2: Appointment Scheduling Modal -->
<div id="appointmentModal" class="customer-dashboard__modal" style="display: none;">
    <div class="customer-dashboard__modal-content">
        <div class="customer-dashboard__modal-header">
            <h3><i class="bi bi-calendar-check"></i> Schedule Appointment</h3>
            <button class="customer-dashboard__modal-close" onclick="closeModal('appointmentModal')">&times;</button>
        </div>
        <form method="post" class="customer-dashboard__modal-form">
            <input type="hidden" name="action" value="schedule_appointment">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
<div id="quoteModificationModal" class="customer-dashboard__modal" style="display: none;">
    <div class="customer-dashboard__modal-content">
        <div class="customer-dashboard__modal-header">
            <h3><i class="bi bi-pencil-square"></i> Request Quote Modification</h3>
            <button class="customer-dashboard__modal-close" onclick="closeModal('quoteModificationModal')">&times;</button>
        </div>
        <form method="post" class="customer-dashboard__modal-form">
            <input type="hidden" name="action" value="request_quote_modification">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
<div id="galleryModal" class="customer-dashboard__modal customer-dashboard__modal--large" style="display: none;">
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

</section>
</div>
</main>
</body>
</html> 