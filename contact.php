<?php 
session_start();
require_once __DIR__ . '/core/Mailer.php';
use Core\Mailer;

// Define ADMIN_EMAIL constant if not already defined
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
}
$contact_errors = [];
$contact_success = false;
$name = '';
$email = '';
$message = '';
$honeypot = '';
$math_question = '';
$math_answer = '';
if (!isset($_SESSION['math_a']) || !isset($_SESSION['math_b'])) {
  $_SESSION['math_a'] = rand(1, 9);
  $_SESSION['math_b'] = rand(1, 9);
}
$math_question = $_SESSION['math_a'] . ' + ' . $_SESSION['math_b'];
$expected_math = $_SESSION['math_a'] + $_SESSION['math_b'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $honeypot = trim($_POST['website'] ?? '');
  $math_answer = trim($_POST['math_answer'] ?? '');
  if ($honeypot !== '') {
    // Spam detected, silently discard
    $contact_success = false;
    $name = $email = $message = '';
  } else {
    if ($name === '') {
      $contact_errors['name'] = 'Please enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $contact_errors['email'] = 'Please enter a valid email address.';
    }
    if ($message === '') {
      $contact_errors['message'] = 'Please enter your message.';
    }
    if ($math_answer === '' || intval($math_answer) !== $expected_math) {
      $contact_errors['math'] = 'Incorrect answer to the math question.';
    }
    if (!$contact_errors) {
      $adminTo = ADMIN_EMAIL;
              $subject = 'Contact Form Message from Painter Near Me';
      $body = '<html><body>';
      $body .= '<h2>Contact Form Submission</h2>';
      $body .= '<table cellpadding="6" style="font-size:1.1rem;">';
      $body .= '<tr><td style="font-weight:bold;">Name:</td><td>' . htmlspecialchars($name) . '</td></tr>';
      $body .= '<tr><td style="font-weight:bold;">Email:</td><td>' . htmlspecialchars($email) . '</td></tr>';
      $body .= '<tr><td style="font-weight:bold;">Message:</td><td>' . nl2br(htmlspecialchars($message)) . '</td></tr>';
      $body .= '</table>';
      $body .= '</body></html>';
      $mailer = new Mailer();
      $mailer->sendMail($adminTo, $subject, $body, strip_tags($body));
      $contact_success = true;
      $name = $email = $message = '';
      unset($_SESSION['math_a'], $_SESSION['math_b']);
    } else {
      // Regenerate math question for next attempt
      $_SESSION['math_a'] = rand(1, 9);
      $_SESSION['math_b'] = rand(1, 9);
      $math_question = $_SESSION['math_a'] . ' + ' . $_SESSION['math_b'];
      $expected_math = $_SESSION['math_a'] + $_SESSION['math_b'];
    }
  }
}
?>
<?php include 'templates/header.php'; ?>
<head>
      <title>Contact | Painter Near Me</title>
    <meta name="description" content="Contact Painter Near Me for support, questions, or partnership opportunities." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk",
      "email": "info@painter-near-me.co.uk"
    }
  </script>
</head>
<main role="main">
  <section class="contact-hero hero">
    <h1 class="hero__title">Contact Us</h1>
    <p class="hero__subtitle">We're here to help. Reach out with any questions or feedback.</p>
  </section>
  <section class="contact-main">
    <div class="contact-main__container" style="max-width:600px;margin:2.5rem auto;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;">
      <?php if ($contact_success): ?>
        <div class="contact-form__success" role="status" style="background:#eaffea;border:1.5px solid #00b050;color:#008040;padding:1.2rem 1.5rem;margin-bottom:2rem;border-radius:1rem;font-size:1.15rem;text-align:center;">
          <span style="font-size:1.5rem;vertical-align:middle;">✅</span> Thank you! Your message has been sent. We'll be in touch soon.
        </div>
      <?php endif; ?>
      <?php if ($contact_errors): ?>
        <div class="contact-form__errors" role="alert" style="background:#ffeaea;color:#b00020;border:1.5px solid #ffb3b3;border-radius:1rem;padding:1rem;margin-bottom:1rem;font-size:1.05rem;">
          <?php foreach ($contact_errors as $err): ?>
            <div class="contact-form__error"><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form class="contact-form" method="post" action="#contact" autocomplete="off" novalidate id="contact">
        <div class="contact-form__honeypot" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
          <label for="website">Website (leave blank)</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" />
        </div>
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-name">Name</label>
          <input class="contact-form__input<?php if(isset($contact_errors['name'])) echo ' input-error'; ?>" type="text" id="contact-name" name="name" value="<?php echo htmlspecialchars($name); ?>" required />
        </div>
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-email">Email</label>
          <input class="contact-form__input<?php if(isset($contact_errors['email'])) echo ' input-error'; ?>" type="email" id="contact-email" name="email" value="<?php echo htmlspecialchars($email); ?>" required />
        </div>
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-message">Message</label>
          <textarea class="contact-form__input<?php if(isset($contact_errors['message'])) echo ' input-error'; ?>" id="contact-message" name="message" rows="5" required><?php echo htmlspecialchars($message); ?></textarea>
        </div>
        <div class="contact-form__group">
          <label class="contact-form__label" for="math-answer">What is <?php echo htmlspecialchars($math_question); ?>? <span style="color:#b00020;">*</span></label>
          <input class="contact-form__input<?php if(isset($contact_errors['math'])) echo ' input-error'; ?>" type="text" id="math-answer" name="math_answer" value="" inputmode="numeric" pattern="[0-9]*" required autocomplete="off" />
        </div>
        <button class="contact-form__button step__button" type="submit">Send Message</button>
      </form>
    </div>
  </section>
</main>
<?php include 'templates/footer.php'; ?>
<style>
.contact-form__group { margin-bottom: 1.3rem; }
.contact-form__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
.contact-form__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
.contact-form__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
.contact-form__button { margin-top: 1rem; }
.input-error { border-color: #b00020 !important; background: #ffeaea; }
</style> 