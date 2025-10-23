/**
 * Priority Module
 *
 * Handles campaign priority slider and display
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Priority Handler
	 *
	 * @param state
	 * @class SCD.Modules.Basic.Priority
	 */
	SCD.Modules.Basic.Priority = function( state ) {
		this.state = state;
		this.elements = {};
	};

	/**
	 * Initialize module
	 */
	SCD.Modules.Basic.Priority.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.loadInitialValue();
		this.updateDisplay();
	};

	/**
	 * Cache DOM elements
	 */
	SCD.Modules.Basic.Priority.prototype.cacheElements = function() {
		this.elements = {
			$slider: $( '[name="priority"]' ),
			$value: $( '.scd-priority-value' ),
			$indicator: $( '.scd-priority-indicator' ),
			$helpText: $( '.scd-priority-help' )
		};
	};

	/**
	 * Bind event handlers
	 */
	SCD.Modules.Basic.Priority.prototype.bindEvents = function() {
		var self = this;

		// Priority slider
		this.elements.$slider.on( 'input change', function( _e ) {
			var value = parseInt( $( this ).val() ) || 5;
			self.handlePriorityChange( value );
		} );

		// Listen to state changes
		$( document ).on( 'scd:basic:state:changed', function( e, data ) {
			if ( 'priority' === data.property ) {
				self.updateDisplay();
			}
		} );
	};

	/**
	 * Load initial value
	 */
	SCD.Modules.Basic.Priority.prototype.loadInitialValue = function() {
		var currentPriority = this.state.getState().priority;
		if ( currentPriority ) {
			this.elements.$slider.val( currentPriority );
		}
	};

	/**
	 * Handle priority change
	 * @param value
	 */
	SCD.Modules.Basic.Priority.prototype.handlePriorityChange = function( value ) {
		// Update state
		this.state.setState( { priority: value } );
	};

	/**
	 * Update priority display
	 */
	SCD.Modules.Basic.Priority.prototype.updateDisplay = function() {
		var state = this.state.getState();
		var value = state.priority;

		// Update value display
		if ( this.elements.$value.length ) {
			this.elements.$value.text( value );
		}

		// Update slider if needed
		if ( this.elements.$slider.val() !== value ) {
			this.elements.$slider.val( value );
		}

		// Update visual indicator
		if ( this.elements.$indicator.length ) {
			// Remove all priority classes
			this.elements.$indicator.removeClass( 'priority-low priority-medium priority-high priority-normal' );

			// Add appropriate class
			var priorityLevel = state.priorityLevel;
			this.elements.$indicator.addClass( 'priority-' + priorityLevel );

			// Update text
			this.elements.$indicator.text( state.priorityText );
		}

		// Update help text
		if ( this.elements.$helpText.length ) {
			var helpMessage = '';
			if ( 3 >= value ) {
				helpMessage = 'Low priority - runs after other campaigns';
			} else if ( 7 >= value ) {
				helpMessage = 'Medium priority - standard execution order';
			} else {
				helpMessage = 'High priority - runs before other campaigns';
			}
			this.elements.$helpText.text( helpMessage );
		}
	};

	/**
	 * Validate module
	 */
	SCD.Modules.Basic.Priority.prototype.validate = function() {
		// Priority is always valid ( has default )
		return { valid: true, errors: [] };
	};

	/**
	 * Get current value
	 */
	SCD.Modules.Basic.Priority.prototype.getValue = function() {
		return this.state.getState().priority;
	};

	/**
	 * Destroy module
	 */
	SCD.Modules.Basic.Priority.prototype.destroy = function() {
		this.elements.$slider.off( 'input change' );
		$( document ).off( 'scd:basic:state:changed' );
	};

} )( jQuery );