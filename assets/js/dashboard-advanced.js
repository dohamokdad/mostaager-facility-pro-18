/**
 * Mostaager Facility PRO - Advanced Dashboard Interactions
 * Smooth animations, live updates, and enhanced user experience
 */

(function($) {
    'use strict';

    // Main Dashboard Class
    class MS_Dashboard_Advanced {
        constructor() {
            this.init();
        }

        init() {
            this.initAnimations();
            this.initLiveUpdates();
            this.initInteractions();
            this.initCharts();
            this.initPerformanceOptimizations();
        }

        // ===== ANIMATIONS =====
        initAnimations() {
            // Intersection Observer for scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('ms-animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe all cards
            $('.ms-stat-card, .ms-chart-card, .ms-activity-card').each(function() {
                observer.observe(this);
            });

            // Add stagger animation to grid items
            $('.ms-stats-grid .ms-stat-card').each(function(index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
            });
        }

        // ===== LIVE UPDATES =====
        initLiveUpdates() {
            // Auto-refresh stats every 30 seconds
            setInterval(() => {
                this.refreshStats();
            }, 30000);

            // Real-time notifications
            this.initWebSocket();
        }

        refreshStats() {
            // AJAX call to refresh stats
            $.ajax({
                url: ms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ms_refresh_dashboard_stats',
                    nonce: ms_ajax.nonce
                },
                beforeSend: () => {
                    $('.ms-stat-card').addClass('ms-loading');
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatValues(response.data);
                    }
                },
                complete: () => {
                    $('.ms-stat-card').removeClass('ms-loading');
                }
            });
        }

        updateStatValues(data) {
            // Update stat values with animation
            $('.ms-stat-value').each(function() {
                const $el = $(this);
                const newValue = data[$el.data('stat')];
                
                if (newValue !== undefined) {
                    $el.prop('Counter', 0).animate({
                        Counter: newValue
                    }, {
                        duration: 1000,
                        easing: 'swing',
                        step: function(now) {
                            $(this).text(Math.ceil(now).toLocaleString());
                        }
                    });
                }
            });
        }

        initWebSocket() {
            // WebSocket connection for real-time updates
            if (typeof WebSocket !== 'undefined' && ms_ajax.websocket_url) {
                const ws = new WebSocket(ms_ajax.websocket_url);
                
                ws.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleRealTimeUpdate(data);
                };
                
                ws.onerror = (error) => {
                    console.log('WebSocket error:', error);
                };
            }
        }

        handleRealTimeUpdate(data) {
            // Handle different types of real-time updates
            switch(data.type) {
                case 'new_maintenance':
                    this.showNotification('طلب صيانة جديد', data.message, 'info');
                    this.refreshStats();
                    break;
                case 'new_invoice':
                    this.showNotification('فاتورة جديدة', data.message, 'success');
                    this.refreshStats();
                    break;
                case 'system_alert':
                    this.showNotification('تنبيه النظام', data.message, 'warning');
                    break;
            }
        }

        // ===== INTERACTIONS =====
        initInteractions() {
            // Sidebar navigation
            $('.ms-sidebar-nav-item').on('click', function(e) {
                e.preventDefault();
                const $this = $(this);
                
                $('.ms-sidebar-nav-item').removeClass('active');
                $this.addClass('active');
                
                // Load content with smooth transition
                this.loadPageContent($this.data('page'));
            });

            // Quick actions
            $('.ms-quick-action').on('click', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                this.executeQuickAction(action);
            });

            // Chart actions
            $('.ms-chart-action-btn').on('click', function() {
                const action = $(this).data('action');
                const chartId = $(this).closest('.ms-chart-card').data('chart');
                this.handleChartAction(action, chartId);
            });

            // Search functionality
            this.initSearch();
        }

        loadPageContent(page) {
            // Show loading state
            $('.ms-main-content').addClass('ms-loading');
            
            $.ajax({
                url: ms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ms_load_page_content',
                    page: page,
                    nonce: ms_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('.ms-main-content').html(response.data.html);
                        this.initPageScripts(page);
                    }
                },
                complete: () => {
                    $('.ms-main-content').removeClass('ms-loading');
                }
            });
        }

        initPageScripts(page) {
            // Initialize page-specific scripts
            switch(page) {
                case 'reports':
                    this.initReportsPage();
                    break;
                case 'import':
                    this.initImportPage();
                    break;
                case 'automation':
                    this.initAutomationPage();
                    break;
            }
        }

        executeQuickAction(action) {
            switch(action) {
                case 'new_maintenance':
                    this.openModal('maintenance-form');
                    break;
                case 'new_invoice':
                    this.openModal('invoice-form');
                    break;
                case 'import_data':
                    window.location.href = ms_ajax.import_url;
                    break;
                case 'generate_report':
                    this.openModal('report-generator');
                    break;
            }
        }

        handleChartAction(action, chartId) {
            switch(action) {
                case 'refresh':
                    this.refreshChart(chartId);
                    break;
                case 'export':
                    this.exportChart(chartId);
                    break;
                case 'fullscreen':
                    this.toggleChartFullscreen(chartId);
                    break;
            }
        }

        initSearch() {
            const $searchInput = $('.ms-dashboard-search');
            let searchTimeout;

            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                searchTimeout = setTimeout(() => {
                    this.performSearch(query);
                }, 300);
            });
        }

        performSearch(query) {
            if (query.length < 2) return;
            
            $.ajax({
                url: ms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ms_dashboard_search',
                    query: query,
                    nonce: ms_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displaySearchResults(response.data.results);
                    }
                }
            });
        }

        displaySearchResults(results) {
            // Display search results in dropdown
            const $resultsContainer = $('.ms-search-results');
            $resultsContainer.html('');
            
            results.forEach(result => {
                $resultsContainer.append(`
                    <div class="ms-search-result" data-url="${result.url}">
                        <div class="ms-search-result-icon">${result.icon}</div>
                        <div class="ms-search-result-content">
                            <div class="ms-search-result-title">${result.title}</div>
                            <div class="ms-search-result-type">${result.type}</div>
                        </div>
                    </div>
                `);
            });
            
            $resultsContainer.show();
        }

        // ===== CHARTS =====
        initCharts() {
            // Initialize all charts with animations
            this.initMainChart();
            this.initSecondaryCharts();
        }

        initMainChart() {
            const ctx = document.getElementById('ms-main-chart');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                    datasets: [{
                        label: 'الإيرادات',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 12,
                            titleFont: {
                                size: 14,
                                family: 'Cairo'
                            },
                            bodyFont: {
                                size: 13,
                                family: 'Cairo'
                            },
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        initSecondaryCharts() {
            // Initialize pie charts, bar charts, etc.
            $('.ms-secondary-chart').each(function() {
                const ctx = this.getContext('2d');
                const chartType = $(this).data('type');
                const chartData = $(this).data('chart');
                
                new Chart(ctx, {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            });
        }

        refreshChart(chartId) {
            // Refresh chart data with animation
            const chart = Chart.getChart(chartId);
            if (chart) {
                chart.update('active');
            }
        }

        exportChart(chartId) {
            // Export chart as image
            const chart = Chart.getChart(chartId);
            if (chart) {
                const link = document.createElement('a');
                link.download = 'chart-export.png';
                link.href = chart.toBase64Image();
                link.click();
            }
        }

        toggleChartFullscreen(chartId) {
            const $chartCard = $(`[data-chart="${chartId}"]`);
            $chartCard.toggleClass('ms-fullscreen');
        }

        // ===== PERFORMANCE OPTIMIZATIONS =====
        initPerformanceOptimizations() {
            // Lazy load images
            this.initLazyLoading();
            
            // Debounce resize events
            this.initDebouncedResize();
            
            // Optimize scroll events
            this.initOptimizedScroll();
        }

        initLazyLoading() {
            const images = document.querySelectorAll('img[data-src]');
            
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));
        }

        initDebouncedResize() {
            let resizeTimeout;
            
            $(window).on('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    this.handleResize();
                }, 250);
            });
        }

        handleResize() {
            // Recalculate chart sizes
            Chart.instances.forEach(chart => {
                chart.resize();
            });
        }

        initOptimizedScroll() {
            let scrollTimeout;
            let isScrolling = false;
            
            $(window).on('scroll', () => {
                isScrolling = true;
                
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    isScrolling = false;
                }, 100);
                
                if (!isScrolling) {
                    this.handleScroll();
                }
            });
        }

        handleScroll() {
            // Handle scroll-based animations
            const scrollTop = $(window).scrollTop();
            
            // Show/hide back to top button
            if (scrollTop > 500) {
                $('.ms-back-to-top').fadeIn();
            } else {
                $('.ms-back-to-top').fadeOut();
            }
        }

        // ===== NOTIFICATIONS =====
        showNotification(title, message, type = 'info') {
            const notification = $(`
                <div class="ms-notification ms-notification-${type}">
                    <div class="ms-notification-icon">${this.getNotificationIcon(type)}</div>
                    <div class="ms-notification-content">
                        <div class="ms-notification-title">${title}</div>
                        <div class="ms-notification-message">${message}</div>
                    </div>
                    <button class="ms-notification-close">&times;</button>
                </div>
            `);
            
            $('.ms-notifications-container').append(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.addClass('ms-notification-removing');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
            
            // Close button
            notification.find('.ms-notification-close').on('click', function() {
                notification.addClass('ms-notification-removing');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
        }

        getNotificationIcon(type) {
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            return icons[type] || icons.info;
        }

        // ===== MODALS =====
        openModal(modalId) {
            const $modal = $(`#${modalId}`);
            $modal.addClass('ms-modal-open');
            $('body').addClass('ms-modal-open');
        }

        closeModal(modalId) {
            const $modal = $(`#${modalId}`);
            $modal.removeClass('ms-modal-open');
            $('body').removeClass('ms-modal-open');
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.msDashboard = new MS_Dashboard_Advanced();
    });

})(jQuery);
