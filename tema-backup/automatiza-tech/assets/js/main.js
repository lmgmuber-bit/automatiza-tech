/**
 * Automatiza Tech - Main JavaScript
 * 
 * @package AutomatizaTech
 * @version 1.0
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        
        // Initialize all functions
        initSmoothScrolling();
        initMobileMenu();
        initContactForm();
        initScrollAnimations();
        initPerformanceOptimizations();
        initHeaderScroll();
        initLazyLoading();
        
    });

    /**
     * Smooth scrolling for anchor links
     */
    function initSmoothScrolling() {
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80 // Account for fixed header
                }, 800, 'easeInOutCubic');
            }
        });
    }

    /**
     * Mobile menu functionality
     */
    function initMobileMenu() {
        $('.mobile-menu-toggle').on('click', function() {
            $(this).toggleClass('active');
            $('#mobile-menu').toggleClass('show');
        });

        // Close mobile menu when clicking on a link
        $('.mobile-nav-menu a').on('click', function() {
            $('.mobile-menu-toggle').removeClass('active');
            $('#mobile-menu').removeClass('show');
        });

        // Close mobile menu when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.mobile-menu-toggle, #mobile-menu').length) {
                $('.mobile-menu-toggle').removeClass('active');
                $('#mobile-menu').removeClass('show');
            }
        });
    }

    /**
     * Contact form handling
     */
    function initContactForm() {
        $('#contact-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $response = $('#form-response');
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');
            
            // Collect form data
            const formData = new FormData(this);
            formData.append('action', 'contact_form');
            
            // Send AJAX request
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $response.html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + response.data + '</div>').show();
                        $form[0].reset();
                        
                        // Track conversion
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'form_submit', {
                                event_category: 'engagement',
                                event_label: 'contact_form'
                            });
                        }
                        
                        // Show WhatsApp follow-up option
                        setTimeout(function() {
                            const followUp = '<div class="alert alert-info mt-3"><p>¿Prefieres hablar directamente con nosotros?</p><a href="' + getWhatsAppUrl('Hola! Acabo de enviar el formulario de contacto. Me gustaría hablar sobre Automatiza Tech.') + '" class="btn btn-success btn-sm" target="_blank"><i class="fab fa-whatsapp"></i> Continuar por WhatsApp</a></div>';
                            $response.append(followUp);
                        }, 2000);
                        
                    } else {
                        $response.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.data + '</div>').show();
                    }
                },
                error: function() {
                    $response.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error al enviar el mensaje. Por favor, intenta nuevamente.</div>').show();
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html('Enviar Mensaje');
                    
                    // Scroll to response
                    $('html, body').animate({
                        scrollTop: $response.offset().top - 100
                    }, 500);
                }
            });
        });
    }

    /**
     * Scroll animations using Intersection Observer
     */
    function initScrollAnimations() {
        if ('IntersectionObserver' in window) {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        
                        // Add staggered animation for grid items
                        if (entry.target.closest('.features-grid, .integrations-grid')) {
                            const index = Array.from(entry.target.parentNode.children).indexOf(entry.target);
                            entry.target.style.animationDelay = (index * 0.1) + 's';
                        }
                        
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            $('.feature-card, .integration-item, .industry-card, .pricing-card, .hero-content').each(function() {
                observer.observe(this);
            });
        }
    }

    /**
     * Header scroll effects
     */
    function initHeaderScroll() {
        let lastScrollTop = 0;
        const $header = $('.site-header');
        
        $(window).on('scroll', throttle(function() {
            const scrollTop = $(this).scrollTop();
            
            // Add/remove scrolled class
            if (scrollTop > 100) {
                $header.addClass('scrolled');
            } else {
                $header.removeClass('scrolled');
            }
            
            // Hide/show header on scroll
            if (scrollTop > lastScrollTop && scrollTop > 200) {
                $header.addClass('header-hidden');
            } else {
                $header.removeClass('header-hidden');
            }
            
            lastScrollTop = scrollTop;
        }, 100));
    }

    /**
     * Performance optimizations
     */
    function initPerformanceOptimizations() {
        // Preload critical resources on hover
        $('a[href="#contact"], .btn-primary').on('mouseenter', function() {
            if (!window.contactFormPreloaded) {
                // Preload form validation scripts
                window.contactFormPreloaded = true;
            }
        });

        // Optimize images with loading="lazy"
        $('img').each(function() {
            if (!$(this).attr('loading')) {
                $(this).attr('loading', 'lazy');
            }
        });

        // Add rel="noopener" to external links
        $('a[target="_blank"]').each(function() {
            const rel = $(this).attr('rel') || '';
            if (rel.indexOf('noopener') === -1) {
                $(this).attr('rel', rel + ' noopener').trim();
            }
        });
    }

    /**
     * Lazy loading implementation
     */
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            $('.lazy').each(function() {
                imageObserver.observe(this);
            });
        }
    }

    /**
     * Utility functions
     */
    
    // Throttle function for performance
    function throttle(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Get WhatsApp URL helper
    function getWhatsAppUrl(message) {
        const number = '+1234567890'; // This should be dynamic from WordPress
        const encodedMessage = encodeURIComponent(message);
        return `https://wa.me/${number.replace(/[^0-9]/g, '')}?text=${encodedMessage}`;
    }

    // Custom easing for animations
    $.easing.easeInOutCubic = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t + b;
        return c / 2 * ((t -= 2) * t * t + 2) + b;
    };

    /**
     * Analytics tracking
     */
    function trackEvent(action, category, label) {
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                event_category: category,
                event_label: label
            });
        }
    }

    // Track important interactions
    $('.whatsapp-float, a[href*="wa.me"]').on('click', function() {
        trackEvent('click', 'engagement', 'whatsapp_contact');
    });

    $('.btn-primary, .btn-secondary').on('click', function() {
        const text = $(this).text().trim();
        trackEvent('click', 'cta', text);
    });

    /**
     * Error handling and fallbacks
     */
    window.addEventListener('error', function(e) {
        console.error('JavaScript error:', e.error);
        
        // Fallback for critical features
        if (e.error && e.error.message.includes('jQuery')) {
            // Fallback for jQuery issues
            document.addEventListener('DOMContentLoaded', function() {
                initBasicFunctionality();
            });
        }
    });

    function initBasicFunctionality() {
        // Basic smooth scrolling without jQuery
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Page load performance monitoring
     */
    $(window).on('load', function() {
        if ('performance' in window) {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            
            // Log performance
            console.log('Page load time:', loadTime + 'ms');
            
            // Send to analytics if load time is concerning
            if (loadTime > 3000) {
                trackEvent('performance', 'page_load', 'slow_load_' + Math.round(loadTime / 1000) + 's');
            }
            
            // Track Core Web Vitals if available
            if ('web-vitals' in window) {
                webVitals.getLCP(metric => trackEvent('performance', 'lcp', Math.round(metric.value)));
                webVitals.getFID(metric => trackEvent('performance', 'fid', Math.round(metric.value)));
                webVitals.getCLS(metric => trackEvent('performance', 'cls', Math.round(metric.value * 1000)));
            }
        }
    });

})(jQuery);