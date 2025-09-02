/**
 * JetReview Aggregate Schema Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Schema preview functionality
        if ($('#jra-schema-preview').length) {
            loadSchemaPreview();
        }
        
        // Settings form validation
        $('form[action="options.php"]').on('submit', function(e) {
            const postTypes = $('input[name="jra_schema_settings[post_types][]"]:checked');
            if (postTypes.length === 0) {
                alert('Please select at least one post type.');
                e.preventDefault();
                return false;
            }
        });
        
        // Test schema markup button
        $('#test-schema-markup').on('click', function(e) {
            e.preventDefault();
            testSchemaMarkup();
        });
        
        // Copy schema to clipboard
        $('#copy-schema').on('click', function(e) {
            e.preventDefault();
            copySchemaToClipboard();
        });
        
        // Real-time settings preview
        $('select[name="jra_schema_settings[schema_type]"]').on('change', function() {
            updateSchemaPreview();
        });
        
        // Tooltip initialization
        initializeTooltips();
        
    });
    
    /**
     * Load schema preview for current post
     */
    function loadSchemaPreview() {
        const postId = $('#post_ID').val() || getUrlParameter('post');
        
        if (!postId) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'jra_get_schema_preview',
                post_id: postId,
                nonce: jraAdmin.nonce
            },
            beforeSend: function() {
                $('#jra-schema-preview').html('<div class="jra-spinner"></div> Loading schema preview...');
            },
            success: function(response) {
                if (response.success) {
                    $('#jra-schema-preview').html('<pre><code>' + response.data + '</code></pre>');
                } else {
                    $('#jra-schema-preview').html('<p class="notice notice-warning">No aggregate review data found for this post.</p>');
                }
            },
            error: function() {
                $('#jra-schema-preview').html('<p class="notice notice-error">Error loading schema preview.</p>');
            }
        });
    }
    
    /**
     * Test schema markup with Google's tool
     */
    function testSchemaMarkup() {
        const currentUrl = window.location.origin + window.location.pathname;
        const testUrl = 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(currentUrl);
        window.open(testUrl, '_blank');
    }
    
    /**
     * Copy schema JSON to clipboard
     */
    function copySchemaToClipboard() {
        const schemaCode = $('#jra-schema-preview code').text();
        
        if (!schemaCode) {
            alert('No schema data to copy.');
            return;
        }
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(schemaCode).then(function() {
                showNotice('Schema JSON copied to clipboard!', 'success');
            }).catch(function() {
                fallbackCopyToClipboard(schemaCode);
            });
        } else {
            fallbackCopyToClipboard(schemaCode);
        }
    }
    
    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showNotice('Schema JSON copied to clipboard!', 'success');
        } catch (err) {
            showNotice('Failed to copy to clipboard. Please copy manually.', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    /**
     * Update schema preview when settings change
     */
    function updateSchemaPreview() {
        // This would typically trigger a preview update
        // Implementation depends on your specific needs
        console.log('Schema type changed, updating preview...');
    }
    
    /**
     * Initialize tooltips
     */
    function initializeTooltips() {
        $('.jra-tooltip').each(function() {
            const $this = $(this);
            const tooltip = $this.attr('data-tooltip');
            
            if (tooltip) {
                $this.on('mouseenter', function() {
                    showTooltip($this, tooltip);
                }).on('mouseleave', function() {
                    hideTooltip();
                });
            }
        });
    }
    
    /**
     * Show tooltip
     */
    function showTooltip($element, text) {
        const $tooltip = $('<div class="jra-tooltip-popup">' + text + '</div>');
        $('body').append($tooltip);
        
        const elementOffset = $element.offset();
        const tooltipWidth = $tooltip.outerWidth();
        const tooltipHeight = $tooltip.outerHeight();
        
        $tooltip.css({
            position: 'absolute',
            top: elementOffset.top - tooltipHeight - 10,
            left: elementOffset.left - (tooltipWidth / 2) + ($element.outerWidth() / 2),
            zIndex: 9999
        });
    }
    
    /**
     * Hide tooltip
     */
    function hideTooltip() {
        $('.jra-tooltip-popup').remove();
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 3000);
    }
    
    /**
     * Get URL parameter value
     */
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    /**
     * Debug helper
     */
    function debug(message, data) {
        if (jraAdmin.debug) {
            console.log('JRA Debug:', message, data);
        }
    }
    
})(jQuery);

// Vanilla JS for non-jQuery environments
document.addEventListener('DOMContentLoaded', function() {
    
    // Add schema status indicators
    const ratingElements = document.querySelectorAll('.jra-aggregate-rating');
    ratingElements.forEach(function(element) {
        addSchemaStatusIndicator(element);
    });
    
    /**
     * Add schema status indicator
     */
    function addSchemaStatusIndicator(element) {
        const hasSchema = document.querySelector('script[type="application/ld+json"]');
        const statusElement = document.createElement('span');
        statusElement.className = 'jra-schema-status ' + (hasSchema ? 'active' : 'inactive');
        statusElement.textContent = hasSchema ? 'Schema Active' : 'No Schema';
        statusElement.title = hasSchema ? 'Aggregate review schema is active' : 'No schema markup found';
        
        element.appendChild(statusElement);
    }
    
});
