<?php
/**
 * Class WC_BuyAdwiser_Feed_Admin
 *
 * Handles all admin-related functionality
 *
 * @package WC_BuyAdwiser_Feed
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_BuyAdwiser_Feed_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Add menu item
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . plugin_basename( WC_BUYADWISER_FEED_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
        
        // Add manual cache clear action
        add_action( 'admin_init', array( $this, 'handle_manual_cache_clear' ) );
        
        // Add admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }
    
    /**
     * Add menu item to WooCommerce submenu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'BuyAdwiser Feed', 'wc-buyadwiser-feed' ),
            __( 'BuyAdwiser Feed', 'wc-buyadwiser-feed' ),
            'manage_woocommerce',
            'wc-buyadwiser-feed',
            array( $this, 'settings_page' )
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'wc_buyadwiser_feed_settings',
            'wc_buyadwiser_feed_options',
            array( $this, 'sanitize_settings' )
        );
        
        // General settings section
        add_settings_section(
            'wc_buyadwiser_feed_general',
            __( 'General Settings', 'wc-buyadwiser-feed' ),
            array( $this, 'general_settings_section_callback' ),
            'wc-buyadwiser-feed'
        );
        
        // Feed enabled/disabled
        add_settings_field(
            'wc_buyadwiser_feed_enabled',
            __( 'Enable Feed', 'wc-buyadwiser-feed' ),
            array( $this, 'enabled_field_callback' ),
            'wc-buyadwiser-feed',
            'wc_buyadwiser_feed_general'
        );
        
        // IP Whitelist
        add_settings_field(
            'wc_buyadwiser_feed_ip_whitelist',
            __( 'IP Whitelist', 'wc-buyadwiser-feed' ),
            array( $this, 'ip_whitelist_field_callback' ),
            'wc-buyadwiser-feed',
            'wc_buyadwiser_feed_general'
        );
        
        // Product limit
        add_settings_field(
            'wc_buyadwiser_feed_limit_results',
            __( 'Limit Results', 'wc-buyadwiser-feed' ),
            array( $this, 'limit_results_field_callback' ),
            'wc-buyadwiser-feed',
            'wc_buyadwiser_feed_general'
        );
        
        // Cache settings section
        add_settings_section(
            'wc_buyadwiser_feed_cache',
            __( 'Cache Settings', 'wc-buyadwiser-feed' ),
            array( $this, 'cache_settings_section_callback' ),
            'wc-buyadwiser-feed'
        );
        
        // Enable caching
        add_settings_field(
            'wc_buyadwiser_feed_enable_caching',
            __( 'Enable Caching', 'wc-buyadwiser-feed' ),
            array( $this, 'enable_caching_field_callback' ),
            'wc-buyadwiser-feed',
            'wc_buyadwiser_feed_cache'
        );
        
        // Cache duration
        add_settings_field(
            'wc_buyadwiser_feed_cache_time',
            __( 'Cache Duration (minutes)', 'wc-buyadwiser-feed' ),
            array( $this, 'cache_time_field_callback' ),
            'wc-buyadwiser-feed',
            'wc_buyadwiser_feed_cache'
        );
    }
    
    /**
     * Sanitize settings input
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Enabled
        $sanitized['enabled'] = isset( $input['enabled'] ) ? 'yes' : 'no';
        
        // IP Whitelist
        $sanitized['ip_whitelist'] = isset( $input['ip_whitelist'] ) ? sanitize_textarea_field( $input['ip_whitelist'] ) : '';
        
        // Limit Results
        $sanitized['limit_results'] = isset( $input['limit_results'] ) ? 'yes' : 'no';
        
        // Results Limit
        $sanitized['results_limit'] = isset( $input['results_limit'] ) ? absint( $input['results_limit'] ) : 1000;
        
        // Enable Caching
        $sanitized['enable_caching'] = isset( $input['enable_caching'] ) ? 'yes' : 'no';
        
        // Cache Time
        $sanitized['cache_time'] = isset( $input['cache_time'] ) ? absint( $input['cache_time'] ) : 15;
        
        // Clear cache if settings changed
        WC_BuyAdwiser_Feed()->clear_xml_feed_cache();
        
        return $sanitized;
    }
    
    /**
     * General settings section callback
     */
    public function general_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure general settings for the BuyAdwiser XML feed.', 'wc-buyadwiser-feed' ) . '</p>';
        echo '<p>' . sprintf(
            esc_html__( 'Your feed URL: %s', 'wc-buyadwiser-feed' ),
            '<code>' . esc_url( add_query_arg( array( 'generate_buyadwiser_feed' => 'true' ), site_url() ) ) . '</code>'
        ) . '</p>';
    }
    
    /**
     * Cache settings section callback
     */
    public function cache_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure caching settings to improve performance.', 'wc-buyadwiser-feed' ) . '</p>';
    }
    
    /**
     * Enable field callback
     */
    public function enabled_field_callback() {
        $options = get_option( 'wc_buyadwiser_feed_options' );
        $enabled = isset( $options['enabled'] ) ? $options['enabled'] : 'yes';
        
        ?>
        <label>
            <input type="checkbox" name="wc_buyadwiser_feed_options[enabled]" <?php checked( $enabled, 'yes' ); ?> value="yes">
            <?php esc_html_e( 'Enable BuyAdwiser XML feed', 'wc-buyadwiser-feed' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'When disabled, the feed will not be accessible.', 'wc-buyadwiser-feed' ); ?></p>
        <?php
    }
    
    /**
     * IP Whitelist field callback
     */
    public function ip_whitelist_field_callback() {
        $options = get_option( 'wc_buyadwiser_feed_options' );
        $ip_whitelist = isset( $options['ip_whitelist'] ) ? $options['ip_whitelist'] : '';
        
        ?>
        <textarea name="wc_buyadwiser_feed_options[ip_whitelist]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( $ip_whitelist ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Enter IP addresses (one per line) to restrict feed access. Leave empty to allow all IPs.', 'wc-buyadwiser-feed' ); ?>
            <br>
            <?php esc_html_e( 'Your current IP: ', 'wc-buyadwiser-feed' ); ?><code><?php echo esc_html( $this->get_current_ip() ); ?></code>
        </p>
        <?php
    }
    
    /**
     * Limit Results field callback
     */
    public function limit_results_field_callback() {
        $options = get_option( 'wc_buyadwiser_feed_options' );
        $limit_results = isset( $options['limit_results'] ) ? $options['limit_results'] : 'no';
        $results_limit = isset( $options['results_limit'] ) ? absint( $options['results_limit'] ) : 1000;
        
        ?>
        <label>
            <input type="checkbox" name="wc_buyadwiser_feed_options[limit_results]" <?php checked( $limit_results, 'yes' ); ?> value="yes">
            <?php esc_html_e( 'Limit the number of products in feed', 'wc-buyadwiser-feed' ); ?>
        </label>
        <br><br>
        <label>
            <?php esc_html_e( 'Maximum number of products:', 'wc-buyadwiser-feed' ); ?>
            <input type="number" name="wc_buyadwiser_feed_options[results_limit]" value="<?php echo esc_attr( $results_limit ); ?>" min="1" step="1" class="small-text">
        </label>
        <p class="description"><?php esc_html_e( 'Limits the feed to the specified number of newest products.', 'wc-buyadwiser-feed' ); ?></p>
        <?php
    }
    
    /**
     * Enable Caching field callback
     */
    public function enable_caching_field_callback() {
        $options = get_option( 'wc_buyadwiser_feed_options' );
        $enable_caching = isset( $options['enable_caching'] ) ? $options['enable_caching'] : 'yes';
        
        ?>
        <label>
            <input type="checkbox" name="wc_buyadwiser_feed_options[enable_caching]" <?php checked( $enable_caching, 'yes' ); ?> value="yes">
            <?php esc_html_e( 'Enable feed caching (recommended)', 'wc-buyadwiser-feed' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Caching significantly improves performance for large stores.', 'wc-buyadwiser-feed' ); ?></p>
        <?php
    }
    
    /**
     * Cache Time field callback
     */
    public function cache_time_field_callback() {
        $options = get_option( 'wc_buyadwiser_feed_options' );
        $cache_time = isset( $options['cache_time'] ) ? absint( $options['cache_time'] ) : 15;
        
        ?>
        <input type="number" name="wc_buyadwiser_feed_options[cache_time]" value="<?php echo esc_attr( $cache_time ); ?>" min="1" step="1" class="small-text">
        <p class="description"><?php esc_html_e( 'How long to store the cached feed (in minutes).', 'wc-buyadwiser-feed' ); ?></p>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wc-buyadwiser-feed', 'action' => 'clear_cache' ), admin_url( 'admin.php' ) ), 'wc_buyadwiser_feed_clear_cache' ) ); ?>" class="button">
                <?php esc_html_e( 'Clear Cache Now', 'wc-buyadwiser-feed' ); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-buyadwiser-feed' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wc_buyadwiser_feed_settings' );
                do_settings_sections( 'wc-buyadwiser-feed' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links
     * @return array Modified action links
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-buyadwiser-feed' ) ) . '">' . esc_html__( 'Settings', 'wc-buyadwiser-feed' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
    
    /**
     * Handle manual cache clear action
     */
    public function handle_manual_cache_clear() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-buyadwiser-feed' && 
             isset( $_GET['action'] ) && $_GET['action'] === 'clear_cache' ) {
            
            // Verify nonce
            check_admin_referer( 'wc_buyadwiser_feed_clear_cache' );
            
            // Clear cache
            WC_BuyAdwiser_Feed()->clear_xml_feed_cache();
            
            // Add admin notice
            set_transient( 'wc_buyadwiser_feed_cache_cleared', true, 30 );
            
            // Redirect to settings page
            wp_safe_redirect( admin_url( 'admin.php?page=wc-buyadwiser-feed' ) );
            exit;
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Cache cleared notice
        if ( get_transient( 'wc_buyadwiser_feed_cache_cleared' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'BuyAdwiser feed cache has been cleared.', 'wc-buyadwiser-feed' ); ?></p>
            </div>
            <?php
            delete_transient( 'wc_buyadwiser_feed_cache_cleared' );
        }
    }
    
    /**
     * Get current user IP
     *
     * @return string
     */
    private function get_current_ip() {
        $ip = '';
        
        // Check for proxy
        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } 
        // Check remote address
        elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        return $ip;
    }
}
