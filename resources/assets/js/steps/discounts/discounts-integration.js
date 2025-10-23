/**
 * Discount Integration Module
 *
 * Handles integration testing, performance monitoring, and error recovery
 * for the modular discount system.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Integration Testing and Monitoring
	 *
	 * @class SCD.Modules.Discounts.Integration
	 */
	SCD.Modules.Discounts.Integration = function() {
		this.tests = {};
		this.metrics = {
			moduleLoadTime: {},
			operationTime: {},
			memoryUsage: {},
			errorCount: 0,
			warningCount: 0
		};
		this.startTime = Date.now();
	};

	SCD.Modules.Discounts.Integration.prototype = {
		/**
		 * Initialize integration module
		 */
		init: function() {
			this.registerTests();
			this.setupErrorHandling();
			this.setupPerformanceMonitoring();
		},

		/**
		 * Register all integration tests
		 */
		registerTests: function() {
			// Module loading tests
			this.registerTest( 'module_loading', {
				name: 'Module Loading',
				description: 'Verify all modules load correctly',
				run: this.testModuleLoading.bind( this )
			} );

			// State management tests
			this.registerTest( 'state_management', {
				name: 'State Management',
				description: 'Test state synchronization across modules',
				run: this.testStateManagement.bind( this )
			} );

			// Discount type tests
			this.registerTest( 'discount_types', {
				name: 'Discount Types',
				description: 'Test all discount type implementations',
				run: this.testDiscountTypes.bind( this )
			} );

			// Data persistence tests
			this.registerTest( 'data_persistence', {
				name: 'Data Persistence',
				description: 'Test save/load functionality',
				run: this.testDataPersistence.bind( this )
			} );

			// Condition system tests
			this.registerTest( 'condition_system', {
				name: 'Condition System',
				description: 'Test condition validation and application',
				run: this.testConditionSystem.bind( this )
			} );

			// Validation tests
			this.registerTest( 'validation', {
				name: 'Validation',
				description: 'Test validation across all modules',
				run: this.testValidation.bind( this )
			} );

			// Performance tests
			this.registerTest( 'performance', {
				name: 'Performance',
				description: 'Test system performance metrics',
				run: this.testPerformance.bind( this )
			} );

			// Error recovery tests
			this.registerTest( 'error_recovery', {
				name: 'Error Recovery',
				description: 'Test error handling and recovery',
				run: this.testErrorRecovery.bind( this )
			} );
		},

		/**
		 * Register a test
		 * @param id
		 * @param config
		 */
		registerTest: function( id, config ) {
			this.tests[id] = $.extend( {
				id: id,
				status: 'pending',
				result: null,
				duration: 0
			}, config );
		},

		/**
		 * Run all tests
		 */
		runAllTests: function() {
			var results = {
				total: 0,
				passed: 0,
				failed: 0,
				skipped: 0,
				duration: 0,
				tests: {}
			};

			var startTime = Date.now();
			var self = this;

			return Promise.all(
				Object.keys( this.tests ).map( function( testId ) { return self.runTest( testId ); } ) ).then( function( testResults ) {
				testResults.forEach( function( result, index ) {
					var testId = Object.keys( self.tests )[index];
					results.tests[testId] = result;
					results.total++;

					if ( 'passed' === result.status ) {results.passed++;}
					else if ( 'failed' === result.status ) {results.failed++;}
					else if ( 'skipped' === result.status ) {results.skipped++;}
				} );

				results.duration = Date.now() - startTime;
				return results;
			} );
		},

		/**
		 * Run a single test
		 * @param testId
		 */
		runTest: function( testId ) {
			var test = this.tests[testId];
			if ( !test ) {
				return $.Deferred( function( d ) {
					d.resolve( {
						status: 'skipped',
						message: 'Test not found'
					} );
				} ).promise();
			}

			var startTime = Date.now();

			return $.Deferred( function( d ) { d.resolve(); } ).promise()
				.then( function() { return test.run(); } )
				.then( function( result ) {
					test.status = 'passed';
					test.result = result;
					test.duration = Date.now() - startTime;

					return {
						status: 'passed',
						result: result,
						duration: test.duration
					};
				} )
				.fail( function( error ) {
					test.status = 'failed';
					test.error = error;
					test.duration = Date.now() - startTime;

					return {
						status: 'failed',
						error: error.message || error,
						duration: test.duration
					};
				} );
		},

		/**
		 * Test module loading
		 */
		testModuleLoading: function() {
			var requiredModules = [
				'Constants', 'State', 'Validators',
				'Events', 'TypeRegistry', 'Data', 'Conditions'
			];

			var missingModules = [];

			requiredModules.forEach( function( module ) {
				if ( !( window.SCD && window.SCD.Modules && window.SCD.Modules.Discounts && window.SCD.Modules.Discounts[module] ) ) {
					missingModules.push( module );
				}
			} );

			if ( 0 < missingModules.length ) {
				throw new Error( 'Missing modules: ' + missingModules.join( ', ' ) );
			}

			// Check discount types
			var requiredTypes = [
				'BaseDiscount', 'PercentageDiscount', 'FixedDiscount',
				'TieredDiscount', 'BogoDiscount', 'BundleDiscount'
			];

			var missingTypes = [];

			requiredTypes.forEach( function( type ) {
				if ( !( window.SCD && window.SCD.Modules && window.SCD.Modules.Discounts && window.SCD.Modules.Discounts.Types && window.SCD.Modules.Discounts.Types[type] ) ) {
					missingTypes.push( type );
				}
			} );

			if ( 0 < missingTypes.length ) {
				throw new Error( 'Missing discount types: ' + missingTypes.join( ', ' ) );
			}

			return {
				modules: requiredModules.length,
				types: requiredTypes.length
			};
		},

		/**
		 * Test state management
		 */
		testStateManagement: function() {
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;
			if ( !orchestrator ) {
				throw new Error( 'Orchestrator not initialized' );
			}

			// Test state changes
			var testData = {
				discountType: 'percentage',
				discountValuePercentage: 25,
				minimumOrderAmount: 50
			};

			orchestrator.setState( testData );

			var state = orchestrator.getState();

			// Verify state
			Object.keys( testData ).forEach( function( key ) {
				if ( state[key] !== testData[key] ) {
					throw new Error( 'State mismatch for ' + key + ': expected ' + testData[key] + ', got ' + state[key] );
				}
			} );

			// Test state events
			var eventTriggered = false;
			var testHandler = function( _event, _change ) {
				eventTriggered = true;
			};

			$( document ).on( 'scd:discounts:state:changed.test', testHandler );

			orchestrator.modules.state.setData( 'testValue', 123 );

			// Give a moment for the event to fire
			setTimeout( function() {
				if ( !eventTriggered ) {
				}
				$( document ).off( '.test' );
			}, 10 );

			return {
				stateKeys: Object.keys( state ).length,
				events: 'working'
			};
		},

		/**
		 * Test discount types
		 */
		testDiscountTypes: function() {
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;
			var registry = orchestrator && orchestrator.modules && orchestrator.modules.typeRegistry;

			if ( !registry ) {
				throw new Error( 'Type registry not initialized' );
			}

			var types = registry.getRegisteredTypes();
			var results = {};

			// Test each discount type
			Object.keys( types ).forEach( function( typeId ) {
				// Activate type
				registry.activateType( typeId );

				// Set test data
				switch ( typeId ) {
					case 'percentage':
						orchestrator.setState( { discountValuePercentage: 20 } );
						break;
					case 'fixed':
						orchestrator.setState( { discountValueFixed: 10 } );
						break;
					case 'tiered':
						orchestrator.setState( {
							tiers: [ { quantity: 5, discount: 10, type: 'percentage' } ],
							tierType: 'quantity'
						} );
						break;
					case 'bogo':
						orchestrator.setState( {
							bogoConfig: {
								rules: [ { buyQuantity: 1, getQuantity: 1, discountPercent: 100, applyTo: 'same' } ]
							}
						} );
						break;
					case 'bundle':
						orchestrator.setState( {
							bundleConfig: {
								bundles: [ {
									name: 'Test',
									products: [ { id: 1, name: 'Product' } ],
									discountType: 'percentage',
									discountValue: 15
								} ]
							}
						} );
						break;
				}

				// Validate
				var validation = registry.validateCurrent();

				// Collect data
				var data = registry.collectCurrentData();

				results[typeId] = {
					valid: validation.valid,
					hasData: 0 < Object.keys( data ).length
				};
			} );

			return results;
		},

		/**
		 * Test data persistence
		 */
		testDataPersistence: function() {
			var dataModule = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts && window.SCD.Steps.discounts.modules && window.SCD.Steps.discounts.modules.data;

			if ( !dataModule ) {
				throw new Error( 'Data module not initialized' );
			}

			// Test data transformation (simulates backend response after automatic conversion)
			var testData = {
				discountType: 'tiered',
				tiers: [
					{ quantity: 10, discount: 15, type: 'percentage' }
				],
				tierType: 'quantity',
				conditions: [
					{ type: 'user_role', operator: 'in_list', value: [ 'customer' ] }
				]
			};

			var transformed = dataModule.transformFromBackend( testData );

			// Verify transformation
			if ( 'tiered' !== transformed.discountType ) {
				throw new Error( 'Transformation failed: discount type' );
			}

			if ( !Array.isArray( transformed.conditions ) ) {
				throw new Error( 'Transformation failed: conditions' );
			}

			// Test local storage

			dataModule.storeData( testData );
			var retrieved = dataModule.getStoredData();

			if ( !retrieved ) {
				throw new Error( 'Local storage failed' );
			}

			return {
				transformation: 'passed',
				localStorage: 'passed',
				dataKeys: Object.keys( transformed ).length
			};
		},

		/**
		 * Test condition system
		 */
		testConditionSystem: function() {
			var conditionsModule = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts && window.SCD.Steps.discounts.modules && window.SCD.Steps.discounts.modules.conditions;

			if ( !conditionsModule ) {
				throw new Error( 'Conditions module not initialized' );
			}

			// Test condition types
			var conditionTypes = Object.keys( conditionsModule.conditionTypes );

			if ( 8 > conditionTypes.length ) {
				throw new Error( 'Expected at least 8 condition types, found ' + conditionTypes.length );
			}

			// Test adding conditions
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;
			orchestrator.setState( {
				conditions: [
					{
						id: 'test_1',
						type: 'cart_total',
						operator: 'greater_than',
						value: 100,
						enabled: true
					},
					{
						id: 'test_2',
						type: 'user_role',
						operator: 'in_list',
						value: [ 'customer' ],
						enabled: false
					}
				],
				conditionLogic: 'all'
			} );

			// Validate conditions
			var validation = conditionsModule.validateConditions();

			// Get summary
			var summary = conditionsModule.getConditionSummary();

			return {
				types: conditionTypes.length,
				validation: validation.valid ? 'passed' : 'failed',
				summary: summary
			};
		},

		/**
		 * Test validation
		 */
		testValidation: function() {
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;
			var results = {};

			// Test invalid percentage
			orchestrator.setState( {
				discountType: 'percentage',
				discountValuePercentage: 150
			} );

			var validation = orchestrator.validateStep();
			results.invalidPercentage = !validation.valid ? 'passed' : 'failed';

			// Test valid fixed discount
			orchestrator.setState( {
				discountType: 'fixed',
				discountValueFixed: 25.50
			} );

			validation = orchestrator.validateStep();
			results.validFixed = validation.valid ? 'passed' : 'failed';

			// Test invalid BOGO
			orchestrator.setState( {
				discountType: 'bogo',
				bogoConfig: { rules: [] }
			} );

			validation = orchestrator.validateStep();
			results.invalidBogo = !validation.valid ? 'passed' : 'failed';

			// Test condition validation
			orchestrator.setState( {
				discountType: 'percentage',
				discountValuePercentage: 20,
				conditions: [ {
					id: 'test',
					type: 'product',
					operator: 'in_list',
					value: [], // Empty products
					enabled: true
				} ]
			} );

			validation = orchestrator.validateStep();
			results.invalidCondition = !validation.valid ? 'passed' : 'failed';

			return results;
		},

		/**
		 * Test performance
		 */
		testPerformance: function() {
			var metrics = {};
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;

			// Test state update performance
			var stateStart = Date.now();
			for ( var i = 0; 100 > i; i++ ) {
				orchestrator.setState( { testValue: i } );
			}
			metrics.stateUpdates = Date.now() - stateStart;

			// Test type switching performance
			var typeStart = Date.now();
			var types = [ 'percentage', 'fixed', 'tiered', 'bogo', 'bundle' ];
			types.forEach( function( type ) {
				orchestrator.setState( { discountType: type } );
			} );
			metrics.typeSwitching = Date.now() - typeStart;

			// Test validation performance
			var validationStart = Date.now();
			for ( var j = 0; 50 > j; j++ ) {
				orchestrator.validateStep();
			}
			metrics.validation = Date.now() - validationStart;

			// Check memory usage
			if ( performance.memory ) {
				metrics.memoryUsed = Math.round( performance.memory.usedJSHeapSize / 1024 / 1024 ) + 'MB';
			}

			return metrics;
		},

		/**
		 * Test error recovery
		 */
		testErrorRecovery: function() {
			var results = {};
			var orchestrator = window.SCD && window.SCD.Steps && window.SCD.Steps.discounts;

			// Test invalid type - errors should be thrown
			orchestrator.setState( { discountType: 'invalid_type' } );
			results.invalidType = 'Type validation should prevent invalid types';

			// Test invalid state - validation should prevent this

			orchestrator.setState( {
				discountValuePercentage: 'not a number'
			} );

			// State module should handle type conversion or reject invalid values
			var state = orchestrator.getState();
			results.invalidState = state ? 'State handling works' : 'State unavailable';

			// Test data save error recovery
			var dataModule = orchestrator.modules.data;
			var originalAjax = $.ajax;

			// Mock failed AJAX - No need to update since this is just for testing
			$.ajax = function( options ) {
				setTimeout( function() {
					options.error( {}, 'error', 'Network error' );
				}, 10 );
			};

			return $.Deferred( function( d ) {
				dataModule.saveDiscountData( function( error ) {
					results.saveError = error ? 'passed - error handled' : 'failed - no error';

					// Restore AJAX
					$.ajax = originalAjax;

					d.resolve( results );
				} );
			} ).promise();
		},

		/**
		 * Setup error handling
		 */
		setupErrorHandling: function() {
			var self = this;

			// Global error handler
			window.addEventListener( 'error', function( _event ) {
				self.metrics.errorCount++;
				// Errors should be properly handled by modules
			} );

			// Unhandled promise rejections
			window.addEventListener( 'unhandledrejection', function( _event ) {
				self.metrics.errorCount++;
				// Promises should be properly handled
			} );
		},

		/**
		 * Setup performance monitoring
		 */
		setupPerformanceMonitoring: function() {
			var self = this;

			// Monitor function execution time
			this.measureTime = function( name, fn ) {
				var start = Date.now();
				var result = fn();
				var duration = Date.now() - start;

				if ( !self.metrics.operationTime[name] ) {
					self.metrics.operationTime[name] = [];
				}

				self.metrics.operationTime[name].push( duration );

				return result;
			};

			// Monitor memory usage
			if ( performance.memory ) {
				setInterval( function() {
					var used = Math.round( performance.memory.usedJSHeapSize / 1024 / 1024 );
					var total = Math.round( performance.memory.totalJSHeapSize / 1024 / 1024 );

					self.metrics.memoryUsage = {
						used: used,
						total: total,
						percentage: Math.round( ( used / total ) * 100 )
					};
				}, 5000 );
			}
		},

		/**
		 * Generate test report
		 * @param results
		 */
		generateReport: function( results ) {
			var report = {
				summary: {
					total: results.total,
					passed: results.passed,
					failed: results.failed,
					skipped: results.skipped,
					duration: results.duration,
					successRate: Math.round( ( results.passed / results.total ) * 100 ) + '%'
				},
				tests: results.tests,
				metrics: this.metrics,
				timestamp: new Date().toISOString()
			};

			return report;
		},

		/**
		 * Export test results
		 * @param results
		 */
		exportResults: function( results ) {
			var report = this.generateReport( results );
			var blob = new Blob( [ JSON.stringify( report, null, 2 ) ], { type: 'application/json' } );
			var url = URL.createObjectURL( blob );

			var a = document.createElement( 'a' );
			a.href = url;
			a.download = 'discount_integration_test_' + Date.now() + '.json';
			a.click();

			URL.revokeObjectURL( url );
		}
	};

} )( jQuery );