<?php include 'templates/header.php'; ?>
<head>
  <title>How it Works | Painter Near Me</title>
  <meta name="description" content="Learn how Painter Near Me's quote process works. Get fast, accurate painter quotes in a few easy steps." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
  </script>
</head>

<main role="main">
  <section class="howitworks-hero hero">
    <h1 class="hero__title">How It Works</h1>
    <p class="hero__subtitle">Get a painter quote in minutes. Simple, fast, and free.</p>
  </section>

  <!-- Main Steps Section -->
  <section class="howitworks-steps">
    <div class="howitworks-steps__container">
      <ol class="howitworks-steps__list">
        <li class="howitworks-steps__item" data-step="1">
          <span class="howitworks-steps__number">1</span> 
          <div class="howitworks-steps__content">
            <span class="howitworks-steps__text">Tell us about your painting project</span>
            <p class="howitworks-steps__detail">Share your postcode, project type, and requirements. Takes just 2 minutes.</p>
          </div>
        </li>
        <li class="howitworks-steps__item" data-step="2">
          <span class="howitworks-steps__number">2</span> 
          <div class="howitworks-steps__content">
            <span class="howitworks-steps__text">Get matched with trusted local painters</span>
            <p class="howitworks-steps__detail">Our system finds verified painters in your area who specialize in your project type.</p>
          </div>
        </li>
        <li class="howitworks-steps__item" data-step="3">
          <span class="howitworks-steps__number">3</span> 
          <div class="howitworks-steps__content">
            <span class="howitworks-steps__text">Receive and compare quotes</span>
            <p class="howitworks-steps__detail">Get up to 5 competitive quotes with detailed breakdowns and painter profiles.</p>
          </div>
        </li>
        <li class="howitworks-steps__item" data-step="4">
          <span class="howitworks-steps__number">4</span> 
          <div class="howitworks-steps__content">
            <span class="howitworks-steps__text">Choose your painter and get started!</span>
            <p class="howitworks-steps__detail">Review profiles, read reviews, and hire the perfect painter for your project.</p>
          </div>
        </li>
      </ol>
      <a href="/quote.php" class="howitworks-cta step__button" style="margin-top:2rem;">Start Your Quote</a>
    </div>
  </section>

  <!-- Interactive Demo Section -->
  <section class="howitworks-demo">
    <div class="howitworks-demo__container">
      <h2 class="howitworks-demo__title">See It In Action</h2>
      <p class="howitworks-demo__subtitle">Click through our interactive demo to see how easy it is</p>
      
      <div class="howitworks-demo__interface">
        <div class="demo-screen" id="demoScreen">
          <div class="demo-step active" data-demo-step="1">
            <div class="demo-form">
              <h3>Tell us about your project</h3>
              <div class="demo-input-group">
                <label>Your Postcode</label>
                <input type="text" placeholder="SW1A 1AA" readonly>
              </div>
              <div class="demo-input-group">
                <label>Project Type</label>
                <select disabled>
                  <option>Interior Painting</option>
                </select>
              </div>
              <button class="demo-btn" onclick="nextDemoStep()">Next Step</button>
            </div>
          </div>
          
          <div class="demo-step" data-demo-step="2">
            <div class="demo-matching">
              <h3>Finding painters in your area...</h3>
              <div class="demo-loader">
                <div class="demo-spinner"></div>
                <p>Searching for qualified painters near SW1A 1AA</p>
              </div>
              <div class="demo-results" style="display: none;">
                <p>✓ Found 8 qualified painters in your area</p>
                <button class="demo-btn" onclick="nextDemoStep()">View Quotes</button>
              </div>
            </div>
          </div>
          
          <div class="demo-step" data-demo-step="3">
            <div class="demo-quotes">
              <h3>Your Quotes</h3>
              <div class="demo-quote-list">
                <div class="demo-quote">
                  <div class="demo-painter-info">
                    <strong>John's Painting Services</strong>
                    <div class="demo-rating">★★★★★ (4.9/5)</div>
                  </div>
                  <div class="demo-price">£850</div>
                </div>
                <div class="demo-quote">
                  <div class="demo-painter-info">
                    <strong>Elite Decorators</strong>
                    <div class="demo-rating">★★★★☆ (4.7/5)</div>
                  </div>
                  <div class="demo-price">£920</div>
                </div>
                <div class="demo-quote">
                  <div class="demo-painter-info">
                    <strong>Perfect Paint Co.</strong>
                    <div class="demo-rating">★★★★★ (4.8/5)</div>
                  </div>
                  <div class="demo-price">£780</div>
                </div>
              </div>
              <button class="demo-btn" onclick="nextDemoStep()">Choose Painter</button>
            </div>
          </div>
          
          <div class="demo-step" data-demo-step="4">
            <div class="demo-success">
              <div class="demo-success-icon">✓</div>
              <h3>Project Booked!</h3>
              <p>Perfect Paint Co. will contact you within 24 hours to schedule your project.</p>
              <button class="demo-btn" onclick="resetDemo()">Try Again</button>
            </div>
          </div>
        </div>
        
        <div class="demo-controls">
          <button class="demo-control" onclick="setDemoStep(1)" data-step="1">1</button>
          <button class="demo-control" onclick="setDemoStep(2)" data-step="2">2</button>
          <button class="demo-control" onclick="setDemoStep(3)" data-step="3">3</button>
          <button class="demo-control" onclick="setDemoStep(4)" data-step="4">4</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Benefits Section -->
  <section class="howitworks-benefits">
    <div class="howitworks-benefits__container">
      <h2 class="howitworks-benefits__title">Why Choose Our Platform?</h2>
      
      <div class="howitworks-benefits__grid">
        <div class="benefit-card">
          <div class="benefit-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b050" stroke-width="2">
              <path d="M9 12l2 2 4-4"/>
              <circle cx="12" cy="12" r="9"/>
            </svg>
          </div>
          <h3>Verified Painters</h3>
          <p>All painters are background checked, insured, and have verified customer reviews.</p>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b050" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12,6 12,12 16,14"/>
            </svg>
          </div>
          <h3>Save Time</h3>
          <p>Get multiple quotes instantly instead of calling painters individually.</p>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b050" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <h3>Save Money</h3>
          <p>Compare competitive quotes to find the best value for your project.</p>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b050" stroke-width="2">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
          </div>
          <h3>Quality Guaranteed</h3>
          <p>All work is backed by our quality guarantee and customer protection.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ Section -->
  <section class="howitworks-faq">
    <div class="howitworks-faq__container">
      <h2 class="howitworks-faq__title">Frequently Asked Questions</h2>
      
      <div class="faq-list">
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>How much does it cost to use Painter Near Me?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>Our service is completely free for customers. You only pay the painter you choose for the work they do. There are no hidden fees or charges from us.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>How quickly will I receive quotes?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>Most customers receive their first quote within 2 hours, with all quotes typically arriving within 24 hours of submitting your project details.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>Are the painters vetted and insured?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>Yes, all painters on our platform are background checked, fully insured, and have verified customer reviews. We only work with professional, qualified tradespeople.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>What if I'm not happy with the work?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>We offer customer protection and will work with you and the painter to resolve any issues. All work comes with our quality guarantee.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>Can I see examples of previous work?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>Absolutely! Each painter's profile includes photos of their previous work, customer reviews, and detailed ratings to help you make an informed decision.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFAQ(this)">
            <span>What types of painting projects do you cover?</span>
            <span class="faq-toggle">+</span>
          </button>
          <div class="faq-answer">
            <p>We cover all types of painting projects including interior painting, exterior painting, commercial work, decorative finishes, and specialized coatings.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Testimonials Section -->
  <section class="howitworks-testimonials">
    <div class="howitworks-testimonials__container">
      <h2 class="howitworks-testimonials__title">What Our Customers Say</h2>
      
      <div class="testimonials-slider">
        <div class="testimonial-track" id="testimonialTrack">
          <div class="testimonial-slide active">
            <blockquote class="testimonial">
              <p class="testimonial__text">"Found an excellent painter within hours. The quality was outstanding and the price was fair. Highly recommend!"</p>
              <cite class="testimonial__author">- Sarah J., London</cite>
              <div class="testimonial__rating">★★★★★</div>
            </blockquote>
          </div>
          
          <div class="testimonial-slide">
            <blockquote class="testimonial">
              <p class="testimonial__text">"Professional service from start to finish. The painter was punctual, tidy, and did amazing work on our living room."</p>
              <cite class="testimonial__author">- Michael P., Manchester</cite>
              <div class="testimonial__rating">★★★★★</div>
            </blockquote>
          </div>
          
          <div class="testimonial-slide">
            <blockquote class="testimonial">
              <p class="testimonial__text">"Great platform for finding reliable painters. Saved me time and money compared to other methods."</p>
              <cite class="testimonial__author">- Emma L., Birmingham</cite>
              <div class="testimonial__rating">★★★★★</div>
            </blockquote>
          </div>
          
          <div class="testimonial-slide">
            <blockquote class="testimonial">
              <p class="testimonial__text">"The quote process was so simple and I got 4 competitive quotes. Chose the best one and couldn't be happier!"</p>
              <cite class="testimonial__author">- David R., Leeds</cite>
              <div class="testimonial__rating">★★★★★</div>
            </blockquote>
          </div>
        </div>
        
        <div class="testimonial-controls">
          <button class="testimonial-prev" onclick="previousTestimonial()">‹</button>
          <div class="testimonial-dots">
            <button class="testimonial-dot active" onclick="goToTestimonial(0)"></button>
            <button class="testimonial-dot" onclick="goToTestimonial(1)"></button>
            <button class="testimonial-dot" onclick="goToTestimonial(2)"></button>
            <button class="testimonial-dot" onclick="goToTestimonial(3)"></button>
          </div>
          <button class="testimonial-next" onclick="nextTestimonial()">›</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Final CTA Section -->
  <section class="howitworks-final-cta">
    <div class="howitworks-final-cta__container">
      <h2>Ready to Get Started?</h2>
      <p>Join thousands of satisfied customers who found their perfect painter through our platform.</p>
      <div class="cta-buttons">
        <a href="/quote.php" class="cta-primary">Get Free Quotes</a>
        <a href="/contact.php" class="cta-secondary">Have Questions?</a>
      </div>
      <div class="trust-indicators">
        <div class="trust-item">
          <strong>50,000+</strong>
          <span>Projects Completed</span>
        </div>
        <div class="trust-item">
          <strong>4.8/5</strong>
          <span>Average Rating</span>
        </div>
        <div class="trust-item">
          <strong>2,500+</strong>
          <span>Verified Painters</span>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include 'templates/footer.php'; ?>

<script>
// Interactive Demo Functionality
let currentDemoStep = 1;

function nextDemoStep() {
  if (currentDemoStep < 4) {
    if (currentDemoStep === 2) {
      // Show loading animation for step 2
      setTimeout(() => {
        document.querySelector('[data-demo-step="2"] .demo-loader').style.display = 'none';
        document.querySelector('[data-demo-step="2"] .demo-results').style.display = 'block';
      }, 1500);
      return;
    }
    setDemoStep(currentDemoStep + 1);
  }
}

function setDemoStep(step) {
  // Hide all demo steps
  document.querySelectorAll('.demo-step').forEach(el => {
    el.classList.remove('active');
  });
  
  // Show target step
  document.querySelector(`[data-demo-step="${step}"]`).classList.add('active');
  
  // Update controls
  document.querySelectorAll('.demo-control').forEach(el => {
    el.classList.remove('active');
  });
  document.querySelector(`[data-step="${step}"]`).classList.add('active');
  
  currentDemoStep = step;
  
  // Reset step 2 if going back to it
  if (step === 2) {
    document.querySelector('[data-demo-step="2"] .demo-loader').style.display = 'block';
    document.querySelector('[data-demo-step="2"] .demo-results').style.display = 'none';
  }
}

function resetDemo() {
  setDemoStep(1);
}

// FAQ Functionality
function toggleFAQ(button) {
  const faqItem = button.parentElement;
  const answer = faqItem.querySelector('.faq-answer');
  const toggle = button.querySelector('.faq-toggle');
  
  const isOpen = faqItem.classList.contains('open');
  
  // Close all other FAQs
  document.querySelectorAll('.faq-item').forEach(item => {
    item.classList.remove('open');
    item.querySelector('.faq-toggle').textContent = '+';
  });
  
  // Toggle current FAQ
  if (!isOpen) {
    faqItem.classList.add('open');
    toggle.textContent = '−';
  }
}

// Testimonials Slider
let currentTestimonial = 0;
const testimonials = document.querySelectorAll('.testimonial-slide');
const totalTestimonials = testimonials.length;

function showTestimonial(index) {
  // Hide all testimonials
  testimonials.forEach(slide => slide.classList.remove('active'));
  document.querySelectorAll('.testimonial-dot').forEach(dot => dot.classList.remove('active'));
  
  // Show target testimonial
  testimonials[index].classList.add('active');
  document.querySelectorAll('.testimonial-dot')[index].classList.add('active');
  
  currentTestimonial = index;
}

function nextTestimonial() {
  const next = (currentTestimonial + 1) % totalTestimonials;
  showTestimonial(next);
}

function previousTestimonial() {
  const prev = (currentTestimonial - 1 + totalTestimonials) % totalTestimonials;
  showTestimonial(prev);
}

function goToTestimonial(index) {
  showTestimonial(index);
}

// Auto-advance testimonials
setInterval(nextTestimonial, 5000);

// Smooth scrolling for anchor links
document.addEventListener('DOMContentLoaded', function() {
  // Add smooth scrolling to all links with hash
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  
  // Initialize demo controls
  document.querySelectorAll('.demo-control').forEach(control => {
    if (control.dataset.step === '1') {
      control.classList.add('active');
    }
  });
});

// Intersection Observer for animations
if ('IntersectionObserver' in window) {
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
      }
    });
  }, observerOptions);
  
  // Observe elements for animation
  document.querySelectorAll('.howitworks-steps__item, .benefit-card, .faq-item').forEach(el => {
    observer.observe(el);
  });
}
</script>

<style>
/* Existing styles preserved */
.howitworks-steps__container { max-width: 600px; margin: 2.5rem auto; background: #fff; border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04); padding: 2.5rem 2rem; }
.howitworks-steps__list { list-style: none; padding: 0; margin: 0; }
.howitworks-steps__item { display: flex; align-items: flex-start; gap: 1.2rem; font-size: 1.18rem; margin-bottom: 2rem; opacity: 0; transform: translateY(20px); transition: all 0.6s ease; }
.howitworks-steps__item.animate-in { opacity: 1; transform: translateY(0); }
.howitworks-steps__number { background: #00b050; color: #fff; font-weight: 900; border-radius: 50%; width: 2.2rem; height: 2.2rem; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.howitworks-steps__content { flex: 1; }
.howitworks-steps__text { color: #222; font-weight: 600; display: block; margin-bottom: 0.5rem; }
.howitworks-steps__detail { color: #666; font-size: 1rem; margin: 0; line-height: 1.5; }

/* Interactive Demo Styles */
.howitworks-demo {
  background: #f8fafc;
  padding: 4rem 1rem;
}

.howitworks-demo__container {
  max-width: 800px;
  margin: 0 auto;
  text-align: center;
}

.howitworks-demo__title {
  font-size: 2.5rem;
  color: #222;
  margin-bottom: 1rem;
}

.howitworks-demo__subtitle {
  font-size: 1.2rem;
  color: #666;
  margin-bottom: 3rem;
}

.howitworks-demo__interface {
  background: #fff;
  border-radius: 1.5rem;
  box-shadow: 0 8px 32px rgba(0,176,80,0.15);
  overflow: hidden;
}

.demo-screen {
  min-height: 400px;
  position: relative;
}

.demo-step {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 3rem 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: translateX(20px);
  transition: all 0.3s ease;
}

.demo-step.active {
  opacity: 1;
  transform: translateX(0);
}

.demo-form h3,
.demo-matching h3,
.demo-quotes h3,
.demo-success h3 {
  margin-bottom: 2rem;
  color: #222;
}

.demo-input-group {
  margin-bottom: 1.5rem;
  text-align: left;
}

.demo-input-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #333;
}

.demo-input-group input,
.demo-input-group select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
}

.demo-btn {
  background: #00b050;
  color: white;
  border: none;
  padding: 0.75rem 2rem;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.demo-btn:hover {
  background: #009140;
}

.demo-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top: 4px solid #00b050;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.demo-quote-list {
  text-align: left;
  margin-bottom: 2rem;
}

.demo-quote {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}

.demo-painter-info strong {
  display: block;
  margin-bottom: 0.25rem;
}

.demo-rating {
  color: #fbbf24;
  font-size: 0.9rem;
}

.demo-price {
  font-size: 1.5rem;
  font-weight: bold;
  color: #00b050;
}

.demo-success-icon {
  font-size: 4rem;
  color: #00b050;
  margin-bottom: 1rem;
}

.demo-controls {
  display: flex;
  justify-content: center;
  gap: 1rem;
  padding: 1.5rem;
  background: #f8fafc;
  border-top: 1px solid #e5e7eb;
}

.demo-control {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid #e5e7eb;
  background: white;
  color: #666;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.demo-control.active,
.demo-control:hover {
  border-color: #00b050;
  background: #00b050;
  color: white;
}

/* Benefits Section */
.howitworks-benefits {
  padding: 4rem 1rem;
}

.howitworks-benefits__container {
  max-width: 1200px;
  margin: 0 auto;
  text-align: center;
}

.howitworks-benefits__title {
  font-size: 2.5rem;
  color: #222;
  margin-bottom: 3rem;
}

.howitworks-benefits__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 2rem;
}

.benefit-card {
  background: white;
  padding: 2rem;
  border-radius: 1rem;
  box-shadow: 0 4px 16px rgba(0,176,80,0.1);
  text-align: center;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.6s ease;
}

.benefit-card.animate-in {
  opacity: 1;
  transform: translateY(0);
}

.benefit-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(0,176,80,0.15);
}

.benefit-icon {
  margin-bottom: 1.5rem;
}

.benefit-card h3 {
  font-size: 1.5rem;
  color: #222;
  margin-bottom: 1rem;
}

.benefit-card p {
  color: #666;
  line-height: 1.6;
}

/* FAQ Section */
.howitworks-faq {
  background: #f8fafc;
  padding: 4rem 1rem;
}

.howitworks-faq__container {
  max-width: 800px;
  margin: 0 auto;
}

.howitworks-faq__title {
  font-size: 2.5rem;
  color: #222;
  text-align: center;
  margin-bottom: 3rem;
}

.faq-list {
  space-y: 1rem;
}

.faq-item {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 2px 8px rgba(0,176,80,0.08);
  margin-bottom: 1rem;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.6s ease;
}

.faq-item.animate-in {
  opacity: 1;
  transform: translateY(0);
}

.faq-question {
  width: 100%;
  padding: 1.5rem;
  background: none;
  border: none;
  text-align: left;
  font-size: 1.1rem;
  font-weight: 600;
  color: #222;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.faq-question:hover {
  color: #00b050;
}

.faq-toggle {
  font-size: 1.5rem;
  font-weight: 300;
  color: #00b050;
  transition: transform 0.2s ease;
}

.faq-item.open .faq-toggle {
  transform: rotate(45deg);
}

.faq-answer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
}

.faq-item.open .faq-answer {
  max-height: 200px;
}

.faq-answer p {
  padding: 0 1.5rem 1.5rem;
  color: #666;
  line-height: 1.6;
  margin: 0;
}

/* Testimonials Section */
.howitworks-testimonials {
  padding: 4rem 1rem;
}

.howitworks-testimonials__container {
  max-width: 800px;
  margin: 0 auto;
  text-align: center;
}

.howitworks-testimonials__title {
  font-size: 2.5rem;
  color: #222;
  margin-bottom: 3rem;
}

.testimonials-slider {
  position: relative;
}

.testimonial-track {
  position: relative;
  min-height: 200px;
}

.testimonial-slide {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  opacity: 0;
  transform: translateX(20px);
  transition: all 0.5s ease;
}

.testimonial-slide.active {
  opacity: 1;
  transform: translateX(0);
}

.testimonial {
  background: white;
  padding: 2rem;
  border-radius: 1rem;
  box-shadow: 0 4px 16px rgba(0,176,80,0.1);
}

.testimonial__text {
  font-size: 1.2rem;
  color: #333;
  font-style: italic;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.testimonial__author {
  color: #666;
  font-weight: 600;
  display: block;
  margin-bottom: 0.5rem;
}

.testimonial__rating {
  color: #fbbf24;
  font-size: 1.2rem;
}

.testimonial-controls {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}

.testimonial-prev,
.testimonial-next {
  background: #00b050;
  color: white;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  font-size: 1.2rem;
  cursor: pointer;
  transition: background 0.2s ease;
}

.testimonial-prev:hover,
.testimonial-next:hover {
  background: #009140;
}

.testimonial-dots {
  display: flex;
  gap: 0.5rem;
}

.testimonial-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: none;
  background: #e5e7eb;
  cursor: pointer;
  transition: background 0.2s ease;
}

.testimonial-dot.active {
  background: #00b050;
}

/* Final CTA Section */
.howitworks-final-cta {
  background: linear-gradient(120deg, #00b050 0%, #009140 100%);
  color: white;
  padding: 4rem 1rem;
  text-align: center;
}

.howitworks-final-cta__container {
  max-width: 800px;
  margin: 0 auto;
}

.howitworks-final-cta h2 {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.howitworks-final-cta p {
  font-size: 1.2rem;
  margin-bottom: 2rem;
  opacity: 0.9;
}

.cta-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 3rem;
  flex-wrap: wrap;
}

.cta-primary,
.cta-secondary {
  padding: 1rem 2rem;
  border-radius: 0.5rem;
  text-decoration: none;
  font-weight: 600;
  font-size: 1.1rem;
  transition: all 0.2s ease;
}

.cta-primary {
  background: white;
  color: #00b050;
}

.cta-primary:hover {
  background: #f8fafc;
  transform: translateY(-2px);
}

.cta-secondary {
  background: transparent;
  color: white;
  border: 2px solid white;
}

.cta-secondary:hover {
  background: white;
  color: #00b050;
}

.trust-indicators {
  display: flex;
  justify-content: center;
  gap: 3rem;
  flex-wrap: wrap;
}

.trust-item {
  text-align: center;
}

.trust-item strong {
  display: block;
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.trust-item span {
  opacity: 0.9;
  font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .howitworks-demo__title,
  .howitworks-benefits__title,
  .howitworks-faq__title,
  .howitworks-testimonials__title {
    font-size: 2rem;
  }
  
  .howitworks-final-cta h2 {
    font-size: 2rem;
  }
  
  .demo-screen {
    min-height: 350px;
  }
  
  .demo-step {
    padding: 2rem 1rem;
  }
  
  .howitworks-benefits__grid {
    grid-template-columns: 1fr;
  }
  
  .cta-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .trust-indicators {
    gap: 2rem;
  }
  
  .trust-item strong {
    font-size: 1.5rem;
  }
}

/* Animation delays for staggered effect */
.howitworks-steps__item:nth-child(1) { transition-delay: 0.1s; }
.howitworks-steps__item:nth-child(2) { transition-delay: 0.2s; }
.howitworks-steps__item:nth-child(3) { transition-delay: 0.3s; }
.howitworks-steps__item:nth-child(4) { transition-delay: 0.4s; }

.benefit-card:nth-child(1) { transition-delay: 0.1s; }
.benefit-card:nth-child(2) { transition-delay: 0.2s; }
.benefit-card:nth-child(3) { transition-delay: 0.3s; }
.benefit-card:nth-child(4) { transition-delay: 0.4s; }

.faq-item:nth-child(1) { transition-delay: 0.1s; }
.faq-item:nth-child(2) { transition-delay: 0.2s; }
.faq-item:nth-child(3) { transition-delay: 0.3s; }
.faq-item:nth-child(4) { transition-delay: 0.4s; }
.faq-item:nth-child(5) { transition-delay: 0.5s; }
.faq-item:nth-child(6) { transition-delay: 0.6s; }
</style>