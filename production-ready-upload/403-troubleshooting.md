# 🚨 HTTP 403 Error Troubleshooting Guide

## Immediate Checks

### 1. Test Basic HTML First
- Upload `test.html` to your server
- Try accessing: `https://painter-near-me.co.uk/test.html`
- If this fails → server/permissions issue
- If this works → .htaccess or PHP issue

### 2. Check Directory Index
Your server might be missing a directory index. Ensure you have:
- `index.php` in the root directory
- OR `index.html` as a fallback

### 3. File Permissions (Most Common)
```bash
# Set correct permissions:
chmod 644 *.php *.html
chmod 644 .htaccess
chmod 755 . (current directory)
chmod 755 assets/ config/ core/ templates/
chmod 600 project.env
```

### 4. .htaccess Issues
**Replace with simplified version first:**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
```

## Server-Specific Issues

### cPanel/Shared Hosting
- Document root should be `public_html/`
- Check if files are in the right directory
- Some hosts require `index.php` to exist

### Apache Configuration
- Ensure `mod_rewrite` is enabled
- Check if `AllowOverride All` is set
- Verify `Options Indexes` if needed

### PHP Issues
- Ensure PHP version is 8.1+
- Check if PHP is enabled for your domain
- Verify `index.php` has correct syntax

## Step-by-Step Debugging

### Step 1: Minimal Setup
1. Upload ONLY `test.html` first
2. Test: Does it load?
3. If yes → proceed to Step 2
4. If no → contact hosting support

### Step 2: Add Index
1. Upload `index.php`
2. Test: `https://painter-near-me.co.uk/`
3. If error → check PHP/server logs

### Step 3: Add Basic .htaccess
1. Upload simplified `.htaccess`
2. Test again
3. If works → gradually add features

### Step 4: Add Full Application
1. Upload remaining files
2. Test each major section
3. Add complex .htaccess rules gradually

## Quick Fixes

### Fix 1: No .htaccess
Remove `.htaccess` entirely and test direct PHP access:
- `https://painter-near-me.co.uk/index.php`

### Fix 2: Wrong Document Root
Ensure files are in the correct directory:
- cPanel: `public_html/`
- Apache: `/var/www/html/`
- Nginx: `/usr/share/nginx/html/`

### Fix 3: Missing Index
Create a simple `index.html`:
```html
<!DOCTYPE html>
<html><body><h1>Working!</h1></body></html>
```

## Emergency Contacts
- Check your hosting provider's documentation
- Access cPanel → Error Logs
- Contact hosting support with specific error details

## Success Indicators
✅ `test.html` loads → Server working
✅ `index.php` loads → PHP working  
✅ Clean URLs work → .htaccess working
✅ Admin panel accessible → Full app working 