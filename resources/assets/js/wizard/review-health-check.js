/**
 * Review Health Check
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/review-health-check.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

(function( $ ) {
	'use strict';

	/**
	 * Review Health Check Module
	 */
	var ReviewHealthCheck = {

		/**
		 * Initialize the health check
		 */
		init: function() {
			if ( ! $( '#wsscd-health-container' ).length ) {
				return;
			}

			// Disable navigation immediately on review step to prevent clicks during initial load
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
				window.WSSCD.Wizard.Navigation.setNavigationState( true );
			}

			this.loadHealthData();
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Listen for step shown event
			$( document ).on( 'wsscd:step:shown', function( event, stepName ) {
				if ( 'review' === stepName ) {
					self.loadHealthData();
				}
			} );

			// Launch option change
			$( 'input[name="launch_option"]' ).on( 'change', function() {
				var option = $( this ).val();
				var infoText = $( '.wsscd-launch-info-text' ).data( option );
				$( '.wsscd-launch-info-text' ).text( infoText );
			} );

			// Action button clicks - navigate to step
			$( document ).on( 'click', '.wsscd-issue-action', function( e ) {
				e.preventDefault();
				var targetStep = $( this ).data( 'step' );

				if ( targetStep && window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
					window.WSSCD.Wizard.Navigation.navigateToStep( targetStep );
				}
			} );

			// Regenerate with AI (review step when campaign was prefilled by Cycle AI)
			$( document ).on( 'click', '#wsscd-regenerate-with-ai-btn', function( e ) {
				e.preventDefault();
				var $btn = $( this );
				if ( $btn.prop( 'disabled' ) || ! window.WSSCD || ! window.WSSCD.Ajax || ! window.WSSCD.Ajax.post ) {
					return;
				}
				var raw = $btn.data( 'userBrief' );
				var userBrief = ( typeof raw === 'string' && raw ) ? raw.trim() : '';
				var payload = userBrief ? { userBrief: userBrief } : {};
				$btn.prop( 'disabled', true ).text( $btn.data( 'loading-text' ) || 'Generating new suggestion…' );
				window.WSSCD.Ajax.post( 'wsscd_cycle_ai_create_full_campaign', payload, { timeout: 90000 } )
					.then( function( response ) {
						var url = response && response.redirectUrl ? response.redirectUrl : '';
						if ( url ) {
							window.location.href = url;
						} else {
							$btn.prop( 'disabled', false ).text( $btn.data( 'default-text' ) || 'Regenerate with AI' );
							if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
								window.WSSCD.Shared.NotificationService.error( 'No redirect URL received.' );
							}
						}
					} )
					.catch( function( error ) {
						$btn.prop( 'disabled', false ).text( $btn.data( 'default-text' ) || 'Regenerate with AI' );
						var msg = error && error.message ? error.message : 'Could not generate a new suggestion. Please try again.';
						if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
							window.WSSCD.Shared.NotificationService.error( msg );
						}
					} );
			} );
		},

		/**
		 * Load health check data via AJAX
		 */
		loadHealthData: function() {
			var self = this;

			$( '#wsscd-health-loading' ).show();
			$( '#wsscd-health-container' ).hide();

			// Disable navigation during AJAX + animation using existing navigation system
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
				window.WSSCD.Wizard.Navigation.setNavigationState( true );
			}

			// Use AjaxService to prevent rate limiting
			WSSCD.Ajax.post( 'campaign_health', {} ).then( function( response ) {
				// AjaxService resolves with response.data directly, so check for valid health data
				if ( response && 'object' === typeof response && response.score !== undefined ) {
					self.renderHealthCheck( response );
				} else {
					var errorMsg = 'Failed to load health check';
					if ( response.error && response.error.length > 0 ) {
						console.error( '[Health Check] Error details:', response.error );
						errorMsg = response.error[0].message || response.error[0];
					} else if ( response.message ) {
						errorMsg = response.message;
					}
					console.error( '[Health Check] Error:', errorMsg, response );
					self.showError( errorMsg );
				}
			} ).catch( function( error ) {
				console.error( '[Health Check] AJAX Error:', error );
				self.showError( 'Failed to load health check: ' + ( error.message || error ) );
			} ).always( function() {
				$( '#wsscd-health-loading' ).hide();
				$( '#wsscd-health-container' ).show();

				// Re-enable navigation after health score animation completes (300ms CSS transition)
				setTimeout( function() {
					if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
						window.WSSCD.Wizard.Navigation.setNavigationState( false );
					}
				}, 300 );
			} );
		},

		/**
		 * Render health check data
		 *
		 * @param {Object} data Health check data
		 */
		renderHealthCheck: function( data ) {
			this.renderHealthScore( data );
			this.renderHealthFactors( data.criticalIssues || [] );
			this.renderRecommendations( data.recommendations || [] );
			this.renderConflictPreview( data.conflictPreview || {} );
			this.renderImpact( data.coverage || {} );
			this.renderStockRisk( data.stockRisk || {} );

			this.updateLaunchButton( data.isReady );
		},

		/**
		 * Render health score
		 *
		 * @param {Object} data Health data
		 */
		renderHealthScore: function( data ) {
			var score = data.score || 0;
			var percentage = data.percentage || 0;
			var status = data.status || 'poor';

			var hasCritical = false;
			if ( data.recommendations && data.recommendations.length > 0 ) {
				for ( var i = 0; i < data.recommendations.length; i++ ) {
					if ( 'critical' === data.recommendations[i].priority ) {
						hasCritical = true;
						break;
					}
				}
			}

			// Determine display status (critical overrides score-based status)
			var displayStatus = hasCritical ? 'critical' : status;

			$( '.wsscd-score-number' )
				.text( score )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			var subtitle = this.getStatusText( status, data.isReady, hasCritical );
			$( '.wsscd-health-score-subtitle' )
				.text( subtitle )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			$( '.wsscd-health-score-card' )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			$( '.wsscd-health-score-fill' )
				.css( 'width', percentage + '%' )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );
		},

		/**
		 * Get status text
		 *
		 * @param {string} status Health status
		 * @param {boolean} isReady Is campaign ready to launch
		 * @param {boolean} hasCritical Has critical priority recommendations
		 * @return {string} Status text
		 */
		getStatusText: function( status, isReady, hasCritical ) {
			if ( ! isReady ) {
			return window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.getValidationMessage ? window.WSSCD.Utils.getValidationMessage( 'navigation.critical_issues', 'Critical issues must be fixed before launch' ) : 'Critical issues must be fixed before launch';
			}

			// If there are CRITICAL priority recommendations, override status message
			if ( hasCritical ) {
				return 'Review critical warnings before launching';
			}

			switch ( status ) {
				case 'excellent':
					return 'Ready to launch - Excellent configuration';
				case 'good':
					return 'Ready to launch - Good configuration';
				case 'fair':
					return 'Ready to launch - Consider reviewing recommendations';
				case 'poor':
					return 'Can launch but has recommendations to review';
				default:
					return 'Campaign health needs attention';
			}
		},

		/**
		 * Render health factors (critical issues)
		 *
		 * @param {Array} criticalIssues Critical issues array
		 */
		renderHealthFactors: function( criticalIssues ) {
			var $container = $( '#wsscd-health-factors' );

			if ( criticalIssues && criticalIssues.length > 0 ) {
				this.renderCriticalIssues( criticalIssues );
				$container.show();
			} else {
				$container.hide();
			}
		},

		/**
		 * Render critical issues
		 *
		 * @param {Array} issues Critical issues array
		 */
		renderCriticalIssues: function( issues ) {
			var $section = $( '#wsscd-critical-issues' );

			if ( ! issues || 0 === issues.length ) {
				$section.hide();
				return;
			}

			var $content = $section.find( '.wsscd-issues-content' );
			$content.empty();

			$section.find( '.wsscd-issues-count' ).text( issues.length );

			for ( var i = 0; i < issues.length; i++ ) {
				var issue = issues[i];
				var $item = $( '<div class="wsscd-issue-item"></div>' );

				// Use message as title (user-friendly), not code (technical)
				var title = this.escapeHtml( issue.message || 'Issue' );
				if ( issue.severity ) {
					title += this.addSeverityBadge( issue.severity );
				}

				$item.append( '<div class="wsscd-issue-title">' + title + '</div>' );

				if ( issue.step ) {
					var $actions = $( '<div class="wsscd-issue-actions"></div>' );
					$actions.append( '<button class="wsscd-issue-action" data-step="' + issue.step + '">Go to ' + this.ucfirst( issue.step ) + ' Step</button>' );
					$item.append( $actions );
				}

				$content.append( $item );
			}

			$section.show();
		},

		/**
		 * Add severity badge
		 *
		 * @param {string} severity Severity level
		 * @return {string} Badge HTML
		 */
		addSeverityBadge: function( severity ) {
			if ( ! severity ) {
				return '';
			}

			var labels = {
				'critical': 'CRITICAL',
				'high': 'HIGH',
				'medium': 'MEDIUM',
				'low': 'LOW'
			};

			var healthModifiers = {
				'critical': 'alert',
				'high': 'alert',
				'medium': 'warning',
				'low': 'info'
			};

			var label = labels[severity] || severity.toUpperCase();
			var modifier = healthModifiers[severity] || 'info';
			return '<span class="wsscd-badge-health--' + modifier + '">' + label + '</span>';
		},

		/**
		 * Render impact analysis
		 *
		 * @param {Object} coverage Coverage data
		 */
		renderImpact: function( coverage ) {
			// Backend automatically converts snake_case to camelCase via WSSCD_AJAX_Response
			var productsMatched = coverage.productsMatched || 0;
			var productsDiscounted = coverage.productsDiscounted || 0;
			var coveragePercentage = coverage.coveragePercentage || 0;
			var totalStoreProducts = coverage.totalStoreProducts || productsMatched;

			$( '[data-metric="products_matched"]' ).text( productsMatched );
			$( '[data-metric="products_discounted"]' ).text( productsDiscounted );
			$( '[data-metric="coverage_percentage"]' ).text( coveragePercentage + '%' );

			this.addProgressBar( 'products_matched', productsMatched, totalStoreProducts, 'of ' + totalStoreProducts + ' in store' );
			this.addProgressBar( 'products_discounted', productsDiscounted, productsMatched, 'of ' + productsMatched + ' matched' );
			this.addProgressBar( 'coverage_percentage', coveragePercentage, 100, '' );

			// Render categorized exclusions
			if ( coverage.exclusions && coverage.exclusions.length > 0 ) {
				var $list = $( '.wsscd-exclusions-list' );
				$list.empty();

				var totalExcluded = 0;
				for ( var i = 0; i < coverage.exclusions.length; i++ ) {
					var exclusion = coverage.exclusions[i];
					totalExcluded += ( exclusion.count || 0 );

					var $item = $( '<div class="wsscd-exclusion-item"></div>' );

					var iconName = this.getExclusionIcon( exclusion.reason );
					var label = $( '<span class="wsscd-exclusion-label"></span>' );
					if ( iconName ) {
						var iconHtml = WSSCD.IconHelper ? WSSCD.IconHelper.get( iconName, { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-' + iconName + '"></span>';
						label.append( iconHtml );
					}
					label.append( this.escapeHtml( exclusion.label || '' ) );

					var count = $( '<span class="wsscd-exclusion-count">' + ( exclusion.count || 0 ) + ' products</span>' );

					$item.append( label );
					$item.append( count );
					$list.append( $item );
				}

				$( '#wsscd-exclusions h4' ).html( 'Excluded Products (' + totalExcluded + ')' );
				$( '#wsscd-exclusions' ).show();
			} else {
				$( '#wsscd-exclusions' ).hide();
			}
		},

		/**
		 * Add progress bar to metric
		 *
		 * @param {string} metric Metric name
		 * @param {number} value Current value
		 * @param {number} max Maximum value
		 * @param {string} detail Detail text
		 */
		addProgressBar: function( metric, value, max, detail ) {
			var $container = $( '[data-metric="' + metric + '"]' ).closest( '.wsscd-impact-item' );

			$container.find( '.wsscd-progress-bar-container' ).remove();
			$container.find( '.wsscd-impact-detail' ).remove();

			// Calculate percentage
			var percentage = max > 0 ? Math.round( ( value / max ) * 100 ) : 0;

			// Determine color class based on percentage
			var colorClass = '';
			if ( metric === 'coverage_percentage' ) {
				if ( percentage >= 80 ) {
					colorClass = 'excellent';
				} else if ( percentage >= 60 ) {
					colorClass = 'good';
				} else if ( percentage >= 40 ) {
					colorClass = 'fair';
				} else {
					colorClass = 'poor';
				}
			}

			var $progressContainer = $( '<div class="wsscd-progress-bar-container"></div>' );
			var $progressBar = $( '<div class="wsscd-progress-bar ' + colorClass + '"></div>' );
			$progressBar.css( 'width', percentage + '%' );
			$progressContainer.append( $progressBar );
			$container.append( $progressContainer );

			if ( detail ) {
				var $detail = $( '<div class="wsscd-impact-detail">' + detail + '</div>' );
				$container.append( $detail );
			}
		},

		/**
		 * Get icon for exclusion reason
		 *
		 * @param {string} reason Exclusion reason
		 * @return {string} Icon name
		 */
		getExclusionIcon: function( reason ) {
			var icons = {
				'on_sale': 'tag',
				'conflicts': 'warning',
				'out_of_stock': 'minus',
				'low_stock': 'chart-line',
				'general': 'info'
			};

			return icons[reason] || 'info';
		},

		/**
		 * Render stock risk warnings
		 *
		 * @param {Object} stockRisk Stock risk data
		 */
		renderStockRisk: function( stockRisk ) {
			var $section = $( '#wsscd-stock-risk' );
			if ( 0 === $section.length ) {
				$section = $( '<div id="wsscd-stock-risk" class="wsscd-stock-risk-section" style="display: none;"></div>' );
				$section.insertAfter( '.wsscd-impact-analysis' );
			}

			// If no risk, hide section
			if ( ! stockRisk || ! stockRisk.hasRisk ) {
				$section.hide();
				return;
			}

			// Build stock risk HTML
			var warningIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 20 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
			var html = '<div class="wsscd-section-header">';
			html += warningIcon;
			html += '<h3>Stock Depletion Risk</h3>';
			html += '<p class="wsscd-section-desc">Products that may sell out during this campaign</p>';
			html += '</div>';

			html += '<div class="wsscd-stock-risk-summary">';
			if ( stockRisk.highRiskCount > 0 ) {
				html += '<div class="wsscd-risk-stat high">';
				html += '<span class="wsscd-risk-count">' + stockRisk.highRiskCount + '</span>';
				html += '<span class="wsscd-risk-label">High Risk</span>';
				html += '</div>';
			}
			if ( stockRisk.mediumRiskCount > 0 ) {
				html += '<div class="wsscd-risk-stat medium">';
				html += '<span class="wsscd-risk-count">' + stockRisk.mediumRiskCount + '</span>';
				html += '<span class="wsscd-risk-label">Medium Risk</span>';
				html += '</div>';
			}
			html += '</div>';

			if ( stockRisk.products && stockRisk.products.length > 0 ) {
				html += '<div class="wsscd-stock-risk-products">';
				for ( var i = 0; i < stockRisk.products.length; i++ ) {
					var product = stockRisk.products[i];
					var riskClass = product.riskLevel === 'high' ? 'wsscd-risk-high' : 'wsscd-risk-medium';
					var riskIconName = product.riskLevel === 'high' ? 'warning' : 'info';
					var riskIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( riskIconName, { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-' + riskIconName + '"></span>';

					html += '<div class="wsscd-stock-risk-item ' + riskClass + '">';
					html += '<div class="wsscd-risk-product-name">';
					html += riskIcon;
					html += this.escapeHtml( product.name );
					html += '</div>';
					html += '<div class="wsscd-risk-details">';
					html += '<span class="wsscd-risk-stock">Stock: ' + product.stock + '</span>';
					html += '<span class="wsscd-risk-demand">Est. Demand: ' + product.estimatedDemand + '</span>';
					html += '</div>';
					html += '</div>';
				}
				html += '</div>';

				var lightbulbIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( 'lightbulb', { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-lightbulb"></span>';
				html += '<div class="wsscd-stock-risk-note">';
				html += lightbulbIcon;
				html += '<p>Consider restocking these items before launching your campaign to avoid customer disappointment.</p>';
				html += '</div>';
			}

			$section.html( html );
			$section.show();
		},

		/**
		 * Render recommendations
		 *
		 * @param {Array} recommendations Recommendations array
		 */
		renderRecommendations: function( recommendations ) {
			var $section = $( '#wsscd-recommendations' );

			if ( ! recommendations || 0 === recommendations.length ) {
				$section.hide();
				return;
			}

			// All recommendations from PHP have category property (modern format)
			this.renderEnhancedRecommendations( recommendations );
		},

		/**
		 * Render recommendations with categories and all capabilities
		 *
		 * @param {Array} recommendations Enhanced recommendations array
		 */
		renderEnhancedRecommendations: function( recommendations ) {
			var $section = $( '#wsscd-recommendations' );
			var $container = $section.find( '.wsscd-recommendations-categories' );

			$container.empty();

			var dismissedIds = this.getDismissedRecommendations();
			var appliedIds = this.getAppliedRecommendations();

			// Filter out dismissed recommendations
			var activeRecommendations = [];
			for ( var i = 0; i < recommendations.length; i++ ) {
				if ( dismissedIds.indexOf( recommendations[i].id ) === -1 ) {
					activeRecommendations.push( recommendations[i] );
				}
			}

			this.renderRecommendationCounter( activeRecommendations.length, appliedIds.length, dismissedIds.length );

			// Group by category
			var categories = {
				'discount': { title: 'Discount Strategy', icon: 'chart-line', items: [] },
				'timing': { title: 'Timing Strategy', icon: 'clock', items: [] },
				'products': { title: 'Product Selection', icon: 'products', items: [] },
				'management': { title: 'Campaign Management', icon: 'admin-settings', items: [] }
			};

			for ( var i = 0; i < activeRecommendations.length; i++ ) {
				var rec = activeRecommendations[i];
				var category = rec.category || 'management';

				// If category doesn't exist in predefined list, default to management
				if ( ! categories[category] ) {
					category = 'management';
				}
				categories[category].items.push( rec );
			}

			// Render each category
			for ( var cat in categories ) {
				if ( ! categories.hasOwnProperty( cat ) ) {
					continue;
				}

				var categoryData = categories[cat];
				if ( 0 === categoryData.items.length ) {
					continue;
				}

				var $category = $( '<div class="wsscd-recommendation-category ' + cat + '"></div>' );

				var iconHtml = WSSCD.IconHelper ? WSSCD.IconHelper.get( categoryData.icon, { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-' + categoryData.icon + '"></span>';
				var $title = $( '<div class="wsscd-recommendation-category-title"></div>' );
				$title.append( iconHtml );
				$title.append( '<span>' + categoryData.title + ' (' + categoryData.items.length + ')</span>' );
				$category.append( $title );

				var $items = $( '<div class="wsscd-recommendation-items"></div>' );

				for ( var j = 0; j < categoryData.items.length; j++ ) {
					var item = categoryData.items[j];
					$items.append( this.renderRecommendationItem( item ) );
				}

				$category.append( $items );
				$container.append( $category );
			}

			$section.show();
		},

		/**
		 * Render recommendation counter
		 *
		 * @param {number} active    Active recommendations count
		 * @param {number} applied   Applied recommendations count
		 * @param {number} dismissed Dismissed recommendations count
		 */
		renderRecommendationCounter: function( active, applied, dismissed ) {
			var $section = $( '#wsscd-recommendations' );
			var $existing = $section.find( '.wsscd-recommendation-counter' );

			$existing.remove();

			if ( 0 === active && 0 === applied && 0 === dismissed ) {
				return;
			}

			var $counter = $( '<div class="wsscd-recommendation-counter"></div>' );

			if ( active > 0 ) {
				$counter.append( '<span class="wsscd-counter-active">' + active + ' Active</span>' );
			}

			if ( applied > 0 ) {
				$counter.append( '<span class="wsscd-counter-applied">✅ ' + applied + ' Applied</span>' );
			}

			if ( dismissed > 0 ) {
				var $dismissedSpan = $( '<span class="wsscd-counter-dismissed">⏭️ ' + dismissed + ' Dismissed</span>' );
				$dismissedSpan.on( 'click', function() {
					this.showDismissedRecommendations();
				}.bind( this ) );
				$counter.append( $dismissedSpan );
			}

			$section.find( '.wsscd-section-header' ).after( $counter );
		},

		/**
		 * Render single recommendation item with all enhancements
		 *
		 * @param {Object} item Recommendation item
		 * @return {jQuery} Rendered item element
		 */
		renderRecommendationItem: function( item ) {
			var $item = $( '<div class="wsscd-recommendation-item" data-id="' + item.id + '"></div>' );

			if ( item.priority ) {
				$item.addClass( 'priority-' + item.priority );
			}

			// Header wrapper (message + priority badge)
			var $header = $( '<div class="wsscd-recommendation-header"></div>' );

			// Message wrapper
			var $message = $( '<div class="wsscd-recommendation-message"></div>' );
			$message.append( '<span class="wsscd-recommendation-text">' + this.escapeHtml( item.message ) + '</span>' );

			// Impact score
			if ( item.impact ) {
				$message.append( '<span class="wsscd-recommendation-impact">💡 ' + this.escapeHtml( item.impact ) + '</span>' );
			}

			$header.append( $message );

			// Priority badge
			if ( item.priority ) {
				$header.append( this.addPriorityBadge( item.priority ) );
			}

			$item.append( $header );

			// Expandable explanation
			if ( item.explanation ) {
				var $explanationToggle = $( '<button type="button" class="wsscd-explanation-toggle">Why this matters ▼</button>' );
				var $explanationContent = $( '<div class="wsscd-explanation-content" style="display:none;">' + this.escapeHtml( item.explanation ) + '</div>' );

				$explanationToggle.on( 'click', function() {
					var $content = $( this ).next( '.wsscd-explanation-content' );
					if ( $content.is( ':visible' ) ) {
						$content.slideUp();
						$( this ).text( 'Why this matters ▼' );
					} else {
						$content.slideDown();
						$( this ).text( 'Why this matters ▲' );
					}
				} );

				$item.append( $explanationToggle );
				$item.append( $explanationContent );
			}

			// Action buttons container
			var $actions = $( '<div class="wsscd-recommendation-actions"></div>' );

			// Apply button (if action available)
			if ( item.action && item.action.type ) {
				var $applyBtn = this.renderApplyButton( item );
				$actions.append( $applyBtn );
			}

			// Dismiss button
			var $dismissBtn = $( '<button type="button" class="button wsscd-dismiss-btn">Dismiss</button>' );
			$dismissBtn.on( 'click', function() {
				this.dismissRecommendation( item.id );
			}.bind( this ) );
			$actions.append( $dismissBtn );

			// Step navigation link (if step available)
			if ( item.step ) {
				var stepNames = {
					'basic': 'Basic Settings',
					'discount': 'Discount Settings',
					'products': 'Product Selection',
					'schedule': 'Schedule'
				};
				var stepName = stepNames[item.step] || item.step;
				var $navLink = $( '<a href="#" class="wsscd-step-link">Go to ' + stepName + ' →</a>' );
				$navLink.on( 'click', function( e ) {
					e.preventDefault();
					this.navigateToStep( item.step );
				}.bind( this ) );
				$actions.append( $navLink );
			}

			$item.append( $actions );

			return $item;
		},

		/**
		 * Render apply button for recommendation
		 *
		 * @param {Object} item Recommendation item
		 * @return {jQuery} Apply button element
		 */
		renderApplyButton: function( item ) {
			var buttonLabels = {
				'change_discount_type': 'Apply This Discount Type',
				'update_discount_value': 'Update Discount Value',
				'set_end_date': 'Set Duration',
				'change_selection_type': 'Change Selection Type'
			};

			var label = buttonLabels[item.action.type] || 'Apply';

			var $btn = $( '<button type="button" class="button button-primary wsscd-apply-btn">' + label + '</button>' );

			$btn.on( 'click', function() {
				this.applyRecommendation( item );
			}.bind( this ) );

			return $btn;
		},

		/**
		 * Apply recommendation action
		 *
		 * @param {Object} item Recommendation item
		 */
		applyRecommendation: function( item ) {
			if ( ! item.action || ! item.action.type ) {
				return;
			}

			var $item = $( '[data-id="' + item.id + '"]' );
			var $btn = $item.find( '.wsscd-apply-btn' );
			var originalBtnText = $btn.text();

			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.showButton( $btn, 'Applying...' );
			}

			// Send AJAX request (via AjaxService to prevent rate limiting)
			WSSCD.Ajax.post( 'apply_recommendation', {
				recommendationId: item.id,
				actionType: item.action.type,
				actionData: item.action.data
			} ).then( function( response ) {
					if ( response.success ) {
						// Track as applied
						this.markRecommendationApplied( item.id );

						var checkIcon = WSSCD.IconHelper ? WSSCD.IconHelper.check( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-check"></span>';
						$btn.html( checkIcon + ' Applied!' ).css( 'background', '#00a32a' );

						var successMessage = this.getApplySuccessMessage( item, response );
						if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) { WSSCD.Shared.NotificationService.success( successMessage ); }

						$item.css( {
							'border-left': '4px solid #00a32a',
							'background': '#f0f6fc'
						} );

						// Navigate to relevant step after brief pause
						var targetStep = item.step || 'discount';
						setTimeout( function() {
							// Fade out the recommendation
							$item.fadeOut( 400, function() {
								$( this ).remove();
								this.updateRecommendationCounter();
							}.bind( this ) );

							// Navigate to step so user can see the changes
							setTimeout( function() {
								this.navigateToStep( targetStep );
							}.bind( this ), 200 );

							// Reload health check after navigation
							setTimeout( function() {
								this.loadHealthCheck();
							}.bind( this ), 1500 );
						}.bind( this ), 800 );

					} else {
						var warningIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
						$btn.html( warningIcon + ' Failed' )
							.css( 'background', '#d63638' );

						var errorMessage = response.data && response.data.message
							? response.data.message
							: ( response.message || 'Failed to apply recommendation' );

						if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) { WSSCD.Shared.NotificationService.error( errorMessage ); }

						// Restore button after 2 seconds
						setTimeout( function() {
							$btn.html( originalBtnText ).css( 'background', '' ).prop( 'disabled', false );
						}, 2000 );
					}
				}.bind( this ) ).catch( function( error ) {
					var errorIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
					$btn.html( errorIcon + ' Error' )
						.css( 'background', '#d63638' );

					var errorMessage = 'Error applying recommendation';
					if ( error.message ) {
						errorMessage += ': ' + error.message;
					}

					if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) { WSSCD.Shared.NotificationService.error( errorMessage ); }

					// Restore button after 2 seconds
					setTimeout( function() {
						$btn.html( originalBtnText ).css( 'background', '' ).prop( 'disabled', false );
					}, 2000 );
				}.bind( this ) );
		},

		/**
		 * Get descriptive success message for applied recommendation
		 *
		 * @param {Object} item Recommendation item
		 * @param {Object} response AJAX response
		 * @return {string} Success message
		 */
		getApplySuccessMessage: function( item, response ) {
			var actionType = item.action.type;
			var data = response.data || {};

			switch ( actionType ) {
				case 'change_discount_type':
					var discountType = data.discountType;
					var message = 'Discount type changed to ' + discountType;

					if ( 'percentage' === discountType && data.discountValue ) {
						message += ' (' + data.discountValue + '%)';
					} else if ( 'fixed' === discountType && data.discountValue ) {
						message += ' (' + data.discountValue + ')';
					} else if ( 'tiered' === discountType && data.tiers ) {
						message += ' with ' + data.tiers.length + ' tiers';
					} else if ( 'bogo' === discountType ) {
						message += ' (Buy X Get Y)';
					}

					return message + '. Navigating to discount step...';

				case 'update_discount_value':
					var value = data.discountValue;
					return 'Discount value updated to ' + value + '%. Navigating to discount step...';

				case 'set_end_date':
					var duration = item.action.data.durationDays || '14';
					return 'Campaign duration set to ' + duration + ' days. Navigating to schedule...';

				case 'change_selection_type':
					var selectionType = data.productSelectionType;
					return 'Product selection changed to ' + selectionType + '. Navigating to products...';

				default:
					return 'Recommendation applied successfully! Navigating to ' + ( item.step || 'settings' ) + '...';
			}
		},

		/**
		 * Dismiss recommendation
		 *
		 * @param {string} id Recommendation ID
		 */
		dismissRecommendation: function( id ) {
			this.markRecommendationDismissed( id );

			$( '[data-id="' + id + '"]' ).fadeOut( function() {
				$( this ).remove();
				this.updateRecommendationCounter();
			}.bind( this ) );

			if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) { WSSCD.Shared.NotificationService.info( 'Recommendation dismissed' ); }
		},

		/**
		 * Navigate to wizard step
		 *
		 * @param {string} step Step name
		 */
		navigateToStep: function( step ) {
			// Navigate using modern wizard navigation API
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.Navigation ) {
				window.WSSCD.Wizard.Navigation.navigateToStep( step );
			} else if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.info( 'Please navigate to the ' + step + ' step to make this change' );
			}
		},

		/**
		 * Get dismissed recommendations from localStorage
		 *
		 * @return {Array} Dismissed recommendation IDs
		 */
		getDismissedRecommendations: function() {
			var campaignId = this._getCampaignId();
			var key = 'wsscd_dismissed_' + campaignId;
			var stored = localStorage.getItem( key );
			return stored ? JSON.parse( stored ) : [];
		},

		/**
		 * Get applied recommendations from localStorage
		 *
		 * @return {Array} Applied recommendation IDs
		 */
		getAppliedRecommendations: function() {
			var campaignId = this._getCampaignId();
			var key = 'wsscd_applied_' + campaignId;
			var stored = localStorage.getItem( key );
			return stored ? JSON.parse( stored ) : [];
		},

		/**
		 * Mark recommendation as dismissed
		 *
		 * @param {string} id Recommendation ID
		 */
		markRecommendationDismissed: function( id ) {
			var dismissed = this.getDismissedRecommendations();
			if ( dismissed.indexOf( id ) === -1 ) {
				dismissed.push( id );
				var campaignId = this._getCampaignId();
				localStorage.setItem( 'wsscd_dismissed_' + campaignId, JSON.stringify( dismissed ) );
			}
		},

		/**
		 * Mark recommendation as applied
		 *
		 * @param {string} id Recommendation ID
		 */
		markRecommendationApplied: function( id ) {
			var applied = this.getAppliedRecommendations();
			if ( applied.indexOf( id ) === -1 ) {
				applied.push( id );
				var campaignId = this._getCampaignId();
				localStorage.setItem( 'wsscd_applied_' + campaignId, JSON.stringify( applied ) );
			}
		},

		/**
		 * Update recommendation counter
		 */
		updateRecommendationCounter: function() {
			var $items = $( '.wsscd-recommendation-item:visible' );
			var active = $items.length;
			var applied = this.getAppliedRecommendations().length;
			var dismissed = this.getDismissedRecommendations().length;

			this.renderRecommendationCounter( active, applied, dismissed );
		},

		/**
		 * Show dismissed recommendations
		 */
		showDismissedRecommendations: function() {
		var dismissed = this.getDismissedRecommendationElements();

		if ( 0 === dismissed.length ) {
			if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.info( 'No dismissed recommendations found.' );
			}
			return;
		}

		var modalContent = this.createDismissedRecommendationsModal( dismissed );

		this.showModal( 'Dismissed Recommendations', modalContent );
		},

	/**
	 * Get dismissed recommendation DOM elements
	 *
	 * @return {Array} Array of dismissed recommendation DOM elements
	 */
	getDismissedRecommendationElements: function() {
		return $( '.wsscd-recommendation-item[data-dismissed="true"]' ).toArray();
	},


		/**
		 * Add priority badge
		 *
		 * @param {string} priority Priority level
		 * @return {string} Badge HTML
		 */
		addPriorityBadge: function( priority ) {
			if ( ! priority ) {
				return '';
			}

			var labels = {
				'high': 'HIGH',
				'medium': 'MEDIUM',
				'low': 'LOW'
			};

			var healthModifiers = {
				'high': 'alert',
				'medium': 'warning',
				'low': 'info'
			};

			var label = labels[priority] || priority.toUpperCase();
			var modifier = healthModifiers[priority] || 'info';
			return '<span class="wsscd-badge-health--' + modifier + '">' + label + '</span>';
		},

		/**
		 * Render conflict preview
		 *
		 * @param {Object} conflictPreview Conflict preview data
		 */
		renderConflictPreview: function( conflictPreview ) {
			var $section = $( '#wsscd-conflict-preview' );

			if ( ! conflictPreview || ! conflictPreview.hasConflicts ) {
				$section.hide();
				return;
			}

			var $list = $section.find( '.wsscd-conflicts-list' );
			$list.empty();

			if ( ! conflictPreview.conflicts || 0 === conflictPreview.conflicts.length ) {
				$section.hide();
				return;
			}

			for ( var i = 0; i < conflictPreview.conflicts.length; i++ ) {
				var conflict = conflictPreview.conflicts[i];

				var $item = $( '<div class="wsscd-conflict-item"></div>' );

				var $header = $( '<div class="wsscd-conflict-header"></div>' );
				$header.append( '<div class="wsscd-conflict-name">' + this.escapeHtml( conflict.campaignName ) + '</div>' );
				$header.append( '<div class="wsscd-conflict-priority">Priority: ' + conflict.priority + '</div>' );
				$item.append( $header );

				var $details = $( '<div class="wsscd-conflict-details"></div>' );
				$details.text( conflict.affectedProducts + ' products affected' );
				$item.append( $details );

				$list.append( $item );
			}

			$section.show();
		},

		/**
		 * Format date
		 *
		 * @param {string} dateString Date string to format
		 * @return {string} Formatted date
		 */
		formatDate: function( dateString ) {
			if ( ! dateString ) {
				return '';
			}

			var date = new Date( dateString );
			var options = { month: 'short', day: 'numeric', year: 'numeric' };
			return date.toLocaleDateString( 'en-US', options );
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function( message ) {
			var $container = $( '#wsscd-health-container' );
			$container.html( '<div class="notice notice-error"><p>' + this.escapeHtml( message ) + '</p></div>' );
			$container.show();
		},

		/**
		 * Update launch button state based on campaign readiness
		 *
		 * @param {boolean} isReady Whether campaign is ready to launch
		 */
		updateLaunchButton: function( isReady ) {
			// Find launch button (complete action button)
			var $launchButton = $( '.wsscd-nav-btn[data-action="complete"]' );

			if ( ! $launchButton.length ) {
				// Button not found (might not be on review step yet)
				return;
			}

			if ( false === isReady || ! isReady ) {
				// Disable launch button when critical issues exist
				$launchButton
					.addClass( 'wsscd-navigation-disabled' )
					.prop( 'disabled', true )
					.attr( 'aria-disabled', 'true' )
					.attr( 'title', 'Please fix all critical issues before launching' );

				// Visual feedback: make button appear disabled
				$launchButton.css( {
					'opacity': '0.5',
					'cursor': 'not-allowed',
					'pointer-events': 'none'
				} );
			} else {
				// Enable launch button when no critical issues
				$launchButton
					.removeClass( 'wsscd-navigation-disabled' )
					.prop( 'disabled', false )
					.attr( 'aria-disabled', 'false' )
					.removeAttr( 'title' );

				$launchButton.css( {
					'opacity': '',
					'cursor': '',
					'pointer-events': ''
				} );
			}
		},

		/**
		 * Get campaign ID for localStorage keys
		 * Uses URL parameter or defaults to 'new' for new campaigns
		 *
		 * @private
		 * @return {string|number} Campaign ID or 'new'
		 */
		_getCampaignId: function() {
			// Try URL parameter
			var urlParams = new URLSearchParams( window.location.search );
			var urlId = urlParams.get( 'id' );
			if ( urlId ) {
				return urlId;
			}

			// Try wsscdWizardData
			if ( window.wsscdWizardData && window.wsscdWizardData.campaignId ) {
				return window.wsscdWizardData.campaignId;
			}

			// Default to 'new' for new campaigns
			return 'new';
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String( text ).replace( /[&<>"']/g, function( m ) {
				return map[m];
			} );
		},

		/**
		 * Uppercase first character
		 *
		 * @param {string} str String to transform
		 * @return {string} Transformed string
		 */
		ucfirst: function( str ) {
			return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
		}
	};

	$( document ).ready( function() {
		ReviewHealthCheck.init();
	} );

	// Expose to global scope for debugging
	window.WSSCD_ReviewHealthCheck = ReviewHealthCheck;

})( jQuery );
