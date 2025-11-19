/**
 * Ui Utilities
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/ui-utilities.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * UI Utilities
	 */
	SCD.Shared.UI = {

	/**
	 * Show loading overlay
	 *
	 * Delegates to centralized LoaderUtil for consistent loading UX.
	 *
	 * @param {string|jQuery} target Target element selector or jQuery object
	 * @param {string} message Loading message
	 * @returns {jQuery} Overlay element
	 */
	showLoading: function( target, message ) {
		// Delegate to centralized LoaderUtil
		if ( window.SCD && window.SCD.LoaderUtil ) {
			return SCD.LoaderUtil.showOverlay( target, message );
		}

		// Fallback for backward compatibility (should never reach here)
		message = message || 'Loading...';
		var $target = $( target );
		if ( ! $target.length ) {
			return null;
		}

		var $overlay = $(
			'<div class="scd-loading-overlay">' +
                '<div class="scd-loading-content">' +
                    '<span class="spinner is-active"></span>' +
                    '<span class="scd-loading-text">' + this.escapeHtml( message ) + '</span>' +
                '</div>' +
            '</div>'
		);

		$target.append( $overlay );
		$overlay.hide().fadeIn( 200 );
		return $overlay;
	},

	/**
	 * Hide loading overlay
	 *
	 * Delegates to centralized LoaderUtil for consistent loading UX.
	 *
	 * @param {string|jQuery} target Target element
	 */
	hideLoading: function( target ) {
		// Delegate to centralized LoaderUtil
		if ( window.SCD && window.SCD.LoaderUtil ) {
			SCD.LoaderUtil.hideOverlay( target );
			return;
		}

		// Fallback for backward compatibility (should never reach here)
		var $target = $( target );
		$target.find( '.scd-loading-overlay' ).fadeOut( 200, function() {
			$( this ).remove();
		} );
	},

		/**
		 * Show confirmation dialog
		 *
		 * @param {string} title Dialog title
		 * @param {string} message Dialog message
		 * @param {object} options Dialog options
		 * @returns {Promise} Promise resolving to boolean
		 */
		confirm: function( title, message, options ) {
			options = options || {};
			return $.Deferred( function( deferred ) {
				var defaults = {
					confirmText: 'OK',
					cancelText: 'Cancel',
					confirmClass: 'button-primary',
					cancelClass: 'button-secondary',
					dangerous: false
				};

				var config = $.extend( {}, defaults, options );

				// Use native confirm for simplicity ( can be enhanced with custom modal )
				if ( false !== config.useNative ) {
					var result = window.confirm( message );
					deferred.resolve( result );
					return;
				}

				// Custom modal implementation
				var $modal = this.createModal( {
					title: title,
					content: '<p>' + ( this.escapeHtml( message ) ) + '</p>',
					buttons: [
						{
							text: config.cancelText,
							class: config.cancelClass,
							click: function() {
								$modal.remove();
								deferred.resolve( false );
							}
						},
						{
							text: config.confirmText,
							class: config.dangerous ? 'button-primary button-danger' : config.confirmClass,
							click: function() {
								$modal.remove();
								deferred.resolve( true );
							}
						}
					]
				} );
			} ).promise();
		},

		/**
		 * Create modal dialog
		 *
		 * @param {object} options Modal options
		 * @returns {jQuery} Modal element
		 */
		createModal: function( options ) {
			options = options || {};
			var defaults = {
				title: '',
				content: '',
				buttons: [],
				closeOnEscape: true,
				closeOnOverlay: true,
				width: 600,
				height: 'auto'
			};

			var config = $.extend( {}, defaults, options );

			var $modal = $(
				'<div class="scd-modal-overlay">' +
                    '<div class="scd-modal" style="width: ' + ( config.width ) + 'px; ' + ( 'auto' !== config.height ? 'height: ' + config.height + 'px;' : '' ) + '">' +
                        '<div class="scd-modal-header">' +
                            '<h2>' + ( this.escapeHtml( config.title ) ) + '</h2>' +
                            '<button type="button" class="scd-modal-close" aria-label="Close">' +
                                ( SCD.IconHelper ? SCD.IconHelper.close( { size: 20 } ) : '<span class="scd-icon scd-icon-close"></span>' ) +
                            '</button>' +
                        '</div>' +
                        '<div class="scd-modal-content">' +
                            config.content +
                        '</div>' +
                        ( config.buttons.length ? '<div class="scd-modal-footer"></div>' : '' ) +
                    '</div>' +
                '</div>'
			);

			if ( config.buttons.length ) {
				var $footer = $modal.find( '.scd-modal-footer' );
				config.buttons.forEach( function( button ) {
					var $button = $( '<button type="button" class="' + ( button.class || 'button' ) + '">' + ( this.escapeHtml( button.text ) ) + '</button>' );
					if ( button.click ) {
						$button.on( 'click', button.click );
					}
					$footer.append( $button );
				} );
			}

			$( 'body' ).append( $modal );

			// Center modal
			var $modalBox = $modal.find( '.scd-modal' );
			$modalBox.css( {
				'margin-top': -( $modalBox.outerHeight() / 2 ),
				'margin-left': -( $modalBox.outerWidth() / 2 )
			} );

			// Event handlers
			$modal.on( 'click', '.scd-modal-close', function() {
				$modal.remove();
			} );

			if ( config.closeOnOverlay ) {
				$modal.on( 'click', function( e ) {
					if ( e.target === this ) {
						$modal.remove();
					}
				} );
			}

			if ( config.closeOnEscape ) {
				$( document ).on( 'keydown.scd-modal', function( e ) {
					if ( 27 === e.keyCode ) {
						$modal.remove();
						$( document ).off( 'keydown.scd-modal' );
					}
				} );
			}

			// Fade in
			$modal.hide().fadeIn( 200 );

			return $modal;
		},

		/**
		 * Create tooltip
		 *
		 * @param {string|jQuery} element Target element
		 * @param {string} content Tooltip content
		 * @param {object} options Tooltip options
		 */
		tooltip: function( element, content, options ) {
			options = options || {};
			var $element = $( element );
			if ( ! $element.length ) {
				return;
			}

			var defaults = {
				position: 'top',
				delay: 200,
				className: ''
			};

			var config = $.extend( {}, defaults, options );

			// WordPress uses title attribute for tooltips
			$element.attr( 'title', content );

			// Custom tooltip with hover delay (optional)
			if ( config.enhanced ) {
				var $tooltip = null;
				var showTimeout = null;
				var hideTimeout = null;

				$element.on( 'mouseenter', function() {
					clearTimeout( hideTimeout );

					// Use CSS transition-delay instead of setTimeout
					var $tempTooltip = $(
						'<div class="scd-tooltip scd-tooltip--delayed ' + ( config.className ) + '" data-position="' + ( config.position ) + '">' +
                            '<div class="scd-tooltip-content">' + ( content ) + '</div>' +
                            '<div class="scd-tooltip-arrow"></div>' +
                        '</div>'
					);

					$tempTooltip.css( 'transition-delay', config.delay + 'ms' );

					// Position tooltip
					$( 'body' ).append( $tempTooltip );
					var offset = $element.offset();
					var width = $element.outerWidth();
					var height = $element.outerHeight();

					switch ( config.position ) {
						case 'top':
							$tempTooltip.css( {
								top: offset.top - $tempTooltip.outerHeight() - 10,
								left: offset.left + ( width / 2 ) - ( $tempTooltip.outerWidth() / 2 )
							} );
							break;
						case 'bottom':
							$tempTooltip.css( {
								top: offset.top + height + 10,
								left: offset.left + ( width / 2 ) - ( $tempTooltip.outerWidth() / 2 )
							} );
							break;
						case 'left':
							$tempTooltip.css( {
								top: offset.top + ( height / 2 ) - ( $tempTooltip.outerHeight() / 2 ),
								left: offset.left - $tempTooltip.outerWidth() - 10
							} );
							break;
						case 'right':
							$tempTooltip.css( {
								top: offset.top + ( height / 2 ) - ( $tempTooltip.outerHeight() / 2 ),
								left: offset.left + width + 10
							} );
							break;
					}

					// Force reflow then add show class
					void $tempTooltip[0].offsetHeight; // Force reflow
					$tempTooltip.addClass( 'scd-tooltip--show' );
					$tooltip = $tempTooltip;
				} );

				$element.on( 'mouseleave', function() {
					clearTimeout( showTimeout );

					if ( $tooltip ) {
						// Use CSS transitions for hide
						$tooltip.removeClass( 'scd-tooltip--show' );
						$tooltip.one( 'transitionend webkitTransitionEnd', function() {
							$( this ).remove();
						} );
						$tooltip = null;
					}
				} );
			}
		},

		/**
		 * Create progress bar
		 *
		 * @param {object} options Progress bar options
		 * @returns {object} Progress bar API
		 */
		progressBar: function( options ) {
			options = options || {};
			var defaults = {
				container: 'body',
				label: '',
				value: 0,
				max: 100,
				showPercent: true,
				animated: true
			};

			var config = $.extend( {}, defaults, options );

			var $progress = $(
				'<div class="scd-progress-bar ' + ( config.animated ? 'animated' : '' ) + '">' +
                    ( config.label ? '<div class="scd-progress-label">' + this.escapeHtml( config.label ) + '</div>' : '' ) +
                    '<div class="scd-progress-track">' +
                        '<div class="scd-progress-fill" style="width: ' + ( config.value ) + '%"></div>' +
                    '</div>' +
                    ( config.showPercent ? '<div class="scd-progress-percent">' + Math.round( config.value ) + '%</div>' : '' ) +
                '</div>'
			);

			$( config.container ).append( $progress );

			// API
			return {
				update: function( value, label ) {
					var percent = Math.min( 100, Math.max( 0, ( value / config.max ) * 100 ) );
					$progress.find( '.scd-progress-fill' ).css( 'width', percent + '%' );

					if ( config.showPercent ) {
						$progress.find( '.scd-progress-percent' ).text( Math.round( percent ) + '%' );
					}

					if ( label !== undefined ) {
						$progress.find( '.scd-progress-label' ).text( label );
					}
				},

				remove: function() {
					$progress.fadeOut( 200, function() {
						$( this ).remove();
					} );
				}
			};
		},

		/**
		 * Escape HTML
		 *
		 * @param {string} text Text to escape
		 * @returns {string} Escaped text
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
		 * Format notice for inline display
		 *
		 * @param {string} message Message text
		 * @param {string} type Notice type
		 * @returns {string} HTML string
		 */
		inlineNotice: function( message, type ) {
			type = type || 'info';
			var iconNames = {
				success: 'check',
				error: 'warning',
				warning: 'info',
				info: 'info'
			};

			var iconName = iconNames[type] || 'info';
			var icon = SCD.IconHelper ? SCD.IconHelper.get( iconName, { size: 16 } ) : '<span class="scd-icon scd-icon-' + iconName + '"></span>';

			return '<div class="scd-inline-notice scd-inline-notice-' + ( type ) + '">' +
                    icon +
                    '<span>' + ( this.escapeHtml( message ) ) + '</span>' +
                '</div>';
		},

		/**
		 * Toggle element visibility with animation
		 *
		 * @param {string|jQuery} element Element to toggle
		 * @param {boolean} show Show/hide
		 * @param {number} duration Animation duration
		 */
		toggle: function( element, show, duration ) {
			duration = duration || 200;
			var $element = $( element );

			if ( show ) {
				$element.stop( true, true ).slideDown( duration );
			} else {
				$element.stop( true, true ).slideUp( duration );
			}
		},

		/**
		 * Initialize WordPress admin pointers
		 *
		 * @param {Array} pointers Array of pointer configurations
		 */
		initPointers: function( pointers ) {
			if ( ! $.fn.pointer ) {
				return;
			}

			pointers.forEach( function( pointer ) {
				$( pointer.target ).pointer( {
					content: '<h3>' + ( this.escapeHtml( pointer.title ) ) + '</h3><p>' + ( this.escapeHtml( pointer.content ) ) + '</p>',
					position: pointer.position || {
						edge: 'left',
						align: 'center'
					},
					pointerWidth: pointer.width || 320,
					close: function() {
						// Save dismissed state
						if ( pointer.id ) {
							SCD.Ajax.post( 'scd_dismiss_pointer', {
								pointer: pointer.id
							} );
						}
					}
				} ).pointer( 'open' );
			} );
		}
	};

} )( jQuery );