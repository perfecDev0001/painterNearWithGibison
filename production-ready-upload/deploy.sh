#!/bin/bash
# 🚀 Painter Near Me - Production Deployment Script
# Run this script to prepare files for production upload

echo "🚀 Preparing Painter Near Me for Production Deployment"
echo "====================================================="

# Create deployment directory
mkdir -p deployment

# Copy essential files
echo "📁 Copying core files..."
cp -r *.php deployment/
cp .htaccess deployment/
cp project.env deployment/

# Copy directories
echo "📁 Copying directories..."
cp -r api deployment/
cp -r assets deployment/
cp -r config deployment/
cp -r core deployment/
cp -r database deployment/
cp -r templates deployment/
cp -r vendor deployment/

# Remove development/test files
echo "🧹 Removing development files..."
rm -f deployment/test-*.php
rm -f deployment/debug-*.php
rm -f deployment/simple-gibson-test.php
rm -f deployment/create-test-admin.php

# Set basic permissions
echo "🔒 Setting file permissions..."
chmod 644 deployment/*.php
chmod 644 deployment/.htaccess
chmod 600 deployment/project.env

# Create upload instructions
cat > deployment/UPLOAD_INSTRUCTIONS.txt << 'EOF'
📋 UPLOAD INSTRUCTIONS
===================

1. Upload ALL files and folders to your web server's public_html directory
2. Ensure your domain has a valid SSL certificate
3. Test the URLs listed in PRODUCTION_SETUP.md
4. Monitor error logs for any issues

🔒 SECURITY CHECKLIST:
- Verify .htaccess is uploaded and working
- Test that /config/ and /logs/ are not accessible
- Ensure project.env is not downloadable via browser
- Confirm HTTP redirects to HTTPS

📞 SUPPORT:
If you encounter any issues, check the error logs and verify:
- PHP version 8.1+
- Apache mod_rewrite enabled
- Database connectivity
- SSL certificate validity
EOF

echo ""
echo "✅ Deployment package ready in ./deployment/ directory"
echo "📋 Next steps:"
echo "   1. Upload ./deployment/* to your web server"
echo "   2. Follow PRODUCTION_SETUP.md guide"
echo "   3. Test all URLs over HTTPS"
echo ""
echo "🎉 Your Painter Near Me platform is ready for production!" 