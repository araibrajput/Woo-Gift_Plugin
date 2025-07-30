<?php
/**
 * Admin functionality for WooCommerce Gift Message Plugin
 * 
 * This class handles all admin-related functionality including:
 * - Adding gift message column to orders list
 * - Displaying gift messages in order details
 * - Admin order management features
 *
 * @package WC_Gift_Message
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gift_Message_Admin Class
 * 
 * Handles admin functionality for gift messages
 */
class WC_Gift_Message_Admin {
    
    /**
     * Constructor - Initialize admin hooks
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for admin functionality
     */
    private function init_hooks() {
        // Add gift message column to orders list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_gift_message_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_gift_message_column_content'), 10, 2);
        
        // Make the gift message column sortable
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_gift_message_column_sortable'));
        
        // Display gift messages in order details page
        add_action('woocommerce_admin_order_item_headers', array($this, 'add_gift_message_header'));
        add_action('woocommerce_admin_order_item_values', array($this, 'display_gift_message_in_admin_order'), 10, 3);
        
        // Add gift message meta box to order edit page
        add_action('add_meta_boxes', array($this, 'add_gift_message_meta_box'));
        
        // Add bulk action to export gift messages
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_export_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_export_action'), 10, 3);
        
        // Apply extensibility hook
        do_action('wc_gift_message_admin_init', $this);
    }
    
    /**
     * Add gift message column to orders list
     * 
     * @param array $columns Existing columns
     * @return array Updated columns
     */
    public function add_gift_message_column($columns) {
        // Insert the gift message column before the actions column
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            // Add all existing columns
            $new_columns[$key] = $column;
            
            // Add gift message column after order status
            if ('order_status' === $key) {
                $new_columns['gift_message'] = __('Gift Message', 'wc-gift-message');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display content for gift message column
     * 
     * @param string $column Column name
     * @param int $post_id Order ID
     */
    public function display_gift_message_column_content($column, $post_id) {
        if ('gift_message' !== $column) {
            return;
        }
        
        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }
        
        $gift_messages = WC_Gift_Message_Order::get_order_gift_messages($order);
        
        if (empty($gift_messages)) {
            echo '<span class="na">–</span>';
            return;
        }
        
        // Count total gift messages
        $total_messages = count($gift_messages);
        
        if ($total_messages === 1) {
            // Single gift message - show preview
            $message = $gift_messages[0]['message'];
            $preview = $this->get_message_preview($message);
            printf(
                '<span class="gift-message-preview" title="%s">%s</span>',
                esc_attr($message),
                esc_html($preview)
            );
        } else {
            // Multiple gift messages - show count
            printf(
                '<span class="gift-messages-count" title="%s">%d %s</span>',
                esc_attr(__('This order contains multiple gift messages', 'wc-gift-message')),
                $total_messages,
                esc_html(_n('message', 'messages', $total_messages, 'wc-gift-message'))
            );
        }
    }
    
    /**
     * Make gift message column sortable
     * 
     * @param array $columns Sortable columns
     * @return array Updated sortable columns
     */
    public function make_gift_message_column_sortable($columns) {
        $columns['gift_message'] = 'gift_message';
        return $columns;
    }
    
    /**
     * Add gift message header to order items table
     */
    public function add_gift_message_header() {
        echo '<th class="gift-message-header">' . esc_html__('Gift Message', 'wc-gift-message') . '</th>';
    }
    
    /**
     * Display gift message in admin order details
     * 
     * @param WC_Product $product Product object
     * @param WC_Order_Item $item Order item object
     * @param int $item_id Order item ID
     */
    public function display_gift_message_in_admin_order($product, $item, $item_id) {
        $gift_message = $item->get_meta(WC_Gift_Message_Frontend::get_meta_key());
        
        echo '<td class="gift-message-cell">';
        
        if (!empty($gift_message)) {
            printf(
                '<div class="gift-message-content"><span class="gift-message-text">%s</span></div>',
                esc_html($gift_message)
            );
        } else {
            echo '<span class="na">–</span>';
        }
        
        echo '</td>';
    }
    
    /**
     * Add gift message meta box to order edit page
     */
    public function add_gift_message_meta_box() {
        add_meta_box(
            'wc-gift-message-details',
            __('Gift Messages', 'wc-gift-message'),
            array($this, 'render_gift_message_meta_box'),
            'shop_order',
            'normal',
            'default'
        );
    }
    
    /**
     * Render gift message meta box content
     * 
     * @param WP_Post $post Order post object
     */
    public function render_gift_message_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        $gift_messages = WC_Gift_Message_Order::get_order_gift_messages($order);
        
        if (empty($gift_messages)) {
            echo '<p>' . esc_html__('This order does not contain any gift messages.', 'wc-gift-message') . '</p>';
            return;
        }
        
        echo '<div class="gift-messages-meta-box">';
        
        foreach ($gift_messages as $gift_data) {
            echo '<div class="gift-message-item">';
            printf(
                '<h4>%s <span class="quantity">(×%d)</span></h4>',
                esc_html($gift_data['product_name']),
                intval($gift_data['quantity'])
            );
            printf(
                '<div class="gift-message-text">%s</div>',
                wp_kses_post(nl2br(esc_html($gift_data['message'])))
            );
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add action for copying messages
        echo '<div class="gift-message-actions">';
        printf(
            '<button type="button" class="button" onclick="wc_gift_message_copy_all()">%s</button>',
            esc_html__('Copy All Messages', 'wc-gift-message')
        );
        echo '</div>';
        
        // Add JavaScript for copy functionality
        ?>
        <script type="text/javascript">
        function wc_gift_message_copy_all() {
            var messages = [];
            jQuery('.gift-message-item').each(function() {
                var productName = jQuery(this).find('h4').text();
                var message = jQuery(this).find('.gift-message-text').text();
                messages.push(productName + ': ' + message);
            });
            
            var allMessages = messages.join('\n\n');
            
            // Create temporary textarea to copy text
            var temp = jQuery('<textarea>');
            jQuery('body').append(temp);
            temp.val(allMessages).select();
            document.execCommand('copy');
            temp.remove();
            
            // Show feedback
            alert('<?php echo esc_js(__('Gift messages copied to clipboard!', 'wc-gift-message')); ?>');
        }
        </script>
        <?php
    }
    
    /**
     * Add bulk action to export gift messages
     * 
     * @param array $actions Existing bulk actions
     * @return array Updated bulk actions
     */
    public function add_bulk_export_action($actions) {
        $actions['export_gift_messages'] = __('Export Gift Messages', 'wc-gift-message');
        return $actions;
    }
    
    /**
     * Handle bulk export of gift messages
     * 
     * @param string $redirect_to Redirect URL
     * @param string $doaction Action name
     * @param array $post_ids Selected post IDs
     * @return string Updated redirect URL
     */
    public function handle_bulk_export_action($redirect_to, $doaction, $post_ids) {
        if ('export_gift_messages' !== $doaction) {
            return $redirect_to;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            return $redirect_to;
        }
        
        $this->export_gift_messages($post_ids);
        
        $redirect_to = add_query_arg('gift_messages_exported', count($post_ids), $redirect_to);
        return $redirect_to;
    }
    
    /**
     * Export gift messages to CSV
     * 
     * @param array $order_ids Array of order IDs
     */
    private function export_gift_messages($order_ids) {
        $filename = 'gift-messages-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array(
            'Order ID',
            'Order Date',
            'Customer Name',
            'Customer Email',
            'Product Name',
            'Quantity',
            'Gift Message'
        ));
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            
            $gift_messages = WC_Gift_Message_Order::get_order_gift_messages($order);
            
            if (empty($gift_messages)) {
                continue;
            }
            
            foreach ($gift_messages as $gift_data) {
                fputcsv($output, array(
                    $order->get_id(),
                    $order->get_date_created()->format('Y-m-d H:i:s'),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    $order->get_billing_email(),
                    $gift_data['product_name'],
                    $gift_data['quantity'],
                    $gift_data['message']
                ));
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get preview text for gift message
     * 
     * @param string $message Full gift message
     * @param int $length Maximum preview length
     * @return string Preview text
     */
    private function get_message_preview($message, $length = 50) {
        $message = sanitize_textarea_field($message);
        $message = trim($message);
        
        if (strlen($message) <= $length) {
            return $message;
        }
        
        return substr($message, 0, $length) . '...';
    }
}