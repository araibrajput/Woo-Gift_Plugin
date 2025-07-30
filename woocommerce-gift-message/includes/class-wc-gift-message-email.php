<?php
/**
 * Email functionality for WooCommerce Gift Message Plugin
 * 
 * This class handles all email-related functionality including:
 * - Adding gift messages to order confirmation emails
 * - Customizing email templates
 * - Email formatting and display
 *
 * @package WC_Gift_Message
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gift_Message_Email Class
 * 
 * Handles email functionality for gift messages
 */
class WC_Gift_Message_Email {
    
    /**
     * Constructor - Initialize email hooks
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for email functionality
     */
    private function init_hooks() {
        // Add gift messages to order confirmation emails
        add_action('woocommerce_email_order_details', array($this, 'add_gift_messages_to_email'), 15, 4);
        
        // Add gift messages to order item meta in emails
        add_action('woocommerce_order_item_meta_end', array($this, 'display_gift_message_in_email'), 10, 4);
        
        // Customize email order items table
        add_filter('woocommerce_email_order_items_args', array($this, 'customize_email_order_items'));
        
        // Apply extensibility hook
        do_action('wc_gift_message_email_init', $this);
    }
    
    /**
     * Add gift messages section to order confirmation emails
     * 
     * This creates a dedicated section for all gift messages in the order,
     * displayed after the order details but before the order meta.
     * 
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Whether this is sent to admin
     * @param bool $plain_text Whether this is plain text email
     * @param WC_Email $email Email object
     */
    public function add_gift_messages_to_email($order, $sent_to_admin, $plain_text, $email) {
        // Only add to customer emails (not admin notifications)
        if ($sent_to_admin) {
            return;
        }
        
        // Only add to specific email types
        $allowed_email_types = apply_filters('wc_gift_message_email_types', array(
            'customer_completed_order',
            'customer_invoice',
            'customer_processing_order'
        ));
        
        if (!in_array($email->id, $allowed_email_types)) {
            return;
        }
        
        $gift_messages = WC_Gift_Message_Order::get_order_gift_messages($order);
        
        if (empty($gift_messages)) {
            return;
        }
        
        if ($plain_text) {
            $this->display_gift_messages_plain_text($gift_messages, $order);
        } else {
            $this->display_gift_messages_html($gift_messages, $order);
        }
    }
    
    /**
     * Display gift message in email order item meta
     * 
     * @param int $item_id Order item ID
     * @param WC_Order_Item $item Order item object
     * @param WC_Order $order Order object
     * @param bool $plain_text Whether this is plain text email
     */
    public function display_gift_message_in_email($item_id, $item, $order, $plain_text) {
        $gift_message = $item->get_meta(WC_Gift_Message_Frontend::get_meta_key());
        
        if (empty($gift_message)) {
            return;
        }
        
        $formatted_message = $this->format_gift_message_for_email($gift_message, $plain_text);
        
        if ($plain_text) {
            printf(
                "\n%s: %s\n",
                __('Gift Message', 'wc-gift-message'),
                $formatted_message
            );
        } else {
            printf(
                '<div class="wc-gift-message-email"><strong>%s:</strong> %s</div>',
                esc_html__('Gift Message', 'wc-gift-message'),
                wp_kses_post($formatted_message)
            );
        }
    }
    
    /**
     * Customize email order items table arguments
     * 
     * @param array $args Order items table arguments
     * @return array Updated arguments
     */
    public function customize_email_order_items($args) {
        // Add our custom CSS class for styling
        if (isset($args['table_class'])) {
            $args['table_class'] .= ' wc-gift-message-email-table';
        } else {
            $args['table_class'] = 'wc-gift-message-email-table';
        }
        
        return $args;
    }
    
    /**
     * Display gift messages in HTML email format
     * 
     * @param array $gift_messages Array of gift message data
     * @param WC_Order $order Order object
     */
    private function display_gift_messages_html($gift_messages, $order) {
        ?>
        <div class="wc-gift-messages-email-section">
            <h2 style="color: #557da1; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 16px 0 8px; text-align: left;">
                <?php esc_html_e('Gift Messages', 'wc-gift-message'); ?>
            </h2>
            
            <div class="gift-messages-list" style="margin-bottom: 40px;">
                <?php foreach ($gift_messages as $gift_data): ?>
                    <div class="gift-message-item" style="margin-bottom: 20px; padding: 15px; background-color: #f8f8f8; border-left: 4px solid #557da1;">
                        <h3 style="color: #333; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; font-weight: bold; margin: 0 0 8px;">
                            <?php echo esc_html($gift_data['product_name']); ?>
                            <?php if ($gift_data['quantity'] > 1): ?>
                                <span style="font-weight: normal; color: #666;">(×<?php echo intval($gift_data['quantity']); ?>)</span>
                            <?php endif; ?>
                        </h3>
                        <div class="gift-message-content" style="color: #636363; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; margin: 0;">
                            <?php echo wp_kses_post($this->format_gift_message_for_email($gift_data['message'], false)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display gift messages in plain text email format
     * 
     * @param array $gift_messages Array of gift message data
     * @param WC_Order $order Order object
     */
    private function display_gift_messages_plain_text($gift_messages, $order) {
        echo "\n" . strtoupper(__('Gift Messages', 'wc-gift-message')) . "\n";
        echo str_repeat('=', strlen(__('Gift Messages', 'wc-gift-message'))) . "\n\n";
        
        foreach ($gift_messages as $gift_data) {
            echo $gift_data['product_name'];
            if ($gift_data['quantity'] > 1) {
                echo ' (×' . intval($gift_data['quantity']) . ')';
            }
            echo ":\n";
            echo $this->format_gift_message_for_email($gift_data['message'], true) . "\n\n";
        }
        
        echo str_repeat('-', 50) . "\n\n";
    }
    
    /**
     * Format gift message for email display
     * 
     * @param string $message Raw gift message
     * @param bool $plain_text Whether this is for plain text email
     * @return string Formatted message
     */
    private function format_gift_message_for_email($message, $plain_text = false) {
        // Sanitize the message
        $message = sanitize_textarea_field($message);
        $message = trim($message);
        
        if ($plain_text) {
            // For plain text emails, just return the message as-is
            return $message;
        } else {
            // For HTML emails, convert line breaks to HTML and add some styling
            $message = nl2br($message);
            
            // Wrap in a styled container
            $message = sprintf(
                '<div style="font-style: italic; padding: 8px 0; border-radius: 3px;">%s</div>',
                $message
            );
            
            return apply_filters('wc_gift_message_email_format', $message);
        }
    }
    
    /**
     * Add custom CSS styles to emails
     * 
     * This method can be called to add custom CSS for gift message styling
     * in HTML emails. It's designed to be used with email template overrides.
     * 
     * @return string CSS styles
     */
    public static function get_email_styles() {
        $styles = "
        .wc-gift-message-email {
            margin: 8px 0;
            padding: 8px 12px;
            background-color: #f9f9f9;
            border-left: 3px solid #557da1;
            font-style: italic;
        }
        
        .wc-gift-messages-email-section {
            margin: 20px 0;
        }
        
        .gift-message-item {
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        
        .gift-message-content {
            font-style: italic;
            color: #666;
        }
        
        @media only screen and (max-width: 600px) {
            .wc-gift-messages-email-section {
                margin: 15px 0;
            }
            
            .gift-message-item {
                padding: 10px;
                margin-bottom: 10px;
            }
        }
        ";
        
        return apply_filters('wc_gift_message_email_styles', $styles);
    }
    
    /**
     * Check if gift messages should be shown in a specific email
     * 
     * @param string $email_id Email ID
     * @param WC_Order $order Order object
     * @return bool Whether to show gift messages
     */
    public static function should_show_in_email($email_id, $order) {
        // Don't show in admin emails by default
        $admin_emails = array(
            'new_order',
            'cancelled_order',
            'failed_order'
        );
        
        if (in_array($email_id, $admin_emails)) {
            return apply_filters('wc_gift_message_show_in_admin_emails', false, $email_id, $order);
        }
        
        // Show in customer emails by default
        return apply_filters('wc_gift_message_show_in_customer_emails', true, $email_id, $order);
    }
}