/**
 * Smart Cycle Discounts - Main Admin JavaScript
 * Core admin functionality that other components depend on
 * @param $
 */

( function( $ ) {
	'use strict';

	/**
	 * Patch jQuery .focus() to handle empty collections gracefully.
	 *
	 * The Freemius SDK (vendor library) has a bug in connect.php where it chains
	 * .focus() on a jQuery collection that may be empty (e.g., when #fs_license_key
	 * doesn't exist). This causes "Cannot read properties of null (reading 'focus')"
	 * errors. Rather than modifying vendor code (which would be overwritten on updates),
	 * we patch jQuery to safely handle empty collections.
	 *
	 * @since 1.0.0
	 */
	( function() {
		var originalFocus = $.fn.focus;
		$.fn.focus = function() {
			if ( 0 === this.length ) {
				// Return this for chaining (empty collection - nothing to focus)
				return this;
			}
			return originalFocus.apply( this, arguments );
		};
	} )();

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Admin = window.WSSCD.Admin || {};

	/**
	 * Core Admin Controller
	 */
	WSSCD.Admin = {
		// Configuration
		config: {
			ajaxUrl: window.ajaxurl || '',
			nonce: ( window.wsscdAdmin && window.wsscdAdmin.nonce ) || '',
			restNonce: ( window.wsscdAdmin && window.wsscdAdmin.restNonce ) || ( window.wpApiSettings && window.wpApiSettings.nonce ) || '',
			debug: ( window.wsscdAdmin && window.wsscdAdmin.debug ) || false,
			strings: ( window.wsscdAdmin && window.wsscdAdmin.strings ) || {}
		},

		/**
		 * Initialize admin functionality
		 */
		init: function() {
			if ( ! window.wsscdAdmin ) {
				return;
			}

			this.bindEvents();
			this.initializeComponents();
			this.setupAjaxHandlers();
			this.initNotificationSystem();
		},

		/**
		 * Bind global admin events
		 */
		bindEvents: function() {
			// Tab navigation
			$( document ).on( 'click', '.wsscd-tabs .wsscd-tab', this.handleTabClick );

			// Form submission
			$( document ).on( 'submit', '.wsscd-ajax-form', this.handleAjaxFormSubmit );

			// Tooltip initialization
			this.initTooltips();
		},

		/**
		 * Initialize admin components
		 */
		initializeComponents: function() {
			this.initConfirmDialogs();
			this.initToggleFields();
		},

		/**
		 * Handle tab clicks
		 * @param e
		 */
		handleTabClick: function( e ) {
			e.preventDefault();

			var $tab = $( this );
			var target = $tab.data( 'target' );

			if ( ! target ) {return;}

			$tab.addClass( 'active' ).siblings().removeClass( 'active' );

			$( target ).addClass( 'active' ).siblings( '.wsscd-tab-content' ).removeClass( 'active' );

			// Save active tab to localStorage
			if ( window.localStorage ) {
				var tabGroup = $tab.closest( '.wsscd-tabs' ).data( 'tab-group' );
				if ( tabGroup ) {
					localStorage.setItem( 'wsscd_active_tab_' + tabGroup, target );
				}
			}
		},

		/**
		 * Handle AJAX form submission
		 * @param e
		 */
		handleAjaxFormSubmit: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $submit = $form.find( '[type="submit"]' );
			var originalText = $submit.text();

			// Disable submit and show loading
			$submit.prop( 'disabled', true ).text( 'Processing...' );

			// Extract action from form data
			var formData = $form.serializeArray();
			var action = '';
			var requestData = {};

			$.each( formData, function( index, item ) {
				if ( 'action' === item.name ) {
					action = item.value;
				} else {
					requestData[item.name] = item.value;
				}
			} );

			// Use shared AJAX service
			if ( !window.WSSCD || !window.WSSCD.Ajax || !action ) {
				WSSCD.Admin.showNotice( 'AJAX service not available or action not specified', 'error' );
				$submit.prop( 'disabled', false ).text( originalText );
				return;
			}

			WSSCD.Ajax.post( action, requestData )
				.then( function( data ) {
					WSSCD.Admin.showNotice( data.message || 'Success!', 'success' );

					// Trigger custom event
					$form.trigger( 'wsscd:form:success', [ { success: true, data: data } ] );
				} )
				.catch( function( error ) {
					WSSCD.Admin.showNotice( error.message || 'An error occurred', 'error' );
				} )
				.finally( function() {
					$submit.prop( 'disabled', false ).text( originalText );
				} );
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips: function() {
			// Simple tooltip implementation
			$( '.wsscd-tooltip' ).each( function() {
				var $this = $( this );
				var title = $this.attr( 'title' );

				if ( ! title ) {return;}

				$this
					.on( 'mouseenter', function() {
						$( '<div class="wsscd-tooltip-content">' )
							.text( title )
							.appendTo( 'body' )
							.fadeIn( 'fast' );
					} )
					.on( 'mouseleave', function() {
						$( '.wsscd-tooltip-content' ).remove();
					} )
					.mousemove( function( e ) {
					$( '.wsscd-tooltip-content' ).css( {
						top: e.pageY + 10,
						left: e.pageX + 10
					} );
				} );
			} );
		},

		/**
		 * Initialize confirm dialogs
		 */
		initConfirmDialogs: function() {
			$( document ).on( 'click', '[data-confirm]', function( e ) {
				var message = $( this ).data( 'confirm' );
				if ( ! confirm( message ) ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					return false;
				}
			} );
		},

		/**
		 * Initialize toggle fields
		 */
		initToggleFields: function() {
			$( document ).on( 'change', '[data-toggle-target]', function() {
				var target = $( this ).data( 'toggle-target' );
				var showOn = $( this ).data( 'toggle-show-on' ) || 'checked';
				var shouldShow = false;

				if ( $( this ).is( ':checkbox' ) ) {
					shouldShow = ( 'checked' === showOn && $( this ).is( ':checked' ) ) ||
                                ( 'unchecked' === showOn && ! $( this ).is( ':checked' ) );
				} else {
					shouldShow = $( this ).val() === showOn;
				}

				$( target ).toggle( shouldShow );
			} );

			// Trigger initial state
			$( '[data-toggle-target]' ).trigger( 'change' );
		},

		/**
		 * Setup AJAX handlers
		 */
		setupAjaxHandlers: function() {
			// AJAX error handling delegated to ErrorHandler (via ajax-service.js)
			// Duplicate global handler removed to prevent double notifications

			// Setup loading indicator
			$( document ).ajaxStart( function() {
				$( 'body' ).addClass( 'wsscd-ajax-loading' );
			} ).ajaxStop( function() {
				$( 'body' ).removeClass( 'wsscd-ajax-loading' );
			} );
		},

		/**
		 * Initialize notification system
		 */
		initNotificationSystem: function() {
			// Listen for notification events
			$( document ).on( 'wsscd:notification:show', function( e, data ) {
				WSSCD.Shared.NotificationService.show( data.message, data.type, data.duration );
			} );
		},


		/**
		 * Make AJAX request
		 * @param action
		 * @param data
		 * @param callback
		 */
		ajax: function( action, data, callback ) {
			data = data || {};

			// Use shared AJAX service
			if ( !window.WSSCD || !window.WSSCD.Ajax ) {
				console.error( 'AJAX service not available' );
				if ( callback ) {
					callback( { success: false, error: 'AJAX service not available' } );
				}
				return $.Deferred().reject( 'AJAX service not available' ).promise();
			}

			var requestData = $.extend( {}, data );
			requestData.nonce = this.config.nonce;

			return WSSCD.Ajax.post( action, requestData )
				.then( function( responseData ) {
					if ( callback ) {
						callback( { success: true, data: responseData } );
					}
					return responseData;
				} )
				.catch( function( error ) {
					if ( callback ) {
						callback( { success: false, error: error } );
					}
					throw error;
				} );
		},


		/**
		 * Format currency - delegates to centralized utility
		 * @param amount
		 */
		formatCurrency: function( amount ) {
			// Use centralized utility
			return WSSCD.Utils.formatCurrency( amount );
		},

		/**
		 * Debounce helper - delegates to consolidated Utils
		 * @param func
		 * @param wait
		 */
		debounce: function( func, wait ) {
			if ( !WSSCD.Utils || !WSSCD.Utils.debounce ) {
				throw new Error( 'WSSCD.Utils not available. Ensure proper script loading order.' );
			}
			return WSSCD.Utils.debounce( func, wait );
		},

		/**
		 * Get URL parameter
		 * @param name
		 */
		getUrlParameter: function( name ) {
			name = name.replace( /\[/, '\\[' ).replace( /\]/, '\\]' );
			var regex = new RegExp( '[\\?&]' + name + '=([^&#]*)' );
			var results = regex.exec( location.search );
			return null === results ? '' : decodeURIComponent( results[1].replace( /\+/g, ' ' ) );
		}
	};

	// Campaign management helpers
	WSSCD.Admin.Campaigns = {
		/**
		 * Handle campaign status toggle
		 * @param campaignId
		 * @param newStatus
		 */
		toggleStatus: function( campaignId, newStatus ) {
			return WSSCD.Admin.ajax( 'wsscd_toggle_campaign_status', {
				campaignId: campaignId,
				status: newStatus
			} ).done( function( response ) {
				if ( response.success ) {
					WSSCD.Shared.NotificationService.success( response.data.message );
					// Reload table or update UI
					if ( 'undefined' !== typeof WSSCD.CampaignsList ) {
						WSSCD.CampaignsList.reload();
					}
				}
			} );
		},

		/**
		 * Delete campaign
		 * @param campaignId
		 */
		deleteCampaign: function( campaignId ) {
			if ( ! confirm( 'Are you sure you want to delete this campaign?' ) ) {
				return;
			}

			return WSSCD.Admin.ajax( 'wsscd_delete_campaign', {
				campaignId: campaignId
			} ).done( function( response ) {
				if ( response.success ) {
					WSSCD.Shared.NotificationService.success( 'Campaign deleted successfully' );
					// Reload or redirect
					if ( 'undefined' !== typeof WSSCD.CampaignsList ) {
						WSSCD.CampaignsList.reload();
					} else {
						window.location.href = window.wsscdCampaigns && window.wsscdCampaigns.urls && window.wsscdCampaigns.urls.campaignsList ?
							window.wsscdCampaigns.urls.campaignsList : '/wp-admin/admin.php?page=wsscd-campaigns';
					}
				}
			} );
		}
	};

	// Analytics helpers
	WSSCD.Admin.Analytics = {
		/**
		 * Load analytics data
		 * @param params
		 */
		loadData: function( params ) {
			return WSSCD.Admin.ajax( 'wsscd_get_analytics_data', params );
		},

		/**
		 * Export report
		 * @param format
		 * @param params
		 */
		exportReport: function( format, params ) {
			params.format = format;
			return WSSCD.Admin.ajax( 'wsscd_export_analytics', params );
		}
	};

	// Product search helpers
	WSSCD.Admin.Products = {
		/**
		 * Search products
		 * @param query
		 * @param params
		 */
		search: function( query, params ) {
			params = params || {};
			params.search = query;

			// REST API calls still use jQuery directly
			return $.ajax( {
				url: window.wsscdProducts && window.wsscdProducts.endpoints && window.wsscdProducts.endpoints.search ?
					window.wsscdProducts.endpoints.search : '/wp-json/wsscd/v1/products/search',
				method: 'GET',
				data: params,
				headers: {
					'X-WP-Nonce': WSSCD.Admin.config.restNonce // Use REST nonce for REST API calls
				}
			} );
		}
	};

	$( document ).ready( function() {
		WSSCD.Admin.init();
	} );

	// Restore active tabs
	$( window ).on( 'load', function() {
		if ( window.localStorage ) {
			$( '.wsscd-tabs[data-tab-group]' ).each( function() {
				var tabGroup = $( this ).data( 'tab-group' );
				var activeTab = localStorage.getItem( 'wsscd_active_tab_' + tabGroup );

				if ( activeTab ) {
					$( this ).find( '[data-target="' + activeTab + '"]' ).trigger( 'click' );
				}
			} );
		}
	} );

} )( jQuery );