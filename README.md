# WooCommerce 1C Integration

Enhanced data exchange between WooCommerce and 1C:Enterprise with improved security, logging, and performance.

## Features

- **Secure Exchange**: Enhanced authentication and authorization
- **Real-time Logging**: Comprehensive logging system with multiple levels
- **Performance Optimized**: Efficient memory usage and caching
- **Error Handling**: Robust error handling and recovery
- **Transaction Safety**: Database transactions for data integrity
- **Flexible Mapping**: Configurable data mapping between systems
- **Admin Interface**: User-friendly administration panel
- **System Monitoring**: Health checks and system status monitoring

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Required PHP extensions: xml, mbstring, curl, zip

## Installation

1. Download the plugin files
2. Upload to your WordPress plugins directory
3. Activate the plugin through the WordPress admin
4. Configure the plugin settings in WooCommerce → 1C Integration

## Configuration

### Basic Settings

Add these constants to your `wp-config.php` file:

```php
// Basic Settings
define('WC1C_ENABLE_LOGGING', true);
define('WC1C_LOG_LEVEL', 'info');
define('WC1C_MAX_EXECUTION_TIME', 300);
define('WC1C_MEMORY_LIMIT', '512M');

// Security Settings
define('WC1C_RATE_LIMIT', 60); // requests per hour
define('WC1C_ENABLE_IP_WHITELIST', false);
define('WC1C_IP_WHITELIST', '127.0.0.1,192.168.1.0/24');

// Exchange Settings
define('WC1C_FILE_LIMIT', '100M');
define('WC1C_CLEANUP_GARBAGE', true);
define('WC1C_CACHE_ENABLED', true);
```

### 1C Configuration

Use one of these URLs in your 1C configuration:

- `https://yoursite.com/?wc1c_endpoint=exchange`
- `https://yoursite.com/wc1c/exchange/` (if pretty permalinks are enabled)

## Usage

### Exchange Process

1. **Authentication**: 1C authenticates with WordPress credentials
2. **Initialization**: Exchange session is initialized
3. **File Upload**: Data files are uploaded and validated
4. **Import**: Data is parsed and imported with validation
5. **Query**: Orders are exported to 1C
6. **Success**: Exchange is completed and logged

### Admin Interface

Access the admin interface at **WooCommerce → 1C Integration**:

- **Dashboard**: Overview of recent exchanges and system status
- **Settings**: Configure exchange parameters
- **Logs**: View detailed exchange logs
- **System Status**: Check system health and requirements
- **Tools**: Utilities for maintenance and troubleshooting

## Development

### Project Structure

```
├── admin/                     # Admin interface
├── exchange/                  # Exchange functionality
│   ├── cache/                # Caching system
│   ├── error/                # Error handling
│   ├── mappers/              # Data mappers
│   └── validators/           # Data validation
├── includes/                 # Core classes
├── public/                   # Public interface
├── languages/                # Translation files
└── assets/                   # CSS, JS, images
```

### Building

```bash
# Install dependencies
composer install
npm install

# Run tests
composer test

# Check code style
composer cs

# Build distribution
composer build
```

## API Reference

### Hooks and Filters

#### Actions

- `wc1c_before_exchange` - Before exchange starts
- `wc1c_after_exchange` - After exchange completes
- `wc1c_before_import` - Before data import
- `wc1c_after_import` - After data import
- `wc1c_product_imported` - After product import
- `wc1c_order_exported` - After order export

#### Filters

- `wc1c_import_product_data` - Modify product data before import
- `wc1c_export_order_data` - Modify order data before export
- `wc1c_validate_data` - Custom data validation
- `wc1c_map_product_fields` - Custom field mapping

### Classes

#### Core Classes

- `WC1C` - Main plugin class
- `WC1C_Exchange` - Exchange handler
- `WC1C_Logger` - Logging system
- `WC1C_Auth` - Authentication

#### Mapper Classes

- `WC1C_Product_Mapper` - Product data mapping
- `WC1C_Order_Mapper` - Order data mapping
- `WC1C_Group_Mapper` - Category mapping
- `WC1C_Offer_Mapper` - Offer/variation mapping

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Check WordPress user credentials
   - Verify user has shop_manager or administrator role
   - Check server authentication headers

2. **File Upload Failed**
   - Check file size limits in PHP configuration
   - Verify directory permissions
   - Check available disk space

3. **Import Errors**
   - Review error logs in admin interface
   - Check XML file format
   - Verify WooCommerce product structure

4. **Memory Issues**
   - Increase PHP memory limit
   - Enable caching
   - Process data in smaller batches

### Debug Mode

Enable debug mode by adding to `wp-config.php`:

```php
define('WC1C_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This plugin is licensed under the GPL v3 or later.

## Support

- [Documentation](https://github.com/soft-hunter/woocommerce-1c-integration/wiki)
- [Issues](https://github.com/soft-hunter/woocommerce-1c-integration/issues)
- [Discussions](https://github.com/soft-hunter/woocommerce-1c-integration/discussions)

## Changelog

### 1.0.0
- Initial release
- Enhanced security and authentication
- Comprehensive logging system
- Performance optimizations
- Modern PHP architecture
- Admin interface improvements
```

## .gitignore

```gitignore:.gitignore
# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/
wp-content/backup-db/
wp-content/advanced-cache.php
wp-content/wp-cache-config.php
wp-content/blogs.dir/
wp-content/upgrade/
wp-content/backup-db/
wp-content/backups/
wp-content/blogs.dir/
wp-content/cache/
wp-content/upgrade/
wp-content/uploads/wc-logs/

# Plugin specific
/vendor/
/node_modules/
/dist/
*.log
.env
.env.local
.env.production

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Composer
composer.lock

# NPM
package-lock.json
yarn.lock

# Build files
/build/
/assets/dist/

# Testing
/tests/coverage/
phpunit.xml
.phpunit.result.cache

# Temporary files
*.tmp
*.temp
/tmp/