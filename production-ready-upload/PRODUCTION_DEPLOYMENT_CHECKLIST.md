# 🚀 Production Deployment Checklist

## ✅ **Pre-Deployment Requirements**

### **1. Update Configuration Files**
- [ ] **Update `project.env`** with live server details:
  - [ ] Database credentials (DB_USERNAME, DB_PASSWORD, DB_DATABASE)
  - [ ] Live Stripe keys (replace test keys)
  - [ ] Real email password (SMTP_PASSWORD)
  - [ ] Set `APP_ENV=production`
  - [ ] Set `APP_DEBUG=false`
  - [ ] Set correct `APP_URL=https://painter-near-me.co.uk`

### **2. Database Setup**
- [ ] Create database on live server
- [ ] Import database schema: `config/gibson_schema.sql`
- [ ] Test database connection
- [ ] Create admin user account

### **3. File Permissions (Critical)**
```bash
# Set on your live server:
chmod 644 *.php
chmod 644 *.html
chmod 644 .htaccess
chmod 600 project.env
chmod 755 assets/
chmod 755 config/
chmod 755 core/
chmod 755 templates/
chmod 755 vendor/
chmod 777 uploads/
chmod 777 logs/
```

### **4. SSL Certificate**
- [ ] Install SSL certificate on your domain
- [ ] Test HTTPS access: `https://painter-near-me.co.uk`
- [ ] Uncomment HTTPS redirect in `.htaccess` (lines 110-111)
- [ ] Uncomment HSTS header in `.htaccess` (line 78)

### **5. Email Configuration**
- [ ] Set up email account: `no-reply@painter-near-me.co.uk`
- [ ] Test email sending functionality
- [ ] Verify SMTP settings work

### **6. Payment System**
- [ ] Get live Stripe API keys
- [ ] Update Stripe configuration
- [ ] Test payment processing
- [ ] Set up webhooks

## 🚫 **Files/Folders NOT to Upload**

### **Development Files (exclude these):**
- [ ] `test-*.php` files
- [ ] `debug-*.php` files
- [ ] `setup-*.php` files
- [ ] `simple-gibson-test.php`
- [ ] `.htaccess.backup`
- [ ] `*.md` documentation files (optional)

## 📁 **Upload Structure**

### **Upload to your hosting public_html/ or www/ directory:**
```
public_html/
├── admin-*.php
├── *.php (main files)
├── .htaccess
├── project.env
├── assets/
├── config/
├── core/
├── database/
├── templates/
├── vendor/
├── uploads/ (create with 777 permissions)
└── logs/ (create with 777 permissions)
```

## ⚠️ **Security Checklist**

- [ ] `project.env` file is protected (should return 403)
- [ ] `/config/` directory is not accessible via browser
- [ ] `/core/` directory is not accessible via browser
- [ ] `/vendor/` directory is not accessible via browser
- [ ] Test file upload restrictions work
- [ ] Verify error pages work (404.php, 500.php)

## 🧪 **Post-Deployment Testing**

### **1. Basic Functionality**
- [ ] Homepage loads: `https://painter-near-me.co.uk`
- [ ] Admin login: `https://painter-near-me.co.uk/admin/login`
- [ ] Clean URLs work: `https://painter-near-me.co.uk/how-it-works`
- [ ] Assets load properly: CSS, JS, images

### **2. Security Tests**
- [ ] Test protected files return 403:
  - `https://painter-near-me.co.uk/project.env`
  - `https://painter-near-me.co.uk/config/`
  - `https://painter-near-me.co.uk/core/`

### **3. Functionality Tests**
- [ ] User registration works
- [ ] Admin login works
- [ ] Database operations work
- [ ] Email notifications work
- [ ] Payment processing works (with test amounts)

## 🚨 **If Something Goes Wrong**

### **Common Issues & Solutions:**

1. **500 Internal Server Error**
   - Check file permissions
   - Check .htaccess syntax
   - Check PHP error logs

2. **Database Connection Error**
   - Verify database credentials in `project.env`
   - Check database server is running
   - Verify database exists

3. **403 Forbidden Errors**
   - Check file permissions
   - Verify .htaccess isn't too restrictive
   - Check directory permissions

4. **SSL/HTTPS Issues**
   - Verify SSL certificate is installed
   - Check mixed content warnings
   - Update all HTTP URLs to HTTPS

## 📞 **Emergency Rollback**

If deployment fails:
1. **Backup current files** before deployment
2. **Keep development version** available
3. **Have hosting support contact** ready
4. **Document any custom server settings**

## 🎯 **Final Verification**

Before going live:
- [ ] All forms submit correctly
- [ ] Payment system processes test transactions
- [ ] Email notifications are received
- [ ] Admin panel accessible and functional
- [ ] Mobile responsiveness works
- [ ] Page loading speeds are acceptable
- [ ] SEO meta tags are in place

## 📝 **Post-Go-Live**

- [ ] Monitor error logs for first 24 hours
- [ ] Test user registration flow
- [ ] Monitor payment transactions
- [ ] Check email delivery rates
- [ ] Set up regular backups
- [ ] Monitor website uptime

---

**⚠️ IMPORTANT**: Always test on a staging environment first if possible! 