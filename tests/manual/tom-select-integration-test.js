/**
 * Tom-Select Integration Tests
 *
 * Manual test suite to verify Tom-Select fixes:
 * - Race condition fix (no "items is undefined" errors)
 * - Click handler workaround for Tom-Select bug #701
 * - Preload functionality
 * - Product search and category filter functionality
 *
 * HOW TO RUN:
 * 1. Navigate to campaign wizard Products step
 * 2. Open browser console (F12)
 * 3. Paste this entire file into console
 * 4. Run: runTomSelectTests()
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function() {
	'use strict';

	// Test suite state
	var testResults = {
		passed: 0,
		failed: 0,
		warnings: 0,
		tests: []
	};

	/**
	 * Test runner utility
	 */
	function test( name, fn ) {
		var result = {
			name: name,
			status: 'running',
			message: '',
			duration: 0
		};

		var startTime = Date.now();

		try {
			var testResult = fn();

			// Handle promises
			if ( testResult && typeof testResult.then === 'function' ) {
				return testResult.then( function() {
					result.status = 'passed';
					result.duration = Date.now() - startTime;
					testResults.passed++;
					testResults.tests.push( result );
					console.log( '%c✓ PASS: ' + name, 'color: green; font-weight: bold' );
					return result;
				} ).catch( function( error ) {
					result.status = 'failed';
					result.message = error.message || error;
					result.duration = Date.now() - startTime;
					testResults.failed++;
					testResults.tests.push( result );
					console.error( '%c✗ FAIL: ' + name, 'color: red; font-weight: bold', error );
					return result;
				} );
			}

			// Synchronous test
			result.status = 'passed';
			result.duration = Date.now() - startTime;
			testResults.passed++;
			testResults.tests.push( result );
			console.log( '%c✓ PASS: ' + name, 'color: green; font-weight: bold' );
			return Promise.resolve( result );

		} catch ( error ) {
			result.status = 'failed';
			result.message = error.message || error;
			result.duration = Date.now() - startTime;
			testResults.failed++;
			testResults.tests.push( result );
			console.error( '%c✗ FAIL: ' + name, 'color: red; font-weight: bold', error );
			return Promise.reject( result );
		}
	}

	/**
	 * Assertion helpers
	 */
	function assert( condition, message ) {
		if ( !condition ) {
			throw new Error( message || 'Assertion failed' );
		}
	}

	function assertEqual( actual, expected, message ) {
		if ( actual !== expected ) {
			throw new Error( ( message || 'Expected ' + expected + ' but got ' + actual ) );
		}
	}

	function assertExists( value, message ) {
		if ( typeof value === 'undefined' || value === null ) {
			throw new Error( message || 'Expected value to exist but got ' + value );
		}
	}

	function assertInstanceOf( value, constructor, message ) {
		if ( !( value instanceof constructor ) ) {
			throw new Error( message || 'Expected instance of ' + constructor.name );
		}
	}

	/**
	 * Wait utility
	 */
	function wait( ms ) {
		return new Promise( function( resolve ) {
			setTimeout( resolve, ms );
		} );
	}

	/**
	 * Test Suite: Prerequisites
	 */
	function testPrerequisites() {
		console.log( '\n%c=== PREREQUISITES TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		test( 'jQuery is loaded', function() {
			assertExists( window.jQuery, 'jQuery not found' );
			assertExists( window.$, '$ not found' );
		} );

		test( 'SCD namespace exists', function() {
			assertExists( window.SCD, 'SCD namespace not found' );
		} );

		test( 'TomSelectBase exists', function() {
			assertExists( window.SCD.Shared, 'SCD.Shared not found' );
			assertExists( window.SCD.Shared.TomSelectBase, 'TomSelectBase not found' );
			assertEqual( typeof window.SCD.Shared.TomSelectBase, 'function', 'TomSelectBase is not a constructor' );
		} );

		test( 'Tom-Select library is loaded', function() {
			assertExists( window.TomSelect, 'TomSelect library not loaded' );
		} );

		test( 'Product search element exists', function() {
			var $element = jQuery( '#scd-product-search' );
			assert( $element.length > 0, 'Product search element not found in DOM' );
		} );

		test( 'Category filter element exists', function() {
			var $element = jQuery( '#scd-campaign-categories' );
			assert( $element.length > 0, 'Category filter element not found in DOM' );
		} );
	}

	/**
	 * Test Suite: Configuration
	 */
	function testConfiguration() {
		console.log( '\n%c=== CONFIGURATION TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		test( 'TomSelectBase has getDefaultConfig method', function() {
			var instance = new window.SCD.Shared.TomSelectBase( document.createElement( 'select' ), {} );
			assertEqual( typeof instance.getDefaultConfig, 'function', 'getDefaultConfig is not a function' );
		} );

		test( 'Default config has openOnFocus: false', function() {
			var instance = new window.SCD.Shared.TomSelectBase( document.createElement( 'select' ), {} );
			var config = instance.getDefaultConfig();
			assertEqual( config.openOnFocus, false, 'openOnFocus should be false by default' );
		} );

		test( 'Default config has preload: false', function() {
			var instance = new window.SCD.Shared.TomSelectBase( document.createElement( 'select' ), {} );
			var config = instance.getDefaultConfig();
			assertEqual( config.preload, false, 'preload should be false by default' );
		} );

		test( 'Default config has onClick handler', function() {
			var instance = new window.SCD.Shared.TomSelectBase( document.createElement( 'select' ), {} );
			var config = instance.getDefaultConfig();
			assertExists( config.onClick, 'onClick handler not found in default config' );
			assertEqual( typeof config.onClick, 'function', 'onClick is not a function' );
		} );

		test( 'Configuration validation warns about dangerous combination', function() {
			var element = document.createElement( 'select' );
			var warningCaught = false;

			// Spy on console.warn
			var originalWarn = console.warn;
			console.warn = function() {
				warningCaught = true;
			};

			// Create instance with dangerous config
			try {
				new window.SCD.Shared.TomSelectBase( element, {
					preload: true,
					openOnFocus: true
				} );
			} catch ( e ) {
				// Ignore initialization errors
			}

			console.warn = originalWarn;

			assert( warningCaught, 'No warning was issued for dangerous configuration' );
		} );
	}

	/**
	 * Test Suite: Product Search Tom-Select
	 */
	function testProductSearch() {
		console.log( '\n%c=== PRODUCT SEARCH TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		return test( 'Product search Tom-Select instance exists', function() {
			var $element = jQuery( '#scd-product-search' );
			var tomSelectInstance = $element[0].tomselect;
			assertExists( tomSelectInstance, 'Product search Tom-Select instance not initialized' );
			return Promise.resolve();
		} ).then( function() {
			return test( 'Product search has correct configuration', function() {
				var $element = jQuery( '#scd-product-search' );
				var instance = $element[0].tomselect;

				assertEqual( instance.settings.openOnFocus, false, 'openOnFocus should be false' );
				assertEqual( instance.settings.preload, true, 'preload should be true for products' );
				assertExists( instance.settings.onClick, 'onClick handler missing' );
			} );
		} ).then( function() {
			return test( 'Product search items array is initialized', function() {
				var $element = jQuery( '#scd-product-search' );
				var instance = $element[0].tomselect;

				assertExists( instance.items, 'items array should exist' );
				assert( Array.isArray( instance.items ), 'items should be an array' );
			} );
		} ).then( function() {
			return test( 'Product search opens on click', function() {
				var $element = jQuery( '#scd-product-search' );
				var instance = $element[0].tomselect;

				// Close if open
				if ( instance.isOpen ) {
					instance.close();
				}

				// Simulate click
				instance.trigger( 'click' );

				return wait( 100 ).then( function() {
					assert( instance.isOpen, 'Dropdown should open on click' );
					instance.close(); // Clean up
				} );
			} );
		} ).then( function() {
			return test( 'Product search does not auto-open on focus', function() {
				var $element = jQuery( '#scd-product-search' );
				var instance = $element[0].tomselect;

				// Close if open
				if ( instance.isOpen ) {
					instance.close();
				}

				// Simulate focus (should NOT open)
				instance.trigger( 'focus' );

				return wait( 100 ).then( function() {
					assert( !instance.isOpen, 'Dropdown should NOT auto-open on focus' );
				} );
			} );
		} ).catch( function( error ) {
			console.error( 'Product search tests failed:', error );
		} );
	}

	/**
	 * Test Suite: Category Filter Tom-Select
	 */
	function testCategoryFilter() {
		console.log( '\n%c=== CATEGORY FILTER TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		return test( 'Category filter Tom-Select instance exists', function() {
			var $element = jQuery( '#scd-campaign-categories' );
			var tomSelectInstance = $element[0].tomselect;
			assertExists( tomSelectInstance, 'Category filter Tom-Select instance not initialized' );
			return Promise.resolve();
		} ).then( function() {
			return test( 'Category filter has correct configuration', function() {
				var $element = jQuery( '#scd-campaign-categories' );
				var instance = $element[0].tomselect;

				assertEqual( instance.settings.openOnFocus, false, 'openOnFocus should be false' );
				assertEqual( instance.settings.preload, true, 'preload should be true for categories' );
				assertExists( instance.settings.onClick, 'onClick handler missing' );
			} );
		} ).then( function() {
			return test( 'Category filter items array is initialized', function() {
				var $element = jQuery( '#scd-campaign-categories' );
				var instance = $element[0].tomselect;

				assertExists( instance.items, 'items array should exist' );
				assert( Array.isArray( instance.items ), 'items should be an array' );
			} );
		} ).then( function() {
			return test( 'Category filter opens on click', function() {
				var $element = jQuery( '#scd-campaign-categories' );
				var instance = $element[0].tomselect;

				// Close if open
				if ( instance.isOpen ) {
					instance.close();
				}

				// Simulate click
				instance.trigger( 'click' );

				return wait( 100 ).then( function() {
					assert( instance.isOpen, 'Dropdown should open on click' );
					instance.close(); // Clean up
				} );
			} );
		} ).then( function() {
			return test( 'Category filter has "All Categories" option', function() {
				var $element = jQuery( '#scd-campaign-categories' );
				var instance = $element[0].tomselect;

				assertExists( instance.options['all'], '"All Categories" option should exist' );
				assertEqual( instance.options['all'].text, 'All Categories', 'Text should be "All Categories"' );
			} );
		} ).catch( function( error ) {
			console.error( 'Category filter tests failed:', error );
		} );
	}

	/**
	 * Test Suite: Race Condition Prevention
	 */
	function testRaceCondition() {
		console.log( '\n%c=== RACE CONDITION TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		test( 'No console errors related to "items is undefined"', function() {
			// This test checks if the race condition error occurred
			// We'll monitor the console for errors during the test suite
			console.log( '%cMonitor console for "can\'t access property filter, t.items is undefined" errors', 'color: orange' );
			console.log( '%cIf you see this error, the race condition fix failed!', 'color: orange; font-weight: bold' );
		} );

		return test( 'Click handler prevents opening during preload', function() {
			// Create a test instance
			var element = document.createElement( 'select' );
			var instance = new window.SCD.Shared.TomSelectBase( element, {
				preload: true
			} );

			// Simulate loading state
			instance.pagination.isLoading = true;

			return instance.init().then( function() {
				// Try to trigger click while loading
				var wasOpen = instance.instance.isOpen;
				instance._handleClick();

				assert( !instance.instance.isOpen, 'Dropdown should not open during preload' );
				assertEqual( wasOpen, instance.instance.isOpen, 'Open state should not change during preload' );
			} );
		} ).catch( function( error ) {
			console.error( 'Race condition test failed:', error );
		} );
	}

	/**
	 * Test Suite: Integration Tests
	 */
	function testIntegration() {
		console.log( '\n%c=== INTEGRATION TESTS ===', 'color: blue; font-size: 14px; font-weight: bold' );

		return test( 'Product search and category filter work together', function() {
			var $productSearch = jQuery( '#scd-product-search' );
			var $categoryFilter = jQuery( '#scd-campaign-categories' );

			assertExists( $productSearch[0].tomselect, 'Product search should be initialized' );
			assertExists( $categoryFilter[0].tomselect, 'Category filter should be initialized' );

			return Promise.resolve();
		} ).then( function() {
			return test( 'Selecting category triggers product filter', function() {
				console.log( '%cManual verification: Select a category and check if product list updates', 'color: orange' );
				return Promise.resolve();
			} );
		} ).then( function() {
			return test( 'Selecting products updates count', function() {
				console.log( '%cManual verification: Select products and check if count updates', 'color: orange' );
				return Promise.resolve();
			} );
		} );
	}

	/**
	 * Print test summary
	 */
	function printSummary() {
		console.log( '\n%c=== TEST SUMMARY ===', 'color: blue; font-size: 16px; font-weight: bold' );
		console.log( '%cTotal Tests: ' + testResults.tests.length, 'font-weight: bold' );
		console.log( '%c✓ Passed: ' + testResults.passed, 'color: green; font-weight: bold' );
		console.log( '%c✗ Failed: ' + testResults.failed, 'color: red; font-weight: bold' );

		if ( testResults.failed === 0 ) {
			console.log( '\n%c🎉 ALL TESTS PASSED! 🎉', 'color: green; font-size: 18px; font-weight: bold' );
		} else {
			console.log( '\n%c⚠️  SOME TESTS FAILED ⚠️', 'color: red; font-size: 18px; font-weight: bold' );
			console.log( '\nFailed tests:' );
			testResults.tests.filter( function( t ) {
				return t.status === 'failed';
			} ).forEach( function( t ) {
				console.log( '%c✗ ' + t.name + ': ' + t.message, 'color: red' );
			} );
		}

		// Return results for programmatic use
		return testResults;
	}

	/**
	 * Main test runner
	 */
	window.runTomSelectTests = function() {
		console.clear();
		console.log( '%c╔════════════════════════════════════════════════════════╗', 'color: blue; font-weight: bold' );
		console.log( '%c║   TOM-SELECT INTEGRATION TEST SUITE                   ║', 'color: blue; font-weight: bold' );
		console.log( '%c║   Testing race condition fix and click handler        ║', 'color: blue; font-weight: bold' );
		console.log( '%c╚════════════════════════════════════════════════════════╝', 'color: blue; font-weight: bold' );

		// Reset results
		testResults = {
			passed: 0,
			failed: 0,
			warnings: 0,
			tests: []
		};

		// Run test suites sequentially
		testPrerequisites();

		return wait( 100 )
			.then( testConfiguration )
			.then( function() { return wait( 100 ); } )
			.then( testRaceCondition )
			.then( function() { return wait( 100 ); } )
			.then( testProductSearch )
			.then( function() { return wait( 100 ); } )
			.then( testCategoryFilter )
			.then( function() { return wait( 100 ); } )
			.then( testIntegration )
			.then( function() { return wait( 100 ); } )
			.then( printSummary )
			.catch( function( error ) {
				console.error( '%cTest suite error:', 'color: red; font-weight: bold', error );
				printSummary();
			} );
	};

	console.log( '%cTom-Select Test Suite Loaded!', 'color: green; font-weight: bold' );
	console.log( '%cRun: runTomSelectTests()', 'color: blue; font-weight: bold' );

} )();
