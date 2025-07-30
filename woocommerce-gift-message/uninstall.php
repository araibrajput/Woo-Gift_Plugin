<?php
/**
 * Uninstall script for WooCommerce Gift Message Plugin
 * 
 * This file is executed when the plugin is deleted from the WordPress admin.
 * It handles cleanup of plugin data if the user chooses to remove it completely.
 *
 * @package WC_Gift_Message
 */

// Prevent direct access and ensure this is a legitimate uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 * 
 * Note: We preserve gift message data by default since it's valuable order information.
 * Store owners can manually clean this up if needed through the database.
 */
class WC_Gift_Message_Uninstaller {
    
    /**
     * Run the uninstall process
     */
    public static function uninstall() {
        // Clean up any transients or cached data
        self::clean_transients();
        
        // Remove any custom options (if we had any)
        self::clean_options();
        
        // Note: We intentionally do NOT remove gift message meta data
        // as it's part of order history and should be preserved
    }
    
    /**
     * Clean up transient data
     */
    private static function clean_transients() {
        // Remove any plugin-specific transients
        delete_transient('wc_gift_message_cache');
        
        // Clean up any site transients
        delete_site_transient('wc_gift_message_version_check');
    }
    
    /**
     * Clean up plugin options
     */
    private static function clean_options() {
        // Remove plugin version option if we had one
        delete_option('wc_gift_message_version');
        
        // Remove any other plugin-specific options
        delete_option('wc_gift_message_settings');
    }
    
    /**
     * Optional: Clean ALL gift message data (dangerous!)
     * This method is not called by default to preserve order data
     */
    private static function clean_all_gift_message_data() {
        global $wpdb;
        
        // WARNING: This will permanently delete all gift message data
        // Only uncomment if you really want to remove everything
        /*
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wc_gift_message' 
             OR meta_key = '_wc_gift_message_display'"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta 
             WHERE meta_key = '_wc_gift_message' 
             OR meta_key = '_wc_gift_message_display'"
        );
        */
    }
}

// Run the uninstall process
WC_Gift_Message_Uninstaller::uninstall();