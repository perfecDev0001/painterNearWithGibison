# Multi-Role User Registration System

## Overview

The Painter Near Me platform now supports a comprehensive multi-role user registration system that allows different types of users to register and access appropriate features based on their role.

## Supported User Types

### 1. **Customers** (Role ID: 4)
- **Purpose**: Individuals looking for painting services
- **Registration**: `register-customer.php`
- **Dashboard**: `customer-dashboard.php`
- **Features**:
  - Post painting projects
  - Receive quotes from painters
  - Manage project communications
  - Leave reviews and ratings

### 2. **Painters** (Role ID: 3)
- **Purpose**: Professional painting companies/contractors
- **Registration**: `register.php` (existing)
- **Dashboard**: `dashboard.php`
- **Features**:
  - View available leads
  - Submit bids on projects
  - Manage customer communications
  - Upload insurance and ID documents

### 3. **Vendors** (Role ID: 5)
- **Purpose**: Businesses selling painting supplies and materials
- **Registration**: `register-vendor.php`
- **Dashboard**: `vendor-dashboard.php` (after approval)
- **Pending Page**: `vendor-pending-approval.php`
- **Features**:
  - Sell products in marketplace
  - Manage inventory and orders
  - Business verification required
  - Approval process for new vendors

### 4. **Admins** (Role ID: 1)
- **Purpose**: Platform administrators
- **Registration**: Manual/existing system
- **Dashboard**: `admin-dashboard.php`
- **Features**:
  - Manage all users and content
  - Approve vendor applications
  - System administration

## Registration Flow

### Entry Point: Registration Hub
- **File**: `register-hub.php`
- **Purpose**: Central hub where users choose their account type
- **Features**:
  - Clear role descriptions
  - Feature comparisons
  - Direct links to specific registration forms

### Registration Process

1. **User visits** `register-hub.php`
2. **Selects account type** (Customer, Painter, or Vendor)
3. **Completes registration form** with role-specific fields
4. **Document upload** (for Painters and Vendors)
5. **Email notifications** sent to user and admin
6. **Account activation**:
   - Customers & Painters: Immediate activation
   - Vendors: Pending approval process

## Technical Implementation

### Core Files

#### Authentication & Data Access
- `core/GibsonAuth.php` - Updated with multi-role support
- `core/GibsonDataAccess.php` - Added user management methods
- `core/GibsonAIService.php` - Added getUserByEmail method

#### Registration Pages
- `register-hub.php` - Multi-role registration hub
- `register-customer.php` - Customer registration form
- `register-vendor.php` - Vendor registration form
- `register.php` - Painter registration (existing, updated)

#### Dashboard Pages
- `customer-dashboard.php` - Customer dashboard (existing)
- `vendor-dashboard.php` - Vendor dashboard (new)
- `vendor-pending-approval.php` - Vendor approval waiting page
- `dashboard.php` - Painter dashboard (existing)

#### Success Pages
- `vendor-application-success.php` - Vendor application confirmation

### Database Integration

The system uses Gibson AI service with local fallbacks:

#### User Management Methods
- `getUserByEmail($email)` - Find user by email
- `createCustomer($customerData)` - Create customer profile
- `createVendor($vendorData)` - Create vendor profile
- `getCustomerById($id)` - Retrieve customer data
- `getVendorById($id)` - Retrieve vendor data
- `updateCustomer($id, $data)` - Update customer profile
- `updateVendor($id, $data)` - Update vendor profile

#### Local Storage Fallbacks
- `data/local_customers.json` - Customer data backup
- `data/local_vendors.json` - Vendor data backup
- `temp_uploads/` - Temporary document storage

## User Experience Features

### Registration Hub
- **Responsive design** with mobile optimization
- **Interactive animations** and hover effects
- **Clear value propositions** for each user type
- **Popular choice highlighting** (Customer registration)

### Form Validation
- **Client-side validation** with JavaScript
- **Server-side validation** with PHP
- **CSRF protection** on all forms
- **File upload validation** for documents

### Email Notifications
- **Welcome emails** for all user types
- **Admin notifications** for new registrations
- **Document attachments** for verification
- **Status updates** for vendor applications

### Navigation Integration
- **Dynamic navigation** based on login status
- **Role-appropriate dashboard links**
- **Consistent branding** across all pages

## Security Features

### Authentication
- **Password hashing** with PHP password_hash()
- **CSRF token protection** on all forms
- **Session management** with role-based access
- **Input sanitization** and validation

### File Upload Security
- **File type validation** (PDF, DOC, images only)
- **File size limits** based on server configuration
- **Secure file naming** with sanitization
- **Temporary storage** with cleanup

### Access Control
- **Role-based redirects** after login
- **Dashboard access restrictions** by user type
- **Vendor approval workflow** for marketplace access

## Admin Features

### Vendor Management
- **Application review** with document access
- **Approval/rejection workflow**
- **Status tracking** and notifications
- **Business verification** process

### User Oversight
- **Registration monitoring** via email notifications
- **User type management** and role assignments
- **Activity tracking** and audit trails

## Future Enhancements

### Planned Features
- **Advanced vendor analytics** dashboard
- **Product catalog management** for vendors
- **Order processing system** for marketplace
- **Customer project management** tools
- **Enhanced communication** between user types

### Technical Improvements
- **API endpoints** for mobile app integration
- **Advanced search** and filtering
- **Payment processing** integration
- **Automated approval** workflows

## Usage Instructions

### For Developers
1. **Test registration flows** for each user type
2. **Verify email notifications** are working
3. **Check file upload functionality** for documents
4. **Test role-based redirects** after login
5. **Validate form security** and CSRF protection

### For Administrators
1. **Monitor vendor applications** via email notifications
2. **Review uploaded documents** for verification
3. **Approve/reject vendor accounts** as needed
4. **Track user registrations** and system usage

### For Users
1. **Visit registration hub** to choose account type
2. **Complete appropriate registration form**
3. **Upload required documents** (if applicable)
4. **Check email** for confirmation and next steps
5. **Login and access** role-appropriate dashboard

## Support & Maintenance

### Regular Tasks
- **Clean up temporary files** from document uploads
- **Monitor email delivery** for notifications
- **Review vendor applications** promptly
- **Update user roles** as needed

### Troubleshooting
- **Check Gibson AI service** connectivity
- **Verify email configuration** for notifications
- **Monitor file upload limits** and permissions
- **Review error logs** for registration issues

---

**Last Updated**: December 2024
**Version**: 1.0
**Author**: AI Assistant