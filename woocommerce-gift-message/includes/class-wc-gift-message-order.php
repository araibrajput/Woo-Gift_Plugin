<?php
/**
 * Order functionality for WooCommerce Gift Message Plugin
 * 
 * This class handles all order-related functionality including:
 * - Displaying gift messages on order confirmation pages
 * - Showing gift messages in My Account order details
 * - Managing order item meta data
 *
 * @package WC_Gift_Message
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gift_Message_Order Class
 * 
 * Handles order-related display of gift messages
 */
class WC_Gift_Message_Order {
    
    /**
     * Constructor - Initialize order hooks
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for order functionality
     */
    private function init_hooks() {
        // Display gift message on order confirmation page (thank you page)
        add_action('woocommerce_order_item_meta_end', array($this, 'display_gift_message_on_order_confirmation'), 10, 4);
        
        // Display gift message in My Account order details
        add_action('woocommerce_order_item_meta_end', array($this, 'display_gift_message_in_my_account'), 10, 4);
        
        // Display gift message on order received page
        add_action('woocommerce_thankyou', array($this, 'display_gift_messages_on_thankyou_page'), 10, 1);
        
        // Apply extensibility hook
        do_action('wc_gift_message_order_init', $this);
    }
    
    /**
     * Display gift message on order confirmation page
     * 
     * @param int $item_id Order item ID
     * @param WC_Order_Item $item Order item object
     * @param WC_Order $order Order object
     * @param bool $plain_text Whether this is plain text (for emails)
     */
    public function display_gift_message_on_order_confirmation($item_id, $item, $order, $plain_text = false) {
        // Only show on order confirmation page (not in admin or emails)
        if (is_admin() || $plain_text) {
            return;
        }
        
        // Only show on thank you page
        if (!is_wc_endpoint_url('order-received')) {
            return;
        }
        
        $this->display_gift_message_for_item($item, $plain_text, 'order-confirmation');
    }
    
    /**
     * Display gift message in My Account order details
     * 
     * @param int $item_id Order item ID
     * @param WC_Order_Item $item Order item object
     * @param WC_Order $order Order object
     * @param bool $plain_text Whether this is plain text
     */
    public function display_gift_message_in_my_account($item_id, $item, $order, $plain_text = false) {
        // Only show in My Account area
        if (!is_account_page() || is_admin() || $plain_text) {
            return;
        }
        
        // Only show on view order endpoint
        if (!is_wc_endpoint_url('view-order')) {
            return;
        }
        
        $this->display_gift_message_for_item($item, $plain_text, 'my-account');
    }
    
    /**
     * Display gift messages on the thank you page
     * 
     * @param int $order_id Order ID
     */
    public function display_gift_messages_on_thankyou_page($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $has_gift_messages = false;
        $gift_messages = array();
        
        // Collect all gift messages from order items
        foreach ($order->get_items() as $item_id => $item) {
            $gift_message = $this->get_gift_message_from_order_item($item);
            if ($gift_message) {
                $has_gift_messages = true;
                $gift_messages[] = array(
                    'product_name' => $item->get_name(),
                    'message' => $gift_message,
                    'item' => $item
                );
            }
        }
        
        // Display summary of gift messages if any exist
        if ($has_gift_messages) {
            $this->display_gift_messages_summary($gift_messages, $order);
        }
    }
    
    /**
     * Display gift message for a specific order item
     * 
     * @param WC_Order_Item $item Order item object
     * @param bool $plain_text Whether this is plain text
     * @param string $context Display context (order-confirmation, my-account, etc.)
     */
    private function display_gift_message_for_item($item, $plain_text = false, $context = '') {
        $gift_message = $this->get_gift_message_from_order_item($item);
        
        if (!$gift_message) {
            return;
        }
        
        // Format the message for display
        $formatted_message = $this->format_gift_message_for_display($gift_message, $plain_text);
        
        if ($plain_text) {
            // Plain text format for emails
            echo "\n" . __('Gift Message:', 'wc-gift-message') . ' ' . $formatted_message . "\n";
        } else {
            // HTML format for web pages
            $css_class = 'wc-gift-message-display';
            if ($context) {
                $css_class .= ' wc-gift-message-' . sanitize_html_class($context);
            }
            
            printf(
                '<div class="%s"><strong>%s:</strong> <span class="gift-message-text">%s</span></div>',
                esc_attr($css_class),
                esc_html__('Gift Message', 'wc-gift-message'),
                wp_kses_post($formatted_message)
            );
        }
    }
    
    /**
     * Display summary of all gift messages on thank you page
     * 
     * @param array $gift_messages Array of gift message data
     * @param WC_Order $order Order object
     */
    private function display_gift_messages_summary($gift_messages, $order) {
        ?>
        <div class="wc-gift-messages-summary">
            <h3><?php esc_html_e('Gift Messages', 'wc-gift-message'); ?></h3>
            <div class="gift-messages-list">
                <?php foreach ($gift_messages as $gift_data): ?>
                    <div class="gift-message-item">
                        <h4 class="product-name"><?php echo esc_html($gift_data['product_name']); ?></h4>
                        <div class="gift-message-content">
                            <?php echo wp_kses_post($this->format_gift_message_for_display($gift_data['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get gift message from order item
     * 
     * @param WC_Order_Item $item Order item object
     * @return string|false Gift message or false if not found
     */
    public function get_gift_message_from_order_item($item) {
        // Try to get the gift message from item meta
        $gift_message = $item->get_meta(WC_Gift_Message_Frontend::get_meta_key());
        
        if (empty($gift_message)) {
            // Fallback: try the display version
            $gift_message = $item->get_meta('_wc_gift_message_display');
        }
        
        return !empty($gift_message) ? sanitize_textarea_field($gift_message) : false;
    }
    
    /**
     * Format gift message for display
     * 
     * @param string $message Raw gift message
     * @param bool $plain_text Whether this is for plain text display
     * @return string Formatted message
     */
    private function format_gift_message_for_display($message, $plain_text = false) {
        // Sanitize the message
        $message = sanitize_textarea_field($message);
        $message = trim($message);
        
        if ($plain_text) {
            // For plain text (emails), just return the sanitized message
            return $message;
        } else {
            // For HTML display, convert line breaks and apply formatting
            $message = nl2br($message);
            return apply_filters('wc_gift_message_order_display_format', $message);
        }
    }
    
    /**
     * Check if an order has any gift messages
     * 
     * @param WC_Order $order Order object
     * @return bool True if order has gift messages
     */
    public static function order_has_gift_messages($order) {
        if (!$order) {
            return false;
        }
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta(WC_Gift_Message_Frontend::get_meta_key())) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all gift messages from an order
     * 
     * @param WC_Order $order Order object
     * @return array Array of gift messages with product names
     */
    public static function get_order_gift_messages($order) {
        $gift_messages = array();
        
        if (!$order) {
            return $gift_messages;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $gift_message = $item->get_meta(WC_Gift_Message_Frontend::get_meta_key());
            if (!empty($gift_message)) {
                $gift_messages[] = array(
                    'item_id' => $item_id,
                    'product_name' => $item->get_name(),
                    'message' => sanitize_textarea_field($gift_message),
                    'quantity' => $item->get_quantity()
                );
            }
        }
        
        return $gift_messages;
    }
}