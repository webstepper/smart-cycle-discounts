/**
 * Smart Cycle Discounts - Main Admin JavaScript
 * Core admin functionality that other components depend on
 * @param $
 */

( function( $ ) {
	'use strict';

	// Create namespace
	window.SCD = window.SCD || {};
	window.SCD.Admin = window.SCD.Admin || {};

	/**
	 * Core Admin Controller
	 */
	SCD.Admin = {
		// Configuration
		config: {
			ajaxUrl: window.ajaxurl || '',
			nonce: ( window.scdAdmin && window.scdAdmin.nonce ) || '',
			restNonce: ( window.scdAdmin && window.scdAdmin.rest_nonce ) || ( window.wpApiSettings && window.wpApiSettings.nonce ) || '',
			debug: ( window.scdAdmin && window.scdAdmin.debug ) || false,
			strings: ( window.scdAdmin && window.scdAdmin.strings ) || {}
		},

		/**
		 * Initialize admin functionality
		 */
		init: function() {
			// Check if we have required data
			if ( ! window.scdAdmin ) {
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
			$( document ).on( 'click', '.scd-tabs .scd-tab', this.handleTabClick );

			// Form submission
			$( document ).on( 'submit', '.scd-ajax-form', this.handleAjaxFormSubmit );

			// Tooltip initialization
			this.initTooltips();
		},

		/**
		 * Initialize admin components
		 */
		initializeComponents: function() {
			// Initialize any admin components
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

			// Update active states
			$tab.addClass( 'active' ).siblings().removeClass( 'active' );

			// Show target content
			$( target ).addClass( 'active' ).siblings( '.scd-tab-content' ).removeClass( 'active' );

			// Save active tab to localStorage
			if ( window.localStorage ) {
				var tabGroup = $tab.closest( '.scd-tabs' ).data( 'tab-group' );
				if ( tabGroup ) {
					localStorage.setItem( 'scd_active_tab_' + tabGroup, target );
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
			if ( !window.SCD || !window.SCD.Ajax || !action ) {
				SCD.Admin.showNotice( 'AJAX service not available or action not specified', 'error' );
				$submit.prop( 'disabled', false ).text( originalText );
				return;
			}

			SCD.Ajax.post( action, requestData )
				.then( function( data ) {
					SCD.Admin.showNotice( data.message || 'Success!', 'success' );

					// Trigger custom event
					$form.trigger( 'scd:form:success', [ { success: true, data: data } ] );
				} )
				.catch( function( error ) {
					SCD.Admin.showNotice( error.message || 'An error occurred', 'error' );
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
			$( '.scd-tooltip' ).each( function() {
				var $this = $( this );
				var title = $this.attr( 'title' );

				if ( ! title ) {return;}

				$this
					.on( 'mouseenter', function() {
						$( '<div class="scd-tooltip-content">' )
							.text( title )
							.appendTo( 'body' )
							.fadeIn( 'fast' );
					} )
					.on( 'mouseleave', function() {
						$( '.scd-tooltip-content' ).remove();
					} )
					.mousemove( function( e ) {
					$( '.scd-tooltip-content' ).css( {
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
				$( 'body' ).addClass( 'scd-ajax-loading' );
			} ).ajaxStop( function() {
				$( 'body' ).removeClass( 'scd-ajax-loading' );
			} );
		},

		/**
		 * Initialize notification system
		 */
		initNotificationSystem: function() {
			// Listen for notification events
			$( document ).on( 'scd:notification:show', function( e, data ) {
				SCD.Shared.NotificationService.show( data.message, data.type, data.duration );
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
			if ( !window.SCD || !window.SCD.Ajax ) {
				console.error( 'AJAX service not available' );
				if ( callback ) {
					callback( { success: false, error: 'AJAX service not available' } );
				}
				return $.Deferred().reject( 'AJAX service not available' ).promise();
			}

			var requestData = $.extend( {}, data );
			requestData.nonce = this.config.nonce;

			return SCD.Ajax.post( action, requestData )
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
			return SCD.Utils.formatCurrency( amount );
		},

		/**
		 * Debounce helper - delegates to consolidated Utils
		 * @param func
		 * @param wait
		 */
		debounce: function( func, wait ) {
			if ( !SCD.Utils || !SCD.Utils.debounce ) {
				throw new Error( 'SCD.Utils not available. Ensure proper script loading order.' );
			}
			return SCD.Utils.debounce( func, wait );
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
	SCD.Admin.Campaigns = {
		/**
		 * Handle campaign status toggle
		 * @param campaignId
		 * @param newStatus
		 */
		toggleStatus: function( campaignId, newStatus ) {
			return SCD.Admin.ajax( 'scd_toggle_campaign_status', {
				campaignId: campaignId,
				status: newStatus
			} ).done( function( response ) {
				if ( response.success ) {
					SCD.Shared.NotificationService.success( response.data.message );
					// Reload table or update UI
					if ( 'undefined' !== typeof SCD.CampaignsList ) {
						SCD.CampaignsList.reload();
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

			return SCD.Admin.ajax( 'scd_delete_campaign', {
				campaignId: campaignId
			} ).done( function( response ) {
				if ( response.success ) {
					SCD.Shared.NotificationService.success( 'Campaign deleted successfully' );
					// Reload or redirect
					if ( 'undefined' !== typeof SCD.CampaignsList ) {
						SCD.CampaignsList.reload();
					} else {
						window.location.href = window.scdCampaigns && window.scdCampaigns.urls && window.scdCampaigns.urls.campaignsList ?
							window.scdCampaigns.urls.campaignsList : '/wp-admin/admin.php?page=scd-campaigns';
					}
				}
			} );
		}
	};

	// Analytics helpers
	SCD.Admin.Analytics = {
		/**
		 * Load analytics data
		 * @param params
		 */
		loadData: function( params ) {
			return SCD.Admin.ajax( 'scd_get_analytics_data', params );
		},

		/**
		 * Export report
		 * @param format
		 * @param params
		 */
		exportReport: function( format, params ) {
			params.format = format;
			return SCD.Admin.ajax( 'scd_export_analytics', params );
		}
	};

	// Product search helpers
	SCD.Admin.Products = {
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
				url: window.scdProducts && window.scdProducts.endpoints && window.scdProducts.endpoints.search ?
					window.scdProducts.endpoints.search : '/wp-json/scd/v1/products/search',
				method: 'GET',
				data: params,
				headers: {
					'X-WP-Nonce': SCD.Admin.config.restNonce // Use REST nonce for REST API calls
				}
			} );
		}
	};

	// Initialize on document ready
	$( document ).ready( function() {
		SCD.Admin.init();
	} );

	// Restore active tabs
	$( window ).on( 'load', function() {
		if ( window.localStorage ) {
			$( '.scd-tabs[data-tab-group]' ).each( function() {
				var tabGroup = $( this ).data( 'tab-group' );
				var activeTab = localStorage.getItem( 'scd_active_tab_' + tabGroup );

				if ( activeTab ) {
					$( this ).find( '[data-target="' + activeTab + '"]' ).trigger( 'click' );
				}
			} );
		}
	} );

} )( jQuery );