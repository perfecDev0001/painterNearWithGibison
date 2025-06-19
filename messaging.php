<?php
require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Check if user is logged in
$isLoggedIn = $auth->isLoggedIn();
$isCustomer = false;
$isPainter = false;
$userId = null;
$userType = '';

if ($isLoggedIn) {
    $user = $auth->getCurrentUser();
    $userId = $auth->getCurrentPainterId();
    $isPainter = true;
    $userType = 'painter';
} else {
    // Check for customer session
    session_start();
    if (isset($_SESSION['customer_authenticated'])) {
        $isCustomer = true;
        $userId = $_SESSION['customer_id'] ?? 'customer_' . session_id();
        $userType = 'customer';
        $isLoggedIn = true;
    }
}

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Handle message sending
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'send_message') {
        $conversationId = $_POST['conversation_id'] ?? '';
        $messageText = trim($_POST['message_text'] ?? '');
        
        if (empty($messageText)) {
            $errors[] = 'Message cannot be empty.';
        } elseif (strlen($messageText) > 1000) {
            $errors[] = 'Message is too long. Maximum 1000 characters allowed.';
        } else {
            $result = $dataAccess->sendMessage($conversationId, $userId, $messageText, $userType);
            if ($result['success']) {
                $success = 'Message sent successfully!';
                // Redirect to prevent form resubmission
                header("Location: messaging.php?conversation_id={$conversationId}&sent=1");
                exit;
            } else {
                $errors[] = 'Failed to send message: ' . ($result['error'] ?? 'Unknown error');
            }
        }
    }
    
    if ($action === 'create_conversation') {
        $leadId = $_POST['lead_id'] ?? '';
        $painterId = $_POST['painter_id'] ?? '';
        $customerId = $isCustomer ? $userId : ($_POST['customer_id'] ?? '');
        
        if (empty($leadId) || empty($painterId) || empty($customerId)) {
            $errors[] = 'Missing required information to create conversation.';
        } else {
            $result = $dataAccess->getOrCreateConversation($leadId, $customerId, $painterId);
            if ($result['success']) {
                header("Location: messaging.php?conversation_id={$result['data']['id']}");
                exit;
            } else {
                $errors[] = 'Failed to create conversation: ' . ($result['error'] ?? 'Unknown error');
            }
        }
    }
}

// Handle conversation creation from dashboard links
if (!empty($_GET['lead_id']) && !empty($_GET['painter_id']) && !empty($_GET['customer_id'])) {
    $leadId = $_GET['lead_id'];
    $painterId = $_GET['painter_id'];
    $customerId = $_GET['customer_id'];
    
    $result = $dataAccess->getOrCreateConversation($leadId, $customerId, $painterId);
    if ($result['success']) {
        header("Location: messaging.php?conversation_id={$result['data']['id']}");
        exit;
    }
} elseif (!empty($_GET['lead_id']) && !empty($_GET['painter_id']) && $isCustomer) {
    // Customer accessing from dashboard
    $leadId = $_GET['lead_id'];
    $painterId = $_GET['painter_id'];
    
    $result = $dataAccess->getOrCreateConversation($leadId, $userId, $painterId);
    if ($result['success']) {
        header("Location: messaging.php?conversation_id={$result['data']['id']}");
        exit;
    }
}

// Get conversations for current user
$conversationsResult = $dataAccess->getConversationsByUser($userId, $userType);
$conversations = $conversationsResult['success'] ? $conversationsResult['data'] : [];

// Get current conversation details if specified
$currentConversation = null;
$messages = [];
$conversationId = $_GET['conversation_id'] ?? '';

if ($conversationId && !empty($conversations)) {
    foreach ($conversations as $conv) {
        if ($conv['id'] == $conversationId) {
            $conversationDetails = $dataAccess->getConversationWithDetails($conversationId);
            if ($conversationDetails['success']) {
                $currentConversation = $conversationDetails['data'];
                $messages = $currentConversation['messages'] ?? [];
            }
            break;
        }
    }
}

// Mark messages as read if viewing a conversation
if ($currentConversation && !empty($messages)) {
    foreach ($messages as $message) {
        if (!$message['is_read'] && $message['sender_type'] !== $userType) {
            $dataAccess->markMessageAsRead($message['id'], $userId);
        }
    }
}

// Get unread message count
$unreadResult = $dataAccess->getUnreadMessageCount($userId, $userType);
$unreadCount = $unreadResult['success'] ? ($unreadResult['data']['count'] ?? 0) : 0;

include 'templates/header.php';
?>

<head>
    <title>Messages | Painter Near Me</title>
    <meta name="description" content="Manage your messages and communicate with <?php echo $isPainter ? 'customers' : 'painters'; ?> on Painter Near Me." />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>

<main role="main" class="messaging">
    <div class="messaging__container">
        <?php if ($isPainter): ?>
            <div style="display:flex;gap:2.5rem;align-items:flex-start;max-width:1100px;margin:0 auto;">
                <div>
                    <?php include 'templates/sidebar-painter.php'; ?>
                </div>
                <div style="flex:1;min-width:0;">
        <?php endif; ?>

        <section class="messaging__header">
            <h1 class="messaging__title">
                <i class="bi bi-chat-dots"></i>
                Messages
                <?php if ($unreadCount > 0): ?>
                    <span class="messaging__unread-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </h1>
            <p class="messaging__subtitle">
                Communicate with <?php echo $isPainter ? 'customers about their painting projects' : 'painters about your project'; ?>
            </p>
        </section>

        <?php if (!empty($errors)): ?>
            <div class="messaging__errors">
                <?php foreach ($errors as $error): ?>
                    <div class="messaging__error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="messaging__success">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="messaging__layout">
            <!-- Conversations List -->
            <aside class="messaging__conversations">
                <h2 class="messaging__conversations-title">Conversations</h2>
                
                <?php if (empty($conversations)): ?>
                    <div class="messaging__empty-conversations">
                        <i class="bi bi-chat-square-dots"></i>
                        <p>No conversations yet</p>
                        <small>
                            <?php if ($isPainter): ?>
                                Start conversations when you bid on projects
                            <?php else: ?>
                                Conversations will appear when painters contact you
                            <?php endif; ?>
                        </small>
                    </div>
                <?php else: ?>
                    <div class="messaging__conversation-list">
                        <?php foreach ($conversations as $conv): ?>
                            <?php 
                            $isActive = $conversationId == $conv['id'];
                            $lastMessage = !empty($conv['messages']) ? end($conv['messages']) : null;
                            $hasUnread = false;
                            if ($lastMessage && !$lastMessage['is_read'] && $lastMessage['sender_type'] !== $userType) {
                                $hasUnread = true;
                            }
                            
                            // Get conversation partner name
                            $partnerName = 'Unknown';
                            if ($isPainter && isset($conv['lead']['customer_name'])) {
                                $partnerName = $conv['lead']['customer_name'];
                            } elseif ($isCustomer && isset($conv['painter']['company_name'])) {
                                $partnerName = $conv['painter']['company_name'];
                            }
                            
                            $leadTitle = $conv['lead']['job_title'] ?? 'Project';
                            ?>
                            <a href="messaging.php?conversation_id=<?php echo $conv['id']; ?>" 
                               class="messaging__conversation-item <?php echo $isActive ? 'messaging__conversation-item--active' : ''; ?>">
                                <div class="messaging__conversation-header">
                                    <h3 class="messaging__conversation-partner">
                                        <?php echo htmlspecialchars($partnerName); ?>
                                        <?php if ($hasUnread): ?>
                                            <span class="messaging__unread-dot"></span>
                                        <?php endif; ?>
                                    </h3>
                                    <small class="messaging__conversation-project"><?php echo htmlspecialchars($leadTitle); ?></small>
                                </div>
                                
                                <?php if ($lastMessage): ?>
                                    <div class="messaging__conversation-preview">
                                        <p><?php echo htmlspecialchars(substr($lastMessage['message_text'], 0, 60) . '...'); ?></p>
                                        <small><?php echo date('M j, g:i A', strtotime($lastMessage['sent_at'])); ?></small>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <!-- Messages Area -->
            <main class="messaging__messages">
                <?php if (!$currentConversation): ?>
                    <div class="messaging__no-conversation">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the list to start messaging</p>
                    </div>
                <?php else: ?>
                    <div class="messaging__conversation-header">
                        <div class="messaging__conversation-info">
                            <h2 class="messaging__conversation-title">
                                <?php 
                                if ($isPainter) {
                                    echo htmlspecialchars($currentConversation['customer']['name'] ?? 'Customer');
                                } else {
                                    echo htmlspecialchars($currentConversation['painter']['company_name'] ?? 'Painter');
                                }
                                ?>
                            </h2>
                            <p class="messaging__conversation-project">
                                Re: <?php echo htmlspecialchars($currentConversation['lead']['job_title'] ?? 'Project'); ?>
                            </p>
                        </div>
                        <div class="messaging__conversation-actions">
                            <a href="<?php echo $isPainter ? 'leads.php' : 'quote-bids.php?lead_id=' . $currentConversation['lead_id']; ?>" 
                               class="messaging__view-project">
                                <i class="bi bi-eye"></i> View Project
                            </a>
                        </div>
                    </div>

                    <div class="messaging__messages-container" id="messagesContainer">
                        <?php if (empty($messages)): ?>
                            <div class="messaging__no-messages">
                                <i class="bi bi-chat"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <?php 
                                $isOwnMessage = $message['sender_type'] === $userType;
                                $senderName = $isOwnMessage ? 'You' : 
                                    ($isPainter ? ($currentConversation['customer']['name'] ?? 'Customer') : 
                                     ($currentConversation['painter']['company_name'] ?? 'Painter'));
                                ?>
                                <div class="messaging__message <?php echo $isOwnMessage ? 'messaging__message--own' : 'messaging__message--other'; ?>">
                                    <div class="messaging__message-header">
                                        <strong class="messaging__message-sender"><?php echo htmlspecialchars($senderName); ?></strong>
                                        <time class="messaging__message-time"><?php echo date('M j, Y g:i A', strtotime($message['sent_at'])); ?></time>
                                    </div>
                                    <div class="messaging__message-content">
                                        <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="messaging__compose" action="messaging.php?conversation_id=<?php echo $conversationId; ?>">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="conversation_id" value="<?php echo $conversationId; ?>">
                        
                        <div class="messaging__compose-body">
                            <textarea name="message_text" 
                                      class="messaging__compose-textarea" 
                                      placeholder="Type your message here..." 
                                      rows="3" 
                                      maxlength="1000" 
                                      required></textarea>
                            <div class="messaging__compose-actions">
                                <small class="messaging__character-count">0/1000 characters</small>
                                <button type="submit" class="messaging__send-btn">
                                    <i class="bi bi-send"></i> Send
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </main>
        </div>

        <?php if ($isPainter): ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'templates/footer.php'; ?>

<script>
// Auto-scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Character counter
const textarea = document.querySelector('.messaging__compose-textarea');
const counter = document.querySelector('.messaging__character-count');

if (textarea && counter) {
    textarea.addEventListener('input', function() {
        const count = this.value.length;
        counter.textContent = count + '/1000 characters';
        
        if (count > 900) {
            counter.style.color = '#dc2626';
        } else if (count > 800) {
            counter.style.color = '#f59e0b';
        } else {
            counter.style.color = '#6b7280';
        }
    });
}

// Auto-scroll on page load
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    // Auto-refresh messages every 30 seconds
    setInterval(function() {
        const conversationId = new URLSearchParams(window.location.search).get('conversation_id');
        if (conversationId) {
            // Simple refresh check - in production, you'd use AJAX
            const now = Date.now();
            const lastRefresh = localStorage.getItem('lastMessageRefresh');
            if (!lastRefresh || now - lastRefresh > 30000) {
                localStorage.setItem('lastMessageRefresh', now);
                // Note: This would need AJAX implementation for real-time updates
            }
        }
    }, 30000);
});

// Show success message if redirected after sending
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('sent') === '1') {
    const successDiv = document.createElement('div');
    successDiv.className = 'messaging__success';
    successDiv.innerHTML = '<i class="bi bi-check-circle"></i> Message sent successfully!';
    
    const header = document.querySelector('.messaging__header');
    if (header) {
        header.parentNode.insertBefore(successDiv, header.nextSibling);
        
        // Remove success message after 3 seconds
        setTimeout(function() {
            successDiv.remove();
        }, 3000);
    }
}
</script>

<style>
/* Messaging Interface Styles */
.messaging {
    min-height: 100vh;
    background: #f8fafc;
    padding: 2rem 1rem;
}

.messaging__container {
    max-width: 1200px;
    margin: 0 auto;
}

.messaging__header {
    text-align: center;
    margin-bottom: 2rem;
}

.messaging__title {
    color: #00b050;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.messaging__unread-badge {
    background: #dc2626;
    color: white;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    min-width: 1.5rem;
    text-align: center;
}

.messaging__subtitle {
    color: #6b7280;
    font-size: 1.1rem;
    margin: 0;
}

.messaging__errors,
.messaging__success {
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 0.5rem;
}

.messaging__errors {
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.messaging__error {
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.messaging__success {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.messaging__layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 1rem;
    height: 70vh;
    background: white;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.messaging__conversations {
    background: #f9fafb;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
}

.messaging__conversations-title {
    color: #374151;
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.messaging__empty-conversations {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #6b7280;
}

.messaging__empty-conversations i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.messaging__conversation-list {
    flex: 1;
    overflow-y: auto;
}

.messaging__conversation-item {
    display: block;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    color: inherit;
    text-decoration: none;
    transition: background-color 0.2s;
}

.messaging__conversation-item:hover {
    background: #f3f4f6;
}

.messaging__conversation-item--active {
    background: #dbeafe;
    border-right: 3px solid #00b050;
}

.messaging__conversation-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.messaging__conversation-partner {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.messaging__unread-dot {
    width: 8px;
    height: 8px;
    background: #dc2626;
    border-radius: 50%;
}

.messaging__conversation-project {
    color: #6b7280;
    font-size: 0.875rem;
}

.messaging__conversation-preview p {
    margin: 0 0 0.25rem 0;
    color: #4b5563;
    font-size: 0.875rem;
}

.messaging__conversation-preview small {
    color: #9ca3af;
    font-size: 0.75rem;
}

.messaging__messages {
    display: flex;
    flex-direction: column;
}

.messaging__no-conversation {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b7280;
    text-align: center;
}

.messaging__no-conversation i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.messaging__conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.messaging__conversation-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.messaging__conversation-project {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0.25rem 0 0 0;
}

.messaging__view-project {
    background: #00b050;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.messaging__view-project:hover {
    background: #009140;
    color: white;
}

.messaging__messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    scroll-behavior: smooth;
}

.messaging__no-messages {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b7280;
    text-align: center;
}

.messaging__no-messages i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.messaging__message {
    margin-bottom: 1rem;
}

.messaging__message--own {
    text-align: right;
}

.messaging__message--own .messaging__message-content {
    background: #00b050;
    color: white;
    margin-left: auto;
}

.messaging__message--other .messaging__message-content {
    background: #f3f4f6;
    color: #111827;
    margin-right: auto;
}

.messaging__message-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.messaging__message--own .messaging__message-header {
    justify-content: flex-end;
}

.messaging__message-sender {
    color: #374151;
}

.messaging__message-time {
    color: #9ca3af;
}

.messaging__message-content {
    max-width: 70%;
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    word-wrap: break-word;
}

.messaging__compose {
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

.messaging__compose-body {
    padding: 1rem;
}

.messaging__compose-textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.75rem;
    font-family: inherit;
    font-size: 0.875rem;
    resize: vertical;
    min-height: 80px;
}

.messaging__compose-textarea:focus {
    outline: none;
    border-color: #00b050;
    box-shadow: 0 0 0 3px rgba(0, 176, 80, 0.1);
}

.messaging__compose-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
}

.messaging__character-count {
    color: #6b7280;
    font-size: 0.75rem;
}

.messaging__send-btn {
    background: #00b050;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.messaging__send-btn:hover {
    background: #009140;
}

.messaging__send-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
    .messaging__layout {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .messaging__conversations {
        display: none;
    }
    
    .messaging__conversation-header {
        padding: 1rem;
    }
    
    .messaging__conversation-title {
        font-size: 1.125rem;
    }
    
    .messaging__messages-container {
        height: 50vh;
    }
    
    .messaging__message-content {
        max-width: 85%;
    }
}

@media (max-width: 480px) {
    .messaging {
        padding: 1rem 0.5rem;
    }
    
    .messaging__title {
        font-size: 2rem;
    }
    
    .messaging__layout {
        border-radius: 0.5rem;
    }
    
    .messaging__compose-body {
        padding: 0.75rem;
    }
    
    .messaging__message-content {
        max-width: 90%;
        padding: 0.5rem 0.75rem;
    }
}
</style> 