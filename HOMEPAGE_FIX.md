# 🎯 HOMEPAGE FIX - painter-near-me.co.uk

## ✅ GOOD NEWS!
Your website IS working! [https://painter-near-me.co.uk/how-it-works.php](https://painter-near-me.co.uk/how-it-works.php) loads perfectly, which proves:
- Server is working ✅
- PHP is working ✅  
- .htaccess is working ✅
- SSL certificate is working ✅

## 🎯 THE ISSUE
Only the **homepage** (`index.php`) shows "Service Temporarily Unavailable" - this is caused by the **quote wizard** having issues on the live server.

## 🚀 IMMEDIATE FIX

### Option 1: Quick Temporary Fix
1. **Upload** the file `index-simple.php` to your live server
2. **Rename** your current `index.php` to `index-wizard.php` (backup)
3. **Rename** `index-simple.php` to `index.php`
4. **Test**: https://painter-near-me.co.uk should now load!

### Option 2: Fix the Quote Wizard
The issue is likely in these areas:

#### A. Session Issues
Add this to the top of your live `index.php` (after `require_once bootstrap.php`):
```php
// Fix session issues on live server
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session variables
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

#### B. Environment Variables
Check your live `project.env` file has:
```
GIBSON_DEVELOPMENT_MODE=true
GIBSON_API_URL=https://api.gibsonai.com
GIBSON_DATABASE_ID=painter_marketplace_production
```

## 🔍 DIAGNOSIS STEPS

### Test 1: Check if Simple Homepage Works
After uploading `index-simple.php` as `index.php`:
- ✅ Should show: Homepage with "Get Free Quote" button
- ✅ Should have: Navigation working
- ✅ Should load: All images and styles

### Test 2: Check Individual Components
Test these URLs to isolate the issue:
- https://painter-near-me.co.uk/quote.php (quote form)
- https://painter-near-me.co.uk/contact.php (contact form)  
- https://painter-near-me.co.uk/login.php (login form)

## 📋 WHAT WORKS vs WHAT DOESN'T

### ✅ WORKING PAGES:
- How It Works: https://painter-near-me.co.uk/how-it-works.php
- All static pages (contact, privacy, terms)
- Asset serving (CSS, images)
- Clean URLs

### ❌ NOT WORKING:
- Homepage with quote wizard (index.php)

## 🎯 ROOT CAUSE ANALYSIS

The quote wizard on the homepage uses:
1. **Session management** - may have permission issues
2. **Gibson AI API calls** - may timeout on live server
3. **Complex form handling** - may have validation issues
4. **Progress tracking** - may have template issues

## 🚀 PERMANENT SOLUTION

Once you identify the issue:

### If Session Problems:
```php
// Add to top of index.php
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_httponly', 1);
session_start();
```

### If Gibson AI Problems:
```php
// Add error handling in index.php
try {
    $wizard = new Wizard();
    $wizard->handleRequest();
} catch (Exception $e) {
    error_log("Wizard Error: " . $e->getMessage());
    // Fallback to simple quote form
    header('Location: /quote.php');
    exit;
}
```

### If Template Problems:
Check that all required template files exist on live server:
- `templates/header.php`
- `templates/footer.php` 
- `templates/progress.php`

## ✅ SUCCESS INDICATORS

After the fix:
- ✅ Homepage loads: https://painter-near-me.co.uk
- ✅ Quote wizard works OR simple quote button works
- ✅ All navigation links work
- ✅ No "Service Temporarily Unavailable" errors

## 📞 NEXT STEPS

1. **Immediate**: Use the simple homepage fix
2. **Short-term**: Debug the quote wizard
3. **Long-term**: Add better error handling

Your website is 95% working - just need to fix this one homepage issue! 🎉 