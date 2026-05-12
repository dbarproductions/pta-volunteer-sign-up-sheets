(function($) {
    'use strict';

    var ptaSusAjax = {
        init: function() {
            this.bindEvents();
            this.handlePopState();
        },

        bindEvents: function() {
            var self = this;

            // Intercept internal navigation links
            $(document).on('click', '.pta-sus-ajax-container a.pta-sus-link', function(e) {
                var $link = $(this);
                var $container = $link.closest('.pta-sus-ajax-container');
                
                if ($link.hasClass('clear-signup')) return; 

                e.preventDefault();
                var url = $link.attr('href');
                self.loadPage(url, true, $container);
            });

            // Intercept signup form submission
            $(document).on('submit', '.pta-sus-ajax-container form[name="pta_sus_signup_form"]', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $container = $form.closest('.pta-sus-ajax-container');
                self.submitSignupForm($form, $container);
            });
        },

        loadPage: function(url, pushState, $container) {
            var self = this;
            var params = this.getQueryParams(url);
            var containerId = $container.data('pta-sus-instance');
            var atts = (typeof pta_sus_instances !== 'undefined' && pta_sus_instances[containerId]) ? pta_sus_instances[containerId] : {};
            
            $container.css('opacity', 0.5);

            var data = {
                action: 'pta_sus_public_navigation',
                security: pta_sus_vars.nonce,
                atts: atts,
                container_id: containerId
            };

            // Add all query params to data
            $.extend(data, params);

            $.post(pta_sus_vars.ajaxurl, data, function(response) {
                if (response.success) {
                    var $newContent = $(response.data.html);
                    $container.html($newContent.html());
                    $container.css('opacity', 1);
                    
                    if (pushState) {
                        window.history.pushState({
                            containerId: containerId,
                            params: params
                        }, '', url);
                    }
                    
                    // Scroll to top of container
                    $('html, body').animate({
                        scrollTop: $container.offset().top - 100
                    }, 500);
                }
            });
        },

        submitSignupForm: function($form, $container) {
            var self = this;
            var formData = $form.serializeArray();
            var containerId = $container.data('pta-sus-instance');
            var atts = (typeof pta_sus_instances !== 'undefined' && pta_sus_instances[containerId]) ? pta_sus_instances[containerId] : {};
            
            $container.css('opacity', 0.5);

            var data = {
                action: 'pta_sus_public_signup_submit',
                security: pta_sus_vars.nonce,
                atts: atts,
                container_id: containerId
            };

            // Add form data to request
            $.each(formData, function(i, field) {
                data[field.name] = field.value;
            });

            $.post(pta_sus_vars.ajaxurl, data, function(response) {
                if (response.success) {
                    var $newContent = $(response.data.html);
                    $container.html($newContent.html());
                    $container.css('opacity', 1);
                    
                    // Scroll to top of container
                    $('html, body').animate({
                        scrollTop: $container.offset().top - 100
                    }, 500);
                }
            });
        },

        handlePopState: function() {
            var self = this;
            $(window).on('popstate', function(e) {
                var state = e.originalEvent.state;
                if (state && state.containerId) {
                    var $container = $('[data-pta-sus-instance="' + state.containerId + '"]');
                    if ($container.length) {
                        self.loadPage(window.location.href, false, $container);
                    }
                } else {
                    // Fallback for initial page state or non-SPA states
                    $('.pta-sus-ajax-container').each(function() {
                        self.loadPage(window.location.href, false, $(this));
                    });
                }
            });
        },

        getQueryParams: function(url) {
            var params = {};
            var parser = document.createElement('a');
            parser.href = url;
            var query = parser.search.substring(1);
            if (!query) return params;
            var vars = query.split('&');
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split('=');
                if (pair[0]) {
                    params[pair[0]] = decodeURIComponent(pair[1] || '');
                }
            }
            return params;
        }
    };

    $(document).ready(function() {
        ptaSusAjax.init();
    });

})(jQuery);
