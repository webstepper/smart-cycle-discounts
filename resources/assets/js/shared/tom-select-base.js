/**
 * Tom Select Base
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/tom-select-base.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * Configuration Constants
	 */
	var BASE_CONFIG = {
		SEARCH: {
			MIN_CHARS: 2,
			THROTTLE_MS: 300,
			PAGE_SIZE: 20,
			INITIAL_PAGE_SIZE: 10,
			MAX_OPTIONS: 200
		},
		TIMEOUTS: {
			// Delay after initialization before triggering events (allows DOM to settle)
			INIT_DELAY: 100,
			// Delay for state synchronization debouncing (prevents rapid updates)
			STATE_SYNC_DELAY: 50,
			// Delay before updating dropdown UI (single animation frame)
			DROPDOWN_UPDATE: 10,
			// Delay before adding load more button (allows dropdown to render)
			LOAD_MORE_UPDATE: 50,
			// Delay before triggering validation (allows user to finish typing)
			VALIDATION_DELAY: 100
		},
		Z_INDEX: {
			// Wrapper z-index: normal stacking context
			WRAPPER: 1000,
			// Dropdown z-index: below WordPress admin bar (99999) but above page content
			DROPDOWN: 9999
		}
	};

	/**
	 * Base Tom Select Constructor
	 *
	 * @param {HTMLElement} element - The select element to enhance
	 * @param {Object} config - Configuration options
	 */
	SCD.Shared.TomSelectBase = function( element, config ) {
		if ( !element ) {
			throw new Error( 'TomSelectBase requires a valid element' );
		}

		this.element = element;
		this.$element = $( element );
		this.config = $.extend( true, {}, this.getDefaultConfig(), config || {} );
		this.instance = null;

		// Validate configuration - warn about suboptimal combination
		// Check for both boolean true and string values like 'focus'
		var hasPreload = true === this.config.preload || ( 'string' === typeof this.config.preload && this.config.preload );
		if ( hasPreload && true === this.config.openOnFocus ) {
			if ( window.SCD && window.SCD.ErrorHandler ) {
				window.SCD.ErrorHandler.handle(
					new Error( 'TomSelectBase: Suboptimal configuration. Both preload and openOnFocus are enabled. For better control, use preload:false with manual loading in onDropdownOpen.' ),
					'tom-select-base-config-validation',
					window.SCD.ErrorHandler.SEVERITY.LOW
				);
			} else {
				console.warn(
					'[TomSelectBase] Suboptimal configuration: Both preload and openOnFocus are enabled. ' +
					'For better control, use preload: false with manual loading in onDropdownOpen.'
				);
			}
		}

		// Single initialization guard using promise
		this._initPromise = null;
		this._initialized = false;

		// Pagination state
		this.pagination = {
			currentQuery: '',
			currentPage: 1,
			totalPages: 1,
			isLoading: false
		};

		// Cache for performance
		this.cache = {
			loadedOptions: new Map(),
			optionTimestamps: new Map()
		};

		// Timers for cleanup
		this.timers = {};

		// Track dynamically created elements for cleanup
		this._dynamicElements = [];

		// Track control element for click handler cleanup
		this._clickControl = null;
	};

	/**
	 * Get default configuration
	 *
	 * IMPORTANT: Configuration Constraint
	 *
	 * Do NOT enable both `openOnFocus: true` AND `preload: true` simultaneously.
	 * This creates a race condition where:
	 * - preload triggers async data loading during initialization
	 * - openOnFocus can trigger search() before items array is initialized
	 * - Result: "can't access property 'filter', t.items is undefined" error
	 *
	 * Solution: Manual click handler with preventDefault
	 * - openOnFocus: false prevents automatic opening during preload (fixes race condition)
	 * - Manual click handler bound in _handleInitialize() (fixes Tom-Select bug #701)
	 * - preventDefault() and focus() keep dropdown open after mouseup (fixes blur closing)
	 *
	 * Tom-Select Bug #701: openOnFocus: false also disables ALL opening mechanisms
	 * - Library bug: Setting openOnFocus: false disables click and keyboard opening
	 * - Workaround: Manually bind click event in _handleInitialize()
	 * - Must use preventDefault() to prevent blur event from closing dropdown
	 * - Must call focus() after open() to maintain focus and keep dropdown visible
	 *
	 * @return {Object} Default configuration object
	 */
	SCD.Shared.TomSelectBase.prototype.getDefaultConfig = function() {
		return {
			plugins: [ 'remove_button', 'clear_button' ],
			placeholder: 'Search and select...',
			maxItems: null,
			valueField: 'value',
			labelField: 'text',
			searchField: [ 'text' ],
			openOnFocus: false, // Keep false - we control opening via click handler
			closeAfterSelect: false,
			preload: false, // Manual loading via onDropdownOpen for category-filtered results
			create: false,
			loadThrottle: BASE_CONFIG.SEARCH.THROTTLE_MS,
			maxOptions: BASE_CONFIG.SEARCH.MAX_OPTIONS,
			duplicates: false,
			persist: true,
			sortField: null,
			dropdownParent: 'body',
			controlInput: '<input type="text" autocomplete="off" />',

			// Event handlers - bind to this instance
			// Note: onInitialize removed - we call _handleInitialize() directly in _createInstance()
			onChange: this._handleChange.bind( this ),
			onFocus: this._handleFocus.bind( this ),
			onDropdownOpen: this._handleDropdownOpen.bind( this ),
			onItemAdd: this._handleItemAdd.bind( this ),
			onItemRemove: this._handleItemRemove.bind( this ),

			// Render functions
			render: {
				option: this.renderOption.bind( this ),
				item: this.renderItem.bind( this ),
				no_results: this.renderNoResults.bind( this ),
				loading: this.renderLoading.bind( this ),
				dropdown: this.renderDropdown.bind( this )
			}
		};
	};

	/**
	 * Initialize Tom Select instance
	 * Uses promise-based single initialization guard
	 *
	 * @return {Promise} Resolves with this instance on success
	 */
	SCD.Shared.TomSelectBase.prototype.init = function() {
		// Return existing promise if initialization already started
		if ( this._initPromise ) {
			return this._initPromise;
		}

		var self = this;

		// Create and store initialization promise
		this._initPromise = new Promise( function( resolve, reject ) {
			// Check if already initialized
			if ( self._initialized ) {
				resolve( self );
				return;
			}

			// Check if TomSelect library is loaded
			if ( 'undefined' === typeof TomSelect ) {
				reject( new Error( 'TomSelect library not loaded' ) );
				return;
			}

			// Check if element already has Tom Select - wait for cleanup
			if ( self.element.tomselect ) {
				self._handleError(
					new Error( 'Tom Select already initialized on element - cleaning up' ),
					'init'
				);
				try {
					// Destroy existing instance
					self.element.tomselect.destroy();
					// Wait for cleanup to complete (Tom Select sets element.tomselect = null asynchronously)
					var cleanupCheck = setInterval( function() {
						if ( ! self.element.tomselect ) {
							clearInterval( cleanupCheck );
							self._createInstance( resolve, reject );
						}
					}, 50 );
					// Timeout after 500ms to prevent infinite wait
					setTimeout( function() {
						clearInterval( cleanupCheck );
						if ( self.element.tomselect ) {
							// Force cleanup
							self.element.tomselect = null;
						}
						self._createInstance( resolve, reject );
					}, 500 );
					return;
				} catch ( destroyError ) {
					self._handleError( destroyError, 'init-cleanup' );
					// Force cleanup on error
					self.element.tomselect = null;
				}
			}

			self._createInstance( resolve, reject );
		} );

		return this._initPromise;
	};

	/**
	 * Create Tom Select instance (extracted for reuse in cleanup scenarios)
	 *
	 * @param {Function} resolve - Promise resolve function
	 * @param {Function} reject - Promise reject function
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._createInstance = function( resolve, reject ) {
		try {
			// Create new Tom Select instance
			this.instance = new TomSelect( this.element, this.config );

			// DEFENSIVE FIX: Monkey patch tom-select's search method to prevent undefined errors
			// Tom-select's search() method has two paths (line 245 in tom-select.js):
			// 1. Fresh search: calls sifter.search() which returns {items: [...]}
			// 2. Cached search: uses currentResults via Object.assign({}, currentResults)
			// If currentResults is null, Object.assign({}, null) returns {} without items property
			// Then t.items.filter() fails with "Cannot read properties of undefined (reading 'filter')"
			var originalSearch = this.instance.search.bind( this.instance );
			this.instance.search = function( query ) {
				// Defensive: Ensure items array exists before search tries to filter it
				if ( 'undefined' === typeof this.items || !Array.isArray( this.items ) ) {
					this.items = [];
				}
				// Call original search
				var result = originalSearch( query );
				// Defensive: Ensure result has items array (prevents filter() errors)
				if ( result && ( 'undefined' === typeof result.items || !Array.isArray( result.items ) ) ) {
					result.items = [];
				}
				return result;
			};

			// Make input non-focusable to reduce unnecessary focus events
			if ( this.instance.control_input ) {
				this.instance.control_input.setAttribute( 'tabindex', '-1' );
				this.instance.control_input.setAttribute( 'readonly', 'readonly' );
			}

			// Call _handleInitialize directly since onInitialize callback doesn't fire reliably
			// This binds the click handler to work around Tom-Select bug #701
			this._handleInitialize();

			this._initialized = true;
			resolve( this );
		} catch ( error ) {
			this._handleError( error, 'init' );
			reject( error );
		}
	};

	/**
	 * Set values (replaces current selection)
	 *
	 * @param {Array|string} values - Values to set
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.setValue = function( values, silent ) {
		if ( !this.instance ) {
			return this;
		}

		// Second parameter controls whether onChange fires:
		// false (default) = trigger onChange
		// true = silent mode, no onChange (for initialization)
		this.instance.setValue( values, silent || false );
		return this;
	};

	/**
	 * Get current values
	 *
	 * @return {Array} Current selected values
	 */
	SCD.Shared.TomSelectBase.prototype.getValue = function() {
		if ( !this.instance ) {
			return [];
		}

		var values = this.instance.getValue();
		return Array.isArray( values ) ? values : ( values ? [ values ] : [] );
	};

	/**
	 * Add options to Tom Select
	 *
	 * @param {Array} options - Array of option objects
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.addOptions = function( options ) {
		if ( !this.instance || !Array.isArray( options ) ) {
			return this;
		}

		var self = this;
		options.forEach( function( option ) {
			if ( option && option.value && !self.instance.options[option.value] ) {
				self.instance.addOption( option );
				// Cache the option
				self.cache.loadedOptions.set( option.value, option );
				self.cache.optionTimestamps.set( option.value, Date.now() );
			}
		} );

		return this;
	};

	/**
	 * Clear all options
	 *
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.clearOptions = function() {
		if ( this.instance ) {
			this.instance.clearOptions();
			this.cache.loadedOptions.clear();
			this.cache.optionTimestamps.clear();
			// Note: items array protection handled by monkey-patched search() method
		}
		return this;
	};

	/**
	 * Clear selected values
	 *
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.clear = function() {
		if ( this.instance ) {
			// Always trigger change events (no silent mode)
			this.instance.clear( false );
		}
		return this;
	};

	/**
	 * Show validation error
	 *
	 * @param {string} message - Error message to display
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.showError = function( message ) {
		if ( ! this.instance || ! window.SCD || ! window.SCD.ValidationError ) {
			return this;
		}

		// Use centralized ValidationError component
		window.SCD.ValidationError.show( this.$element, message );

		// Add error class to Tom Select wrapper
		if ( this.instance.wrapper ) {
			$( this.instance.wrapper ).addClass( 'error' );
		}

		return this;
	};

	/**
	 * Clear validation error
	 *
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.clearError = function() {
		if ( ! this.instance || ! window.SCD || ! window.SCD.ValidationError ) {
			return this;
		}

		// Use centralized ValidationError component
		window.SCD.ValidationError.clear( this.$element );

		// Remove error class from Tom Select wrapper
		if ( this.instance.wrapper ) {
			$( this.instance.wrapper ).removeClass( 'error' );
		}

		return this;
	};

	/**
	 * Refresh options display
	 *
	 * @param {boolean} triggerDropdown - Whether to trigger dropdown
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.refreshOptions = function( triggerDropdown ) {
		if ( this.instance ) {
			this.instance.refreshOptions( triggerDropdown );
		}
		return this;
	};

	/**
	 * Check if dropdown is open
	 *
	 * @return {boolean} True if dropdown is open
	 */
	SCD.Shared.TomSelectBase.prototype.isOpen = function() {
		return this.instance ? this.instance.isOpen : false;
	};

	/**
	 * Open dropdown
	 *
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.open = function() {
		if ( this.instance ) {
			this.instance.open();
		}
		return this;
	};

	/**
	 * Close dropdown
	 *
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.close = function() {
		if ( this.instance ) {
			this.instance.close();
		}
		return this;
	};

	/**
	 * Load data (triggers load callback)
	 *
	 * @param {string} query - Search query
	 * @return {Object} This instance for chaining
	 */
	SCD.Shared.TomSelectBase.prototype.load = function( query ) {
		if ( this.instance ) {
			this.instance.load( query );
		}
		return this;
	};

	// =========================================================================
	// Event Handlers (Internal)
	// =========================================================================

	/**
	 * Handle initialize event
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleInitialize = function() {
		// Bind click handler to control element
		// Workaround for Tom-Select bug #701: openOnFocus: false disables click opening
		// Solution: Manually bind click handler with preventDefault to keep dropdown open
		if ( this.instance && this.instance.control ) {
			var self = this;

			// Store reference to control for cleanup
			this._clickControl = this.instance.control;

			// Bind click handler directly to control element
			$( this.instance.control ).on( 'click.scd-tomselect', function( e ) {
				// Don't interfere with remove button clicks
				if ( $( e.target ).closest( '.remove' ).length > 0 ) {
					return;
				}

				// Don't interfere with clear button clicks
				if ( $( e.target ).closest( '.clear-button' ).length > 0 ) {
					return;
				}

				// Prevent default and stop propagation
				e.preventDefault();
				e.stopPropagation();

				// Toggle dropdown state
				if ( self.instance.isOpen ) {
					self.instance.close();
				} else if ( !self.pagination.isLoading ) {
					self.instance.open();
				}
			} );
		}

		this.onInitialize();
	};

	/**
	 * Handle change event
	 * Always fires (no silent mode) to keep state in sync
	 *
	 * @param {*} value - Changed value
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleChange = function( value ) {
		// Clear validation errors when value changes
		this.clearError();

		this.onChange( value );
	};

	/**
	 * Handle focus event
	 * Note: With readonly input, this won't fire from user interaction
	 * Kept for subclass extensibility
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleFocus = function() {
		this.onFocus();
	};

	/**
	 * Handle dropdown open event
	 *
	 * @param {HTMLElement} dropdown - Dropdown element
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleDropdownOpen = function( dropdown ) {
		if ( this.pagination.currentPage < this.pagination.totalPages ) {
			var self = this;
			setTimeout( function() {
				self._addLoadMoreButton();
			}, BASE_CONFIG.TIMEOUTS.DROPDOWN_UPDATE );
		}
		this.onDropdownOpen( dropdown );
	};

	/**
	 * Handle item add event
	 *
	 * @param {*} value - Added value
	 * @param {HTMLElement} item - Item element
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleItemAdd = function( value, item ) {
		this.onItemAdd( value, item );
	};

	/**
	 * Handle item remove event
	 *
	 * @param {*} value - Removed value
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleItemRemove = function( value ) {
		this.onItemRemove( value );
	};

	// =========================================================================
	// Hooks (Override in subclasses)
	// =========================================================================

	/**
	 * Hook: On initialize
	 */
	SCD.Shared.TomSelectBase.prototype.onInitialize = function() {
		// Override in subclasses
	};

	/**
	 * Hook: On change
	 *
	 * @param {*} _value - Changed value
	 */
	SCD.Shared.TomSelectBase.prototype.onChange = function( _value ) {
		// Override in subclasses
	};

	/**
	 * Hook: On focus
	 * Note: Won't fire from user interaction due to readonly input
	 * Available for programmatic focus or subclass override
	 */
	SCD.Shared.TomSelectBase.prototype.onFocus = function() {
		// Override in subclasses if needed
	};

	/**
	 * Hook: On dropdown open
	 *
	 * @param {HTMLElement} _dropdown - Dropdown element
	 */
	SCD.Shared.TomSelectBase.prototype.onDropdownOpen = function( _dropdown ) {
		// Override in subclasses
	};

	/**
	 * Hook: On item add
	 *
	 * @param {*} _value - Added value
	 * @param {HTMLElement} _item - Item element
	 */
	SCD.Shared.TomSelectBase.prototype.onItemAdd = function( _value, _item ) {
		// Override in subclasses
	};

	/**
	 * Hook: On item remove
	 *
	 * @param {*} _value - Removed value
	 */
	SCD.Shared.TomSelectBase.prototype.onItemRemove = function( _value ) {
		// Override in subclasses
	};

	// =========================================================================
	// Render Methods
	// =========================================================================

	/**
	 * Render option in dropdown
	 *
	 * @param {Object} data - Option data
	 * @param {Function} escape - Escape function for XSS prevention
	 * @return {string} HTML string
	 */
	SCD.Shared.TomSelectBase.prototype.renderOption = function( data, escape ) {
		var text = data.text || data.label || data.value || '';
		return '<div>' + escape( text ) + '</div>';
	};

	/**
	 * Render selected item
	 *
	 * @param {Object} data - Item data
	 * @param {Function} escape - Escape function for XSS prevention
	 * @return {string} HTML string
	 */
	SCD.Shared.TomSelectBase.prototype.renderItem = function( data, escape ) {
		var text = data.text || data.label || data.value || '';
		return '<div>' + escape( text ) + '</div>';
	};

	/**
	 * Render no results message
	 *
	 * @param {Object} _data - Search data
	 * @param {Function} _escape - Escape function for XSS prevention
	 * @return {string} HTML string
	 */
	SCD.Shared.TomSelectBase.prototype.renderNoResults = function( _data, _escape ) {
		return '<div class="no-results">No results found</div>';
	};

	/**
	 * Render loading indicator with ARIA live region
	 *
	 * @return {string} HTML string
	 */
	SCD.Shared.TomSelectBase.prototype.renderLoading = function() {
		return '<div class="spinner" role="status" aria-live="polite" aria-atomic="true">' +
			'<span class="screen-reader-text">Loading options...</span>' +
			'<span aria-hidden="true">Loading...</span>' +
		'</div>';
	};

	/**
	 * Render dropdown container
	 *
	 * @return {string} HTML string
	 */
	SCD.Shared.TomSelectBase.prototype.renderDropdown = function() {
		return '<div style="position: absolute; z-index: ' + BASE_CONFIG.Z_INDEX.DROPDOWN + ';"></div>';
	};

	// =========================================================================
	// Pagination Methods
	// =========================================================================

	/**
	 * Add load more button to dropdown
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._addLoadMoreButton = function() {
		if ( !this.instance || !this.instance.dropdown ) {
			return;
		}

		try {
			var $dropdown = $( this.instance.dropdown );

			// Clean up existing footers and their event handlers
			this._cleanupLoadMoreButton();

			// Check if more pages available
			if ( this.pagination.currentPage >= this.pagination.totalPages ) {
				return;
			}

			// Create footer with load more button
			var $footer = $(
				'<div class="scd-tom-select-footer">' +
					'<button type="button" class="scd-load-more-btn">' +
						'<span class="dashicons dashicons-arrow-down-alt2"></span> ' +
						'Load More' +
					'</button>' +
				'</div>'
			);

			$dropdown.append( $footer );

			// Track for cleanup
			this._dynamicElements.push( $footer[0] );

			// Bind click handler with proper cleanup
			var self = this;
			var clickHandler = function( e ) {
				e.preventDefault();
				e.stopPropagation();
				self.loadMore();
			};

			$footer.find( '.scd-load-more-btn' ).on( 'click', clickHandler );

			// Store handler reference for cleanup
			$footer.data( 'scd-click-handler', clickHandler );

		} catch ( error ) {
			this._handleError( error, 'addLoadMoreButton' );
		}
	};

	/**
	 * Clean up load more button and its event handlers
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._cleanupLoadMoreButton = function() {
		if ( !this.instance || !this.instance.dropdown ) {
			return;
		}

		var $dropdown = $( this.instance.dropdown );
		var $footer = $dropdown.find( '.scd-tom-select-footer' );

		if ( $footer.length ) {
			// Remove event handler
			var clickHandler = $footer.data( 'scd-click-handler' );
			if ( clickHandler ) {
				$footer.find( '.scd-load-more-btn' ).off( 'click', clickHandler );
			}

			// Remove element
			$footer.remove();
		}

		// Clean up tracking array
		this._dynamicElements = this._dynamicElements.filter( function( el ) {
			return el && el.parentNode;
		} );
	};

	/**
	 * Load more results (override in subclasses)
	 */
	SCD.Shared.TomSelectBase.prototype.loadMore = function() {
		// Override in subclasses
	};

	/**
	 * Reset pagination state
	 */
	SCD.Shared.TomSelectBase.prototype.resetPagination = function() {
		this.pagination.currentPage = 1;
		this.pagination.totalPages = 1;
		this.pagination.currentQuery = '';
		this.pagination.isLoading = false;
	};

	/**
	 * Update pagination from response
	 *
	 * @param {Object} response - API response
	 * @param {number} page - Current page number
	 */
	SCD.Shared.TomSelectBase.prototype.updatePagination = function( response, page ) {
		this.pagination.currentPage = page || 1;

		if ( response && response.pagination ) {
			this.pagination.totalPages = response.pagination.total_pages || 1;
		}
	};

	// =========================================================================
	// Utility Methods
	// =========================================================================

	/**
	 * Normalize value to array
	 *
	 * @param {*} value - Value to normalize
	 * @return {Array} Normalized array
	 */
	SCD.Shared.TomSelectBase.prototype.normalizeToArray = function( value ) {
		if ( Array.isArray( value ) ) {
			return value;
		}
		if ( value ) {
			return [ value ];
		}
		return [];
	};

	/**
	 * Check if instance is ready
	 *
	 * @return {boolean} True if initialized and ready
	 */
	SCD.Shared.TomSelectBase.prototype.isReady = function() {
		return this._initialized && this.instance !== null;
	};

	/**
	 * Check if element is visible
	 *
	 * @return {boolean} True if element is visible
	 */
	SCD.Shared.TomSelectBase.prototype.isVisible = function() {
		return this.$element.is( ':visible' );
	};

	/**
	 * Handle visibility change
	 * Destroy instance when hidden, reinitialize when shown
	 *
	 * @param {boolean} visible - Whether element is now visible
	 * @return {Promise} Promise that resolves when operation completes
	 */
	SCD.Shared.TomSelectBase.prototype.handleVisibilityChange = function( visible ) {
		var self = this;

		if ( ! visible && this.isReady() ) {
			// Element hidden - destroy instance to free resources
			this.destroy();
			return Promise.resolve();
		} else if ( visible && ! this.isReady() ) {
			// Element shown - reinitialize
			return this.init().catch( function( error ) {
				self._handleError( error, 'handleVisibilityChange' );
				return Promise.reject( error );
			} );
		}

		return Promise.resolve();
	};

	/**
	 * Handle errors with consistent logging
	 *
	 * @param {Error} error - Error object
	 * @param {string} context - Context where error occurred
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._handleError = function( error, context ) {
		var fullContext = 'TomSelectBase.' + context;

		if ( window.SCD && window.SCD.ErrorHandler ) {
			window.SCD.ErrorHandler.handle( error, fullContext, window.SCD.ErrorHandler.SEVERITY.MEDIUM );
		} else {
			console.error( '[' + fullContext + ']', error );
		}
	};

	// =========================================================================
	// Cache Management
	// =========================================================================

	/**
	 * Clear expired cache entries
	 * Removes entries older than maxAge milliseconds
	 *
	 * @param {number} maxAge - Maximum age in milliseconds (default: 5 minutes)
	 */
	SCD.Shared.TomSelectBase.prototype.clearExpiredCache = function( maxAge ) {
		maxAge = maxAge || 300000; // 5 minutes default
		var now = Date.now();
		var self = this;

		this.cache.optionTimestamps.forEach( function( timestamp, key ) {
			if ( now - timestamp > maxAge ) {
				self.cache.loadedOptions.delete( key );
				self.cache.optionTimestamps.delete( key );
			}
		} );
	};

	/**
	 * Get cache size
	 *
	 * @return {number} Number of cached options
	 */
	SCD.Shared.TomSelectBase.prototype.getCacheSize = function() {
		return this.cache.loadedOptions.size;
	};

	// =========================================================================
	// Lifecycle Methods
	// =========================================================================

	/**
	 * Destroy Tom Select instance
	 */
	SCD.Shared.TomSelectBase.prototype.destroy = function() {
		try {
			this._cleanupLoadMoreButton();
			this._clearTimers();
			this._destroyInstance();
			this._clearCache();
			this._resetState();
		} catch ( error ) {
			this._handleError( error, 'destroy' );
		}
	};

	/**
	 * Clear all timers
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._clearTimers = function() {
		var self = this;
		Object.keys( this.timers ).forEach( function( key ) {
			if ( self.timers[key] ) {
				clearTimeout( self.timers[key] );
			}
		} );
		this.timers = {};
	};

	/**
	 * Destroy Tom Select instance
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._destroyInstance = function() {
		if ( this.instance ) {
			// Unbind click handler before destroying
			if ( this._clickControl ) {
				$( this._clickControl ).off( 'click.scd-tomselect' );
				this._clickControl = null;
			}
			this.instance.destroy();
			this.instance = null;
		}
	};

	/**
	 * Clear cache
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._clearCache = function() {
		if ( this.cache.loadedOptions ) {
			this.cache.loadedOptions.clear();
		}
		if ( this.cache.optionTimestamps ) {
			this.cache.optionTimestamps.clear();
		}
	};

	/**
	 * Reset internal state
	 *
	 * @private
	 */
	SCD.Shared.TomSelectBase.prototype._resetState = function() {
		this._initialized = false;
		this._initPromise = null;
		this.resetPagination();
	};

	// =========================================================================
	// Static Properties
	// =========================================================================

	/**
	 * Export configuration constants for subclasses
	 */
	SCD.Shared.TomSelectBase.CONFIG = BASE_CONFIG;

} )( jQuery );
