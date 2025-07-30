/**
 * Frontend JavaScript for WooCommerce Gift Message Plugin
 * 
 * This file handles all the interactive functionality for the gift message field
 * including character counting, validation, and user experience enhancements.
 */

(function($) {
    'use strict';
    
    /**
     * Gift Message Handler Object
     */
    var WCGiftMessage = {
        
        // Configuration
        config: {
            maxLength: wcGiftMessage.maxLength || 150,
            warningThreshold: 0.8, // Show warning at 80% of max length
            selectors: {
                wrapper: '.wc-gift-message-wrapper',
                field: '.wc-gift-message-field',
                input: '.wc-gift-message-input',
                counter: '.wc-gift-message-counter',
                currentLength: '.current-length',
                maxLength: '.max-length',
                remainingText: '.remaining-text'
            }
        },
        
        /**
         * Initialize the gift message functionality
         */
        init: function() {
            this.bindEvents();
            this.updateCounters();
            this.setupValidation();
            this.enhanceAccessibility();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Character counter updates
            $(document).on('input keyup paste', self.config.selectors.input, function() {
                self.updateCounter($(this));
            });
            
            // Form submission validation
            $('form.cart').on('submit', function(e) {
                if (!self.validateGiftMessage()) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Real-time validation feedback
            $(document).on('blur', self.config.selectors.input, function() {
                self.validateField($(this));
            });
            
            // Enhanced user experience
            $(document).on('focus', self.config.selectors.input, function() {
                self.highlightField($(this));
            });
            
            $(document).on('input', self.config.selectors.input, function() {
                self.clearValidationErrors($(this));
            });
        },
        
        /**
         * Update all character counters on page load
         */
        updateCounters: function() {
            var self = this;
            $(self.config.selectors.input).each(function() {
                self.updateCounter($(this));
            });
        },
        
        /**
         * Update character counter for a specific input
         * @param {jQuery} $input - The textarea input element
         */
        updateCounter: function($input) {
            var currentLength = $input.val().length;
            var maxLength = this.config.maxLength;
            var remaining = maxLength - currentLength;
            var $wrapper = $input.closest(this.config.selectors.wrapper);
            var $counter = $wrapper.find(this.config.selectors.counter);
            var $currentLengthSpan = $counter.find(this.config.selectors.currentLength);
            
            // Update the counter display
            $currentLengthSpan.text(currentLength);
            
            // Apply styling based on character count
            this.updateCounterStyling($counter, currentLength, maxLength);
            
            // Update remaining text
            this.updateRemainingText($counter, remaining);
        },
        
        /**
         * Update counter styling based on character count
         * @param {jQuery} $counter - The counter element
         * @param {number} currentLength - Current character count
         * @param {number} maxLength - Maximum allowed characters
         */
        updateCounterStyling: function($counter, currentLength, maxLength) {
            var warningLength = Math.floor(maxLength * this.config.warningThreshold);
            
            // Remove existing classes
            $counter.removeClass('warning limit-reached');
            
            if (currentLength >= maxLength) {
                $counter.addClass('limit-reached');
            } else if (currentLength >= warningLength) {
                $counter.addClass('warning');
            }
        },
        
        /**
         * Update remaining text based on character count
         * @param {jQuery} $counter - The counter element
         * @param {number} remaining - Remaining characters
         */
        updateRemainingText: function($counter, remaining) {
            var $remainingText = $counter.find(this.config.selectors.remainingText);
            
            if (remaining < 0) {
                $remainingText.text(wcGiftMessage.maxReachedText || 'Maximum character limit reached');
            } else if (remaining <= 20) {
                $remainingText.text(remaining + ' ' + (wcGiftMessage.remainingText || 'characters remaining'));
            } else {
                $remainingText.text(wcGiftMessage.remainingText || 'characters');
            }
        },
        
        /**
         * Setup form validation
         */
        setupValidation: function() {
            // Add required attributes and validation rules
            $(this.config.selectors.input).each(function() {
                $(this).attr('data-validation', 'gift-message');
            });
        },
        
        /**
         * Validate gift message field
         * @param {jQuery} $input - The input field to validate
         * @returns {boolean} - Whether the field is valid
         */
        validateField: function($input) {
            var value = $input.val().trim();
            var maxLength = this.config.maxLength;
            var isValid = true;
            var errorMessage = '';
            
            // Check length
            if (value.length > maxLength) {
                isValid = false;
                errorMessage = 'Gift message cannot exceed ' + maxLength + ' characters.';
            }
            
            // Check for potentially harmful content (basic client-side check)
            if (this.containsSuspiciousContent(value)) {
                isValid = false;
                errorMessage = 'Gift message contains invalid content.';
            }
            
            // Display validation result
            this.displayValidationResult($input, isValid, errorMessage);
            
            return isValid;
        },
        
        /**
         * Validate all gift messages on form submission
         * @returns {boolean} - Whether all fields are valid
         */
        validateGiftMessage: function() {
            var self = this;
            var isValid = true;
            
            $(self.config.selectors.input).each(function() {
                if (!self.validateField($(this))) {
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        /**
         * Display validation result
         * @param {jQuery} $input - The input field
         * @param {boolean} isValid - Whether the field is valid
         * @param {string} errorMessage - Error message to display
         */
        displayValidationResult: function($input, isValid, errorMessage) {
            var $wrapper = $input.closest(this.config.selectors.wrapper);
            var $errorContainer = $wrapper.find('.validation-error');
            
            if (!isValid) {
                // Add error styling
                $input.addClass('error');
                
                // Create or update error message
                if ($errorContainer.length === 0) {
                    $errorContainer = $('<div class="validation-error"></div>');
                    $wrapper.append($errorContainer);
                }
                
                $errorContainer.html('<span class="error-text">' + errorMessage + '</span>').show();
            } else {
                // Remove error styling
                $input.removeClass('error');
                $errorContainer.hide();
            }
        },
        
        /**
         * Clear validation errors for a field
         * @param {jQuery} $input - The input field
         */
        clearValidationErrors: function($input) {
            var $wrapper = $input.closest(this.config.selectors.wrapper);
            $input.removeClass('error');
            $wrapper.find('.validation-error').hide();
        },
        
        /**
         * Highlight field on focus for better UX
         * @param {jQuery} $input - The input field
         */
        highlightField: function($input) {
            var $field = $input.closest(this.config.selectors.field);
            $field.addClass('focused');
            
            // Remove highlight on blur
            $input.one('blur', function() {
                $field.removeClass('focused');
            });
        },
        
        /**
         * Basic client-side check for suspicious content
         * @param {string} content - Content to check
         * @returns {boolean} - Whether suspicious content is found
         */
        containsSuspiciousContent: function(content) {
            // Basic patterns to check for
            var suspiciousPatterns = [
                /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
                /<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi,
                /javascript:/gi,
                /on\w+\s*=/gi
            ];
            
            for (var i = 0; i < suspiciousPatterns.length; i++) {
                if (suspiciousPatterns[i].test(content)) {
                    return true;
                }
            }
            
            return false;
        },
        
        /**
         * Enhance accessibility
         */
        enhanceAccessibility: function() {
            $(this.config.selectors.input).each(function() {
                var $input = $(this);
                var $counter = $input.closest('.wc-gift-message-wrapper').find('.wc-gift-message-counter');
                
                // Add ARIA attributes
                $input.attr({
                    'aria-describedby': 'gift-message-counter-' + $input.attr('id'),
                    'aria-label': 'Gift message (optional, maximum 150 characters)'
                });
                
                $counter.attr('id', 'gift-message-counter-' + $input.attr('id'));
                $counter.attr('aria-live', 'polite');
            });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WCGiftMessage.init();
    });
    
    /**
     * Re-initialize on AJAX complete (for dynamic content)
     */
    $(document).ajaxComplete(function() {
        // Small delay to ensure DOM is updated
        setTimeout(function() {
            WCGiftMessage.updateCounters();
        }, 100);
    });
    
    // Make WCGiftMessage available globally for extensibility
    window.WCGiftMessage = WCGiftMessage;
    
})(jQuery);