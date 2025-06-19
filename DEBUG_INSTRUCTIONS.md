# 🔧 Quote Wizard Debug Instructions

## Problem Summary
Your quote wizard works perfectly locally but fails on `https://painter-near-me.co.uk` with "Service Temporarily Unavailable". All other pages work fine.

## 🎯 Testing Strategy

### Step 1: Test Session-Safe Version
Upload and test: `https://painter-near-me.co.uk/index-session-safe.php`

**What it tests**: Session security issues
**Expected result**: Should work if session permissions are the problem

### Step 2: Test Gibson-Free Version  
Upload and test: `https://painter-near-me.co.uk/index-no-gibson.php`

**What it tests**: Gibson AI API timeout/failure issues
**Expected result**: Should work if Gibson AI calls are causing timeouts

### Step 3: Test Debug Version
Upload and test: `https://painter-near-me.co.uk/debug-homepage.php`

**What it tests**: Exact failure point with detailed logging
**Expected result**: Will show specific error message and create debug logs

## 📁 Files to Upload

1. `index-session-safe.php` - Relaxed session security
2. `index-no-gibson.php` - No external API calls  
3. `debug-homepage.php` - Detailed error logging
4. `QUOTE_WIZARD_DEBUG.md` - Technical analysis

## 🔍 What to Look For

### If Session-Safe Version Works:
- **Root Cause**: Session directory permissions or security settings
- **Fix**: Modify `bootstrap.php` lines 89-94 (session security)
- **OR**: Check server session directory permissions

### If Gibson-Free Version Works:
- **Root Cause**: Gibson AI API timeouts or connection issues
- **Fix**: Add timeout protection in `core/GibsonDataAccess.php`
- **OR**: Use local fallback mode in production

### If Debug Version Shows Errors:
- Check the error logs it creates
- Look for specific PHP errors or permission issues
- Check file path problems or missing dependencies

## 🚀 Quick Fixes to Try

### Fix 1: Session Security (Most Likely)
Edit `bootstrap.php`, comment out lines 89-94:
```php
/*
if (!$isDevelopment) {
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
*/
```

### Fix 2: Gibson API Timeout Protection
Edit `core/GibsonDataAccess.php`, modify `createLead()` method:
```php
public function createLead($leadData) {
    try {
        ini_set('default_socket_timeout', 5);
        $result = $this->gibson->createLead($leadData);
        if ($result['success']) {
            return $result;
        }
    } catch (Exception $e) {
        error_log("Gibson API failed: " . $e->getMessage());
    }
    
    // Always use local fallback
    return $this->createLocalLead($leadData);
}
```

## 📊 Expected Results

**90% chance**: Session security is blocking the wizard
**8% chance**: Gibson AI timeouts are causing 500 errors  
**2% chance**: File permissions or path issues

## 🎉 Success Indicators

- **Session-safe version works**: Fix session security in bootstrap.php
- **Gibson-free version works**: Add API timeout protection
- **Debug version shows specific error**: Apply targeted fix
- **All versions fail**: Check basic PHP/server configuration

## 📞 Next Steps

1. Test the three debug files on your live server
2. Report which ones work/fail
3. Check any log files created
4. Apply the appropriate fix based on results

The debug files will pinpoint exactly what's causing the failure! 