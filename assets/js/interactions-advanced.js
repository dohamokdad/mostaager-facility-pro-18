/**
 * Mostaager Facility PRO - Advanced Interactions & Animations
 * Live updates, smooth animations, and enhanced user experience
 */

(function($) {
    'use strict';

    // Main Interactions Class
    class MS_Advanced_Interactions {
        constructor() {
            this.init();
        }

        init() {
            this.initLiveAnimations();
            this.initHoverEffects();
            this.initClickAnimations();
            this.initScrollAnimations();
            this.initLoadingStates();
            this.initMicroInteractions();
            this.initTransitions();
        }

        // ===== LIVE ANIMATIONS =====
        initLiveAnimations() {
            // Counter animations for numbers
            this.animateCounters();
            
            // Progress bar animations
            this.animateProgressBars();
            
            // Chart animations
            this.animateCharts();
            
            // Pulsing effects for live elements
            this.initPulsingEffects();
        }

        animateCounters() {
            $('.ms-stat-value, .ms-report-summary-value, .ms-monitoring-metric').each(function() {
                const $this = $(this);
                const target = parseInt($this.text().replace(/[^0-9]/g, '')) || 0;
                
                if (target > 0) {
                    $this.prop('Counter', 0).animate({
                        Counter: target
                    }, {
                        duration: 2000,
                        easing: 'swing',
                        step: function(now) {
                            $this.text(Math.ceil(now).toLocaleString());
                        }
                    });
                }
            });
        }

        animateProgressBars() {
            $('.ms-import-progress-fill, .ms-progress-bar-fill').each(function() {
                const $this = $(this);
                const targetWidth = $this.data('width') || $this.css('width');
                
                $this.css('width', '0%');
                
                setTimeout(() => {
                    $this.css('transition', 'width 1s ease');
                    $this.css('width', targetWidth);
                }, 100);
            });
        }

        animateCharts() {
            // Animate chart elements when they come into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const $chart = $(entry.target);
                        $chart.addClass('ms-chart-animate');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            $('.ms-chart-container canvas, .ms-monitoring-chart canvas').each(function() {
                observer.observe(this);
            });
        }

        initPulsingEffects() {
            // Add pulsing effect to live indicators
            $('.ms-automation-task-status, .ms-monitoring-card-status').each(function() {
                const $this = $(this);
                
                setInterval(() => {
                    $this.addClass('ms-pulse');
                    setTimeout(() => {
                        $this.removeClass('ms-pulse');
                    }, 1000);
                }, 2000);
            });
        }

        // ===== HOVER EFFECTS =====
        initHoverEffects() {
            // Card hover effects
            $('.ms-stat-card, .ms-report-chart-card, .ms-automation-task-card, .ms-monitoring-card').on({
                mouseenter: function() {
                    $(this).addClass('ms-hover-active');
                },
                mouseleave: function() {
                    $(this).removeClass('ms-hover-active');
                }
            });

            // Button hover effects
            $('.ms-header-button, .ms-reports-button, .ms-automation-button').on({
                mouseenter: function() {
                    const $this = $(this);
                    $this.addClass('ms-button-hover');
                    
                    // Add ripple effect
                    this.createRipple($this);
                },
                mouseleave: function() {
                    $(this).removeClass('ms-button-hover');
                }
            });

            // Navigation item hover effects
            $('.ms-sidebar-nav-item, .ms-reports-category, .ms-automation-nav-item').on({
                mouseenter: function() {
                    $(this).addClass('ms-nav-hover');
                },
                mouseleave: function() {
                    $(this).removeClass('ms-nav-hover');
                }
            });
        }

        createRipple($element) {
            const ripple = $('<span class="ms-ripple"></span>');
            const size = Math.max($element.outerWidth(), $element.outerHeight());
            const position = $element.offset();
            
            ripple.css({
                width: size,
                height: size,
                left: ($element.outerWidth() / 2) - (size / 2),
                top: ($element.outerHeight() / 2) - (size / 2)
            });
            
            $element.append(ripple);
            
            ripple.on('animationend', function() {
                $(this).remove();
            });
        }

        // ===== CLICK ANIMATIONS =====
        initClickAnimations() {
            // Button click animations
            $('.ms-header-button, .ms-reports-button, .ms-automation-button').on('click', function(e) {
                const $this = $(this);
                
                $this.addClass('ms-button-click');
                
                setTimeout(() => {
                    $this.removeClass('ms-button-click');
                }, 200);
            });

            // Card click animations
            $('.ms-stat-card, .ms-report-summary-card').on('click', function() {
                $(this).addClass('ms-card-click');
                
                setTimeout(() => {
                    $(this).removeClass('ms-card-click');
                }, 300);
            });

            // Toggle animations
            $('.ms-automation-rule-toggle').on('click', function() {
                const $this = $(this);
                $this.toggleClass('active');
                
                if ($this.hasClass('active')) {
                    $this.addClass('ms-toggle-on');
                    setTimeout(() => {
                        $this.removeClass('ms-toggle-on');
                    }, 300);
                } else {
                    $this.addClass('ms-toggle-off');
                    setTimeout(() => {
                        $this.removeClass('ms-toggle-off');
                    }, 300);
                }
            });
        }

        // ===== SCROLL ANIMATIONS =====
        initScrollAnimations() {
            // Scroll reveal animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('ms-scroll-reveal');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe elements
            $('.ms-stat-card, .ms-report-chart-card, .ms-automation-task-card, .ms-monitoring-card').each(function() {
                observer.observe(this);
            });

            // Parallax effect on scroll
            $(window).on('scroll', this.throttle(() => {
                const scrollTop = $(window).scrollTop();
                
                $('.ms-sidebar-advanced').css('transform', `translateY(${scrollTop * 0.1}px)`);
            }, 100));
        }

        throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }

        // ===== LOADING STATES =====
        initLoadingStates() {
            // Skeleton loading
            this.initSkeletonLoading();
            
            // Spinner animations
            this.initSpinnerAnimations();
            
            // Progress indicators
            this.initProgressIndicators();
        }

        initSkeletonLoading() {
            $('.ms-skeleton').each(function() {
                const $this = $(this);
                const duration = $this.data('duration') || 1500;
                
                setTimeout(() => {
                    $this.removeClass('ms-skeleton');
                    $this.addClass('ms-skeleton-loaded');
                }, duration);
            });
        }

        initSpinnerAnimations() {
            $('.ms-loading-spinner').each(function() {
                const $this = $(this);
                
                $this.css('animation', 'ms-spin 1s linear infinite');
            });
        }

        initProgressIndicators() {
            $('.ms-progress-indicator').each(function() {
                const $this = $(this);
                const progress = $this.data('progress') || 0;
                
                $this.find('.ms-progress-fill').css('width', progress + '%');
                
                // Animate progress on scroll
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            $this.find('.ms-progress-fill').css('transition', 'width 1s ease');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                
                observer.observe(this);
            });
        }

        // ===== MICRO INTERACTIONS =====
        initMicroInteractions() {
            // Tooltips
            this.initTooltips();
            
            // Popovers
            this.initPopovers();
            
            // Dropdowns
            this.initDropdowns();
            
            // Modals
            this.initModals();
        }

        initTooltips() {
            $('[data-tooltip]').each(function() {
                const $this = $(this);
                const tooltip = $this.data('tooltip');
                
                $this.on('mouseenter', function() {
                    const $tooltip = $('<div class="ms-tooltip"></div>');
                    $tooltip.text(tooltip);
                    $tooltip.css({
                        position: 'absolute',
                        bottom: '100%',
                        left: '50%',
                        transform: 'translateX(-50%)',
                        padding: '8px 12px',
                        background: '#0f172a',
                        color: 'white',
                        borderRadius: '6px',
                        fontSize: '0.85rem',
                        whiteSpace: 'nowrap',
                        zIndex: 10000,
                        opacity: 0,
                        transition: 'opacity 0.3s ease'
                    });
                    
                    $this.append($tooltip);
                    
                    setTimeout(() => {
                        $tooltip.css('opacity', '1');
                    }, 10);
                });
                
                $this.on('mouseleave', function() {
                    $(this).find('.ms-tooltip').remove();
                });
            });
        }

        initPopovers() {
            $('[data-popover]').each(function() {
                const $this = $(this);
                const content = $this.data('popover');
                
                $this.on('click', function(e) {
                    e.preventDefault();
                    
                    const $popover = $('<div class="ms-popover"></div>');
                    $popover.html(content);
                    $popover.css({
                        position: 'absolute',
                        top: '100%',
                        left: 0,
                        marginTop: '8px',
                        padding: '16px',
                        background: 'white',
                        borderRadius: '12px',
                        boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15)',
                        zIndex: 10000,
                        opacity: 0,
                        transform: 'translateY(-10px)',
                        transition: 'all 0.3s ease'
                    });
                    
                    $this.append($popover);
                    
                    setTimeout(() => {
                        $popover.css({
                            opacity: 1,
                            transform: 'translateY(0)'
                        });
                    }, 10);
                    
                    // Close on click outside
                    $(document).on('click.popover', function(e) {
                        if (!$(e.target).closest($this).length) {
                            $popover.remove();
                            $(document).off('click.popover');
                        }
                    });
                });
            });
        }

        initDropdowns() {
            $('.ms-dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $this = $(this);
                const $dropdown = $this.next('.ms-dropdown-menu');
                
                $dropdown.toggleClass('ms-dropdown-open');
                
                if ($dropdown.hasClass('ms-dropdown-open')) {
                    $dropdown.css({
                        opacity: 0,
                        transform: 'translateY(-10px)'
                    });
                    
                    setTimeout(() => {
                        $dropdown.css({
                            opacity: 1,
                            transform: 'translateY(0)'
                        });
                    }, 10);
                }
            });
            
            // Close dropdowns on click outside
            $(document).on('click', function() {
                $('.ms-dropdown-menu').removeClass('ms-dropdown-open');
            });
        }

        initModals() {
            $('.ms-modal-trigger').on('click', function(e) {
                e.preventDefault();
                
                const $this = $(this);
                const modalId = $this.data('modal');
                const $modal = $('#' + modalId);
                
                $modal.addClass('ms-modal-open');
                $('body').addClass('ms-modal-open');
            });
            
            $('.ms-modal-close, .ms-modal-overlay').on('click', function() {
                $(this).closest('.ms-modal').removeClass('ms-modal-open');
                $('body').removeClass('ms-modal-open');
            });
        }

        // ===== TRANSITIONS =====
        initTransitions() {
            // Page transitions
            this.initPageTransitions();
            
            // Element transitions
            this.initElementTransitions();
            
            // State transitions
            this.initStateTransitions();
        }

        initPageTransitions() {
            // Smooth page transitions
            $('a[href^="#"]').on('click', function(e) {
                const target = $(this.getAttribute('href'));
                
                if (target.length) {
                    e.preventDefault();
                    
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 800, 'easeInOutQuad');
                }
            });
        }

        initElementTransitions() {
            // Add transition classes
            $('.ms-transition-fade').addClass('ms-transition-fade-in');
            $('.ms-transition-slide').addClass('ms-transition-slide-in');
            $('.ms-transition-scale').addClass('ms-transition-scale-in');
        }

        initStateTransitions() {
            // State-based transitions
            $('.ms-state-active').addClass('ms-state-enter');
            
            setTimeout(() => {
                $('.ms-state-active').removeClass('ms-state-enter');
            }, 300);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.msInteractions = new MS_Advanced_Interactions();
    });

})(jQuery);

// Additional CSS for animations
const style = document.createElement('style');
style.textContent = `
    /* Button hover effects */
    .ms-button-hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .ms-button-click {
        transform: scale(0.95);
    }
    
    /* Card hover effects */
    .ms-hover-active {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    
    .ms-card-click {
        transform: scale(0.98);
    }
    
    /* Navigation hover */
    .ms-nav-hover {
        background: rgba(37, 99, 235, 0.1);
    }
    
    /* Ripple effect */
    .ms-ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    /* Scroll reveal */
    .ms-scroll-reveal {
        animation: scrollReveal 0.6s ease forwards;
    }
    
    @keyframes scrollReveal {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Chart animation */
    .ms-chart-animate {
        animation: chartReveal 1s ease forwards;
    }
    
    @keyframes chartReveal {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    /* Pulsing effect */
    .ms-pulse {
        animation: pulse 1s ease;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.7;
        }
    }
    
    /* Toggle animations */
    .ms-toggle-on {
        animation: toggleOn 0.3s ease;
    }
    
    .ms-toggle-off {
        animation: toggleOff 0.3s ease;
    }
    
    @keyframes toggleOn {
        from {
            background: var(--ms-automation-border);
        }
        to {
            background: var(--ms-automation-success);
        }
    }
    
    @keyframes toggleOff {
        from {
            background: var(--ms-automation-success);
        }
        to {
            background: var(--ms-automation-border);
        }
    }
    
    /* Skeleton loading */
    .ms-skeleton-loaded {
        animation: skeletonFade 0.3s ease;
    }
    
    @keyframes skeletonFade {
        from {
            opacity: 0.5;
        }
        to {
            opacity: 1;
        }
    }
    
    /* Spinner */
    @keyframes ms-spin {
        to {
            transform: rotate(360deg);
        }
    }
    
    /* Dropdown */
    .ms-dropdown-open {
        display: block !important;
    }
    
    /* Modal */
    .ms-modal-open {
        display: flex !important;
    }
    
    /* Transitions */
    .ms-transition-fade-in {
        animation: fadeIn 0.3s ease;
    }
    
    .ms-transition-slide-in {
        animation: slideIn 0.3s ease;
    }
    
    .ms-transition-scale-in {
        animation: scaleIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    /* State transitions */
    .ms-state-enter {
        animation: stateEnter 0.3s ease;
    }
    
    @keyframes stateEnter {
        from {
            transform: scale(0.95);
            opacity: 0.5;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
`;

document.head.appendChild(style);