/**
 * Review Health Check
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/review-health-check.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
			if ( ! $( '#scd-health-container' ).length ) {
				return;
			}

			// Disable navigation immediately on review step to prevent clicks during initial load
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
				window.SCD.Wizard.Navigation.setNavigationState( true );
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
			$( document ).on( 'scd:step:shown', function( event, stepName ) {
				if ( 'review' === stepName ) {
					self.loadHealthData();
				}
			} );

			// Launch option change
			$( 'input[name="launch_option"]' ).on( 'change', function() {
				var option = $( this ).val();
				var infoText = $( '.scd-launch-info-text' ).data( option );
				$( '.scd-launch-info-text' ).text( infoText );
			} );

			// Action button clicks - navigate to step
			$( document ).on( 'click', '.scd-issue-action', function( e ) {
				e.preventDefault();
				var targetStep = $( this ).data( 'step' );

				if ( targetStep && window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
					window.SCD.Wizard.Navigation.navigateToStep( targetStep );
				}
			} );
		},

		/**
		 * Load health check data via AJAX
		 */
		loadHealthData: function() {
			var self = this;

			$( '#scd-health-loading' ).show();
			$( '#scd-health-container' ).hide();

			// Disable navigation during AJAX + animation using existing navigation system
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
				window.SCD.Wizard.Navigation.setNavigationState( true );
			}

			// Use AjaxService to prevent rate limiting
			SCD.Ajax.post( 'campaign_health', {} ).then( function( response ) {
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
				$( '#scd-health-loading' ).hide();
				$( '#scd-health-container' ).show();

				// Re-enable navigation after health score animation completes (300ms CSS transition)
				setTimeout( function() {
					if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
						window.SCD.Wizard.Navigation.setNavigationState( false );
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

			$( '.scd-score-number' )
				.text( score )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			var subtitle = this.getStatusText( status, data.isReady, hasCritical );
			$( '.scd-health-score-subtitle' )
				.text( subtitle )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			$( '.scd-health-score-card' )
				.removeClass( 'excellent good fair poor critical' )
				.addClass( displayStatus );

			$( '.scd-health-score-fill' )
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
			return window.SCD && window.SCD.Utils && window.SCD.Utils.getValidationMessage ? window.SCD.Utils.getValidationMessage( 'navigation.critical_issues', 'Critical issues must be fixed before launch' ) : 'Critical issues must be fixed before launch';
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
			var $container = $( '#scd-health-factors' );

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
			var $section = $( '#scd-critical-issues' );

			if ( ! issues || 0 === issues.length ) {
				$section.hide();
				return;
			}

			var $content = $section.find( '.scd-issues-content' );
			$content.empty();

			$section.find( '.scd-issues-count' ).text( issues.length );

			for ( var i = 0; i < issues.length; i++ ) {
				var issue = issues[i];
				var $item = $( '<div class="scd-issue-item"></div>' );

				// Use message as title (user-friendly), not code (technical)
				var title = this.escapeHtml( issue.message || 'Issue' );
				if ( issue.severity ) {
					title += this.addSeverityBadge( issue.severity );
				}

				$item.append( '<div class="scd-issue-title">' + title + '</div>' );

				if ( issue.step ) {
					var $actions = $( '<div class="scd-issue-actions"></div>' );
					$actions.append( '<button class="scd-issue-action" data-step="' + issue.step + '">Go to ' + this.ucfirst( issue.step ) + ' Step</button>' );
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
			return '<span class="scd-badge-health--' + modifier + '">' + label + '</span>';
		},

		/**
		 * Render impact analysis
		 *
		 * @param {Object} coverage Coverage data
		 */
		renderImpact: function( coverage ) {
			// Backend automatically converts snake_case to camelCase via SCD_AJAX_Response
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
				var $list = $( '.scd-exclusions-list' );
				$list.empty();

				var totalExcluded = 0;
				for ( var i = 0; i < coverage.exclusions.length; i++ ) {
					var exclusion = coverage.exclusions[i];
					totalExcluded += ( exclusion.count || 0 );

					var $item = $( '<div class="scd-exclusion-item"></div>' );

					var iconName = this.getExclusionIcon( exclusion.reason );
					var label = $( '<span class="scd-exclusion-label"></span>' );
					if ( iconName ) {
						var iconHtml = SCD.IconHelper ? SCD.IconHelper.get( iconName, { size: 16 } ) : '<span class="scd-icon scd-icon-' + iconName + '"></span>';
						label.append( iconHtml );
					}
					label.append( this.escapeHtml( exclusion.label || '' ) );

					var count = $( '<span class="scd-exclusion-count">' + ( exclusion.count || 0 ) + ' products</span>' );

					$item.append( label );
					$item.append( count );
					$list.append( $item );
				}

				$( '#scd-exclusions h4' ).html( 'Excluded Products (' + totalExcluded + ')' );
				$( '#scd-exclusions' ).show();
			} else {
				$( '#scd-exclusions' ).hide();
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
			var $container = $( '[data-metric="' + metric + '"]' ).closest( '.scd-impact-item' );

			$container.find( '.scd-progress-bar-container' ).remove();
			$container.find( '.scd-impact-detail' ).remove();

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

			var $progressContainer = $( '<div class="scd-progress-bar-container"></div>' );
			var $progressBar = $( '<div class="scd-progress-bar ' + colorClass + '"></div>' );
			$progressBar.css( 'width', percentage + '%' );
			$progressContainer.append( $progressBar );
			$container.append( $progressContainer );

			if ( detail ) {
				var $detail = $( '<div class="scd-impact-detail">' + detail + '</div>' );
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
			var $section = $( '#scd-stock-risk' );
			if ( 0 === $section.length ) {
				$section = $( '<div id="scd-stock-risk" class="scd-stock-risk-section" style="display: none;"></div>' );
				$section.insertAfter( '.scd-impact-analysis' );
			}

			// If no risk, hide section
			if ( ! stockRisk || ! stockRisk.hasRisk ) {
				$section.hide();
				return;
			}

			// Build stock risk HTML
			var warningIcon = SCD.IconHelper ? SCD.IconHelper.warning( { size: 20 } ) : '<span class="scd-icon scd-icon-warning"></span>';
			var html = '<div class="scd-section-header">';
			html += warningIcon;
			html += '<h3>Stock Depletion Risk</h3>';
			html += '<p class="scd-section-desc">Products that may sell out during this campaign</p>';
			html += '</div>';

			html += '<div class="scd-stock-risk-summary">';
			if ( stockRisk.highRiskCount > 0 ) {
				html += '<div class="scd-risk-stat high">';
				html += '<span class="scd-risk-count">' + stockRisk.highRiskCount + '</span>';
				html += '<span class="scd-risk-label">High Risk</span>';
				html += '</div>';
			}
			if ( stockRisk.mediumRiskCount > 0 ) {
				html += '<div class="scd-risk-stat medium">';
				html += '<span class="scd-risk-count">' + stockRisk.mediumRiskCount + '</span>';
				html += '<span class="scd-risk-label">Medium Risk</span>';
				html += '</div>';
			}
			html += '</div>';

			if ( stockRisk.products && stockRisk.products.length > 0 ) {
				html += '<div class="scd-stock-risk-products">';
				for ( var i = 0; i < stockRisk.products.length; i++ ) {
					var product = stockRisk.products[i];
					var riskClass = product.riskLevel === 'high' ? 'scd-risk-high' : 'scd-risk-medium';
					var riskIconName = product.riskLevel === 'high' ? 'warning' : 'info';
					var riskIcon = SCD.IconHelper ? SCD.IconHelper.get( riskIconName, { size: 16 } ) : '<span class="scd-icon scd-icon-' + riskIconName + '"></span>';

					html += '<div class="scd-stock-risk-item ' + riskClass + '">';
					html += '<div class="scd-risk-product-name">';
					html += riskIcon;
					html += this.escapeHtml( product.name );
					html += '</div>';
					html += '<div class="scd-risk-details">';
					html += '<span class="scd-risk-stock">Stock: ' + product.stock + '</span>';
					html += '<span class="scd-risk-demand">Est. Demand: ' + product.estimatedDemand + '</span>';
					html += '</div>';
					html += '</div>';
				}
				html += '</div>';

				var lightbulbIcon = SCD.IconHelper ? SCD.IconHelper.get( 'lightbulb', { size: 16 } ) : '<span class="scd-icon scd-icon-lightbulb"></span>';
				html += '<div class="scd-stock-risk-note">';
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
			var $section = $( '#scd-recommendations' );

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
			var $section = $( '#scd-recommendations' );
			var $container = $section.find( '.scd-recommendations-categories' );

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

				if ( categories[category] ) {
					categories[category].items.push( rec );
				}
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

				var $category = $( '<div class="scd-recommendation-category ' + cat + '"></div>' );

				var iconHtml = SCD.IconHelper ? SCD.IconHelper.get( categoryData.icon, { size: 16 } ) : '<span class="scd-icon scd-icon-' + categoryData.icon + '"></span>';
				var $title = $( '<div class="scd-recommendation-category-title"></div>' );
				$title.append( iconHtml );
				$title.append( '<span>' + categoryData.title + ' (' + categoryData.items.length + ')</span>' );
				$category.append( $title );

				var $items = $( '<div class="scd-recommendation-items"></div>' );

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
			var $section = $( '#scd-recommendations' );
			var $existing = $section.find( '.scd-recommendation-counter' );

			$existing.remove();

			if ( 0 === active && 0 === applied && 0 === dismissed ) {
				return;
			}

			var $counter = $( '<div class="scd-recommendation-counter"></div>' );

			if ( active > 0 ) {
				$counter.append( '<span class="scd-counter-active">' + active + ' Active</span>' );
			}

			if ( applied > 0 ) {
				$counter.append( '<span class="scd-counter-applied">✅ ' + applied + ' Applied</span>' );
			}

			if ( dismissed > 0 ) {
				var $dismissedSpan = $( '<span class="scd-counter-dismissed">⏭️ ' + dismissed + ' Dismissed</span>' );
				$dismissedSpan.on( 'click', function() {
					this.showDismissedRecommendations();
				}.bind( this ) );
				$counter.append( $dismissedSpan );
			}

			$section.find( '.scd-section-header' ).after( $counter );
		},

		/**
		 * Render single recommendation item with all enhancements
		 *
		 * @param {Object} item Recommendation item
		 * @return {jQuery} Rendered item element
		 */
		renderRecommendationItem: function( item ) {
			var $item = $( '<div class="scd-recommendation-item" data-id="' + item.id + '"></div>' );

			if ( item.priority ) {
				$item.addClass( 'priority-' + item.priority );
			}

			// Header wrapper (message + priority badge)
			var $header = $( '<div class="scd-recommendation-header"></div>' );

			// Message wrapper
			var $message = $( '<div class="scd-recommendation-message"></div>' );
			$message.append( '<span class="scd-recommendation-text">' + this.escapeHtml( item.message ) + '</span>' );

			// Impact score
			if ( item.impact ) {
				$message.append( '<span class="scd-recommendation-impact">💡 ' + this.escapeHtml( item.impact ) + '</span>' );
			}

			$header.append( $message );

			// Priority badge
			if ( item.priority ) {
				$header.append( this.addPriorityBadge( item.priority ) );
			}

			$item.append( $header );

			// Expandable explanation
			if ( item.explanation ) {
				var $explanationToggle = $( '<button type="button" class="scd-explanation-toggle">Why this matters ▼</button>' );
				var $explanationContent = $( '<div class="scd-explanation-content" style="display:none;">' + this.escapeHtml( item.explanation ) + '</div>' );

				$explanationToggle.on( 'click', function() {
					var $content = $( this ).next( '.scd-explanation-content' );
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
			var $actions = $( '<div class="scd-recommendation-actions"></div>' );

			// Apply button (if action available)
			if ( item.action && item.action.type ) {
				var $applyBtn = this.renderApplyButton( item );
				$actions.append( $applyBtn );
			}

			// Dismiss button
			var $dismissBtn = $( '<button type="button" class="button scd-dismiss-btn">Dismiss</button>' );
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
				var $navLink = $( '<a href="#" class="scd-step-link">Go to ' + stepName + ' →</a>' );
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

			var $btn = $( '<button type="button" class="button button-primary scd-apply-btn">' + label + '</button>' );

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
			var $btn = $item.find( '.scd-apply-btn' );
			var originalBtnText = $btn.text();

			if ( window.SCD && window.SCD.LoaderUtil ) {
				SCD.LoaderUtil.showButton( $btn, 'Applying...' );
			}

			// Send AJAX request (via AjaxService to prevent rate limiting)
			SCD.Ajax.post( 'apply_recommendation', {
				recommendationId: item.id,
				actionType: item.action.type,
				actionData: item.action.data
			} ).then( function( response ) {
					if ( response.success ) {
						// Track as applied
						this.markRecommendationApplied( item.id );

						var checkIcon = SCD.IconHelper ? SCD.IconHelper.check( { size: 16 } ) : '<span class="scd-icon scd-icon-check"></span>';
						$btn.html( checkIcon + ' Applied!' ).css( 'background', '#00a32a' );

						var successMessage = this.getApplySuccessMessage( item, response );
						if ( SCD.Shared && SCD.Shared.NotificationService ) { SCD.Shared.NotificationService.success( successMessage ); }

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
						var warningIcon = SCD.IconHelper ? SCD.IconHelper.warning( { size: 16 } ) : '<span class="scd-icon scd-icon-warning"></span>';
						$btn.html( warningIcon + ' Failed' )
							.css( 'background', '#d63638' );

						var errorMessage = response.data && response.data.message
							? response.data.message
							: ( response.message || 'Failed to apply recommendation' );

						if ( SCD.Shared && SCD.Shared.NotificationService ) { SCD.Shared.NotificationService.error( errorMessage ); }

						// Restore button after 2 seconds
						setTimeout( function() {
							$btn.html( originalBtnText ).css( 'background', '' ).prop( 'disabled', false );
						}, 2000 );
					}
				}.bind( this ) ).catch( function( error ) {
					var errorIcon = SCD.IconHelper ? SCD.IconHelper.warning( { size: 16 } ) : '<span class="scd-icon scd-icon-warning"></span>';
					$btn.html( errorIcon + ' Error' )
						.css( 'background', '#d63638' );

					var errorMessage = 'Error applying recommendation';
					if ( error.message ) {
						errorMessage += ': ' + error.message;
					}

					if ( SCD.Shared && SCD.Shared.NotificationService ) { SCD.Shared.NotificationService.error( errorMessage ); }

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

			if ( SCD.Shared && SCD.Shared.NotificationService ) { SCD.Shared.NotificationService.info( 'Recommendation dismissed' ); }
		},

		/**
		 * Navigate to wizard step
		 *
		 * @param {string} step Step name
		 */
		navigateToStep: function( step ) {
			// Navigate using modern wizard navigation API
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.Navigation ) {
				window.SCD.Wizard.Navigation.navigateToStep( step );
			} else if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.info( 'Please navigate to the ' + step + ' step to make this change' );
			}
		},

		/**
		 * Get dismissed recommendations from localStorage
		 *
		 * @return {Array} Dismissed recommendation IDs
		 */
		getDismissedRecommendations: function() {
			var campaignId = this._getCampaignId();
			var key = 'scd_dismissed_' + campaignId;
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
			var key = 'scd_applied_' + campaignId;
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
				localStorage.setItem( 'scd_dismissed_' + campaignId, JSON.stringify( dismissed ) );
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
				localStorage.setItem( 'scd_applied_' + campaignId, JSON.stringify( applied ) );
			}
		},

		/**
		 * Update recommendation counter
		 */
		updateRecommendationCounter: function() {
			var $items = $( '.scd-recommendation-item:visible' );
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
			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.info( 'No dismissed recommendations found.' );
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
		return $( '.scd-recommendation-item[data-dismissed="true"]' ).toArray();
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
			return '<span class="scd-badge-health--' + modifier + '">' + label + '</span>';
		},

		/**
		 * Render conflict preview
		 *
		 * @param {Object} conflictPreview Conflict preview data
		 */
		renderConflictPreview: function( conflictPreview ) {
			var $section = $( '#scd-conflict-preview' );

			if ( ! conflictPreview || ! conflictPreview.hasConflicts ) {
				$section.hide();
				return;
			}

			var $list = $section.find( '.scd-conflicts-list' );
			$list.empty();

			if ( ! conflictPreview.conflicts || 0 === conflictPreview.conflicts.length ) {
				$section.hide();
				return;
			}

			for ( var i = 0; i < conflictPreview.conflicts.length; i++ ) {
				var conflict = conflictPreview.conflicts[i];

				var $item = $( '<div class="scd-conflict-item"></div>' );

				var $header = $( '<div class="scd-conflict-header"></div>' );
				$header.append( '<div class="scd-conflict-name">' + this.escapeHtml( conflict.campaignName ) + '</div>' );
				$header.append( '<div class="scd-conflict-priority">Priority: ' + conflict.priority + '</div>' );
				$item.append( $header );

				var $details = $( '<div class="scd-conflict-details"></div>' );
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
			var $container = $( '#scd-health-container' );
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
			var $launchButton = $( '.scd-nav-btn[data-action="complete"]' );

			if ( ! $launchButton.length ) {
				// Button not found (might not be on review step yet)
				return;
			}

			if ( false === isReady || ! isReady ) {
				// Disable launch button when critical issues exist
				$launchButton
					.addClass( 'scd-navigation-disabled' )
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
					.removeClass( 'scd-navigation-disabled' )
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

			// Try scdWizardData
			if ( window.scdWizardData && window.scdWizardData.campaignId ) {
				return window.scdWizardData.campaignId;
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
	window.SCD_ReviewHealthCheck = ReviewHealthCheck;

})( jQuery );
