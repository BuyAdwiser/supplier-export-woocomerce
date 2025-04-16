<?php
/**
 * Plugin Name:       WooCommerce BuyAdwiser XML Feed Generator
 * Plugin URI:        https://github.com/BuyAdwiser/supplier-export-woocomerce
 * Description:       Generates an optimized WooCommerce product XML feed for BuyAdwiser with caching and configuration options
 * Version:           2.0.0
 * Author:            BuyAdwiser
 * Author URI:        https://visosnuolaidos.lt
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-buyadwiser-feed
 * Domain Path:       /languages
 * WC requires at least: 3.0
 * WC tested up to:   9.0
 * Requires PHP:      7.2
 * Requires at least: 5.0
 * 
 * WooCommerce HPOS Compatibility
 * @package WooCommerce\Admin
 * WC tested up to: 9.0
 * COT supported: true
 * HPOS supported: true
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class to handle initialization, hooks, and core functionality
 */
class WC_BuyAdwiser_Feed {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    const VERSION = '2.0.0';
    
    /**
     * The single instance of the class
     * 
     * @var WC_BuyAdwiser_Feed
     */
    protected static $_instance = null;
    
    /**
     * Plugin options
     * 
     * @var array
     */
    protected $options = [];
    
    /**
     * Transient name for cached feed
     * 
     * @var string
     */
    protected $transient_name = 'wc_buyadwiser_feed_xml';
    
    /**
     * Main plugin instance
     * 
     * @return WC_BuyAdwiser_Feed
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Define plugin constants
        $this->define_constants();
        
        // Include required files
        $this->includes();
        
        // Load plugin options
        $this->load_options();
        
        // Init hooks
        $this->init_hooks();
        
        // Load text domain
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
        
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        define( 'WC_BUYADWISER_FEED_VERSION', self::VERSION );
        define( 'WC_BUYADWISER_FEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'WC_BUYADWISER_FEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'WC_BUYADWISER_FEED_PLUGIN_FILE', __FILE__ );
    }
    
    /**
     * Include required files
     */
    private function includes() {
        include_once WC_BUYADWISER_FEED_PLUGIN_DIR . 'includes/class-wc-buyadwiser-feed-admin.php';
        include_once WC_BUYADWISER_FEED_PLUGIN_DIR . 'includes/class-wc-buyadwiser-feed-generator.php';
    }
    
    /**
     * Load plugin options
     */
    private function load_options() {
        $this->options = get_option( 'wc_buyadwiser_feed_options', array(
            'enabled'           => 'yes',
            'ip_whitelist'      => '',
            'limit_results'     => 'no',
            'results_limit'     => 1000,
            'enable_caching'    => 'yes',
            'cache_time'        => 15 // minutes
        ) );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        add_action( 'plugins_loaded', array( $this, 'check_woocommerce_dependency' ) );
        
        // Initialize admin interface
        if ( is_admin() ) {
            new WC_BuyAdwiser_Feed_Admin();
        }
        
        // Add feed endpoint hook
        add_action( 'init', array( $this, 'handle_feed_request' ) );
        
        // Add schedule event for clearing cache
        add_action( 'wc_buyadwiser_feed_clear_cache', array( $this, 'clear_xml_feed_cache' ) );
        
        // Register activation, deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Check WooCommerce dependency
     */
    public function check_woocommerce_dependency() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return false;
        }
        return true;
    }
    
    /**
     * Admin notice if WooCommerce is missing
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'BuyAdwiser XML Feed Generator requires WooCommerce to be installed and active.', 'wc-buyadwiser-feed' ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Handle feed request and generate XML feed
     */
    public function handle_feed_request() {
        if ( isset( $_GET['generate_buyadwiser_feed'] ) && $_GET['generate_buyadwiser_feed'] === 'true' ) {
            // Check if feed is enabled
            if ( $this->get_option( 'enabled' ) !== 'yes' ) {
                wp_die( esc_html__( 'BuyAdwiser XML Feed is currently disabled.', 'wc-buyadwiser-feed' ), esc_html__( 'Feed Disabled', 'wc-buyadwiser-feed' ), array( 'response' => 403 ) );
            }
            
            // Check IP whitelist if enabled
            if ( ! $this->validate_ip_access() ) {
                wp_die( esc_html__( 'Access denied. Your IP is not on the whitelist.', 'wc-buyadwiser-feed' ), esc_html__( 'Access Denied', 'wc-buyadwiser-feed' ), array( 'response' => 403 ) );
            }
            
            // Set XML header
            header( 'Content-Type: application/xml; charset=utf-8' );
            
            // Get XML feed (from cache or generate new)
            echo $this->get_xml_feed();
            
            exit; // Stop WordPress from processing further
        }
    }
    
    /**
     * Validate IP access
     *
     * @return bool
     */
    private function validate_ip_access() {
        $ip_whitelist = $this->get_option( 'ip_whitelist' );
        
        // If no whitelist is set, allow all
        if ( empty( $ip_whitelist ) ) {
            return true;
        }
        
        // Get client IP
        $client_ip = $this->get_client_ip();
        
        // Process whitelist
        $allowed_ips = array_map( 'trim', explode( "\n", $ip_whitelist ) );
        
        return in_array( $client_ip, $allowed_ips, true );
    }
    
    /**
     * Get client IP
     *
     * @return string
     */
    private function get_client_ip() {
        // Check for proxy first
        $ip = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
        
        // Check for remote address if proxy is not set
        if ( empty( $ip ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        return $ip;
    }
    
    /**
     * Get XML feed content with caching
     *
     * @return string
     */
    public function get_xml_feed() {
        // Check if caching is enabled
        if ( $this->get_option( 'enable_caching' ) === 'yes' ) {
            // Try to get from cache
            $cached_xml = get_transient( $this->transient_name );
            
            if ( false !== $cached_xml ) {
                return $cached_xml;
            }
        }
        
        // Generate fresh XML feed
        $generator = new WC_BuyAdwiser_Feed_Generator( $this->options );
        $xml = $generator->generate();
        
        // Cache it if enabled
        if ( $this->get_option( 'enable_caching' ) === 'yes' ) {
            $cache_time = intval( $this->get_option( 'cache_time', 15 ) );
            set_transient( $this->transient_name, $xml, $cache_time * MINUTE_IN_SECONDS );
        }
        
        return $xml;
    }
    
    /**
     * Clear XML feed cache
     */
    public function clear_xml_feed_cache() {
        delete_transient( $this->transient_name );
    }
    
    /**
     * Get a plugin option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_option( $key, $default = null ) {
        if ( isset( $this->options[ $key ] ) ) {
            return $this->options[ $key ];
        }
        return $default;
    }
    
    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 
            'wc-buyadwiser-feed',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Ensure options exist
        if ( ! get_option( 'wc_buyadwiser_feed_options' ) ) {
            update_option( 'wc_buyadwiser_feed_options', array(
                'enabled'           => 'yes',
                'ip_whitelist'      => '',
                'limit_results'     => 'no',
                'results_limit'     => 1000,
                'enable_caching'    => 'yes',
                'cache_time'        => 15
            ) );
        }
        
        // Schedule cache clearing event
        if ( ! wp_next_scheduled( 'wc_buyadwiser_feed_clear_cache' ) ) {
            wp_schedule_event( time(), 'daily', 'wc_buyadwiser_feed_clear_cache' );
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivate plugin
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'wc_buyadwiser_feed_clear_cache' );
        
        // Clear cache
        $this->clear_xml_feed_cache();
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            // Declare compatibility with Custom Order Tables (HPOS)
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( WC_BUYADWISER_FEED_PLUGIN_FILE ), true );
            
            // Declare compatibility with cart and checkout blocks
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', plugin_basename( WC_BUYADWISER_FEED_PLUGIN_FILE ), true );
        }
    }
}

/**
 * Main instance of plugin
 *
 * @return WC_BuyAdwiser_Feed
 */
function WC_BuyAdwiser_Feed() {
    return WC_BuyAdwiser_Feed::instance();
}

// Initialize the plugin
WC_BuyAdwiser_Feed();
