/**
 * Jest setup file
 *
 * @package SmartCycleDiscounts
 */

/* eslint-env jest, node */

// Mock jQuery
global.jQuery = jest.fn( function( _selector ) {
	var element = {
		val: jest.fn(),
		html: jest.fn(),
		text: jest.fn(),
		show: jest.fn(),
		hide: jest.fn(),
		addClass: jest.fn(),
		removeClass: jest.fn(),
		hasClass: jest.fn(),
		attr: jest.fn(),
		data: jest.fn(),
		on: jest.fn(),
		off: jest.fn(),
		trigger: jest.fn(),
		find: jest.fn( function() { return element; } ),
		closest: jest.fn( function() { return element; } ),
		parent: jest.fn( function() { return element; } ),
		children: jest.fn( function() { return element; } ),
		each: jest.fn( function( callback ) {
			callback.call( element, 0, element );
			return element;
		} ),
	};

	// Make chainable
	var keys = Object.keys( element );
	for ( var i = 0; i < keys.length; i++ ) {
		var key = keys[i];
		if ( 'function' === typeof element[key] ) {
			var originalFn = element[key];
			element[key] = jest.fn( function() {
				var args = Array.prototype.slice.call( arguments );
				originalFn.apply( null, args );
				return element;
			} );
		}
	}

	return element;
} );

global.$ = global.jQuery;

// Mock jQuery AJAX
global.jQuery.ajax = jest.fn( function() { return Promise.resolve( { success: true, data: {} } ); } );
global.jQuery.post = jest.fn( function() { return Promise.resolve( { success: true, data: {} } ); } );
global.jQuery.get = jest.fn( function() { return Promise.resolve( { success: true, data: {} } ); } );

// Mock jQuery document ready
global.jQuery.fn = {
	ready: jest.fn( function( callback ) { return callback(); } ),
};

// Mock WordPress globals
global.wp = {
	ajax: {
		post: jest.fn( function() { return Promise.resolve( { success: true } ); } ),
		send: jest.fn(),
	},
	hooks: {
		addFilter: jest.fn(),
		addAction: jest.fn(),
		doAction: jest.fn(),
		applyFilters: jest.fn( function( name, value ) { return value; } ),
	},
};

// Mock window methods
global.alert = jest.fn();
global.confirm = jest.fn( function() { return true; } );
global.console.error = jest.fn();

// Mock localStorage
var localStorageMock = {
	getItem: jest.fn(),
	setItem: jest.fn(),
	removeItem: jest.fn(),
	clear: jest.fn(),
};
global.localStorage = localStorageMock;

// Mock performance API
global.performance = {
	now: jest.fn( function() { return Date.now(); } ),
};

// Add custom matchers
expect.extend( {
	toHaveBeenCalledWithMatch: function( received, expected ) {
		var calls = received.mock.calls;
		var pass = false;

		for ( var i = 0; i < calls.length; i++ ) {
			var call = calls[i];
			for ( var j = 0; j < call.length; j++ ) {
				var arg = call[j];
				if ( -1 !== JSON.stringify( arg ).indexOf( JSON.stringify( expected ) ) ) {
					pass = true;
					break;
				}
			}
			if ( pass ) {
				break;
			}
		}

		return {
			pass: pass,
			message: function() {
				return pass
					? 'Expected function not to have been called with matching ' + JSON.stringify( expected )
					: 'Expected function to have been called with matching ' + JSON.stringify( expected );
			},
		};
	},
} );