(function($) {
    'use strict';

    var ptaSusAjax = {
        init: function() {
            this.container = $('#pta-sus-container');
            if (!this.container.length) return;

            this.bindEvents();
            this.handlePopState();
        },

        bindEvents: function() {
            var self = this;

            // Intercept internal navigation links
            $(document).on('click', '#pta-sus-container a.pta-sus-link', function(e) {
                var $link = $(this);
                
                // Skip external links or clear links (clear links might need full reload for nonce/cookie reasons, 
                // but we can try to AJAX them too if we want. The requirement says "intercept all internal navigation links")
                if ($link.hasClass('clear-signup')) return; 

                e.preventDefault();
                var url = $link.attr('href');
                self.loadPage(url, true);
            });

            // Intercept signup form submission
            $(document).on('submit', '#pta-sus-container form[name="pta_sus_signup_form"]', function(e) {
                e.preventDefault();
                self.submitSignupForm($(this));
            });
        },

        loadPage: function(url, pushState) {
            var self = this;
            var params = this.getQueryParams(url);
            
            this.container.css('opacity', 0.5);

            var data = {
                action: 'pta_sus_public_navigation',
                security: pta_sus_vars.nonce,
                sheet_id: params.sheet_id || '',
                date: params.date || '',
                task_id: params.task_id || '',
                atts: pta_sus_vars.atts || {}
            };

            $.post(pta_sus_vars.ajaxurl, data, function(response) {
                if (response.success) {
                    self.container.html($(response.data.html).html());
                    self.container.css('opacity', 1);
                    
                    if (pushState) {
                        window.history.pushState(params, '', url);
                    }
                    
                    // Scroll to top of container
                    $('html, body').animate({
                        scrollTop: self.container.offset().top - 100
                    }, 500);
                }
            });
        },

        submitSignupForm: function($form) {
            var self = this;
            var formData = $form.serializeArray();
            
            this.container.css('opacity', 0.5);

            var data = {
                action: 'pta_sus_public_signup_submit',
                security: pta_sus_vars.nonce,
                atts: pta_sus_vars.atts || {}
            };

            // Add form data to request
            $.each(formData, function(i, field) {
                data[field.name] = field.value;
            });

            $.post(pta_sus_vars.ajaxurl, data, function(response) {
                if (response.success) {
                    self.container.html($(response.data.html).html());
                    self.container.css('opacity', 1);
                    
                    // Scroll to top of container
                    $('html, body').animate({
                        scrollTop: self.container.offset().top - 100
                    }, 500);
                }
            });
        },

        handlePopState: function() {
            var self = this;
            $(window).on('popstate', function(e) {
                // If we have state, use it to reload the container
                self.loadPage(window.location.href, false);
            });
        },

        getQueryParams: function(url) {
            var params = {};
            var parser = document.createElement('a');
            parser.href = url;
            var query = parser.search.substring(1);
            var vars = query.split('&');
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split('=');
                if (pair[0]) {
                    params[pair[0]] = decodeURIComponent(pair[1]);
                }
            }
            return params;
        }
    };

    $(document).ready(function() {
        ptaSusAjax.init();
    });

})(jQuery);
