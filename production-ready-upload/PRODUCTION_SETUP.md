# 🚀 HTTPS Production Setup Guide

## Server Configuration

### 1. Domain & SSL Certificate
```apache
# Ensure SSL is properly configured in your Apache virtual host:
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /path/to/your/project
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/ca_bundle.crt
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

### 2. File Permissions
```bash
# Set correct permissions for security
chmod 644 *.php
chmod 644 .htaccess
chmod 600 project.env
chmod -R 755 assets/
chmod -R 750 config/
chmod -R 750 core/
chmod -R 700 logs/ (if on server)
```

### 3. Environment Configuration
Copy `project.env` and verify these settings:
```env
APP_ENV=production
APP_DEBUG=false
GIBSON_DEVELOPMENT_MODE=false
FORCE_HTTPS=true
```

## Testing Checklist

### A. Basic Functionality ✅
- [ ] Homepage loads over HTTPS
- [ ] All CSS/JS assets load correctly
- [ ] Forms submit without errors
- [ ] Database connections work

### B. Security Tests ✅  
- [ ] HTTP automatically redirects to HTTPS
- [ ] .env files are not accessible via browser
- [ ] /config/ directory is protected
- [ ] /logs/ directory is protected
- [ ] Admin areas require authentication

### C. Performance Tests ✅
- [ ] Page load times under 3 seconds
- [ ] GZIP compression working
- [ ] Static assets cached properly
- [ ] Database queries optimized

### D. Admin Panel Tests ✅
- [ ] `/admin/login` works over HTTPS
- [ ] Dashboard loads analytics
- [ ] User management functions
- [ ] Payment system integration

## Post-Deployment Monitoring

### 1. Error Monitoring
Check these logs regularly:
- Apache error logs
- PHP error logs  
- Application logs (if configured)

### 2. SSL Certificate Monitoring
- Monitor certificate expiration
- Verify SSL ratings with tools like SSL Labs

### 3. Performance Monitoring
- Monitor page load speeds
- Database query performance
- Server resource usage

## Troubleshooting Common Issues

### Mixed Content Warnings
If you see mixed content warnings:
1. Ensure all assets use HTTPS URLs
2. Check serve-asset.php for HTTP references
3. Verify database URLs are HTTPS

### Admin Access Issues
If admin login fails:
1. Check Gibson AI configuration
2. Verify database connectivity
3. Check session configuration

### Performance Issues
If site is slow:
1. Enable GZIP compression
2. Optimize database queries
3. Consider CDN for static assets 