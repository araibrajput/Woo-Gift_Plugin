<?php
/**
 * Plugin Name: WooCommerce Gift Message
 * Plugin URI: https://github.com/areeb/woocommerce-gift-message
 * Description: Adds a gift message field to WooCommerce products, allowing customers to include personalized messages with their orders.
 * Version: 1.0.0
 * Author: Areeb
 * Author URI: https://areeb.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-gift-message
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_GIFT_MESSAGE_VERSION', '1.0.0');
define('WC_GIFT_MESSAGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_GIFT_MESSAGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_GIFT_MESSAGE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC_GIFT_MESSAGE_TEXT_DOMAIN', 'wc-gift-message');

/**
 * Main WooCommerce Gift Message Plugin Class
 * 
 * This class initializes the plugin and coordinates all functionality.
 * It ensures WooCommerce is active before loading plugin features.
 */
class WC_Gift_Message_Plugin {
    
    /**
     * Single instance of the plugin
     * @var WC_Gift_Message_Plugin
     */
    private static $instance = null;
    
    /**
     * Get the single instance of the plugin
     * 
     * @return WC_Gift_Message_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize the plugin
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin after all plugins are loaded
     * This ensures WooCommerce is available before we try to use its functions
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize plugin components
        $this->init_hooks();
        
        // Load text domain for internationalization
        load_plugin_textdomain(WC_GIFT_MESSAGE_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Load required plugin files
     */
    private function load_dependencies() {
        require_once WC_GIFT_MESSAGE_PLUGIN_DIR . 'includes/class-wc-gift-message-frontend.php';
        require_once WC_GIFT_MESSAGE_PLUGIN_DIR . 'includes/class-wc-gift-message-cart.php';
        require_once WC_GIFT_MESSAGE_PLUGIN_DIR . 'includes/class-wc-gift-message-order.php';
        require_once WC_GIFT_MESSAGE_PLUGIN_DIR . 'includes/class-wc-gift-message-admin.php';
        require_once WC_GIFT_MESSAGE_PLUGIN_DIR . 'includes/class-wc-gift-message-email.php';
    }
    
    /**
     * Initialize plugin hooks and components
     */
    private function init_hooks() {
        // Initialize frontend functionality
        new WC_Gift_Message_Frontend();
        
        // Initialize cart functionality
        new WC_Gift_Message_Cart();
        
        // Initialize order functionality
        new WC_Gift_Message_Order();
        
        // Initialize admin functionality
        if (is_admin()) {
            new WC_Gift_Message_Admin();
        }
        
        // Initialize email functionality
        new WC_Gift_Message_Email();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WooCommerce Gift Message requires WooCommerce to be installed and active.', 'wc-gift-message'));
        }
        
        // Add any activation tasks here (e.g., create database tables, set default options)
        // For this plugin, we don't need any special activation tasks
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clean up any temporary data if needed
        // Note: We don't remove gift message data as it should persist even if plugin is deactivated
    }
    
    /**
     * Show admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        $message = sprintf(
            /* translators: %s: WooCommerce download link */
            __('WooCommerce Gift Message requires WooCommerce to be installed and active. You can download %s here.', 'wc-gift-message'),
            '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * Enqueue frontend assets (CSS and JavaScript)
     */
    public function enqueue_frontend_assets() {
        // Only load on single product pages and cart/checkout
        if (is_product() || is_cart() || is_checkout()) {
            wp_enqueue_style(
                'wc-gift-message-frontend',
                WC_GIFT_MESSAGE_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WC_GIFT_MESSAGE_VERSION
            );
            
            wp_enqueue_script(
                'wc-gift-message-frontend',
                WC_GIFT_MESSAGE_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WC_GIFT_MESSAGE_VERSION,
                true
            );
            
            // Localize script for AJAX and translations
            wp_localize_script('wc-gift-message-frontend', 'wcGiftMessage', array(
                'maxLength' => 150,
                'remainingText' => __('characters remaining', 'wc-gift-message'),
                'maxReachedText' => __('Maximum character limit reached', 'wc-gift-message')
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on WooCommerce admin pages
        if (in_array($hook, array('edit.php', 'post.php'), true)) {
            $screen = get_current_screen();
            if ($screen && 'shop_order' === $screen->post_type) {
                wp_enqueue_style(
                    'wc-gift-message-admin',
                    WC_GIFT_MESSAGE_PLUGIN_URL . 'assets/css/admin.css',
                    array(),
                    WC_GIFT_MESSAGE_VERSION
                );
            }
        }
    }
}

/**
 * Get the main plugin instance
 * 
 * @return WC_Gift_Message_Plugin
 */
function wc_gift_message() {
    return WC_Gift_Message_Plugin::get_instance();
}

// Initialize the plugin
wc_gift_message();