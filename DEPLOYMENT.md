# 🚀 WooCommerce 1C Integration - Deployment Guide

## 📋 Pre-deployment Requirements

### System Requirements
- ✅ WordPress 5.0 or higher
- ✅ WooCommerce 5.0 or higher  
- ✅ PHP 7.4 or higher
- ✅ MySQL 5.6 or higher

### Server Requirements
- ✅ `xml` PHP extension
- ✅ `mbstring` PHP extension
- ✅ `curl` PHP extension
- ✅ `zip` PHP extension
- ✅ Write permissions for uploads directory

### Pre-deployment Checklist
- [ ] WordPress site backup completed
- [ ] WooCommerce plugin installed and active
- [ ] Server meets minimum requirements
- [ ] Admin access to WordPress dashboard
- [ ] FTP/SSH access (if needed)

## 🌐 Deployment Methods

### Method 1: WordPress Admin Upload (Recommended)

**Best for:** Most users, shared hosting, managed WordPress

1. **Download Plugin Package**
   - Download `woocommerce-1c-integration-v1.0.0.zip` from releases
   - Verify package integrity (check SHA256 if provided)

2. **Login to WordPress Admin**
   ```
   https://yoursite.com/wp-admin
   ```

3. **Navigate to Plugins**
   - Go to `Plugins` → `Add New`
   - Click `Upload Plugin` button

4. **Upload Plugin**
   - Choose file: `woocommerce-1c-integration-v1.0.0.zip`
   - Click `Install Now`
   - Wait for installation to complete
   - Click `Activate Plugin`

5. **Verify Installation**
   - Check `Plugins` → `Installed Plugins`
   - Look for "WooCommerce 1C Integration" in active plugins list

### Method 2: FTP/SFTP Upload

**Best for:** Users with FTP access, custom server setups

1. **Extract Plugin Package**
   ```bash
   unzip woocommerce-1c-integration-v1.0.0.zip
   ```

2. **Connect via FTP/SFTP**
   - Use your preferred FTP client (FileZilla, WinSCP, etc.)
   - Connect to your server

3. **Upload Plugin Files**
   ```
   Local folder: woocommerce-1c-integration-v1.0.0/
   Remote path: /wp-content/plugins/woocommerce-1c-integration/
   ```

4. **Set Permissions**
   ```
   Folders: 755
   Files: 644
   ```

5. **Activate Plugin**
   - Go to WordPress Admin → `Plugins`
   - Find "WooCommerce 1C Integration"
   - Click "Activate"

### Method 3: SSH/SCP Upload

**Best for:** VPS/dedicated servers, command-line users

1. **Upload Package to Server**
   ```bash
   scp woocommerce-1c-integration-v1.0.0.zip user@yoursite.com:/tmp/
   ```

2. **SSH to Server**
   ```bash
   ssh user@yoursite.com
   cd /path/to/wordpress/wp-content/plugins/
   ```

3. **Extract and Install**
   ```bash
   # Extract plugin
   unzip /tmp/woocommerce-1c-integration-v1.0.0.zip
   
   # Rename to standard directory name
   mv woocommerce-1c-integration-v1.0.0 woocommerce-1c-integration
   
   # Set proper ownership (adjust user/group as needed)
   chown -R www-data:www-data woocommerce-1c-integration
   
   # Set proper permissions
   find woocommerce-1c-integration -type d -exec chmod 755 {} \;
   find woocommerce-1c-integration -type f -exec chmod 644 {} \;
   
   # Clean up
   rm /tmp/woocommerce-1c-integration-v1.0.0.zip
   ```

4. **Activate via WordPress Admin**
   - Go to `Plugins` → `Installed Plugins`
   - Activate "WooCommerce 1C Integration"

### Method 4: WP-CLI Installation

**Best for:** Developers, automated deployments

1. **Install via WP-CLI**
   ```bash
   # Upload and activate in one command
   wp plugin install woocommerce-1c-integration-v1.0.0.zip --activate
   
   # Or install from local file
   wp plugin install /path/to/woocommerce-1c-integration-v1.0.0.zip --activate
   ```

2. **Verify Installation**
   ```bash
   wp plugin list | grep woocommerce-1c-integration
   ```

### Method 5: Git Deployment

**Best for:** Development environments, version control workflows

1. **Clone Repository**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/yourusername/woocommerce-1c-integration.git
   ```

2. **Install Dependencies (if needed)**
   ```bash
   cd woocommerce-1c-integration
   composer install --no-dev --optimize-autoloader
   ```

3. **Activate Plugin**
   ```bash
   wp plugin activate woocommerce-1c-integration
   ```

## ⚙️ Post-Installation Configuration

### 1. Verify Installation

1. **Check Plugin Status**
   - Go to `Plugins` → `Installed Plugins`
   - Verify "WooCommerce 1C Integration" shows as active
   - Check for any error messages

2. **Check System Status**
   - Go to `WooCommerce` → `1C Integration`
   - Review system status page
   - Verify all requirements are met

3. **Check Error Logs**
   - Look for any PHP errors in server logs
   - Check WordPress debug log if enabled

### 2. Configure Exchange Settings

1. **Access Plugin Settings**
   ```
   WordPress Admin → WooCommerce → 1C Integration
   ```

2. **Note Exchange URLs**
   ```
   Standard URL: https://yoursite.com/?wc1c=exchange
   Pretty URLs:  https://yoursite.com/wc1c/exchange/
   ```

3. **Configure Authentication**
   - Use existing WordPress admin credentials
   - Or create dedicated user with "Shop Manager" role
   - Ensure user has proper permissions

### 3. Test Exchange Connection

1. **Test from WordPress**
   - Use built-in connection test (if available)
   - Check system status indicators

2. **Test from 1C Software**
   - Configure exchange URL in 1C
   - Test connection with credentials
   - Verify authentication works
   - Test basic data exchange

3. **Monitor Logs**
   - Enable logging in plugin settings
   - Monitor exchange logs for errors
   - Check WordPress error logs

## 🔧 Advanced Configuration

### wp-config.php Settings

Add these constants to your `wp-config.php` file for advanced configuration:

```php
// Basic Settings
define('WC1C_ENABLE_LOGGING', true);
define('WC1C_FILE_LIMIT', '100M');
define('WC1C_MAX_EXECUTION_TIME', 300);
define('WC1C_RATE_LIMIT', 60);

// Product Import Settings
define('WC1C_MATCH_BY_SKU', false);
define('WC1C_MATCH_CATEGORIES_BY_TITLE', false);
define('WC1C_PREVENT_CLEAN', false);
define('WC1C_UPDATE_POST_NAME', false);

// Stock Management
define('WC1C_MANAGE_STOCK', 'yes');
define('WC1C_OUTOFSTOCK_STATUS', 'outofstock');

// Security Settings
define('WC1C_SUPPRESS_NOTICES', true);
define('WC1C_CLEANUP_GARBAGE', true);

// Optional: Price and Currency Settings
// define('WC1C_PRICE_TYPE', null);
// define('WC1C_CURRENCY', null);
// define('WC1C_PRESERVE_PRODUCT_VARIATIONS', false);
```

### Server Configuration

#### Apache Configuration

Add to `.htaccess` file:

```apache
# Increase upload limits for 1C exchange
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value max_execution_time 300
php_value memory_limit 512M

# Enable authorization headers for 1C authentication
RewriteEngine On
RewriteRule . - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx Configuration

Add to server block:

```nginx
# Increase upload limits
client_max_body_size 100M;

# Increase timeouts for large exchanges
fastcgi_read_timeout 300s;
proxy_read_timeout 300s;
send_timeout 300s;

# Handle authorization headers
fastcgi_param HTTP_AUTHORIZATION $http_authorization;

# Security headers
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";

# 1C exchange endpoint
location ~ ^/wc1c/exchange/?$ {
    try_files $uri $uri/ /index.php?wc1c=exchange;
}
```

#### PHP Configuration

Recommended `php.ini` settings:

```ini
# Memory and execution limits
memory_limit = 512M
max_execution_time = 300
max_input_time = 300

# File upload limits
upload_max_filesize = 100M
post_max_size = 100M
max_file_uploads = 20

# Other recommended settings
default_charset = "UTF-8"
mbstring.internal_encoding = UTF-8
```

## 🔍 Troubleshooting

### Common Issues and Solutions

#### 1. Authentication Failed

**Symptoms:**
- "No permissions" error
- "Not logged in" error
- 401 Unauthorized responses

**Solutions:**
```apache
# Add to .htaccess
RewriteRule . - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

- Verify username/password are correct
- Ensure user has "Shop Manager" or "Administrator" role
- Check if HTTP Basic Auth is working
- Test with different user account

#### 2. File Upload Errors

**Symptoms:**
- "File too large" errors
- Upload timeouts
- Incomplete file transfers

**Solutions:**
```php
// Add to wp-config.php or php.ini
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 300);
```

- Increase server upload limits
- Check directory permissions (755 for folders, 644 for files)
- Verify disk space availability

#### 3. Memory Errors

**Symptoms:**
- "Fatal error: Allowed memory size exhausted"
- Plugin crashes during large imports

**Solutions:**
```php
// Add to wp-config.php
ini_set('memory_limit', '512M');
define('WP_MEMORY_LIMIT', '512M');
```

- Increase PHP memory limit
- Enable memory optimization in plugin settings
- Process data in smaller chunks

#### 4. Timeout Errors

**Symptoms:**
- "Maximum execution time exceeded"
- Connection timeouts
- Incomplete data processing

**Solutions:**
```php
// Add to wp-config.php
ini_set('max_execution_time', 300);
set_time_limit(0); // For CLI scripts only
```

- Increase server timeout settings
- Process data in batches
- Use background processing for large operations

#### 5. Permission Errors

**Symptoms:**
- "Permission denied" errors
- Cannot create directories
- Cannot write files

**Solutions:**
```bash
# Set proper permissions
chown -R www-data:www-data /path/to/wordpress/
find /path/to/wordpress/ -type d -exec chmod 755 {} \;
find /path/to/wordpress/ -type f -exec chmod 644 {} \;
```

### Debug Mode

Enable debug mode for troubleshooting:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WC1C_DEBUG', true);
```

### Log Files

Check these log files for errors:

- **WordPress Debug Log:** `/wp-content/debug.log`
- **Plugin Logs:** `/wp-content/uploads/woocommerce-1c-integration/logs/`
- **Server Error Log:** Check your hosting control panel
- **Web Server Logs:** Apache/Nginx access and error logs

### Testing Exchange Connection

Use these tools to test the exchange endpoint:

```bash
# Test basic connectivity
curl -I https://yoursite.com/?wc1c=exchange

# Test authentication
curl -u "username:password" https://yoursite.com/?wc1c=exchange?type=catalog&mode=checkauth

# Test with verbose output
curl -v -u "username:password" https://yoursite.com/?wc1c=exchange?type=catalog&mode=init
```

## 📊 Performance Optimization

### Database Optimization

1. **Enable Object Caching**
   ```php
   // Use Redis, Memcached, or file-based caching
   define('WP_CACHE', true);
   ```

2. **Optimize Database Tables**
   ```sql
   -- Run periodically
   OPTIMIZE TABLE wp_posts, wp_postmeta, wp_terms, wp_termmeta;
   ```

3. **Index Optimization**
   - Plugin automatically creates necessary indexes
   - Monitor slow query log for additional optimization opportunities

### Server Optimization

1. **Enable OPcache**
   ```ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.max_accelerated_files=10000
   ```

2. **Use HTTP/2**
   - Enable HTTP/2 on your web server
   - Improves concurrent request handling

3. **Enable Gzip Compression**
   ```apache
   # Apache
   LoadModule deflate_module modules/mod_deflate.so
   <Location />
       SetOutputFilter DEFLATE
   </Location>
   ```

## 🔄 Updates and Maintenance

### Updating the Plugin

1. **Backup Current Installation**
   ```bash
   # Backup plugin directory
   tar -czf wc1c-backup-$(date +%Y%m%d).tar.gz wp-content/plugins/woocommerce-1c-integration/
   
   # Backup database
   mysqldump -u user -p database_name > backup-$(date +%Y%m%d).sql
   ```

2. **Update Process**
   - Deactivate current plugin
   - Upload new version
   - Activate updated plugin
   - Test functionality

3. **Rollback if Needed**
   - Deactivate new version
   - Restore backup
   - Reactivate previous version

### Maintenance Tasks

1. **Regular Backups**
   - Schedule automatic backups
   - Test backup restoration process
   - Store backups off-site

2. **Log Monitoring**
   - Review exchange logs regularly
   - Monitor error patterns
   - Set up log rotation

3. **Performance Monitoring**
   - Monitor exchange performance
   - Track memory usage
   - Monitor database growth

## 📞 Support and Resources

### Getting Help

1. **Documentation**
   - Review plugin settings page
   - Check system status regularly
   - Read error messages carefully

2. **Debugging**
   - Enable debug logging
   - Monitor exchange logs
   - Check server error logs

3. **Community Support**
   - Check GitHub issues
   - Search existing solutions
   - Report new issues with details

### Reporting Issues

When reporting issues, include:

1. **System Information**
   - WordPress version
   - WooCommerce version
   - PHP version
   - Server type (Apache/Nginx)

2. **Error Details**
   - Complete error messages
   - Steps to reproduce
   - Expected vs actual behavior

3. **Log Files**
   - Plugin logs
   - WordPress debug log
   - Server error logs

### Contributing

1. **Code Contributions**
   - Fork the repository
   - Create feature branch
   - Submit pull request

2. **Bug Reports**
   - Use GitHub issues
   - Provide detailed information
   - Include reproduction steps

3. **Documentation**
   - Improve existing docs
   - Add new examples
   - Translate to other languages

---

## 📋 Quick Reference

### Exchange URLs
- **Standard:** `https://yoursite.com/?wc1c=exchange`
- **Pretty URLs:** `https://yoursite.com/wc1c/exchange/`

### Required Credentials
- **Username:** WordPress admin username
- **Password:** WordPress admin password
- **Role:** Administrator or Shop Manager

### Important Directories
- **Plugin:** `/wp-content/plugins/woocommerce-1c-integration/`
- **Uploads:** `/wp-content/uploads/woocommerce-1c-integration/`
- **Logs:** `/wp-content/uploads/woocommerce-1c-integration/logs/`

### Configuration Files
- **WordPress:** `wp-config.php`
- **Apache:** `.htaccess`
- **Nginx:** `nginx.conf`
- **PHP:** `php.ini`

---

**Plugin Version:** 1.0.0  
**Last Updated:** 2025-05-28  
**Compatibility:** WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+  
**License:** GPL v3 or later
```

```markdown:QUICK_START.md
# ⚡ Quick Start Guide

## 🚀 5-Minute Deployment

### Prerequisites
- ✅ WordPress 5.0+ with WooCommerce 5.0+
- ✅ PHP 7.4+ with required extensions
- ✅ Admin access to WordPress

### Step 1: Download Plugin
Download the latest release:
```
woocommerce-1c-integration-v1.0.0.zip
```

### Step 2: Install Plugin

**Option A: WordPress Admin (Recommended)**
1. Login to WordPress Admin
2. Go to `Plugins` → `Add New` → `Upload Plugin`
3. Choose `woocommerce-1c-integration-v1.0.0.zip`
4. Click `Install Now` → `Activate`

**Option B: FTP Upload**
1. Extract zip file
2. Upload `woocommerce-1c-integration/` to `/wp-content/plugins/`
3. Activate in WordPress Admin

### Step 3: Configure 1C Exchange

1. **Get Exchange URL**
   - Go to `WooCommerce` → `1C Integration`
   - Copy URL: `https://yoursite.com/?wc1c=exchange`

2. **Configure 1C Software**
   - Exchange URL: `https://yoursite.com/?wc1c=exchange`
   - Username: Your WordPress admin username
   - Password: Your WordPress admin password

### Step 4: Test Connection
1. Test connection from 1C software
2. Check logs in `WooCommerce` → `1C Integration`
3. Verify data exchange works

## ✅ You're Ready!

### Next Steps
- Configure exchange settings as needed
- Set up automated sync schedule
- Monitor exchange logs regularly

### Need Help?
- Check [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions
- Review [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common issues
- Enable debug logging for troubleshooting

---

**🎯 Total Setup Time: ~5 minutes**  
**🔧 Configuration Time: ~10 minutes**  
**🚀 Ready for Production!**
```

```markdown:TROUBLESHOOTING.md
# 🔧 Troubleshooting Guide

## 🚨 Common Issues and Solutions

### Authentication Problems

#### Issue: "No permissions" or "Not logged in" errors

**Cause:** HTTP Authorization headers not passed to PHP

**Solution 1: Apache .htaccess**
```apache
RewriteEngine On
RewriteRule . - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

**Solution 2: Nginx Configuration**
```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

**Solution 3: Alternative Authentication**
- Create dedicated user with "Shop Manager" role
- Use application passwords (WordPress 5.6+)
- Test with different credentials

#### Issue: 401 Unauthorized responses

**Diagnostic Steps:**
```bash
# Test basic connectivity
curl -I https://yoursite.com/?wc1c=exchange

# Test authentication
curl -u "username:password" "https://yoursite.com/?wc1c=exchange?type=catalog&mode=checkauth"
```

**Solutions:**
- Verify username/password combination
- Check user role permissions
- Ensure user account is active
- Test with administrator account

### File Upload Issues

#### Issue: "File too large" errors

**PHP Configuration:**
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 512M
```

**WordPress Configuration:**
```php
// Add to wp-config.php
define('WP_MEMORY_LIMIT', '512M');
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
```

**Server Configuration:**
```apache
# Apache .htaccess
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value memory_limit 512M
```

```nginx
# Nginx
client_max_body_size 100M;
```

#### Issue: Upload timeouts

**Solutions:**
```php
// Increase timeouts
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
```

```nginx
# Nginx timeouts
fastcgi_read_timeout 300s;
proxy_read_timeout 300s;
send_timeout 300s;
```

### Memory and Performance Issues

#### Issue: "Memory exhausted" errors

**Immediate Fix:**
```php
// Add to wp-config.php
ini_set('memory_limit', '512M');
define('WP_MEMORY_LIMIT', '512M');
```

**Long-term Solutions:**
- Enable object caching (Redis/Memcached)
- Process data in smaller batches
- Optimize database queries
- Use background processing

#### Issue: Slow exchange performance

**Database Optimization:**
```sql
-- Check for missing indexes
SHOW INDEX FROM wp_posts;
SHOW INDEX FROM wp_postmeta;

-- Optimize tables
OPTIMIZE TABLE wp_posts, wp_postmeta, wp_terms, wp_termmeta;
```

**Caching Solutions:**
```php
// Enable object caching
define('WP_CACHE', true);

// Enable OPcache
opcache.enable=1
opcache.memory_consumption=256
```

### Connection and Network Issues

#### Issue: Connection timeouts

**Diagnostic Commands:**
```bash
# Test DNS resolution
nslookup yoursite.com

# Test connectivity
telnet yoursite.com 80
telnet yoursite.com 443

# Test HTTP response
curl -v https://yoursite.com/?wc1c=exchange
```

**Solutions:**
- Check firewall settings
- Verify SSL certificate
- Test from different network
- Check server load

#### Issue: SSL/HTTPS problems

**Certificate Verification:**
```bash
# Check SSL certificate
openssl s_client -connect yoursite.com:443 -servername yoursite.com

# Test SSL with curl
curl -I https://yoursite.com
```

**Solutions:**