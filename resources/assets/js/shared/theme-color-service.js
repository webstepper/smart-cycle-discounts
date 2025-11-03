/**
 * Theme Color Service
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/theme-color-service.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Shared = window.SCD.Shared || {};

	/**
	 * Theme Color Service
	 *
	 * @namespace SCD.Shared.ThemeColorService
	 */
	window.SCD.Shared.ThemeColorService = {

		// Current theme colors
		colors: null,

		// Default color palette
		defaultColors: {
			primary: '#2271b1',
			secondary: '#72aee6',
			success: '#00a32a',
			warning: '#dba617',
			danger: '#d63638',
			accent: '#3858e9',
			info: '#72aee6',
			dark: '#1d2327',
			light: '#f0f0f1',
			muted: '#646970'
		},

		// Color subscribers
		subscribers: [],

		// Component color mappings
		componentMappings: {},

		// Initialization state
		initialized: false,

		/**
		 * Initialize the theme color service
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.loadColors();
			this.applyGlobalColors();
			this.setupColorWatcher();
			this.initialized = true;

			// Emit ready event
			$( document ).trigger( 'scd:theme-colors:ready', [ this.colors ] );
		},

		/**
		 * Load theme colors from WordPress/WooCommerce
		 */
		loadColors: function() {
			if ( window.scdAdmin && window.scdAdmin.colors ) {
				this.colors = $.extend( {}, this.defaultColors, window.scdAdmin.colors );
			} else {
				// Fallback to defaults
				this.colors = $.extend( {}, this.defaultColors );
			}

			// Apply filter for custom modifications
			this.colors = this.applyColorFilters( this.colors );
		},

		/**
		 * Apply color filters for customization
		 * @param colors
		 */
		applyColorFilters: function( colors ) {
			// Allow other scripts to modify colors
			var filteredColors = colors;

			$( document ).trigger( 'scd:theme-colors:filter', [ filteredColors ] );

			return filteredColors;
		},

		/**
		 * Apply colors globally to CSS custom properties
		 */
		applyGlobalColors: function() {
			var root = document.documentElement;

			// Global color properties
			Object.keys( this.colors ).forEach( function( key ) {
				root.style.setProperty( '--scd-color-' + key, this.colors[key] );
			}.bind( this ) );

			// Generate color variations
			this.applyColorVariations( root );
		},

		/**
		 * Generate and apply color variations (lighter/darker)
		 * @param root
		 */
		applyColorVariations: function( root ) {
			var self = this;

			// Generate lighter variations
			Object.keys( this.colors ).forEach( function( key ) {
				var lighter = self.lightenColor( self.colors[key], 20 );
				var darker = self.darkenColor( self.colors[key], 20 );

				root.style.setProperty( '--scd-color-' + key + '-light', lighter );
				root.style.setProperty( '--scd-color-' + key + '-dark', darker );
			} );
		},

		/**
		 * Apply colors to a specific component
		 *
		 * @param {string} componentName Component identifier
		 * @param {object} mappings Color property mappings
		 */
		applyToComponent: function( componentName, mappings ) {
			if ( !componentName || !mappings ) {
				return;
			}

			this.componentMappings[componentName] = mappings;

			// Apply CSS variables for component
			var root = document.documentElement;

			Object.keys( mappings ).forEach( function( property ) {
				var colorKey = mappings[property];
				var colorValue = this.colors[colorKey] || colorKey; // Use direct value if not a key

				root.style.setProperty( '--scd-' + componentName + '-' + property, colorValue );
			}.bind( this ) );

			// Notify subscribers
			this.notifySubscribers( componentName );
		},

		/**
		 * Subscribe to color changes
		 *
		 * @param {Function} callback Callback function
		 * @param {string} component Optional component filter
		 */
		subscribe: function( callback, component ) {
			if ( 'function' !== typeof callback ) {
				return;
			}

			this.subscribers.push( {
				callback: callback,
				component: component || null
			} );

			// Call immediately if already initialized
			if ( this.initialized ) {
				callback( this.colors, component );
			}
		},

		/**
		 * Notify subscribers of color changes
		 * @param component
		 */
		notifySubscribers: function( component ) {
			var self = this;

			this.subscribers.forEach( function( subscriber ) {
				if ( !subscriber.component || subscriber.component === component ) {
					subscriber.callback( self.colors, component );
				}
			} );
		},

		/**
		 * Setup watcher for admin color scheme changes
		 */
		setupColorWatcher: function() {
			var self = this;

			// Listen for WordPress admin color scheme changes
			$( document ).on( 'wp-color-scheme-change', function() {
				self.loadColors();
				self.applyGlobalColors();
				self.reapplyComponentColors();
				self.notifySubscribers();
			} );
		},

		/**
		 * Reapply all component colors
		 */
		reapplyComponentColors: function() {
			var self = this;

			Object.keys( this.componentMappings ).forEach( function( component ) {
				self.applyToComponent( component, self.componentMappings[component] );
			} );
		},

		/**
		 * Get a specific color
		 *
		 * @param {string} colorKey Color key
		 * @returns {string} Color value
		 */
		getColor: function( colorKey ) {
			return this.colors[colorKey] || this.defaultColors[colorKey] || colorKey;
		},

		/**
		 * Get all colors
		 *
		 * @returns {object} All colors
		 */
		getAllColors: function() {
			return $.extend( {}, this.colors );
		},

		/**
		 * Lighten a color
		 *
		 * @param {string} color Hex color
		 * @param {number} percent Percentage to lighten
		 * @returns {string} Lightened color
		 */
		lightenColor: function( color, percent ) {
			if ( !color || !color.match( /^#[0-9A-F]{6}$/i ) ) {
				return color;
			}

			var num = parseInt( color.slice( 1 ), 16 );
			var amt = Math.round( 2.55 * percent );
			var R = ( num >> 16 ) + amt;
			var G = ( num >> 8 & 0x00FF ) + amt;
			var B = ( num & 0x0000FF ) + amt;

			return '#' + ( 0x1000000 + ( 255 > R ? 1 > R ? 0 : R : 255 ) * 0x10000 +
                ( 255 > G ? 1 > G ? 0 : G : 255 ) * 0x100 +
                ( 255 > B ? 1 > B ? 0 : B : 255 ) ).toString( 16 ).slice( 1 );
		},

		/**
		 * Darken a color
		 *
		 * @param {string} color Hex color
		 * @param {number} percent Percentage to darken
		 * @returns {string} Darkened color
		 */
		darkenColor: function( color, percent ) {
			if ( !color || !color.match( /^#[0-9A-F]{6}$/i ) ) {
				return color;
			}

			var num = parseInt( color.slice( 1 ), 16 );
			var amt = Math.round( 2.55 * percent );
			var R = ( num >> 16 ) - amt;
			var G = ( num >> 8 & 0x00FF ) - amt;
			var B = ( num & 0x0000FF ) - amt;

			return '#' + ( 0x1000000 + ( 0 < R ? R : 0 ) * 0x10000 +
                ( 0 < G ? G : 0 ) * 0x100 +
                ( 0 < B ? B : 0 ) ).toString( 16 ).slice( 1 );
		},

		/**
		 * Get contrast color (black or white) for a background
		 *
		 * @param {string} hexcolor Background color
		 * @returns {string} #000000 or #FFFFFF
		 */
		getContrastColor: function( hexcolor ) {
			if ( !hexcolor || !hexcolor.match( /^#[0-9A-F]{6}$/i ) ) {
				return '#000000';
			}

			// Convert to RGB
			var r = parseInt( hexcolor.slice( 1, 3 ), 16 );
			var g = parseInt( hexcolor.slice( 3, 5 ), 16 );
			var b = parseInt( hexcolor.slice( 5, 7 ), 16 );

			// Calculate luminance
			var yiq = ( ( r * 299 ) + ( g * 587 ) + ( b * 114 ) ) / 1000;

			return ( 128 <= yiq ) ? '#000000' : '#FFFFFF';
		}
	};

} )( jQuery );