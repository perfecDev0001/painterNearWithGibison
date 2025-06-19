<?php
require_once 'core/GibsonAuth.php';

$auth = new GibsonAuth();

// Check admin authentication
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';
    
    if (empty($old)) {
        $errors[] = 'Current password is required.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $new2) {
        $errors[] = 'New passwords do not match.';
    }
    
    if (empty($errors)) {
        $adminId = $_SESSION['admin_id'];
        $result = $auth->changeAdminPassword($adminId, $old, $new);
        
        if ($result['success']) {
            $success = true;
        } else {
            $errors[] = $result['error'] ?? 'Failed to change password.';
        }
    }
}

include 'templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
      <title>Change Password | Painter Near Me Admin</title>
    <meta name="description" content="Change your admin password for Painter Near Me marketplace." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
<body>
<div class="admin-layout">
  <?php include 'templates/sidebar-admin.php'; ?>
  <main class="admin-main" role="main">
    <section class="admin-password hero admin-card">
      <h1 class="hero__title">Change Password</h1>
      <p class="hero__subtitle">Update your admin password for better security.</p>
    </section>

    <section class="admin-password__form-section admin-card">
      <?php if ($success): ?>
        <div class="admin-password__alert admin-password__alert--success" role="alert">
          Password changed successfully.
        </div>
      <?php endif; ?>
      
      <?php if (!empty($errors)): ?>
        <div class="admin-password__alert admin-password__alert--error" role="alert">
          <ul class="admin-password__error-list">
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form class="admin-password__form" method="post" action="" novalidate>
        <div class="admin-password__form-group">
          <label class="admin-password__label" for="old_password">Current Password</label>
          <input 
            class="admin-password__input" 
            type="password" 
            name="old_password" 
            id="old_password" 
            required 
            aria-required="true"
          />
        </div>

        <div class="admin-password__form-group">
          <label class="admin-password__label" for="new_password">New Password</label>
          <input 
            class="admin-password__input" 
            type="password" 
            name="new_password" 
            id="new_password" 
            required 
            minlength="8"
            aria-required="true"
          />
          <span class="admin-password__hint">Must be at least 8 characters long</span>
        </div>

        <div class="admin-password__form-group">
          <label class="admin-password__label" for="new_password2">Confirm New Password</label>
          <input 
            class="admin-password__input" 
            type="password" 
            name="new_password2" 
            id="new_password2" 
            required 
            minlength="8"
            aria-required="true"
          />
        </div>

        <div class="admin-password__actions">
          <button class="admin-password__submit" type="submit">
            Change Password
          </button>
          <a href="admin-manage-leads.php" class="admin-password__back-link">
            &larr; Back to Dashboard
          </a>
        </div>
      </form>
    </section>
  </main>
</div>

<style>
.admin-password__form-section {
  max-width: 500px;
  margin: 0 auto;
}

.admin-password__alert {
  padding: 1rem;
  border-radius: 0.7rem;
  margin-bottom: 1.5rem;
  font-weight: 600;
}

.admin-password__alert--success {
  background: #e6f7ea;
  color: #00b050;
}

.admin-password__alert--error {
  background: #fee2e2;
  color: #dc2626;
}

.admin-password__error-list {
  margin: 0;
  padding-left: 1.5rem;
}

.admin-password__form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.admin-password__form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.admin-password__label {
  color: #00b050;
  font-weight: 700;
  font-size: 1.1rem;
}

.admin-password__input {
  padding: 0.9rem 1.1rem;
  border: 1.5px solid #e5e7eb;
  border-radius: 0.7rem;
  font-size: 1.1rem;
  width: 100%;
  transition: all 0.2s;
}

.admin-password__input:focus {
  border-color: #00b050;
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 176, 80, 0.1);
}

.admin-password__hint {
  color: #6b7280;
  font-size: 0.9rem;
  margin-top: -0.2rem;
}

.admin-password__actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.admin-password__submit {
  background: #00b050;
  color: #fff;
  border: none;
  padding: 0.9rem 2rem;
  border-radius: 0.7rem;
  font-size: 1.1rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}

.admin-password__submit:hover {
  background: #009140;
}

.admin-password__back-link {
  color: #00b050;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.admin-password__back-link:hover {
  color: #009140;
  text-decoration: underline;
}

@media (max-width: 768px) {
  .admin-password__actions {
    flex-direction: column;
    align-items: stretch;
  }
  
  .admin-password__submit {
    width: 100%;
  }
  
  .admin-password__back-link {
    text-align: center;
  }
}
</style>
</body>
</html> 