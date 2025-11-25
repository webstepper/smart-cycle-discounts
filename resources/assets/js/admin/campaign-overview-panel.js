/**
 * Campaign Overview Panel Controller
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/assets/js/admin
 * @since      1.0.0
 */
( function( $, window, document ) {
	'use strict';


	var CampaignOverviewPanel = {
		elements: {
			backdrop: null,
			panel: null,
			closeButton: null,
			loadingContainer: null,
			errorContainer: null,
			sectionsContainer: null,
			retryButton: null,
			editButton: null,
			closeFooterButton: null
		},

		currentCampaignId: null,
		lastFocusedElement: null,
		isOpen: false,

		init: function() {
			this.cacheElements();

			// Only initialize if panel element exists on the page
			if ( ! this.elements.panel || 0 === this.elements.panel.length ) {
				return;
			}

			this.bindEvents();
			this.checkUrlState();
		},

		cacheElements: function() {
			this.elements.backdrop = $( '.scd-overview-panel-backdrop' );
			this.elements.panel = $( '#scd-campaign-overview-panel' );
			this.elements.closeButton = $( '.scd-overview-panel-close' );
			this.elements.loadingContainer = $( '#scd-overview-loading' );
			this.elements.errorContainer = $( '#scd-overview-error' );
			this.elements.sectionsContainer = $( '#scd-overview-sections' );
			this.elements.retryButton = $( '#scd-overview-retry' );
			this.elements.editButton = $( '#scd-overview-edit-button' );
			this.elements.closeFooterButton = $( '#scd-overview-close-button' );
		},

		bindEvents: function() {
			var self = this;

			// View campaign triggers
			$( document ).on( 'click', '.scd-view-campaign', function( e ) {
				e.preventDefault();
				var campaignId = $( this ).data( 'campaign-id' );
				if ( campaignId ) {
					self.openPanel( campaignId );
				}
			} );

			// Click on campaign row to open panel
			$( document ).on( 'click', '.wp-list-table.campaigns tbody tr', function( e ) {
				// Don't trigger if clicking on action buttons or checkboxes
				if ( $( e.target ).closest( '.scd-actions-column button, input[type="checkbox"]' ).length ) {
					return;
				}

				// Prevent default link behavior
				e.preventDefault();

				var campaignId = $( this ).find( '.scd-view-campaign' ).data( 'campaign-id' );
				if ( campaignId ) {
					self.openPanel( campaignId );
				}
			} );

			// Close button clicks
			this.elements.closeButton.on( 'click', function( e ) {
				e.preventDefault();
				self.closePanel();
			} );

			this.elements.closeFooterButton.on( 'click', function( e ) {
				e.preventDefault();
				self.closePanel();
			} );

			// Backdrop click
			this.elements.backdrop.on( 'click', function( e ) {
				if ( e.target === this ) {
					self.closePanel();
				}
			} );

			// Retry button
			this.elements.retryButton.on( 'click', function( e ) {
				e.preventDefault();
				if ( self.currentCampaignId ) {
					self.loadCampaign( self.currentCampaignId );
				}
			} );

			// Edit button
			this.elements.editButton.on( 'click', function( e ) {
				e.preventDefault();
				var campaignId = $( this ).data( 'campaign-id' );
				if ( campaignId ) {
					var editUrl = self.getEditUrl( campaignId );
					window.location.href = editUrl;
				} else {
				}
			} );

			// Keyboard events
			$( document ).on( 'keydown', function( e ) {
				if ( self.isOpen && e.key === 'Escape' ) {
					e.preventDefault();
					self.closePanel();
				}
			} );

			// Browser back/forward navigation
			$( window ).on( 'popstate', function() {
				self.checkUrlState();
			} );

		},

	checkUrlState: function() {
		var urlParams = new URLSearchParams( window.location.search );
		var action = urlParams.get( 'action' );

		// Canonical format: ?page=scd-campaigns&action=view&id=X
		var campaignId = 'view' === action ? urlParams.get( 'id' ) : null;

		if ( campaignId && ! this.isOpen ) {
			this.openPanel( campaignId );
		} else if ( ! campaignId && this.isOpen ) {
			this.closePanel( false );
		}
	},

		openPanel: function( campaignId ) {
			var self = this;

			// Safety check: ensure elements exist
			if ( ! this.elements.panel || 0 === this.elements.panel.length ) {
				return;
			}

			// Store last focused element
			this.lastFocusedElement = document.activeElement;

			// Update state
			this.currentCampaignId = campaignId;
			this.isOpen = true;

			// Update URL
			this.updateUrl( campaignId );

			// Show panel
			this.elements.backdrop.addClass( 'is-active' );
			this.elements.panel.addClass( 'is-active' );

			// Load campaign data
			this.loadCampaign( campaignId );

			// Focus management
			if ( this.elements.closeButton.length ) {
				this.elements.closeButton[0].focus();
			}

			// Trap focus
			this.trapFocus();

			// Prevent body scroll
			$( 'body' ).css( 'overflow', 'hidden' );
		},

		closePanel: function( updateUrl ) {
			updateUrl = updateUrl !== false;

			// Safety check: ensure elements exist
			if ( ! this.elements.panel || 0 === this.elements.panel.length ) {
				return;
			}

			// Update state
			this.isOpen = false;

			// Hide panel
			this.elements.backdrop.removeClass( 'is-active' );
			this.elements.panel.removeClass( 'is-active' );

			// Update URL - remove action and id parameters
			if ( updateUrl ) {
				this.removeUrlParameter( 'action' );
				this.removeUrlParameter( 'id' );
			}

			// Restore focus
			if ( this.lastFocusedElement ) {
				this.lastFocusedElement.focus();
				this.lastFocusedElement = null;
			}

			// Restore body scroll
			$( 'body' ).css( 'overflow', '' );

			// Reset panel state
			this.resetPanel();
		},

		loadCampaign: function( campaignId ) {
			var self = this;

			// Show loading state
			this.showLoading();

			// Check if AjaxService is available

			// Make AJAX request
			if ( window.SCD && window.SCD.AjaxService ) {
				window.SCD.AjaxService.request( 'scd_campaign_overview', {
					campaign_id: campaignId
				} ).done( function( response ) {
					if ( response.success && response.data ) {
						self.renderCampaign( response.data );
					} else {
						var errorMsg = self.extractErrorMessage( response );
						self.showError( errorMsg );
					}
				} ).fail( function( xhr, status, error ) {
					var errorMsg = self.extractErrorMessage( xhr.responseJSON ) || 'An error occurred while loading campaign data';
					self.showError( errorMsg );
				} );
			} else {
				var ajaxUrl = window.scdOverviewPanel && window.scdOverviewPanel.ajaxUrl ? window.scdOverviewPanel.ajaxUrl : '/wp-admin/admin-ajax.php';
				var nonce = window.scdOverviewPanel && window.scdOverviewPanel.nonce ? window.scdOverviewPanel.nonce : '';

				if ( ! nonce ) {
					self.showError( 'Security token missing' );
					return;
				}

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'scd_ajax',
						scdAction: 'scd_campaign_overview',
						nonce: nonce,
						campaign_id: campaignId
					},
					beforeSend: function() {
						// Show loading state while request is processing
					},
					success: function( response ) {
						if ( response.success && response.data ) {
							self.renderCampaign( response.data );
						} else {
							var errorMsg = self.extractErrorMessage( response );
							self.showError( errorMsg );
						}
					},
					error: function( xhr, status, error ) {
						var errorMsg = self.extractErrorMessage( xhr.responseJSON ) || 'An error occurred while loading campaign data';
						self.showError( errorMsg );
					}
				} );
			}
		},

		extractErrorMessage: function( response ) {
			if ( ! response ) {
				return 'Failed to load campaign data';
			}

			// Try response.message first
			if ( response.message && 'string' === typeof response.message ) {
				return response.message;
			}

			// Try response.data.message
			if ( response.data && response.data.message && 'string' === typeof response.data.message ) {
				return response.data.message;
			}

			// Try response.error array
			if ( response.error && Array.isArray( response.error ) && response.error.length > 0 ) {
				return response.error[0];
			}

			// Try response.error string
			if ( response.error && 'string' === typeof response.error ) {
				return response.error;
			}

			return 'Failed to load campaign data';
		},

		renderCampaign: function( data ) {
			
			// Hide loading
			if ( window.SCD && window.SCD.LoaderUtil ) {
			SCD.LoaderUtil.hide( 'scd-overview-loading', { fade: true } );
		}

			// Hide error if visible
			this.elements.errorContainer.removeClass( 'visible' );

			// Update edit button
			this.elements.editButton.data( 'campaign-id', data.campaign_id || this.currentCampaignId );

			// Render sections
			if ( data.sections ) {
				this.renderSections( data.sections );
			} else {
			}

			// Show sections container
			this.elements.sectionsContainer.addClass( 'visible' );
		},

	renderSections: function( sections ) {
		var sectionMap = {
			basic: '#scd-section-basic',
			health: '#scd-section-health',
			schedule: '#scd-section-schedule',
			recurringSchedule: '#scd-section-recurring-schedule',
			products: '#scd-section-products',
			discounts: '#scd-section-discounts',
			performance: '#scd-section-performance'
		};

		// Render each section
		// Use native DOM innerHTML to preserve SVG elements
		for ( var key in sections ) {
			if ( sections.hasOwnProperty( key ) && sectionMap[key] ) {
				var element = document.querySelector( sectionMap[key] );
				if ( element ) {
					element.innerHTML = sections[key];
				}
			}
		}
	},

		showLoading: function() {
			if ( window.SCD && window.SCD.LoaderUtil ) {
			SCD.LoaderUtil.show( 'scd-overview-loading', { fade: true } );
		}
			this.elements.errorContainer.removeClass( 'visible' );
			this.elements.sectionsContainer.removeClass( 'visible' );
		},

		showError: function( message ) {

			// Hide loading
			if ( window.SCD && window.SCD.LoaderUtil ) {
			SCD.LoaderUtil.hide( 'scd-overview-loading', { fade: true } );
		}

			// Hide sections
			this.elements.sectionsContainer.removeClass( 'visible' );

			// Ensure message is a string
			var errorText = 'string' === typeof message ? message : 'Failed to load campaign data';

			// Show error
			this.elements.errorContainer.find( '.scd-overview-error-message' ).text( errorText );
			this.elements.errorContainer.addClass( 'visible' );

			// Show notification
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.error( errorText );
			}
		},

		resetPanel: function() {
			this.currentCampaignId = null;
			if ( window.SCD && window.SCD.LoaderUtil ) {
			SCD.LoaderUtil.hide( 'scd-overview-loading', { fade: true } );
		}
			this.elements.errorContainer.removeClass( 'visible' );
			this.elements.sectionsContainer.removeClass( 'visible' );
			this.elements.sectionsContainer.find( '.scd-overview-section-content' ).html( '' );
		},

		updateUrl: function( campaignId ) {
			if ( ! window.history || ! window.history.pushState ) {
				return;
			}

			var url = new URL( window.location );
			url.searchParams.set( 'action', 'view' );
			url.searchParams.set( 'id', campaignId );
			window.history.pushState( { campaignId: campaignId }, '', url );
		},

		removeUrlParameter: function( parameter ) {
			if ( ! window.history || ! window.history.pushState ) {
				return;
			}

			var url = new URL( window.location );
			url.searchParams.delete( parameter );
			window.history.pushState( {}, '', url );
		},

		getEditUrl: function( campaignId ) {
			var baseUrl = window.scdOverviewPanel && window.scdOverviewPanel.editUrl ? window.scdOverviewPanel.editUrl : 'admin.php?page=scd-campaigns';
			var separator = baseUrl.indexOf( '?' ) > -1 ? '&' : '?';
			var fullUrl = baseUrl + separator + 'action=wizard&intent=edit&id=' + campaignId;
			return fullUrl;
		},

		trapFocus: function() {
			var self = this;
			var focusableElements = this.elements.panel.find( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' );
			var firstElement = focusableElements.first();
			var lastElement = focusableElements.last();

			// Remove any existing focus trap handlers
			this.elements.panel.off( 'keydown.focustrap' );

			// Add new focus trap handler
			this.elements.panel.on( 'keydown.focustrap', function( e ) {
				if ( e.key !== 'Tab' ) {
					return;
				}

				// Shift + Tab on first element
				if ( e.shiftKey && document.activeElement === firstElement[0] ) {
					e.preventDefault();
					if ( lastElement.length ) {
					lastElement[0].focus();
				}
				}
				// Tab on last element
				else if ( ! e.shiftKey && document.activeElement === lastElement[0] ) {
					e.preventDefault();
					if ( firstElement.length ) {
					firstElement[0].focus();
				}
				}
			} );
		}
	};

	// Initialize on DOM ready
	$( document ).ready( function() {
		CampaignOverviewPanel.init();
	} );

	// Expose to global SCD namespace
	window.SCD = window.SCD || {};
	window.SCD.CampaignOverviewPanel = CampaignOverviewPanel;

} )( jQuery, window, document );
