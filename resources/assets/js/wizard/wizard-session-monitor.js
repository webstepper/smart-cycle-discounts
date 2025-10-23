/**
 * Wizard Session Monitor
 *
 * Monitors session expiration and warns users before data loss.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Session Monitor Module
    var SessionMonitor = {
        /**
         * Check interval (1 minute)
         */
        CHECK_INTERVAL: 60000,

        /**
         * Warning threshold (5 minutes before expiration)
         */
        WARNING_THRESHOLD: 300,

        /**
         * Timer ID
         */
        timerId: null,

        /**
         * Warning shown flag
         */
        warningShown: false,

        /**
         * Last check timestamp
         */
        lastCheck: 0,

        /**
         * Initialize the session monitor
         */
        init: function() {
            if (typeof SCD === 'undefined' || !SCD.wizard) {
                return;
            }

            // Start monitoring
            this.startMonitoring();

            // Listen for wizard activity to extend session
            this.bindActivityListeners();

        },

        /**
         * Start monitoring session expiration
         */
        startMonitoring: function() {
            var self = this;

            // Clear any existing timer
            if (this.timerId) {
                clearInterval(this.timerId);
            }

            // Check immediately
            this.checkSession();

            // Check every minute
            this.timerId = setInterval(function() {
                self.checkSession();
            }, this.CHECK_INTERVAL);
        },

        /**
         * Stop monitoring
         */
        stopMonitoring: function() {
            if (this.timerId) {
                clearInterval(this.timerId);
                this.timerId = null;
            }
        },

        /**
         * Check session status
         */
        checkSession: function() {
            var self = this;
            var now = Date.now();

            // Throttle checks to once per minute
            if (now - this.lastCheck < this.CHECK_INTERVAL - 1000) {
                return;
            }

            this.lastCheck = now;

            // Make AJAX request to check session (via AjaxService to prevent rate limiting)
            SCD.Ajax.post('session_status', {}).then(function(response) {
                if (response.success && response.data) {
                    self.handleSessionStatus(response.data);
                }
            }).catch(function(error) {
                console.error('[SCD Session Monitor] AJAX error:', error);
            });
        },

        /**
         * Handle session status response
         *
         * @param {Object} data - Response data
         */
        handleSessionStatus: function(data) {
            if (!data.session_exists) {
                // Session expired
                this.showSessionExpiredModal();
                this.stopMonitoring();
                return;
            }

            var expirationInfo = data.expiration_info;

            if (expirationInfo.is_expired) {
                // Session just expired
                this.showSessionExpiredModal();
                this.stopMonitoring();
            } else if (expirationInfo.is_expiring_soon && !this.warningShown) {
                // Session expiring soon (less than 5 minutes)
                this.showExpirationWarning(expirationInfo.time_remaining);
                this.warningShown = true;
            }
        },

        /**
         * Show expiration warning
         *
         * @param {number} timeRemaining - Seconds until expiration
         */
        showExpirationWarning: function(timeRemaining) {
            var minutes = Math.ceil(timeRemaining / 60);

            var message = 'Your session will expire in ' + minutes + ' minute' + (minutes !== 1 ? 's' : '') + '. ';
            message += 'Save your progress to avoid losing data.';

            // Show warning using NotificationService
            if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
                window.SCD.Shared.NotificationService.warning(
                    'Session Expiration Warning: ' + message,
                    10000, // 10 seconds for important session warning
                    {
                        id: 'session-expiration-warning',
                        replace: true,
                        dismissible: true
                    }
                );
            }

            // Trigger event
            if (SCD.EventBus) {
                SCD.EventBus.trigger('session:warning', {
                    timeRemaining: timeRemaining,
                    minutes: minutes
                });
            }

        },

        /**
         * Show session expired modal
         */
        showSessionExpiredModal: function() {
            var modalHTML = [
                '<div class="scd-modal scd-session-expired-modal" style="display: block;">',
                '    <div class="scd-modal-overlay"></div>',
                '    <div class="scd-modal-content">',
                '        <div class="scd-modal-header">',
                '            <h2>Session Expired</h2>',
                '        </div>',
                '        <div class="scd-modal-body">',
                '            <p>Your session has expired. Any unsaved changes may be lost.</p>',
                '            <p>Please refresh the page to continue working.</p>',
                '        </div>',
                '        <div class="scd-modal-footer">',
                '            <button type="button" class="button button-primary scd-refresh-page">Refresh Page</button>',
                '        </div>',
                '    </div>',
                '</div>'
            ].join('\n');

            // Add modal to page
            $('body').append(modalHTML);

            // Bind refresh button
            $('.scd-refresh-page').on('click', function() {
                window.location.reload();
            });

            // Trigger event
            if (SCD.EventBus) {
                SCD.EventBus.trigger('session:expired');
            }

            console.error('[SCD Session Monitor] Session expired');
        },

        /**
         * Bind activity listeners to extend session
         */
        bindActivityListeners: function() {
            var self = this;

            // Listen for step saves (session automatically extends)
            if (SCD.EventBus) {
                SCD.EventBus.on('step:saved', function() {
                    // Session was extended by save, reset warning
                    self.warningShown = false;

                    // Remove any existing warning notice
                    $('.scd-session-warning').fadeOut(function() {
                        $(this).remove();
                    });
                });
            }

            // Listen for wizard navigation (indicates user is active)
            $(document).on('wizard:navigate', function() {
                // User is active, check session status
                self.checkSession();
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize on wizard pages
        if ($('.scd-wizard-container').length > 0) {
            SessionMonitor.init();
        }
    });

    // Expose to global scope
    window.SCD = window.SCD || {};
    window.SCD.SessionMonitor = SessionMonitor;

})(jQuery);
