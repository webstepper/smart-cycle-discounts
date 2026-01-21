/**
 * Timeline Visualizer
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/components/timeline-visualizer.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $, window, document ) {
	'use strict';

	/**
	 * Timeline Visualizer Constructor
	 * @param container
	 * @param options
	 */
	var TimelineVisualizer = function( container, options ) {
		this.container = $( container );
		this.options = $.extend( {}, TimelineVisualizer.defaults, options );
		this.state = {
			startDate: null,
			endDate: null,
			currentDate: new Date(),
			isInteractive: true,
			isDragging: false,
			dragType: null // 'start', 'end', 'move'
		};

		this.init();
	};

	/**
	 * Default options
	 */
	TimelineVisualizer.defaults = {
		height: 80,
		padding: { top: 20, right: 20, bottom: 40, left: 20 },
		colors: window.wsscdAdmin && window.wsscdAdmin.colors ? {
			background: window.wsscdAdmin.colors.background || '#f0f0f1',
			timeline: window.wsscdAdmin.colors.border || '#dcdcde',
			active: window.wsscdAdmin.colors.primary || '#0073aa',
			future: window.wsscdAdmin.colors.secondary || '#72aee6',
			past: window.wsscdAdmin.colors.text_muted || '#646970',
			current: window.wsscdAdmin.colors.danger || '#d63638',
			interactive: window.wsscdAdmin.colors.primary || '#2271b1',
			text: window.wsscdAdmin.colors.text || '#1e1e1e',
			grid: window.wsscdAdmin.colors.border_light || '#e0e0e0'
		} : {
			background: '#f0f0f1',
			timeline: '#dcdcde',
			active: '#0073aa',
			future: '#72aee6',
			past: '#646970',
			current: '#d63638',
			interactive: '#2271b1',
			text: '#1e1e1e',
			grid: '#e0e0e0'
		},
		showGrid: true,
		showMarkers: true,
		showTooltips: true,
		enableInteraction: true,
		onDateChange: null,
		dateFormat: {
			short: 'MMM D',
			long: 'MMM D, YYYY h:mm A'
		}
	};

	/**
	 * Initialize timeline
	 */
	TimelineVisualizer.prototype.init = function() {
		this.createStructure();
		this.bindEvents();
		this.render();
	};

	/**
	 * Create DOM structure
	 */
	TimelineVisualizer.prototype.createStructure = function() {
		this.container.empty().addClass( 'wsscd-timeline-visualizer' );

		// Ensure container has dimensions
		this.container.css( {
			'position': 'relative',
			'min-height': this.options.height + 'px'
		} );

		this.canvas = $( '<canvas class="wsscd-timeline-canvas"></canvas>' );
		this.container.append( this.canvas );

		this.tooltip = $( '<div class="wsscd-timeline-tooltip" style="display: none;"></div>' );
		this.container.append( this.tooltip );

		if ( this.options.enableInteraction ) {
			this.interactionLayer = $( '<div class="wsscd-timeline-interaction"></div>' );
			this.container.append( this.interactionLayer );

			// Ensure interaction layer has proper styles
			this.interactionLayer.css( {
				'position': 'absolute',
				'top': '0',
				'left': '0',
				'right': '0',
				'bottom': '0',
				'z-index': '10',
				'cursor': 'default'
			} );
		}

		this.updateDimensions();

		this.ctx = this.canvas[0].getContext( '2d' );

		this.ratio = window.devicePixelRatio || 1;
	};

	/**
	 * Update canvas dimensions
	 */
	TimelineVisualizer.prototype.updateDimensions = function() {
		var width = this.container.width() || 600; // Default width if container has no width
		var height = this.options.height;
		var ratio = window.devicePixelRatio || 1;

		this.canvas.attr( {
			width: width * ratio,
			height: height * ratio
		} ).css( {
			width: width + 'px',
			height: height + 'px'
		} );

		this.dimensions = {
			width: width,
			height: height,
			innerWidth: Math.max( width - this.options.padding.left - this.options.padding.right, 100 ),
			innerHeight: Math.max( height - this.options.padding.top - this.options.padding.bottom, 40 )
		};

		this.ratio = ratio;
	};

	/**
	 * Bind events
	 */
	TimelineVisualizer.prototype.bindEvents = function() {
		var self = this;

		// Window resize
		$( window ).on( 'resize.timeline', this.debounce( function() {
			self.updateDimensions();
			self.render();
		}, 250 ) );

		// Interactive events
		if ( this.options.enableInteraction && this.interactionLayer ) {

			this.interactionLayer
				.on( 'mousedown', function( e ) { self.handleMouseDown( e ); } )
				.on( 'mousemove', function( e ) { self.handleMouseMove( e ); } )
				.on( 'mouseup', function( e ) { self.handleMouseUp( e ); } )
				.on( 'mouseleave', function( e ) { self.handleMouseLeave( e ); } )
				.on( 'touchstart', function( e ) { self.handleTouchStart( e ); } )
				.on( 'touchmove', function( e ) { self.handleTouchMove( e ); } )
				.on( 'touchend', function( e ) { self.handleTouchEnd( e ); } );

			// Also bind document-level events for dragging
			$( document ).on( 'mousemove.timeline', function( e ) {
				if ( self.state.isDragging ) {
					self.handleMouseMove( e );
				}
			} );

			$( document ).on( 'mouseup.timeline', function( e ) {
				if ( self.state.isDragging ) {
					self.handleMouseUp( e );
				}
			} );
		}
	};

	/**
	 * Render timeline
	 */
	TimelineVisualizer.prototype.render = function() {
		if ( !this.ctx || !this.dimensions ) {
			return;
		}

		this.ctx.clearRect( 0, 0, this.canvas[0].width, this.canvas[0].height );

		// Save context and scale for retina displays
		this.ctx.save();
		this.ctx.scale( this.ratio || 1, this.ratio || 1 );

		// Draw background
		this.drawBackground();

		// Draw grid if enabled
		if ( this.options.showGrid && this.state.startDate && this.state.endDate ) {
			this.drawGrid();
		}

		// Draw timeline
		this.drawTimeline();

		// Draw markers
		if ( this.options.showMarkers && this.state.startDate && this.state.endDate ) {
			this.drawMarkers();
		}

		// Draw labels
		if ( this.state.startDate && this.state.endDate ) {
			this.drawLabels();
		}

		// Draw current time indicator
		if ( this.state.startDate && this.state.endDate ) {
			this.drawCurrentTimeIndicator();
		}

		// Restore context
		this.ctx.restore();
	};

	/**
	 * Draw background
	 */
	TimelineVisualizer.prototype.drawBackground = function() {
		this.ctx.fillStyle = this.options.colors.background;
		this.ctx.fillRect( 0, 0, this.dimensions.width, this.dimensions.height );
	};

	/**
	 * Draw grid lines
	 */
	TimelineVisualizer.prototype.drawGrid = function() {
		if ( !this.state.startDate || !this.state.endDate ) {return;}

		var start = new Date( this.state.startDate );
		var end = new Date( this.state.endDate );
		var duration = end - start;

		// Determine grid interval based on duration
		var interval;
		if ( 86400000 >= duration ) { // 1 day
			interval = 3600000 * 4; // 4 hours
		} else if ( 604800000 >= duration ) { // 1 week
			interval = 86400000; // 1 day
		} else if ( 2592000000 >= duration ) { // 30 days
			interval = 86400000 * 7; // 1 week
		} else {
			interval = 2592000000; // 30 days
		}

		// Draw grid lines
		this.ctx.strokeStyle = this.options.colors.grid;
		this.ctx.lineWidth = 1;
		this.ctx.setLineDash( [ 2, 4 ] );

		var current = new Date( Math.ceil( start.getTime() / interval ) * interval );
		while ( current <= end ) {
			var x = this.timeToX( current );

			this.ctx.beginPath();
			this.ctx.moveTo( x, this.options.padding.top );
			this.ctx.lineTo( x, this.options.height - this.options.padding.bottom );
			this.ctx.stroke();

			current = new Date( current.getTime() + interval );
		}

		this.ctx.setLineDash( [] );
	};

	/**
	 * Draw main timeline bar
	 */
	TimelineVisualizer.prototype.drawTimeline = function() {
		var y = this.options.padding.top + this.dimensions.innerHeight / 2;
		var barHeight = 12;

		// Draw timeline background
		this.ctx.fillStyle = this.options.colors.timeline;
		this.ctx.fillRect( this.options.padding.left,
			y - barHeight / 2,
			this.dimensions.innerWidth,
			barHeight );

		// Draw active period if dates are set
		if ( this.state.startDate && this.state.endDate ) {
			var now = new Date();
			var start = new Date( this.state.startDate );
			var end = new Date( this.state.endDate );

			var startX = this.timeToX( start );
			var endX = this.timeToX( end );

			// Determine color based on status
			var color;
			if ( now < start ) {
				color = this.options.colors.future;
			} else if ( now > end ) {
				color = this.options.colors.past;
			} else {
				color = this.options.colors.active;
			}

			// Draw active period
			this.ctx.fillStyle = color;
			this.ctx.fillRect( startX,
				y - barHeight / 2,
				endX - startX,
				barHeight );

			// Draw progress if active
			if ( now >= start && now <= end ) {
				var progressX = this.timeToX( now );

				// Darken the completed portion
				this.ctx.fillStyle = this.options.colors.past;
				this.ctx.globalAlpha = 0.3;
				this.ctx.fillRect( startX,
					y - barHeight / 2,
					progressX - startX,
					barHeight );
				this.ctx.globalAlpha = 1;
			}
		}
	};

	/**
	 * Draw start/end markers
	 */
	TimelineVisualizer.prototype.drawMarkers = function() {
		if ( !this.state.startDate || !this.state.endDate ) {return;}

		var y = this.options.padding.top + this.dimensions.innerHeight / 2;
		var markerSize = 16;

		// Draw start marker
		this.drawMarker(
			this.timeToX( new Date( this.state.startDate ) ),
			y,
			markerSize,
			this.options.colors.interactive,
			'start'
		);

		// Draw end marker
		this.drawMarker(
			this.timeToX( new Date( this.state.endDate ) ),
			y,
			markerSize,
			this.options.colors.interactive,
			'end'
		);
	};

	/**
	 * Draw individual marker
	 * @param x
	 * @param y
	 * @param size
	 * @param color
	 * @param type
	 */
	TimelineVisualizer.prototype.drawMarker = function( x, y, size, color, type ) {
		this.ctx.fillStyle = color;
		this.ctx.strokeStyle = this.options.colors.background || '#fff';
		this.ctx.lineWidth = 2;

		// Draw circle
		this.ctx.beginPath();
		this.ctx.arc( x, y, size / 2, 0, Math.PI * 2 );
		this.ctx.fill();
		this.ctx.stroke();

		// Draw icon
		this.ctx.fillStyle = this.options.colors.background || '#fff';
		this.ctx.font = 'bold 12px sans-serif';
		this.ctx.textAlign = 'center';
		this.ctx.textBaseline = 'middle';

		if ( 'start' === type ) {
			this.ctx.fillText( '▶', x, y );
		} else if ( 'end' === type ) {
			this.ctx.fillText( '■', x, y );
		}
	};

	/**
	 * Draw date labels
	 */
	TimelineVisualizer.prototype.drawLabels = function() {
		if ( !this.state.startDate || !this.state.endDate ) {return;}

		var y = this.options.height - 10;

		this.ctx.fillStyle = this.options.colors.text;
		this.ctx.font = '12px sans-serif';

		// Start date label
		var startDate = new Date( this.state.startDate );
		var startX = this.timeToX( startDate );
		this.ctx.textAlign = 'left';
		this.ctx.fillText(
			this.formatDate( startDate, 'short' ),
			Math.max( startX, this.options.padding.left ),
			y
		);

		// End date label
		var endDate = new Date( this.state.endDate );
		var endX = this.timeToX( endDate );
		this.ctx.textAlign = 'right';
		this.ctx.fillText(
			this.formatDate( endDate, 'short' ),
			Math.min( endX, this.dimensions.width - this.options.padding.right ),
			y
		);

		// Duration label
		var duration = this.formatDuration( endDate - startDate );
		this.ctx.textAlign = 'center';
		this.ctx.fillStyle = this.options.colors.interactive;
		this.ctx.font = 'bold 14px sans-serif';
		this.ctx.fillText( duration,
			this.dimensions.width / 2,
			this.options.padding.top - 5 );
	};

	/**
	 * Draw current time indicator
	 */
	TimelineVisualizer.prototype.drawCurrentTimeIndicator = function() {
		if ( !this.state.startDate || !this.state.endDate ) {return;}

		var now = new Date();
		var start = new Date( this.state.startDate );
		var end = new Date( this.state.endDate );

		// Only show if current time is within visible range
		if ( now < start || now > end ) {return;}

		var x = this.timeToX( now );

		// Draw line
		this.ctx.strokeStyle = this.options.colors.current;
		this.ctx.lineWidth = 2;
		this.ctx.setLineDash( [ 4, 4 ] );

		this.ctx.beginPath();
		this.ctx.moveTo( x, this.options.padding.top );
		this.ctx.lineTo( x, this.options.height - this.options.padding.bottom );
		this.ctx.stroke();

		this.ctx.setLineDash( [] );

		// Draw "NOW" label
		this.ctx.fillStyle = this.options.colors.current;
		this.ctx.font = 'bold 10px sans-serif';
		this.ctx.textAlign = 'center';
		this.ctx.fillText( 'NOW', x, this.options.padding.top - 5 );
	};

	/**
	 * Convert time to X coordinate
	 * @param date
	 */
	TimelineVisualizer.prototype.timeToX = function( date ) {
		if ( !this.state.startDate || !this.state.endDate ) {return 0;}

		var start = new Date( this.state.startDate ).getTime();
		var end = new Date( this.state.endDate ).getTime();
		var time = date.getTime();

		var ratio = ( time - start ) / ( end - start );
		return this.options.padding.left + ratio * this.dimensions.innerWidth;
	};

	/**
	 * Convert X coordinate to time
	 * @param x
	 */
	TimelineVisualizer.prototype.xToTime = function( x ) {
		if ( !this.state.startDate || !this.state.endDate ) {return null;}

		var ratio = ( x - this.options.padding.left ) / this.dimensions.innerWidth;
		var start = new Date( this.state.startDate ).getTime();
		var end = new Date( this.state.endDate ).getTime();

		return new Date( start + ratio * ( end - start ) );
	};

	/**
	 * Handle mouse down
	 * @param e
	 */
	TimelineVisualizer.prototype.handleMouseDown = function( e ) {
		if ( !this.options.enableInteraction || !this.state.startDate || !this.state.endDate ) {return;}

		var rect = this.canvas[0].getBoundingClientRect();
		var x = e.clientX - rect.left;
		var y = e.clientY - rect.top;

		var startX = this.timeToX( new Date( this.state.startDate ) );
		var endX = this.timeToX( new Date( this.state.endDate ) );
		var markerY = this.options.padding.top + this.dimensions.innerHeight / 2;

		if ( this.isNearPoint( x, y, startX, markerY, 16 ) ) {
			this.state.isDragging = true;
			this.state.dragType = 'start';
			this.interactionLayer.css( 'cursor', 'ew-resize' );
			e.preventDefault();
		} else if ( this.isNearPoint( x, y, endX, markerY, 16 ) ) {
			this.state.isDragging = true;
			this.state.dragType = 'end';
			this.interactionLayer.css( 'cursor', 'ew-resize' );
			e.preventDefault();
		} else if ( x >= startX && x <= endX && 12 >= Math.abs( y - markerY ) ) {
			this.state.isDragging = true;
			this.state.dragType = 'move';
			this.state.dragOffset = x - startX;
			this.interactionLayer.css( 'cursor', 'move' );
			e.preventDefault();
		}
	};

	/**
	 * Handle mouse move
	 * @param e
	 */
	TimelineVisualizer.prototype.handleMouseMove = function( e ) {
		var rect = this.canvas[0].getBoundingClientRect();
		var x = e.clientX - rect.left;
		var y = e.clientY - rect.top;

		if ( this.state.isDragging ) {
			e.preventDefault();

			if ( 'start' === this.state.dragType ) {
				var newDate = this.xToTime( x );
				if ( newDate && newDate < new Date( this.state.endDate ) ) {
					this.updateStartDate( newDate );
				}
			} else if ( 'end' === this.state.dragType ) {
				var newEndDate = this.xToTime( x );
				if ( newEndDate && newEndDate > new Date( this.state.startDate ) ) {
					this.updateEndDate( newEndDate );
				}
			} else if ( 'move' === this.state.dragType ) {
				var duration = new Date( this.state.endDate ) - new Date( this.state.startDate );
				var newStart = this.xToTime( x - this.state.dragOffset );
				if ( newStart ) {
					var newEnd = new Date( newStart.getTime() + duration );
					this.updateDates( newStart, newEnd );
				}
			}
		} else if ( this.state.startDate && this.state.endDate ) {
			var startX = this.timeToX( new Date( this.state.startDate ) );
			var endX = this.timeToX( new Date( this.state.endDate ) );
			var markerY = this.options.padding.top + this.dimensions.innerHeight / 2;

			if ( this.isNearPoint( x, y, startX, markerY, 16 ) ||
                this.isNearPoint( x, y, endX, markerY, 16 ) ) {
				this.interactionLayer.css( 'cursor', 'ew-resize' );
				this.showTooltip( x, y, 'Drag to adjust date' );
			} else if ( x >= startX && x <= endX && 12 >= Math.abs( y - markerY ) ) {
				this.interactionLayer.css( 'cursor', 'move' );
				this.showTooltip( x, y, 'Drag to move campaign' );
			} else {
				this.interactionLayer.css( 'cursor', 'default' );
				this.hideTooltip();
			}
		}
	};

	/**
	 * Handle mouse up
	 * @param {Event} _e - Mouse event (unused)
	 */
	TimelineVisualizer.prototype.handleMouseUp = function( _e ) {
		if ( this.state.isDragging ) {
			this.state.isDragging = false;
			this.state.dragType = null;
			this.state.dragOffset = null;
			this.interactionLayer.css( 'cursor', 'default' );
		}
	};

	/**
	 * Handle mouse leave
	 * @param e
	 */
	TimelineVisualizer.prototype.handleMouseLeave = function( e ) {
		this.handleMouseUp( e );
		this.hideTooltip();
	};

	/**
	 * Check if point is near another point
	 * @param x1
	 * @param y1
	 * @param x2
	 * @param y2
	 * @param threshold
	 */
	TimelineVisualizer.prototype.isNearPoint = function( x1, y1, x2, y2, threshold ) {
		var dx = x1 - x2;
		var dy = y1 - y2;
		return Math.sqrt( dx * dx + dy * dy ) <= threshold;
	};

	/**
	 * Update start date
	 * @param date
	 */
	TimelineVisualizer.prototype.updateStartDate = function( date ) {
		this.state.startDate = date;
		this.render();

		if ( this.options.onDateChange ) {
			this.options.onDateChange( 'start', date );
		}
	};

	/**
	 * Update end date
	 * @param date
	 */
	TimelineVisualizer.prototype.updateEndDate = function( date ) {
		this.state.endDate = date;
		this.render();

		if ( this.options.onDateChange ) {
			this.options.onDateChange( 'end', date );
		}
	};

	/**
	 * Update both dates
	 * @param startDate
	 * @param endDate
	 */
	TimelineVisualizer.prototype.updateDates = function( startDate, endDate ) {
		this.state.startDate = startDate;
		this.state.endDate = endDate;
		this.render();

		if ( this.options.onDateChange ) {
			this.options.onDateChange( 'both', { start: startDate, end: endDate } );
		}
	};

	/**
	 * Show tooltip
	 * @param x
	 * @param y
	 * @param text
	 */
	TimelineVisualizer.prototype.showTooltip = function( x, y, text ) {
		if ( !this.options.showTooltips ) {return;}

		this.tooltip
			.text( text )
			.css( {
				left: x + 'px',
				top: ( y - 30 ) + 'px'
			} )
			.show();
	};

	/**
	 * Hide tooltip
	 */
	TimelineVisualizer.prototype.hideTooltip = function() {
		this.tooltip.hide();
	};

	/**
	 * Format date
	 * @param date
	 * @param format
	 */
	TimelineVisualizer.prototype.formatDate = function( date, format ) {
		var options = 'short' === format ?
			{ month: 'short', day: 'numeric' } :
			{ month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' };

		return date.toLocaleDateString( 'en-US', options );
	};

	/**
	 * Format duration
	 * @param ms
	 */
	TimelineVisualizer.prototype.formatDuration = function( ms ) {
		var days = Math.floor( ms / 86400000 );
		var hours = Math.floor( ( ms % 86400000 ) / 3600000 );
		var minutes = Math.floor( ( ms % 3600000 ) / 60000 );

		if ( 0 < days ) {
			return days + ' day' + ( 1 !== days ? 's' : '' ) +
                   ( 0 < hours ? ', ' + hours + ' hour' + ( 1 !== hours ? 's' : '' ) : '' );
		} else if ( 0 < hours ) {
			return hours + ' hour' + ( 1 !== hours ? 's' : '' ) +
                   ( 0 < minutes ? ', ' + minutes + ' minute' + ( 1 !== minutes ? 's' : '' ) : '' );
		} else {
			return minutes + ' minute' + ( 1 !== minutes ? 's' : '' );
		}
	};

	/**
	 * Set dates externally
	 * @param startDate
	 * @param endDate
	 */
	TimelineVisualizer.prototype.setDates = function( startDate, endDate ) {
		this.state.startDate = startDate;
		this.state.endDate = endDate;
		this.render();
	};

	/**
	 * Enable/disable interaction
	 * @param enabled
	 */
	TimelineVisualizer.prototype.setInteractive = function( enabled ) {
		this.state.isInteractive = enabled;
		if ( this.interactionLayer ) {
			this.interactionLayer.css( 'display', enabled ? 'block' : 'none' );
		}
	};

	/**
	 * Destroy timeline
	 */
	TimelineVisualizer.prototype.destroy = function() {
		$( window ).off( '.timeline' );
		$( document ).off( '.timeline' );
		if ( this.interactionLayer ) {
			this.interactionLayer.off();
		}
		this.container.empty().removeClass( 'wsscd-timeline-visualizer' );
	};

	/**
	 * Debounce helper
	 * @param func
	 * @param wait
	 */
	TimelineVisualizer.prototype.debounce = function( func, wait ) {
		var timeout;
		return function executedFunction() {
			var args = Array.prototype.slice.call( arguments );
			var later = function() {
				clearTimeout( timeout );
				func.apply( this, args );
			};
			clearTimeout( timeout );
			timeout = setTimeout( later, wait );
		};
	};

	// Touch event handlers
	TimelineVisualizer.prototype.handleTouchStart = function( e ) {
		var touch = e.originalEvent.touches[0];
		var mouseEvent = new MouseEvent( 'mousedown', {
			clientX: touch.clientX,
			clientY: touch.clientY
		} );
		this.handleMouseDown( mouseEvent );
	};

	TimelineVisualizer.prototype.handleTouchMove = function( e ) {
		var touch = e.originalEvent.touches[0];
		var mouseEvent = new MouseEvent( 'mousemove', {
			clientX: touch.clientX,
			clientY: touch.clientY
		} );
		this.handleMouseMove( mouseEvent );
	};

	TimelineVisualizer.prototype.handleTouchEnd = function( e ) {
		this.handleMouseUp( e );
	};

	// Export to global namespace
	window.WSSCD = window.WSSCD || {};
	window.WSSCD.TimelineVisualizer = TimelineVisualizer;

	// jQuery plugin
	$.fn.wsscdTimeline = function( options ) {
		return this.each( function() {
			var $this = $( this );
			var instance = $this.data( 'wsscdTimeline' );

			if ( !instance ) {
				instance = new TimelineVisualizer( this, options );
				$this.data( 'wsscdTimeline', instance );
			}

			if ( 'string' === typeof options && instance[options] ) {
				instance[options].apply( instance, Array.prototype.slice.call( arguments, 1 ) );
			}
		} );
	};

} )( jQuery, window, document );