# Painter Near Me - Marketplace Platform

A comprehensive painter marketplace platform connecting customers with local painting professionals. Features robust database management, authentication systems, payment processing, and administrative tools.

## 🎯 Current Status: Database & Authentication Working

This codebase represents a fully functional painter marketplace with:
- ✅ Complete database schema and migrations
- ✅ User authentication system (customers, painters, admins)
- ✅ Admin panel with analytics and management tools
- ✅ Payment integration with Stripe
- ✅ Lead management and bidding system
- ✅ Messaging system between customers and painters

## 🏗️ Architecture

### Backend Technologies
- **PHP 8.1+** - Core application logic
- **MySQL/MariaDB** - Primary database
- **Gibson AI Service** - Enhanced data processing and AI capabilities
- **Stripe API** - Payment processing
- **PHPMailer** - Email notifications

### Frontend Technologies
- **HTML5** with semantic markup
- **CSS3** with BEM methodology
- **Vanilla JavaScript** - Interactive components
- **Chart.js** - Analytics visualization
- **Bootstrap Icons** - UI iconography

## 📁 Project Structure

```
├── admin-*.php              # Admin panel pages
├── api/                     # API endpoints
├── assets/                  # CSS, images, and static files
├── config/                  # Configuration files
├── core/                    # Core PHP classes and services
├── database/                # Database schemas and migrations
├── templates/               # Reusable template components
├── vendor/                  # Composer dependencies
└── *.php                   # Public-facing pages
```

## 🚀 Getting Started

### Prerequisites
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/painter-near-me.git
   cd painter-near-me
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp project.env.example project.env
   # Edit project.env with your database and API credentials
   ```

4. **Set up database**
   ```bash
   # Import the database schema
   mysql -u username -p database_name < config/gibson_schema.sql
   ```

5. **Configure web server**
   - Point document root to project directory
   - Ensure mod_rewrite is enabled (Apache)
   - Configure appropriate permissions

## 🔐 Authentication System

### User Types
- **Customers** - Post painting jobs and receive bids
- **Painters** - Browse leads and submit bids  
- **Admins** - Manage platform and view analytics

### Security Features
- Password hashing with PHP's password_hash()
- Session management with timeout
- CSRF protection
- Input sanitization and validation
- Role-based access control

## 💾 Database Schema

The database includes tables for:
- User management (`user`, `role`, `user_session`)
- Painter profiles (`painter_profile`, `painter_portfolio_image`)
- Service management (`service_category`, `service_type`, `service`)
- Lead management (`job_lead`, `job_lead_address`, `lead_claim`)
- Payment processing (`stripe_payment`, `payment_method`)
- Messaging system (`notification`, `notification_recipient`)

## 🎨 Features

### For Customers
- Multi-step quote request form
- Lead posting and management
- Bid comparison and painter selection
- Secure payment processing
- Project communication tools

### For Painters
- Lead browsing and claiming
- Bid submission and management
- Portfolio management
- Payment method setup
- Performance analytics

### For Admins
- Comprehensive analytics dashboard
- User and lead management
- Payment oversight
- System monitoring
- Quality control tools

## 📊 Admin Analytics

The admin panel includes:
- Revenue analytics with visual charts
- Lead conversion funnel analysis
- Geographic distribution mapping
- Painter performance metrics
- System health monitoring

## 🔧 Configuration

### Environment Variables
Key configuration options in `project.env`:
- Database connection settings
- Gibson AI API credentials
- Stripe API keys
- Email server configuration
- System maintenance flags

### Gibson AI Integration
The platform integrates with Gibson AI for:
- Enhanced data processing
- User authentication
- Lead management
- Analytics generation

## 🧪 Testing

The project includes comprehensive test files:
- `test-authentication-system.php` - Full auth system testing
- `test-gibson-*.php` - Gibson AI integration tests
- `admin-system-test.php` - System health checks

Run tests via:
```bash
php test-authentication-system.php
php admin-system-test.php
```

## 📝 API Documentation

### Core Services
- `GibsonAuth` - Authentication management
- `GibsonDataAccess` - Database operations
- `StripePaymentManager` - Payment processing
- `EmailNotificationService` - Email communications

### REST Endpoints
- `/api/payment-api.php` - Payment operations
- `/webhook.php` - External webhook handler

## 🔄 Development Workflow

1. **Local Development**
   - Use `bootstrap.php` for environment setup
   - Enable debug mode in development
   - Monitor logs in `logs/` directory

2. **Code Standards**
   - Follow BEM methodology for CSS
   - Use semantic HTML5 elements
   - Implement proper WCAG accessibility
   - PHP PSR-12 coding standards

3. **Deployment**
   - Use `setup-production-database.php` for production setup
   - Configure proper file permissions
   - Enable HTTPS and security headers

## 🛡️ Security Considerations

- Environment files are excluded from version control
- Database credentials are stored securely
- CSRF tokens protect form submissions
- Input validation prevents SQL injection
- File upload restrictions prevent malicious uploads

## 📞 Support

For technical support or questions:
- Check the admin system monitor for health status
- Review error logs in the `logs/` directory
- Use the built-in debugging tools

## 📄 License

This project is proprietary software. All rights reserved.

## 🤝 Contributing

This is a private project. For authorized contributors:
1. Follow the established coding standards
2. Test all changes thoroughly
3. Update documentation as needed
4. Ensure backward compatibility

---

**Note**: This codebase represents a production-ready painter marketplace platform with robust authentication, database management, and payment processing capabilities. 