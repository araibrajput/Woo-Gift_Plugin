<?php
/**
 * Cart functionality for WooCommerce Gift Message Plugin
 * 
 * This class handles all cart-related functionality including:
 * - Displaying gift messages in cart
 * - Showing gift messages on checkout page
 * - Managing cart item meta data
 *
 * @package WC_Gift_Message
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gift_Message_Cart Class
 * 
 * Handles cart and checkout display of gift messages
 */
class WC_Gift_Message_Cart {
    
    /**
     * Constructor - Initialize cart hooks
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for cart functionality
     */
    private function init_hooks() {
        // Display gift message in cart item data
        add_filter('woocommerce_get_item_data', array($this, 'display_gift_message_in_cart'), 10, 2);
        
        // Add gift message to cart item meta (for session storage)
        add_action('woocommerce_add_to_cart', array($this, 'add_gift_message_to_cart_session'), 10, 6);
        
        // Save gift message to order when checkout is processed
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_gift_message_to_order_item'), 10, 4);
        
        // Display gift message on checkout review
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'display_gift_message_on_checkout'), 10, 3);
        
        // Apply extensibility hook
        do_action('wc_gift_message_cart_init', $this);
    }
    
    /**
     * Display gift message in cart item data
     * 
     * This function adds the gift message to the cart item's displayed
     * metadata so customers can see their message in the cart.
     * 
     * @param array $item_data Existing item data
     * @param array $cart_item Cart item array
     * @return array Updated item data
     */
    public function display_gift_message_in_cart($item_data, $cart_item) {
        // Check if this cart item has a gift message
        if (isset($cart_item[WC_Gift_Message_Frontend::get_meta_key()])) {
            $gift_message = $cart_item[WC_Gift_Message_Frontend::get_meta_key()];
            
            // Only display if message is not empty
            if (!empty(trim($gift_message))) {
                $item_data[] = array(
                    'name'    => __('Gift Message', 'wc-gift-message'),
                    'value'   => $this->format_gift_message_for_display($gift_message),
                    'display' => $this->format_gift_message_for_display($gift_message),
                );
            }
        }
        
        return $item_data;
    }
    
    /**
     * Add gift message to cart session when item is added
     * 
     * @param string $cart_item_key Cart item key
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variation Variation data
     * @param array $cart_item_data Cart item data
     */
    public function add_gift_message_to_cart_session($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Check if cart item has gift message
        if (isset($cart_item_data[WC_Gift_Message_Frontend::get_meta_key()])) {
            $gift_message = $cart_item_data[WC_Gift_Message_Frontend::get_meta_key()];
            
            // Store in cart session for persistence
            WC()->cart->cart_contents[$cart_item_key][WC_Gift_Message_Frontend::get_meta_key()] = $gift_message;
        }
    }
    
    /**
     * Save gift message to order item when checkout is processed
     * 
     * This is the crucial function that transfers the gift message
     * from the cart to the actual order in the database.
     * 
     * @param WC_Order_Item_Product $item Order item object
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function save_gift_message_to_order_item($item, $cart_item_key, $values, $order) {
        // Check if cart item has gift message
        if (isset($values[WC_Gift_Message_Frontend::get_meta_key()])) {
            $gift_message = $values[WC_Gift_Message_Frontend::get_meta_key()];
            
            // Sanitize and save to order item meta
            if (!empty(trim($gift_message))) {
                $item->add_meta_data(
                    WC_Gift_Message_Frontend::get_meta_key(),
                    sanitize_textarea_field($gift_message),
                    true // Make it unique
                );
                
                // Also add a display-friendly version
                $item->add_meta_data(
                    '_wc_gift_message_display',
                    $this->format_gift_message_for_display($gift_message),
                    true
                );
            }
        }
    }
    
    /**
     * Display gift message on checkout review
     * 
     * @param string $quantity_html Quantity HTML
     * @param array $cart_item Cart item
     * @param string $cart_item_key Cart item key
     * @return string Updated quantity HTML
     */
    public function display_gift_message_on_checkout($quantity_html, $cart_item, $cart_item_key) {
        // Check if this cart item has a gift message
        if (isset($cart_item[WC_Gift_Message_Frontend::get_meta_key()])) {
            $gift_message = $cart_item[WC_Gift_Message_Frontend::get_meta_key()];
            
            if (!empty(trim($gift_message))) {
                $formatted_message = $this->format_gift_message_for_display($gift_message);
                $quantity_html .= sprintf(
                    '<div class="wc-gift-message-checkout"><small><strong>%s:</strong> %s</small></div>',
                    esc_html__('Gift Message', 'wc-gift-message'),
                    esc_html($formatted_message)
                );
            }
        }
        
        return $quantity_html;
    }
    
    /**
     * Format gift message for display
     * 
     * This function ensures consistent formatting of gift messages
     * across all display contexts (cart, checkout, orders, etc.)
     * 
     * @param string $message Raw gift message
     * @return string Formatted message
     */
    private function format_gift_message_for_display($message) {
        // Sanitize the message
        $message = sanitize_textarea_field($message);
        
        // Trim whitespace
        $message = trim($message);
        
        // Limit length for display (though it should already be limited)
        $max_display_length = apply_filters('wc_gift_message_display_max_length', 150);
        if (strlen($message) > $max_display_length) {
            $message = substr($message, 0, $max_display_length) . '...';
        }
        
        // Convert line breaks to HTML breaks for display
        $message = nl2br($message);
        
        // Allow developers to customize formatting
        return apply_filters('wc_gift_message_formatted_display', $message);
    }
    
    /**
     * Get gift message from cart item
     * 
     * @param array $cart_item Cart item data
     * @return string|false Gift message or false if not found
     */
    public static function get_gift_message_from_cart_item($cart_item) {
        if (isset($cart_item[WC_Gift_Message_Frontend::get_meta_key()])) {
            return sanitize_textarea_field($cart_item[WC_Gift_Message_Frontend::get_meta_key()]);
        }
        
        return false;
    }
    
    /**
     * Check if cart has any items with gift messages
     * 
     * @return bool True if any cart items have gift messages
     */
    public static function cart_has_gift_messages() {
        if (!WC()->cart) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (self::get_gift_message_from_cart_item($cart_item)) {
                return true;
            }
        }
        
        return false;
    }
}