# 🌐 Domain Migration Guide - Hilal Public School Website

## 📋 **Pre-Migration Checklist**

### ✅ **What You'll Need:**
1. **New Domain Name** (e.g., `hilalpublicschool.edu.np`)
2. **Hosting Control Panel** access (cPanel/Plesk)
3. **Domain Registrar** access (where you bought the domain)
4. **Current Website Files** (already ready in your HPSS folder)
5. **Database Backup** (MySQL database)

## 🔧 **Step-by-Step Migration Process**

### **Step 1: Domain Setup**

#### A) **Point Domain to Hosting**
```
At your domain registrar (where you bought the domain):
1. Login to domain control panel
2. Find "DNS Settings" or "Nameservers"
3. Update nameservers to your hosting provider's:
   - ns1.yourhostingprovider.com
   - ns2.yourhostingprovider.com
   
   OR
   
   Add A Record:
   - Name: @ (or leave blank)
   - Type: A
   - Value: Your hosting IP address
   - TTL: 3600
```

#### B) **Add Domain in Hosting**
```
In your hosting control panel (cPanel):
1. Go to "Subdomains" or "Addon Domains"
2. Add your new domain
3. Set document root to: public_html/hpss (or similar)
4. Wait for DNS propagation (24-48 hours max)
```

### **Step 2: Upload Website Files**

#### A) **Via File Manager (cPanel)**
```
1. Login to cPanel
2. Open "File Manager"
3. Navigate to your domain's folder
4. Upload all HPSS files:
   - index.php
   - admin/
   - assets/
   - includes/
   - uploads/
   - database/
   - All other folders and files
```

#### B) **Via FTP (Alternative)**
```
FTP Details:
- Host: ftp.yournewdomain.com (or your hosting IP)
- Username: Your hosting username
- Password: Your hosting password
- Port: 21 (or 22 for SFTP)

Upload all files to: /public_html/ or /public_html/yournewdomain/
```

### **Step 3: Database Migration**

#### A) **Export from Current Database**
```sql
-- If you have access to current database
1. Login to phpMyAdmin
2. Select "hilal_school" database
3. Go to "Export" tab
4. Choose "Quick" export method
5. Download the .sql file
```

#### B) **Import to New Hosting**
```
1. Login to new hosting cPanel
2. Open "MySQL Databases"
3. Create new database: "hilal_school" or similar
4. Create database user with full privileges
5. Open "phpMyAdmin"
6. Select your new database
7. Go to "Import" tab
8. Upload and import the .sql file
```

### **Step 4: Update Configuration Files**

#### A) **Update Database Connection (`includes/db.php`)**
```php
<?php
// Update with your new hosting database details
$servername = "localhost"; // Usually localhost
$username = "yournew_dbuser";     // New database username
$password = "yournew_password";   // New database password
$dbname = "yournew_hilal_school"; // New database name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Helper function for input sanitization
function sanitize($conn, $input) {
    return htmlspecialchars(trim($conn->real_escape_string($input)));
}

// CSRF Token function
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>
```

#### B) **Update Base URLs (if needed)**
Check these files for any hardcoded URLs:
- `includes/header.php`
- `admin/includes/layout_top.php`
- Any email templates or configuration files

### **Step 5: File Permissions**

#### **Set Correct Permissions**
```bash
# Via cPanel File Manager or FTP
Folders: 755 (rwxr-xr-x)
- uploads/
- uploads/admissions/
- uploads/events/
- uploads/gallery/
- uploads/notices/
- uploads/scholarship/
- uploads/teachers/
- uploads/sliders/

PHP Files: 644 (rw-r--r--)
- *.php files
- *.css files
- *.js files

Writable Folders: 755 or 777 (if needed)
- uploads/ and all subfolders
```

### **Step 6: SSL Certificate Setup**

#### **Enable HTTPS**
```
In cPanel:
1. Go to "SSL/TLS"
2. Choose "Let's Encrypt" (free) or upload custom certificate
3. Force HTTPS redirect:
   - Go to "Redirects"
   - Redirect from: http://yournewdomain.com
   - Redirect to: https://yournewdomain.com
   - Check "Wildcard Redirect"
```

#### **Update .htaccess (if needed)**
```apache
# Add to .htaccess file
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Clean URLs (already configured)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^.]+)$ $1.php [NC,L]
```

### **Step 7: Testing & Verification**

#### **Test All Functionality**
```
✅ Homepage loads correctly
✅ Admin panel login works
✅ Database connections working
✅ File uploads working
✅ All menu links working
✅ Forms submitting correctly
✅ Email functionality (contact forms)
✅ Hero slider management
✅ Clean URLs working
✅ Mobile responsive design
✅ SSL certificate working (https://)
```

### **Step 8: Update External References**

#### **Update Any External Links**
- Google Business Profile
- Facebook page website link
- Email signatures
- Business cards/marketing materials
- School documents with website URL

## 🚨 **Common Issues & Solutions**

### **Database Connection Errors**
```
Error: "Connection failed"
Solution:
1. Double-check database credentials
2. Ensure database exists
3. Check if database user has proper permissions
4. Verify database host (usually "localhost")
```

### **File Permission Errors**
```
Error: "Permission denied" or upload failures
Solution:
1. Set uploads folders to 755 or 777
2. Check if web server can write to uploads directory
3. Ensure proper ownership (usually www-data)
```

### **Clean URLs Not Working**
```
Error: 404 on pages without .php
Solution:
1. Check if .htaccess file exists and has rewrite rules
2. Ensure mod_rewrite is enabled on server
3. Check if AllowOverride is set to All
```

### **SSL Issues**
```
Error: "Not secure" or SSL warnings
Solution:
1. Install SSL certificate properly
2. Update any mixed content (http links on https pages)
3. Force HTTPS redirect in .htaccess
```

## 📞 **Need Help?**

### **Hosting Provider Support**
- Contact your hosting provider if:
  - DNS issues persist beyond 48 hours
  - Database import fails
  - File permissions problems
  - SSL certificate installation issues

### **Domain Registrar Support**
- Contact domain registrar if:
  - DNS settings won't save
  - Nameserver updates not working
  - Domain management issues

## 🎯 **Migration Checklist**

- [ ] Domain pointing to new hosting
- [ ] Website files uploaded
- [ ] Database exported and imported
- [ ] Database connection updated
- [ ] File permissions set correctly
- [ ] SSL certificate installed
- [ ] All pages testing correctly
- [ ] Admin panel working
- [ ] Forms and uploads working
- [ ] Clean URLs functioning
- [ ] External references updated

## ⏱️ **Timeline Expectations**

- **DNS Propagation**: 1-48 hours
- **File Upload**: 30 minutes - 2 hours (depending on internet speed)
- **Database Migration**: 15-30 minutes
- **Configuration Updates**: 30 minutes
- **Testing**: 1-2 hours
- **Total**: 2-72 hours (mostly waiting for DNS)

Once everything is migrated and tested, your school website will be live on the new domain with all functionality intact! 🎉

## 📧 **Support**

If you encounter any issues during migration, please provide:
1. Error messages (exact text)
2. Steps that led to the error
3. Current domain and new domain names
4. Hosting provider details

The website is fully ready for migration - all files are properly organized and the database structure is complete!