/**
 * YouTube UX Suite - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize lazy loading
        if (ytuxAjax.lazyLoad === '1' && typeof lozad !== 'undefined') {
            const observer = lozad('.lozad', {
                loaded: function(el) {
                    el.classList.add('loaded');
                }
            });
            observer.observe();
        }
        
        // Notification bar functionality
        initNotificationBar();
        
        // Video search functionality
        initVideoSearch();
        
        // Keyboard shortcuts for videos
        if (ytuxAjax.enableKeyboard === '1') {
            initKeyboardShortcuts();
        }
        
        // Timestamp links
        initTimestampLinks();
    });
    
    /**
     * Initialize notification bar
     */
    function initNotificationBar() {
        const $notificationBar = $('.ytux-notification-bar');
        
        if ($notificationBar.length === 0) {
            return;
        }
        
        // Close button functionality
        $('.ytux-notification-close').on('click', function(e) {
            e.preventDefault();
            
            const postId = $notificationBar.data('post-id');
            
            // Animate out
            $notificationBar.fadeOut(300);
            
            // Send AJAX to dismiss
            $.post(ytuxAjax.ajaxurl, {
                action: 'ytux_dismiss_notification',
                nonce: ytuxAjax.nonce,
                post_id: postId
            });
        });
    }
    
    /**
     * Initialize video search
     */
    function initVideoSearch() {
        const $searchForm = $('.ytux-search-form');
        
        if ($searchForm.length === 0) {
            return;
        }
        
        $searchForm.on('submit', function(e) {
            e.preventDefault();
            
            const searchTerm = $(this).find('.ytux-search-input').val().trim();
            
            if (searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }
            
            performSearch(searchTerm);
        });
        
        // Clear search
        $('.ytux-clear-search').on('click', function() {
            $('.ytux-search-input').val('');
            $('.ytux-search-results').hide();
            $('.ytux-results-grid').empty();
        });
    }
    
    /**
     * Perform video search
     */
    function performSearch(searchTerm) {
        const $resultsContainer = $('.ytux-search-results');
        const $resultsGrid = $('.ytux-results-grid');
        const $resultsCount = $('.ytux-results-count');
        const $loading = $('.ytux-search-loading');
        
        // Show loading state
        $resultsContainer.hide();
        $loading.show();
        
        // Send AJAX request
        $.post(ytuxAjax.ajaxurl, {
            action: 'ytux_video_search',
            nonce: ytuxAjax.nonce,
            search: searchTerm
        })
        .done(function(response) {
            if (response.success && response.data.length > 0) {
                displaySearchResults(response.data, searchTerm);
            } else {
                displayNoResults(searchTerm);
            }
        })
        .fail(function() {
            displayError();
        })
        .always(function() {
            $loading.hide();
        });
    }
    
    /**
     * Display search results
     */
    function displaySearchResults(results, searchTerm) {
        const $resultsContainer = $('.ytux-search-results');
        const $resultsGrid = $('.ytux-results-grid');
        const $resultsCount = $('.ytux-results-count');
        
        // Clear previous results
        $resultsGrid.empty();
        
        // Update count
        $resultsCount.text(results.length + ' video' + (results.length !== 1 ? 's' : '') + ' found for "' + searchTerm + '"');
        
        // Add results
        results.forEach(function(video) {
            const $item = $('<div class="ytux-result-item">')
                .append(
                    $('<a>').attr('href', video.url).append(
                        $('<div class="ytux-result-thumbnail">').append(
                            video.thumbnail ? $('<img>').attr('src', video.thumbnail).attr('alt', video.title) : ''
                        ),
                        $('<div class="ytux-result-info">').append(
                            $('<h3 class="ytux-result-title">').text(video.title),
                            $('<p class="ytux-result-excerpt">').text(video.excerpt),
                            $('<div class="ytux-result-date">').text(video.date)
                        )
                    )
                );
            
            $resultsGrid.append($item);
        });
        
        // Show results
        $resultsContainer.fadeIn(300);
    }
    
    /**
     * Display no results message
     */
    function displayNoResults(searchTerm) {
        const $resultsContainer = $('.ytux-search-results');
        const $resultsGrid = $('.ytux-results-grid');
        const $resultsCount = $('.ytux-results-count');
        
        $resultsGrid.empty();
        $resultsCount.text('No videos found for "' + searchTerm + '"');
        
        $resultsGrid.append(
            $('<div class="ytux-no-results">')
                .css({
                    'padding': '40px',
                    'text-align': 'center',
                    'color': '#666'
                })
                .html('<p>Try searching with different keywords.</p>')
        );
        
        $resultsContainer.fadeIn(300);
    }
    
    /**
     * Display error message
     */
    function displayError() {
        alert('An error occurred while searching. Please try again.');
    }
    
    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Only work on singular post pages
            if (!$('body').hasClass('single')) {
                return;
            }
            
            const $video = $('iframe[src*="youtube"]').first();
            
            if ($video.length === 0) {
                return;
            }
            
            // Space bar - play/pause (prevent if in input field)
            if (e.keyCode === 32 && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                toggleVideoPlayback($video);
            }
            
            // Left arrow - rewind 5 seconds
            if (e.keyCode === 37 && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                seekVideo($video, -5);
            }
            
            // Right arrow - forward 5 seconds
            if (e.keyCode === 39 && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                seekVideo($video, 5);
            }
        });
    }
    
    /**
     * Toggle video playback
     */
    function toggleVideoPlayback($video) {
        // This would require YouTube API integration
        // For now, we'll just focus the iframe
        $video[0].contentWindow.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
    }
    
    /**
     * Seek video by seconds
     */
    function seekVideo($video, seconds) {
        // This would require YouTube API integration
        console.log('Seek video by ' + seconds + ' seconds');
    }
    
    /**
     * Initialize timestamp links
     */
    function initTimestampLinks() {
        $('.ytux-timestamp-link').on('click', function(e) {
            // Allow default behavior (opens YouTube with timestamp)
            // Could add custom behavior here if needed
        });
    }
    
    /**
     * Lazy load images as they enter viewport
     */
    function lazyLoadImages() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            const lazyImages = document.querySelectorAll('img.lazy');
            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }
    
    /**
     * Add smooth scrolling to anchor links
     */
    function initSmoothScroll() {
        $('a[href^="#"]').on('click', function(e) {
            const target = $(this.getAttribute('href'));
            
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 800);
            }
        });
    }
    
    // Initialize smooth scroll
    initSmoothScroll();
    
})(jQuery);