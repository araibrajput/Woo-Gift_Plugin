<?php
/**
 * Frontend functionality for WooCommerce Gift Message Plugin
 * 
 * This class handles all frontend-related functionality including:
 * - Adding gift message field to product pages
 * - Validating and sanitizing user input
 * - Processing form submissions
 *
 * @package WC_Gift_Message
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gift_Message_Frontend Class
 * 
 * Handles the frontend display and processing of gift message fields
 */
class WC_Gift_Message_Frontend {
    
    /**
     * Maximum length for gift messages
     */
    const MAX_LENGTH = 150;
    
    /**
     * Meta key for storing gift messages
     */
    const META_KEY = '_wc_gift_message';
    
    /**
     * Constructor - Initialize frontend hooks
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for frontend functionality
     */
    private function init_hooks() {
        // Add gift message field to single product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_gift_message_field'));
        
        // Validate gift message when product is added to cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_gift_message'), 10, 3);
        
        // Add gift message to cart item data
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_gift_message_to_cart_item'), 10, 3);
        
        // Apply extensibility hook for other developers
        do_action('wc_gift_message_frontend_init', $this);
    }
    
    /**
     * Display gift message input field on single product pages
     * 
     * This function outputs an HTML form field that allows customers
     * to enter a personalized gift message for their purchase.
     */
    public function display_gift_message_field() {
        global $product;
        
        // Only show on simple and variable products
        if (!$product || !in_array($product->get_type(), array('simple', 'variable'))) {
            return;
        }
        
        // Allow developers to disable the field for specific products
        if (!apply_filters('wc_gift_message_show_field', true, $product)) {
            return;
        }
        
        // Get any existing gift message (useful for product page refreshes)
        $existing_message = isset($_POST['gift_message']) ? sanitize_textarea_field($_POST['gift_message']) : '';
        
        // Start output buffering to capture the HTML
        ob_start();
        ?>
        <div class="wc-gift-message-wrapper">
            <div class="wc-gift-message-field">
                <label for="gift_message">
                    <?php esc_html_e('Gift Message (Optional)', 'wc-gift-message'); ?>
                    <span class="wc-gift-message-help" title="<?php esc_attr_e('Add a personal message to this gift', 'wc-gift-message'); ?>">?</span>
                </label>
                <textarea 
                    id="gift_message" 
                    name="gift_message" 
                    placeholder="<?php esc_attr_e('Enter your gift message here...', 'wc-gift-message'); ?>"
                    maxlength="<?php echo self::MAX_LENGTH; ?>"
                    rows="3"
                    class="wc-gift-message-input"
                ><?php echo esc_textarea($existing_message); ?></textarea>
                <div class="wc-gift-message-counter">
                    <span class="current-length">0</span>
                    <span class="separator">/</span>
                    <span class="max-length"><?php echo self::MAX_LENGTH; ?></span>
                    <span class="remaining-text"><?php esc_html_e('characters', 'wc-gift-message'); ?></span>
                </div>
            </div>
        </div>
        <?php
        
        // Get the output and clean the buffer
        $output = ob_get_clean();
        
        // Allow developers to modify the field HTML
        echo apply_filters('wc_gift_message_field_html', $output, $product, $existing_message);
    }
    
    /**
     * Validate gift message input when adding product to cart
     * 
     * @param bool $passed Validation status
     * @param int $product_id Product ID
     * @param int $quantity Product quantity
     * @return bool Updated validation status
     */
    public function validate_gift_message($passed, $product_id, $quantity) {
        // Check if gift message was submitted
        if (!isset($_POST['gift_message'])) {
            return $passed;
        }
        
        $gift_message = sanitize_textarea_field($_POST['gift_message']);
        
        // Validate gift message length
        if (strlen($gift_message) > self::MAX_LENGTH) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: maximum character limit */
                    __('Gift message cannot exceed %d characters.', 'wc-gift-message'),
                    self::MAX_LENGTH
                ),
                'error'
            );
            $passed = false;
        }
        
        // Check for potentially harmful content (basic security)
        if ($this->contains_suspicious_content($gift_message)) {
            wc_add_notice(
                __('Gift message contains invalid content. Please review your message.', 'wc-gift-message'),
                'error'
            );
            $passed = false;
        }
        
        // Allow developers to add custom validation
        $passed = apply_filters('wc_gift_message_validation', $passed, $gift_message, $product_id);
        
        return $passed;
    }
    
    /**
     * Add gift message to cart item data
     * 
     * This function adds the validated gift message to the cart item's
     * metadata so it can be retrieved later in the checkout process.
     * 
     * @param array $cart_item_data Existing cart item data
     * @param int $product_id Product ID
     * @param int $variation_id Variation ID (if applicable)
     * @return array Updated cart item data
     */
    public function add_gift_message_to_cart_item($cart_item_data, $product_id, $variation_id) {
        // Check if gift message exists and is valid
        if (isset($_POST['gift_message'])) {
            $gift_message = sanitize_textarea_field($_POST['gift_message']);
            
            // Only add to cart data if message is not empty
            if (!empty(trim($gift_message))) {
                $cart_item_data[self::META_KEY] = $gift_message;
                
                // Make sure each cart item with a gift message is treated as unique
                // This prevents multiple items with different messages from being grouped together
                $cart_item_data['unique_key'] = md5($gift_message . time());
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Check if gift message contains suspicious content
     * 
     * This is a basic security check to prevent obvious malicious content.
     * In a production environment, you might want to use more sophisticated
     * content filtering or integrate with external services.
     * 
     * @param string $message The gift message to check
     * @return bool True if suspicious content is found
     */
    private function contains_suspicious_content($message) {
        // List of patterns that might indicate malicious content
        $suspicious_patterns = array(
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', // Script tags
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', // Iframe tags
            '/javascript:/i', // JavaScript protocols
            '/on\w+\s*=/i', // Event handlers (onclick, onload, etc.)
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi', // Object tags
            '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi', // Embed tags
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the maximum length for gift messages
     * 
     * @return int Maximum character length
     */
    public static function get_max_length() {
        return apply_filters('wc_gift_message_max_length', self::MAX_LENGTH);
    }
    
    /**
     * Get the meta key used for storing gift messages
     * 
     * @return string Meta key
     */
    public static function get_meta_key() {
        return apply_filters('wc_gift_message_meta_key', self::META_KEY);
    }
}