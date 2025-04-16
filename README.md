#### Variable Products Display Format

- **Separate Products**: Each variation appears as a separate product in the feed (default)
- **Nested Variations**: Variations appear as child elements within the parent product# WooCommerce BuyAdwiser XML Feed Generator

A professional WordPress plugin that generates an optimized WooCommerce product XML feed for BuyAdwiser with caching and configuration options.

## Features

- **Performance Optimized**: Built-in caching system to reduce server load
- **Configurable Options**: Control feed settings through an admin interface
- **Security Features**: Optional IP whitelisting to restrict feed access
- **Customizable Results**: Limit feed to specific number of newest products
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce data
- **Developer Friendly**: Clean, maintainable code following WordPress standards

## Installation

1. Upload the `wc-buyadwiser-feed` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings via the WooCommerce → BuyAdwiser Feed menu

## Usage

### Feed URL

Access your product feed using the following URL:
```
https://your-website.com/?generate_buyadwiser_feed=true
```

### Admin Settings

Navigate to **WooCommerce → BuyAdwiser Feed** to configure plugin settings:

#### General Settings

- **Enable Feed**: Toggle feed availability
- **IP Whitelist**: Restrict feed access to specific IP addresses (one per line)
- **Limit Results**: Optionally limit feed to a specific number of newest products
- **Variable Products Format**: Choose how variable products and their variations appear in the feed
  - Separate products: Each variation appears as an individual product (default)
  - Nested variations: Variations appear as nested elements within the parent product

#### Cache Settings

- **Enable Caching**: Toggle feed caching (recommended for performance)
- **Cache Duration**: Set how long the feed should be cached (in minutes)
- **Clear Cache**: Manually clear the cache when needed

## XML Feed Format

The feed follows the BuyAdwiser specifications and includes the following information:

- **Product Identification**: ID, SKU, product type
- **Basic Information**: Name, URL, descriptions
- **Pricing**: Regular price, sale price, tax information
- **Inventory**: Stock status, quantity, backorders
- **Shipping**: Weight, dimensions, shipping class
- **Images**: Main product image and gallery images
- **Categories & Tags**: Category path, product tags
- **Attributes**: All visible product attributes
- **Brand & EAN**: Special handling for brand and EAN information

## Variable Products Support

The plugin properly handles variable products by including:
- Parent product information in the feed
- Individual variations as separate products in the feed

## Performance Considerations

For large stores (>1000 products), the built-in caching feature significantly improves performance. 
The default cache duration is 15 minutes, which can be adjusted based on how frequently your 
product data changes.

## Customization

Developers can extend this plugin by using WordPress filters and actions:

```php
// Example: Modify feed query arguments
add_filter('wc_buyadwiser_feed_query_args', function($args) {
    // Add custom logic here
    return $args;
});
```

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## WooCommerce Compatibility

This plugin is fully compatible with:
- WooCommerce HPOS (High-Performance Order Storage)
- Custom Order Tables (COT)
- WooCommerce Blocks (Cart and Checkout)

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support inquiries, please visit https://visosnuolaidos.lt

## Changelog

### 2.0.0
- Complete refactoring with improved architecture
- Added caching system for better performance
- Added admin settings page
- Added IP whitelisting feature
- Added product limiting feature
- Added full HPOS (High-Performance Order Storage) compatibility
- Added WooCommerce Blocks compatibility
- Added variable products display format options (separate or nested)

### 1.0.0
- Initial release
