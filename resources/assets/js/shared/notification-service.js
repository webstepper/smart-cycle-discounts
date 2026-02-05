/**
 * Notification Service
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/notification-service.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Shared = WSSCD.Shared || {};

	/**
	 * Notification Service
	 */
	WSSCD.Shared.NotificationService = {
		/**
		 * Configuration
		 */
		config: {
			defaultDuration: 3000,
			fadeOutDuration: 300,
			containerClass: 'wsscd-notifications-container',
			containerId: 'wsscd-notifications-container'
		},

		/**
		 * Active notifications
		 */
		notifications: {},

		/**
		 * Initialization flags
		 */
		initialized: false,
		containerInitialized: false,

		/**
		 * Event handler references for cleanup
		 */
		onlineHandler: null,
		offlineHandler: null,

		/**
		 * Initialize service
		 */
		init: function() {
			// FIX Bug #8: Add initialization guard
			if ( this.initialized ) {
				return;
			}

			this.createContainer();
			this.bindGlobalEvents();
			this.initialized = true;
		},

		/**
		 * FIX Bug #1: Ensure container initialized before use
		 */
		ensureInitialized: function() {
			if ( ! this.containerInitialized ) {
				this.createContainer();
				this.containerInitialized = true;
			}
		},

		/**
		 * Create notification container
		 */
		createContainer: function() {
			if ( ! $( '#' + this.config.containerId ).length ) {
				var $container = $( '<div>' )
					.attr( 'id', this.config.containerId )
					.addClass( this.config.containerClass );
				$( 'body' ).append( $container );
			}
			this.containerInitialized = true;
		},

		/**
		 * Show notification
		 *
		 * @param {string} message Message text
		 * @param {string} type Type: success, error, warning, info
		 * @param {number} duration Duration in ms (0 = permanent)
		 * @param {object} options Additional options
		 * @returns {jQuery} Notification element
		 */
		show: function( message, type, duration, options ) {
			// FIX Bug #1: Lazy initialization
			this.ensureInitialized();

			type = type || 'info';
			duration = 'undefined' !== typeof duration ? duration : this.config.defaultDuration;
			options = options || {};

			// FIX Bug #3: Always escape messages
			message = this.escapeHtml( this.extractMessage( message ) );

			// Handle replacement of existing notifications
			if ( options.id && options.replace ) {
				var existing = this.notifications[options.id];
				if ( existing ) {
					this.update( options.id, message, type );
					return existing.$element;
				}
			}

			var notification = this.create( message, type, options );

			notification.duration = duration;
			notification.startedAt = Date.now();

			this.addToContainer( notification );

			// Setup auto-hide
			if ( 0 < duration ) {
				this.scheduleHide( notification, duration );
			}

			if ( options.id ) {
				this.notifications[options.id] = notification;
			}

			return notification.$element;
		},

		/**
		 * Create notification object
		 * @param message
		 * @param type
		 * @param options
		 */
		create: function( message, type, options ) {
			var id = options.id ? 'wsscd-notification-' + options.id : 'wsscd-notification-' + Date.now();

			var $element = $( '<div>' )
				.attr( 'id', id )
				.addClass( 'wsscd-notification wsscd-notification--' + type + ' wsscd-notification--show' )
				.attr( 'role', 'alert' )
				.attr( 'aria-live', 'polite' );

		// Message element (icon provided by CSS ::before pseudo-element)
			$( '<span>' )
				.addClass( 'wsscd-notification__message' )
				.text( message )
				.appendTo( $element );

			var $closeButton = $( '<button>' )
				.addClass( 'wsscd-notification__close' )
				.attr( 'aria-label', 'Close notification' )
				.attr( 'type', 'button' );

			$closeButton.html( WSSCD.IconHelper.close( { size: 16 } ) );

			$closeButton.appendTo( $element );

		// Icon styling handled by CSS ::before pseudo-elements (see notifications.css:208-222)

			var notification = {
				id: id,
				$element: $element,
				type: type,
				timer: null,
				fadeTimer: null // FIX Bug #4: Track fade timer
			};

			// Bind events
			this.bindNotificationEvents( notification );

			return notification;
		},

		/**
		 * Update existing notification
		 * @param id
		 * @param message
		 * @param type
		 */
		update: function( id, message, type ) {
			var notification = this.notifications[id];
			if ( ! notification ) {
				return;
			}

			// FIX Bug #3: Escape message in update
			message = this.escapeHtml( message );

			notification.$element.find( '.wsscd-notification__message' ).text( message );

			if ( type ) {
				notification.$element
					.removeClass( 'wsscd-notification--info wsscd-notification--success wsscd-notification--warning wsscd-notification--error' )
					.addClass( 'wsscd-notification--' + type );

			}

			// Flash to indicate update using CSS animation
			notification.$element.addClass( 'wsscd-notification--flash' );
			notification.$element.one( 'animationend webkitAnimationEnd', function() {
				notification.$element.removeClass( 'wsscd-notification--flash' );
			} );
		},

		/**
		 * Add notification to container
		 * @param notification
		 */
		addToContainer: function( notification ) {
			var $container = $( '#' + this.config.containerId );
			$container.append( notification.$element );

			// CSS animation handles entrance (wsscdSlideDown - 200ms)
			// Removed jQuery .hide().fadeIn() to prevent double animation conflict

			setTimeout( function() {
				notification.$element.attr( 'tabindex', '-1' ).trigger( 'focus' );
			}, 50 ); // Small delay to prevent focus from interfering with animation
		},

		/**
		 * Bind notification events
		 * @param notification
		 */
		bindNotificationEvents: function( notification ) {
			var self = this;

			// Close button
			notification.$element.find( '.wsscd-notification__close' ).on( 'click', function( e ) {
				e.preventDefault();
				self.hide( notification );
			} );

			// Keyboard support
			notification.$element.on( 'keydown', function( e ) {
				if ( 'Escape' === e.key ) {
					self.hide( notification );
				}
			} );

			// Hover to pause auto-hide
			notification.$element.on( 'mouseenter', function() {
				if ( notification.timer ) {
					clearTimeout( notification.timer );
					notification.timer = null;
					notification.pausedAt = Date.now();
					notification.remainingTime = notification.duration - ( notification.pausedAt - notification.startedAt );
				}
			} );

			notification.$element.on( 'mouseleave', function() {
				// Resume timer with remaining time
				if ( notification.remainingTime && 0 < notification.remainingTime ) {
					notification.timer = setTimeout( function() {
						self.hide( notification );
					}, notification.remainingTime );
					notification.startedAt = Date.now() - ( notification.duration - notification.remainingTime );
				}
			} );
		},

		/**
		 * Schedule auto-hide
		 * @param notification
		 * @param duration
		 */
		scheduleHide: function( notification, duration ) {
			var self = this;

			// Use JavaScript timer for consistent behavior
			notification.timer = setTimeout( function() {
				self.hide( notification );
			}, duration );
		},

		/**
		 * Hide notification
		 * @param notification
		 */
		hide: function( notification ) {
			var self = this;

			// FIX Bug #4: Clear auto-hide timer
			if ( notification.timer ) {
				clearTimeout( notification.timer );
				notification.timer = null;
			}

			notification.$element.addClass( 'wsscd-notification--fade-out' );

			// FIX Bug #4: Track fade timer
			notification.fadeTimer = setTimeout( function() {
				// FIX Bug #2: Unbind events before removing
				notification.$element.off();
				notification.$element.remove();

				for ( var id in self.notifications ) {
					if ( self.notifications[id] === notification ) {
						delete self.notifications[id];
						break;
					}
				}
			}, self.config.fadeOutDuration );
		},

		/**
		 * Hide all notifications
		 */
		hideAll: function() {
			for ( var id in this.notifications ) {
				if ( Object.prototype.hasOwnProperty.call( this.notifications, id ) ) {
					var notification = this.notifications[id];

					// FIX Bug #4: Clear all timers
					if ( notification.timer ) {
						clearTimeout( notification.timer );
						notification.timer = null;
					}
					if ( notification.fadeTimer ) {
						clearTimeout( notification.fadeTimer );
						notification.fadeTimer = null;
					}

					this.hide( notification );
				}
			}
		},

		/**
		 * Dismiss notification by ID
		 * @param id
		 */
		dismiss: function( id ) {
			// FIX Bug #6: Don't double-prefix ID
			var notification = this.notifications[id];
			if ( notification ) {
				this.hide( notification );
			}
		},

		/**
		 * FIX Bug #5: Add destroy method for cleanup
		 */
		destroy: function() {
			this.hideAll();

			// Unbind global events
			$( document ).off( 'wsscd:notify wsscd:wizard:stepChanged wsscd:session:expired' );

			if ( this.onlineHandler ) {
				window.removeEventListener( 'online', this.onlineHandler );
				this.onlineHandler = null;
			}
			if ( this.offlineHandler ) {
				window.removeEventListener( 'offline', this.offlineHandler );
				this.offlineHandler = null;
			}

			$( '#' + this.config.containerId ).remove();

			this.initialized = false;
			this.containerInitialized = false;
		},

		/**
		 * Success notification shorthand
		 * @param message
		 * @param options
		 */
		success: function( message, options ) {
			return this.show( message, 'success', undefined, options );
		},

		/**
		 * Error notification shorthand
		 * @param message
		 * @param options
		 */
		error: function( message, options ) {
			return this.show( message, 'error', undefined, options );
		},

		/**
		 * Warning notification shorthand
		 * @param message
		 * @param options
		 */
		warning: function( message, options ) {
			return this.show( message, 'warning', undefined, options );
		},

		/**
		 * Info notification shorthand
		 * @param message
		 * @param options
		 */
		info: function( message, options ) {
			return this.show( message, 'info', undefined, options );
		},

		/**
		 * Extract message from various formats
		 * @param message
		 */
		extractMessage: function( message ) {
			if ( 'object' === typeof message && null !== message ) {
				if ( message.message ) {
					return message.message;
				} else if ( message.error ) {
					return message.error;
				} else if ( message.data && message.data.message ) {
					return message.data.message;
				} else if ( message.toString ) {
					return message.toString();
				}
			}

			return message || 'An error occurred';
		},


		/**
		 * FIX Bug #3: Escape HTML to prevent XSS
		 * @param text
		 */
		escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};

			return String( text ).replace( /[&<>"']/g, function( m ) {
				return map[m];
			} );
		},

		/**
		 * Bind global events
		 */
		bindGlobalEvents: function() {
			var self = this;

			// Listen for notification events
			$( document ).on( 'wsscd:notify', function( e, data ) {
				self.show( data.message, data.type, data.duration, data.options );
			} );

			// This ensures users get a clean slate when moving between steps
			$( document ).on( 'wsscd:wizard:stepChanged', function() {
				self.hideAll();

				// Also clear inline validation errors for fresh step state
				if ( window.WSSCD && window.WSSCD.ValidationError ) {
					var $wizardContainer = $( '.wsscd-wizard-content, .wsscd-wizard-wrap' );
					if ( $wizardContainer.length ) {
						window.WSSCD.ValidationError.clearAll( $wizardContainer );
					}
				}
			} );

			// Handle session expiration
			$( document ).on( 'wsscd:session:expired', function() {
				self.error( 'Your session has expired. Please refresh the page to continue.', {
					duration: 0, // Permanent notification
					id: 'session-expired',
					replace: true
				} );

				// Disable forms to prevent further actions
				$( 'form' ).find( 'input, button, select, textarea' ).prop( 'disabled', true );
			} );

			// FIX Bug #5: Store handlers for cleanup
			// Network connectivity monitoring
			if ( window.addEventListener ) {
				var isOnline = navigator.onLine;

				this.onlineHandler = function() {
					if ( ! isOnline ) {
						isOnline = true;
						self.success( 'Connection restored', {
							duration: 3000,
							id: 'connection-status',
							replace: true
						} );
					}
				};

				this.offlineHandler = function() {
					if ( isOnline ) {
						isOnline = false;
						self.error( 'Connection lost. Please check your internet connection.', {
							duration: 0, // Permanent until connection restored
							id: 'connection-status',
							replace: true
						} );
					}
				};

				window.addEventListener( 'online', this.onlineHandler );
				window.addEventListener( 'offline', this.offlineHandler );
			}
		}
	};

	/**
	 * Extract error message from API response (success/data/message or error code/message).
	 * Use for consistent error display in admin (tools, overview panel, etc.).
	 *
	 * @param {Object} response Response object (may be null).
	 * @param {string} fallback Fallback message if none found.
	 * @return {string} Error message.
	 */
	WSSCD.Shared.extractErrorMessage = function( response, fallback ) {
		var defaultFallback = fallback || 'An error occurred. Please try again.';
		if ( ! response ) {
			return defaultFallback;
		}
		if ( response.message && 'string' === typeof response.message ) {
			return response.message;
		}
		if ( response.data && response.data.message && 'string' === typeof response.data.message ) {
			return response.data.message;
		}
		if ( response.error && typeof response.error === 'object' && response.error.message && 'string' === typeof response.error.message ) {
			return response.error.message;
		}
		if ( response.error && Array.isArray( response.error ) && response.error.length > 0 ) {
			return response.error[0].message || response.error[0];
		}
		if ( response.error && 'string' === typeof response.error ) {
			return response.error;
		}
		return defaultFallback;
	};

	$( document ).ready( function() {
		WSSCD.Shared.NotificationService.init();
	} );

} )( jQuery );
