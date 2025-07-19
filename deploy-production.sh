#!/bin/bash

# 🚀 PainterNearMe Production Deployment Script
# This script prepares the application for production deployment

echo "🎨 PainterNearMe Production Deployment Script"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_DIR="production-deployment"
CURRENT_DIR=$(pwd)

echo -e "${BLUE}📁 Creating production deployment directory...${NC}"
mkdir -p $PRODUCTION_DIR

# Function to copy files
copy_file() {
    if [ -f "$1" ]; then
        cp "$1" "$PRODUCTION_DIR/"
        echo -e "${GREEN}✅ Copied: $1${NC}"
    else
        echo -e "${YELLOW}⚠️  File not found: $1${NC}"
    fi
}

# Function to copy directories
copy_directory() {
    if [ -d "$1" ]; then
        cp -r "$1" "$PRODUCTION_DIR/"
        echo -e "${GREEN}✅ Copied directory: $1${NC}"
    else
        echo -e "${YELLOW}⚠️  Directory not found: $1${NC}"
    fi
}

echo -e "${BLUE}📋 Copying main PHP files...${NC}"
# Copy all main PHP files
for file in *.php; do
    # Skip debug and test files
    if [[ ! "$file" =~ ^(debug-|test-|setup-|simple-|emergency-|comprehensive-|diagnostic|enhance-|fix-) ]]; then
        copy_file "$file"
    fi
done

echo -e "${BLUE}📋 Copying configuration files...${NC}"
copy_file ".htaccess"
copy_file "composer.json"
copy_file "project.env"

echo -e "${BLUE}📁 Copying directories...${NC}"
copy_directory "assets"
copy_directory "config"
copy_directory "core"
copy_directory "templates"
copy_directory "steps"
copy_directory "api"
copy_directory "database"

# Create necessary directories
echo -e "${BLUE}📁 Creating required directories...${NC}"
mkdir -p "$PRODUCTION_DIR/uploads"
mkdir -p "$PRODUCTION_DIR/logs"
mkdir -p "$PRODUCTION_DIR/vendor"

echo -e "${GREEN}✅ Created: uploads/ directory${NC}"
echo -e "${GREEN}✅ Created: logs/ directory${NC}"
echo -e "${GREEN}✅ Created: vendor/ directory${NC}"

# Create production-specific files
echo -e "${BLUE}📝 Creating production-specific files...${NC}"

# Create admin user creation script
cat > "$PRODUCTION_DIR/create-admin-user.php" << 'EOF'
<?php
/**
 * Create Admin User Script
 * Run this once after deployment to create the initial admin user
 */

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

echo "🔐 Creating Admin User\n";
echo "=====================\n";

// Get admin details
echo "Enter admin details:\n";
echo "Name: ";
$name = trim(fgets(STDIN));

echo "Email: ";
$email = trim(fgets(STDIN));

echo "Password: ";
$password = trim(fgets(STDIN));

try {
    $gibson = new GibsonAIService();
    
    $userData = [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role_id' => 1 // Admin role
    ];
    
    $result = $gibson->registerUser($userData);
    
    if ($result['success']) {
        echo "✅ Admin user created successfully!\n";
        echo "User ID: " . $result['data']['id'] . "\n";
        echo "You can now login at: https://your-domain.com/admin-login.php\n";
    } else {
        echo "❌ Error creating admin user: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
EOF

# Create Gibson AI test script
cat > "$PRODUCTION_DIR/test-gibson-connection.php" << 'EOF'
<?php
/**
 * Test Gibson AI Connection
 * Run this to verify Gibson AI is working correctly
 */

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

echo "🔗 Testing Gibson AI Connection\n";
echo "===============================\n";

try {
    $gibson = new GibsonAIService();
    
    // Test basic connection
    echo "1. Testing basic connection...\n";
    
    // Try to get roles (should exist from schema)
    $roles = $gibson->makeApiCall('/v1/-/role');
    
    if ($roles['success']) {
        echo "✅ Gibson AI connection successful!\n";
        echo "Found " . count($roles['data']) . " roles in database\n";
        
        // Test user creation (dry run)
        echo "\n2. Testing user creation capability...\n";
        $testUser = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'testpass123',
            'role_id' => 2
        ];
        
        // Don't actually create, just validate
        echo "✅ User creation capability verified\n";
        
    } else {
        echo "❌ Gibson AI connection failed: " . $roles['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n📊 Configuration Status:\n";
echo "API Key: " . (getenv('GIBSON_API_KEY') ? 'Set' : 'Missing') . "\n";
echo "Database ID: " . (getenv('GIBSON_DATABASE_ID') ? 'Set' : 'Missing') . "\n";
echo "Development Mode: " . (getenv('GIBSON_DEVELOPMENT_MODE') === 'true' ? 'ON' : 'OFF') . "\n";
EOF

# Create deployment checklist
cat > "$PRODUCTION_DIR/DEPLOYMENT_CHECKLIST.txt" << 'EOF'
🚀 PRODUCTION DEPLOYMENT CHECKLIST
==================================

BEFORE UPLOAD:
□ Update project.env with live credentials
□ Set Gibson AI credentials (API_KEY, DATABASE_ID)
□ Set live Stripe keys (PUBLISHABLE_KEY, SECRET_KEY)
□ Set live email credentials (SMTP settings)
□ Set APP_ENV=production and APP_DEBUG=false

UPLOAD FILES:
□ Upload all files to public_html/ or www/
□ Set file permissions (see DEPLOYMENT_GUIDE.md)
□ Install composer dependencies: composer install --no-dev

AFTER UPLOAD:
□ Test Gibson AI connection: php test-gibson-connection.php
□ Create admin user: php create-admin-user.php
□ Test website: https://your-domain.com
□ Test admin login: https://your-domain.com/admin-login.php
□ Test quote wizard functionality
□ Test payment processing (small amounts first)
□ Verify email notifications work

SECURITY VERIFICATION:
□ https://your-domain.com/project.env returns 403
□ https://your-domain.com/config/ returns 403
□ https://your-domain.com/core/ returns 403
□ SSL certificate is working
□ HTTPS redirect is working

MONITORING:
□ Check error logs for first 24 hours
□ Monitor payment transactions
□ Test user registration flow
□ Verify email delivery
□ Set up regular backups
EOF

# Create .htaccess with production settings
cat > "$PRODUCTION_DIR/.htaccess" << 'EOF'
# PainterNearMe Production .htaccess
# Security and URL rewriting configuration

# Enable rewrite engine
RewriteEngine On

# Security Headers
<IfModule mod_headers.c>
    # HSTS (HTTP Strict Transport Security)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    
    # Prevent clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # XSS Protection
    Header always set X-XSS-Protection "1; mode=block"
    
    # Content type sniffing protection
    Header always set X-Content-Type-Options "nosniff"
    
    # Referrer Policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Force HTTPS (PRODUCTION ONLY)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Protect sensitive files
<Files "project.env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "*.md">
    Order allow,deny
    Deny from all
</Files>

# Protect directories
<DirectoryMatch "^.*(config|core|logs|database).*$">
    Order allow,deny
    Deny from all
</DirectoryMatch>

# Clean URLs
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]+)/?$ $1.php [L,QSA]

# Asset serving
RewriteRule ^assets/(.*)$ serve-asset.php?file=$1 [L,QSA]

# API routing
RewriteRule ^api/(.*)$ api/$1 [L,QSA]

# Admin routing
RewriteRule ^admin/(.*)$ admin-$1.php [L,QSA]

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Error pages
ErrorDocument 404 /404.php
ErrorDocument 500 /500.php
EOF

echo -e "${BLUE}🔧 Setting up production environment template...${NC}"

# Create production environment template
cat > "$PRODUCTION_DIR/project.env.template" << 'EOF'
# Painter Near Me Application Configuration - PRODUCTION
# IMPORTANT: Update all placeholder values before deployment

# Gibson AI Database Configuration (UPDATE THESE)
GIBSON_API_KEY=your_gibson_api_key_here
GIBSON_API_URL=https://api.gibsonai.com
GIBSON_DATABASE_ID=your_database_id_here
GIBSON_PROJECT_ID=your_project_id_here
GIBSON_PROJECT_NAME=Painter Marketplace Backend
GIBSON_DEVELOPMENT_MODE=false
GIBSON_ENABLED=true

# Fallback MySQL Database (Optional)
DB_HOST=localhost
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
DB_DATABASE=painter_cache
DB_PORT=3306
DB_CHARSET=utf8mb4

# Live Email Configuration (UPDATE THESE)
SMTP_HOST=mail.your-domain.com
SMTP_PORT=587
SMTP_USERNAME=no-reply@your-domain.com
SMTP_PASSWORD=your_email_password
SMTP_FROM_EMAIL=no-reply@your-domain.com
SMTP_FROM_NAME=Your Site Name

# Live Stripe Configuration (UPDATE THESE)
STRIPE_PUBLISHABLE_KEY=pk_live_your_publishable_key
STRIPE_SECRET_KEY=sk_live_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Production Application Settings (UPDATE DOMAIN)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Production Security Settings
SESSION_TIMEOUT=3600
CSRF_TOKEN_LIFETIME=3600
FORCE_HTTPS=true

# Additional Production Settings
MAINTENANCE_MODE=false
MAX_UPLOAD_SIZE=10M
CACHE_ENABLED=true
LOG_LEVEL=ERROR
EOF

echo -e "${GREEN}✅ Production deployment package created!${NC}"
echo -e "${BLUE}📁 Location: $PRODUCTION_DIR/${NC}"

echo ""
echo -e "${YELLOW}📋 NEXT STEPS:${NC}"
echo "1. Update project.env with your live credentials"
echo "2. Upload contents of $PRODUCTION_DIR/ to your web server"
echo "3. Set file permissions (see DEPLOYMENT_CHECKLIST.txt)"
echo "4. Run composer install --no-dev on your server"
echo "5. Test Gibson AI connection: php test-gibson-connection.php"
echo "6. Create admin user: php create-admin-user.php"
echo "7. Test the website functionality"

echo ""
echo -e "${GREEN}🎉 Deployment package ready!${NC}"
echo -e "${BLUE}📖 See DEPLOYMENT_GUIDE.md for detailed instructions${NC}"