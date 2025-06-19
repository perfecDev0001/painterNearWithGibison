# Quote Wizard Debug Analysis

## Issue Summary
The quote wizard works perfectly on localhost but fails on the live server (painter-near-me.co.uk), showing "Service Temporarily Unavailable" only on the homepage. All other pages work correctly.

## Root Cause Analysis

Based on code examination and server logs, the most likely failure points are:

### 1. **Session Permission Issues** (Most Likely)
```php
// In bootstrap.php lines 89-94
if (!$isDevelopment) {
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
```

**Problem**: Live server may not have proper session directory permissions or the session configuration conflicts with hosting provider settings.

**Evidence**: 
- Local server: Uses default session handling (works)
- Live server: Enforces strict session security (fails)

### 2. **Gibson AI API Timeouts** (Secondary)
```php
// In GibsonDataAccess.php - createLead() method
$result = $this->gibson->createLead($leadData);
```

**Problem**: Gibson AI API calls may timeout or fail on live server due to network restrictions.

**Evidence from logs**:
```
[GibsonAI] Response Code: 400
[GibsonAI] Response: {"detail":"Entity painter_bid does not exist"}
[GibsonAI] Client Error: GET https://api.gibsonai.com/v1/-/painter-bid - HTTP 400
```

### 3. **File Path Issues** (Less Likely)
```php
// In Wizard.php
$stepFile = __DIR__ . '/../steps/Step' . ($this->currentStep + 1) . '_' . $this->steps[$this->currentStep]['id'] . '.php';
```

**Problem**: File paths may resolve differently on live server.

### 4. **Memory/Execution Limits** (Possible)
```php
// In bootstrap.php
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
```

**Problem**: Live server may have stricter limits or override these settings.

## Debugging Steps

### Step 1: Create Session-Safe Version
Create `index-session-safe.php` with minimal session usage:

```php
<?php
require_once 'bootstrap.php';

// Skip session security in production for debugging
if (ENVIRONMENT === 'production') {
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_httponly', 0);
    ini_set('session.use_strict_mode', 0);
}

// Simple session start without security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rest of index.php code...
```

### Step 2: Create Gibson-Free Version
Create `index-no-gibson.php` that skips Gibson AI entirely:

```php
<?php
// Modify core/Wizard.php showSummary() method to skip Gibson calls
// Use only local storage and email notifications
```

### Step 3: Create Debug Version
Test with the debug homepage file to capture exact error.

## Quick Fixes (In Priority Order)

### Fix 1: Session Directory Permissions
```bash
# On live server, ensure session directory is writable
chmod 755 /tmp
# Or create custom session directory
mkdir -p /var/www/sessions
chmod 755 /var/www/sessions
```

### Fix 2: Disable Session Security Temporarily
```php
// In bootstrap.php, comment out lines 89-94:
/*
if (!$isDevelopment) {
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
*/
```

### Fix 3: Add Gibson Timeout Protection
```php
// In core/GibsonDataAccess.php, modify createLead():
public function createLead($leadData) {
    try {
        // Set timeout
        ini_set('default_socket_timeout', 5);
        
        $result = $this->gibson->createLead($leadData);
        
        if ($result['success']) {
            return $result;
        }
    } catch (Exception $e) {
        error_log("Gibson API failed, using local fallback: " . $e->getMessage());
    }
    
    // Always fall back to local storage
    return $this->createLocalLead($leadData);
}
```

## Testing Strategy

1. **Test the debug homepage**: Upload `debug-homepage.php` and access it
2. **Check log files**: Look for `debug.log` and `debug-errors.log`
3. **Test session-safe version**: Try with relaxed session settings
4. **Test Gibson-free version**: Skip all external API calls

## Expected Outcome

The most likely fix is **session permissions/configuration**. The debug file should show exactly where the failure occurs and provide specific error messages for targeted fixes. 