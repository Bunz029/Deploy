# Large File Upload Configuration Guide

## Issue
Getting "413 Content Too Large" error when uploading map layouts over 70MB.

## Root Cause
The server's PHP configuration is limiting uploads to 50MB (`upload_max_filesize`) and 40MB (`post_max_size`), which is lower than the application's requirements.

## Solutions Applied

### 1. Laravel Application Level
- ✅ Updated validation rule in `MapExportController.php` to allow up to 300MB
- ✅ Created local PHP configuration files

### 2. Local Configuration Files Updated
- ✅ `.user.ini` - Updated to 300M/350M limits
- ✅ `public/.htaccess` - Updated PHP values for Apache
- ✅ `public/.user.ini` - Additional PHP configuration
- ✅ `php.ini` - Template PHP configuration
- ✅ `nginx.conf` - Nginx configuration template

### 3. Server-Level Configuration Required

Since local PHP configuration files are not being applied, you need to update the server's main PHP configuration:

#### For Apache/PHP-FPM/CGI:
1. Locate your main `php.ini` file:
   ```bash
   php --ini
   ```

2. Edit the main `php.ini` file and update these values:
   ```ini
   upload_max_filesize = 300M
   post_max_size = 350M
   max_file_uploads = 50
   memory_limit = 1024M
   max_input_time = 600
   max_execution_time = 600
   max_input_vars = 10000
   ```

3. Restart your web server:
   ```bash
   # For Apache
   sudo systemctl restart apache2
   # or
   sudo service apache2 restart
   
   # For Nginx with PHP-FPM
   sudo systemctl restart nginx
   sudo systemctl restart php8.1-fpm  # or your PHP version
   ```

#### For Nginx:
1. Update your Nginx server configuration to include:
   ```nginx
   client_max_body_size 350M;
   client_body_timeout 600s;
   client_header_timeout 600s;
   ```

2. Restart Nginx:
   ```bash
   sudo systemctl restart nginx
   ```

#### For Development (XAMPP/WAMP/MAMP):
1. Edit the `php.ini` file in your XAMPP/WAMP/MAMP installation
2. Update the values as shown above
3. Restart Apache/MySQL services

### 4. Verification

After making server-level changes, verify the configuration:

```bash
cd isuedblaravel
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL; echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
```

Expected output:
```
upload_max_filesize: 300M
post_max_size: 350M
```

### 5. Alternative Quick Fix

If you cannot modify server configuration, you can temporarily reduce the validation limit in Laravel:

1. Edit `app/Http/Controllers/MapExportController.php`
2. Change line 191 from `max:307200` to `max:40960` (40MB)
3. This will work with current server limits but may not support larger files

## Testing

1. Restart your web server after making configuration changes
2. Try uploading a map layout file
3. Check the browser network tab for any remaining 413 errors
4. Monitor Laravel logs for any validation errors

## Current Status

- ✅ Application-level configurations updated
- ⚠️ Server-level PHP configuration needs manual update
- ✅ All local configuration files prepared

The 413 error should be resolved once the server-level PHP configuration is updated to match the application requirements.


