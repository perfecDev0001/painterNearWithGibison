# 🚀 Complete Deployment Guide: PainterNearMe E-commerce Platform

## 📋 **Overview**
This guide will help you deploy the PainterNearMe marketplace platform from development to production with Gibson AI database integration.

## 🎯 **What We're Deploying**
- **Marketplace Platform**: Connects customers with painters
- **Payment System**: Stripe integration for lead purchases
- **Gibson AI Database**: Primary data storage
- **Admin Dashboard**: Complete management system
- **Multi-user System**: Customer, Painter, Admin roles

---

## 📦 **Phase 1: Gibson AI Setup**

### **Step 1: Create Gibson AI Account**
1. Go to [Gibson AI Console](https://console.gibsonai.com)
2. Create new account or login
3. Create new project: "Painter Marketplace"
4. Note down your credentials:
   - API Key
   - Database ID
   - Project ID

### **Step 2: Initialize Gibson AI Database**
```bash
# Upload the database schema to Gibson AI
# Use the schema file: config/gibson_schema.sql
```

**Required Tables:**
- `user` - User accounts (admin, painter, customer)
- `role` - User roles
- `painter_profile` - Painter business details
- `job_lead` - Customer job requests
- `stripe_payment` - Payment processing
- `lead_claim` - Painter lead purchases
- `notification` - System notifications

---

## 🔧 **Phase 2: Environment Configuration**

### **Step 1: Update project.env**
```env
# Gibson AI Configuration (UPDATE THESE)
GIBSON_API_KEY=your_actual_gibson_api_key_here
GIBSON_API_URL=https://api.gibsonai.com
GIBSON_DATABASE_ID=your_database_id_here
GIBSON_PROJECT_ID=your_project_id_here
GIBSON_PROJECT_NAME=Painter Marketplace Backend
GIBSON_DEVELOPMENT_MODE=false
GIBSON_ENABLED=true

# Production Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
FORCE_HTTPS=true

# Database Fallback (Optional)
DB_HOST=localhost
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_DATABASE=painter_cache
DB_PORT=3306

# Email Configuration (UPDATE THESE)
SMTP_HOST=mail.your-domain.com
SMTP_PORT=587
SMTP_USERNAME=no-reply@your-domain.com
SMTP_PASSWORD=your_email_password
SMTP_FROM_EMAIL=no-reply@your-domain.com
SMTP_FROM_NAME=Your Site Name

# Stripe Configuration (UPDATE THESE)
STRIPE_PUBLISHABLE_KEY=pk_live_your_live_publishable_key
STRIPE_SECRET_KEY=sk_live_your_live_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Security Settings
SESSION_TIMEOUT=3600
CSRF_TOKEN_LIFETIME=3600
MAINTENANCE_MODE=false
MAX_UPLOAD_SIZE=10M
CACHE_ENABLED=true
LOG_LEVEL=ERROR
```

---

## 📁 **Phase 3: File Preparation**

### **Files to Upload to Production:**
```
public_html/
├── *.php (all main PHP files)
├── .htaccess
├── project.env
├── composer.json
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
├── core/
├── templates/
├── steps/
├── api/
├── uploads/ (create with 777 permissions)
└── logs/ (create with 777 permissions)
```

### **Files NOT to Upload:**
- `test-*.php`
- `debug-*.php`
- `setup-*.php`
- `*.md` files
- Development configuration files

---

## 🔐 **Phase 4: Security Setup**

### **Step 1: File Permissions**
```bash
# Set on your production server:
chmod 644 *.php
chmod 644 *.html
chmod 644 .htaccess
chmod 600 project.env
chmod 755 assets/
chmod 755 config/
chmod 755 core/
chmod 755 templates/
chmod 777 uploads/
chmod 777 logs/
```

### **Step 2: .htaccess Security**
Ensure your .htaccess includes:
```apache
# Protect sensitive files
<Files "project.env">
    Order allow,deny
    Deny from all
</Files>

<DirectoryMatch "^.*(config|core|logs).*$">
    Order allow,deny
    Deny from all
</DirectoryMatch>

# Force HTTPS (uncomment for production)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 💳 **Phase 5: Payment System Setup**

### **Step 1: Stripe Configuration**
1. Login to [Stripe Dashboard](https://dashboard.stripe.com)
2. Switch to Live mode
3. Get your Live API keys:
   - Publishable key (pk_live_...)
   - Secret key (sk_live_...)
4. Set up webhooks:
   - Endpoint: `https://your-domain.com/api/stripe-webhook.php`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`

### **Step 2: Test Payment Flow**
```bash
# Test with small amounts first
# Verify webhook delivery
# Check payment notifications
```

---

## 🗄️ **Phase 6: Database Initialization**

### **Step 1: Install Dependencies**
```bash
# On your server, run:
composer install --no-dev --optimize-autoloader
```

### **Step 2: Initialize Admin Account**
```php
# Run this script once to create admin user:
php create-admin-user.php
```

---

## 🧪 **Phase 7: Testing & Validation**

### **Critical Tests:**
1. **Homepage**: `https://your-domain.com`
2. **Admin Login**: `https://your-domain.com/admin-login.php`
3. **Quote Wizard**: Test full customer flow
4. **Payment Processing**: Test with Stripe test mode first
5. **Email Notifications**: Verify SMTP works
6. **Gibson AI**: Test data creation/retrieval

### **Security Tests:**
- `https://your-domain.com/project.env` → Should return 403
- `https://your-domain.com/config/` → Should return 403
- `https://your-domain.com/core/` → Should return 403

---

## 🚨 **Phase 8: Go-Live Checklist**

### **Before Going Live:**
- [ ] Gibson AI database is populated with initial data
- [ ] Stripe is in Live mode with real keys
- [ ] Email notifications are working
- [ ] SSL certificate is installed and working
- [ ] All forms submit successfully
- [ ] Admin panel is accessible
- [ ] Payment flow works end-to-end
- [ ] Error pages (404, 500) are working

### **After Going Live:**
- [ ] Monitor error logs for 24 hours
- [ ] Test user registration flow
- [ ] Monitor payment transactions
- [ ] Check email delivery rates
- [ ] Set up regular backups
- [ ] Monitor website uptime

---

## 🔧 **Troubleshooting Common Issues**

### **Gibson AI Connection Issues:**
```php
// Check Gibson AI status
php gibson-mcp-status.php
```

### **Payment Issues:**
- Verify Stripe webhook endpoints
- Check webhook secret matches
- Monitor Stripe dashboard for failed payments

### **Email Issues:**
- Test SMTP settings
- Check spam folders
- Verify DNS records (SPF, DKIM)

### **Session Issues:**
- Check session directory permissions
- Verify session security settings
- Test with different browsers

---

## 📞 **Support & Maintenance**

### **Regular Maintenance:**
- Monitor Gibson AI usage/limits
- Update Stripe webhook endpoints if needed
- Check SSL certificate expiration
- Monitor server resources
- Regular security updates

### **Emergency Contacts:**
- Hosting provider support
- Gibson AI support
- Stripe support
- Domain registrar support

---

## 🎉 **Success Indicators**

✅ **Website loads over HTTPS**  
✅ **Admin panel accessible**  
✅ **Customer can submit quotes**  
✅ **Painters can register and pay for leads**  
✅ **Email notifications working**  
✅ **Payment processing successful**  
✅ **Gibson AI database operational**  

---

*This deployment guide ensures a complete, secure, and functional e-commerce marketplace deployment with Gibson AI integration.*