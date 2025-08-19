# Environment Setup Guide

## Why .gitignore Wasn't Working

The `.gitignore` file wasn't working because:

1. **Files were already tracked**: Once Git tracks a file, adding it to `.gitignore` won't stop tracking it
2. **Incomplete rules**: The original `.gitignore` only had `logs/*` but missed many important patterns
3. **Sensitive data was committed**: Files like `project.env` containing API keys were being tracked

## What Was Fixed

### 1. Updated .gitignore
Added comprehensive rules to ignore:
- Environment files (`*.env`, `project.env`, `.gibson-env`)
- Log files (`logs/`, `*.log`)
- Temporary files (`tmp/`, `cache/`, `*.tmp`)
- IDE files (`.vscode/`, `.idea/`)
- Upload directories (`uploads/`)
- Test files (`test-*.php`, `debug-*.php`)
- Sensitive data patterns (`*password*`, `*secret*`, `*key*`)

### 2. Untracked Sensitive Files
Removed from Git tracking:
- `project.env` (contains API keys and passwords)
- `logs/error.log` (log files shouldn't be in version control)
- Test files created during development

### 3. Created Example File
- `project.env.example` - Safe template for environment configuration

## Environment Setup Instructions

### 1. Copy Environment File
```bash
cp project.env.example project.env
```

### 2. Edit Configuration
Open `project.env` and fill in your actual values:
```bash
# Gibson AI Configuration
GIBSON_API_KEY=your_actual_api_key_here
GIBSON_DATABASE_ID=your_actual_database_id_here

# Database Configuration
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Email Configuration
SMTP_USERNAME=your_email_username
SMTP_PASSWORD=your_email_password

# Stripe Configuration (if using payments)
STRIPE_SECRET_KEY=sk_test_your_actual_key
```

### 3. Verify Setup
Run the connection test:
```bash
php check-gibson.php
```

## Security Best Practices

### ✅ Do This
- Keep `project.env` in `.gitignore`
- Use `project.env.example` for documentation
- Never commit API keys, passwords, or secrets
- Use different credentials for development/production

### ❌ Don't Do This
- Don't commit `project.env` to version control
- Don't hardcode credentials in PHP files
- Don't share environment files via email/chat
- Don't use production credentials in development

## Checking .gitignore Status

To verify files are being ignored:
```bash
# Check if specific files are ignored
git check-ignore project.env logs/error.log

# See what would be added (should not include ignored files)
git add . --dry-run

# Check current Git status
git status
```

## If You Accidentally Commit Sensitive Data

If you accidentally commit sensitive files:

1. **Remove from tracking** (keeps local file):
```bash
git rm --cached filename
```

2. **Add to .gitignore**:
```bash
echo "filename" >> .gitignore
```

3. **Commit the changes**:
```bash
git add .gitignore
git commit -m "Remove sensitive file from tracking"
```

4. **For already pushed commits**, you may need to:
   - Change the exposed credentials immediately
   - Consider using `git filter-branch` or BFG Repo-Cleaner for history cleanup

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|---------|
| `GIBSON_API_KEY` | Gibson AI API authentication key | `gAAAAABo...` |
| `GIBSON_DATABASE_ID` | Your Gibson AI database identifier | `painter_marketplace_prod` |
| `DB_PASSWORD` | MySQL database password | `SecurePassword123!` |
| `SMTP_PASSWORD` | Email server password | `EmailPass456!` |
| `STRIPE_SECRET_KEY` | Stripe payment processing key | `sk_live_...` |

## Troubleshooting

### .gitignore Still Not Working?
1. Check if files are already tracked: `git ls-files | grep filename`
2. Remove from tracking: `git rm --cached filename`
3. Verify ignore rules: `git check-ignore filename`
4. Clear Git cache: `git rm -r --cached . && git add .`

### Environment File Not Loading?
1. Check file exists: `ls -la project.env`
2. Check file permissions: `chmod 644 project.env`
3. Verify syntax: No spaces around `=`, no quotes unless needed
4. Test loading: `php -r "var_dump(getenv('GIBSON_API_KEY'));"`