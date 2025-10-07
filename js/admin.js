/**
 * YouTube UX Suite - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Copy shortcode on click
        $('.ytux-quick-start code').on('click', function() {
            const text = $(this).text();
            copyToClipboard(text);
            
            // Show feedback
            const $this = $(this);
            const originalText = $this.text();
            $this.text('✓ Copied!');
            
            setTimeout(function() {
                $this.text(originalText);
            }, 2000);
        });
        
        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.ytux-color-picker').wpColorPicker();
        }
        
        // Settings form validation
        $('form').on('submit', function() {
            const duration = $('input[name="ytux_notification_duration"]').val();
            
            if (duration && (duration < 1 || duration > 30)) {
                alert('Notification duration must be between 1 and 30 days.');
                return false;
            }
            
            return true;
        });
        
        // Toggle dependent settings
        $('input[name="ytux_notification_bar"]').on('change', function() {
            const $notificationSettings = $(this).closest('table').find('tr').slice(1);
            
            if ($(this).is(':checked')) {
                $notificationSettings.fadeIn();
            } else {
                $notificationSettings.fadeOut();
            }
        }).trigger('change');
        
        $('input[name="ytux_enable_search"]').on('change', function() {
            const $searchSettings = $(this).closest('table').find('tr').slice(1);
            
            if ($(this).is(':checked')) {
                $searchSettings.fadeIn();
            } else {
                $searchSettings.fadeOut();
            }
        }).trigger('change');
        
        // Preview notification bar
        $('#preview-notification').on('click', function(e) {
            e.preventDefault();
            
            const text = $('input[name="ytux_notification_text"]').val();
            const color = $('input[name="ytux_notification_color"]').val();
            const bg = $('input[name="ytux_notification_bg"]').val();
            const position = $('select[name="ytux_notification_position"]').val();
            
            showPreview(text, color, bg, position);
        });
    });
    
    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }
    }
    
    /**
     * Show notification bar preview
     */
    function showPreview(text, color, bg, position) {
        // Remove existing preview
        $('.ytux-notification-preview').remove();
        
        // Create preview
        const $preview = $('<div class="ytux-notification-preview">')
            .css({
                'position': 'fixed',
                'left': 0,
                'right': 0,
                'padding': '15px 20px',
                'background': bg,
                'color': color,
                'z-index': 99999,
                'box-shadow': '0 2px 10px rgba(0,0,0,0.2)',
                'display': 'flex',
                'align-items': 'center',
                'justify-content': 'space-between',
                'animation': 'slideDown 0.4s ease'
            })
            .css(position, 0)
            .html(
                '<div style="display: flex; align-items: center; gap: 10px;">' +
                '<span style="font-size: 24px;">🎬</span>' +
                '<span>' + text + '</span>' +
                '</div>' +
                '<button style="background: transparent; border: none; color: ' + color + '; font-size: 24px; cursor: pointer;">×</button>'
            );
        
        $('body').append($preview);
        
        // Close preview
        $preview.find('button').on('click', function() {
            $preview.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $preview.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
})(jQuery);