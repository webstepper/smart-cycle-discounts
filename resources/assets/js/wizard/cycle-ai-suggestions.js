/**
 * Cycle AI Campaign Suggestions (Wizard)
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/cycle-ai-suggestions.js
 * @author     Webstepper <contact@webstepper.io>
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};

	/**
	 * Cycle AI helper
	 */
	var CycleAI = {
		init: function() {
			var self = this;

			$( document ).on( 'click', '.wsscd-cycle-ai-suggest-button', function( e ) {
				e.preventDefault();
				self.handleClick( $( this ) );
			} );
		},

		handleClick: function( $button ) {
			if ( ! window.WSSCD || ! window.WSSCD.Ajax || ! WSSCD.Ajax.post ) {
				return;
			}

			var $container = $( '.wsscd-cycle-ai-suggestions-container' );
			var originalText = $button.text();

			$button.prop( 'disabled', true ).text( $button.data( 'loading-text' ) || originalText );

			if ( $container.length ) {
				$container
					.empty()
					.append( '<p class="wsscd-cycle-ai-status">' + this.escHtml( $button.data( 'status-text' ) || 'Generating suggestions\u2026' ) + '</p>' );
			}

			WSSCD.Ajax.post( 'wsscd_cycle_ai_suggest_campaigns', {} )
				.then( function( response ) {
					var suggestions = response && response.suggestions ? response.suggestions : [];
					CycleAI.renderSuggestions( suggestions, $container );
				} )
				.catch( function( error ) {
					var msg = error && error.message ? error.message : 'Cycle AI request failed.';
					if ( error && error.response && error.response.data && error.response.data.reason ) {
						msg = msg + ' (' + CycleAI.escHtml( error.response.data.reason ) + ')';
					}
					CycleAI.showError( msg );
				} )
				.always( function() {
					$button.prop( 'disabled', false ).text( originalText );
				} );
		},

		renderSuggestions: function( suggestions, $container ) {
			if ( ! $container || ! $container.length ) {
				return;
			}

			$container.empty();

			if ( ! suggestions || ! suggestions.length ) {
				$container.append( '<p class="wsscd-cycle-ai-status">' + this.escHtml( 'No suggestions available yet. Try again later.' ) + '</p>' );
				return;
			}

			var html = '<ul class="wsscd-cycle-ai-suggestions">';

			for ( var i = 0; i < suggestions.length; i++ ) {
				var item = suggestions[i];
				var name = item.name || '';
				var description = item.description || '';
				var target = item.target || '';
				var discountType = item.discount_type || '';
				var discountValue = item.discount_value || '';
				var duration = item.duration_days || '';

				html += '<li class="wsscd-cycle-ai-suggestion" data-index="' + i + '">';
				html += '<div class="wsscd-cycle-ai-suggestion-main">';
				html += '<strong class="wsscd-cycle-ai-suggestion-name">' + this.escHtml( name ) + '</strong>';
				if ( description ) {
					html += '<div class="wsscd-cycle-ai-suggestion-description">' + this.escHtml( description ) + '</div>';
				}
				html += '</div>';

				html += '<div class="wsscd-cycle-ai-suggestion-meta">';
				if ( target ) {
					html += '<span class="wsscd-cycle-ai-meta-item">' + this.escHtml( target ) + '</span>';
				}
				if ( discountType ) {
					var typeLabel = discountType;
					if ( 'percentage' === discountType && discountValue ) {
						typeLabel = discountValue + '% ' + discountType;
					} else if ( discountValue ) {
						typeLabel = discountValue + ' ' + discountType;
					}
					html += '<span class="wsscd-cycle-ai-meta-item">' + this.escHtml( typeLabel ) + '</span>';
				}
				if ( duration ) {
					html += '<span class="wsscd-cycle-ai-meta-item">' + this.escHtml( duration + ' days' ) + '</span>';
				}
				html += '</div>';

				html += '<div class="wsscd-cycle-ai-suggestion-actions">';
				html += '<button type="button" class="button button-secondary wsscd-cycle-ai-apply" data-index="' + i + '">' + this.escHtml( 'Use this campaign' ) + '</button>';
				html += '</div>';

				html += '</li>';
			}

			html += '</ul>';

			$container.append( html );

			this.bindApplyHandlers( suggestions, $container );
		},

		bindApplyHandlers: function( suggestions, $container ) {
			var self = this;

			$container.off( 'click.wsscd-cycle-ai' ).on( 'click.wsscd-cycle-ai', '.wsscd-cycle-ai-apply', function( e ) {
				e.preventDefault();

				var index = parseInt( $( this ).attr( 'data-index' ), 10 );
				if ( isNaN( index ) || ! suggestions[ index ] ) {
					return;
				}

				self.applySuggestionToWizard( suggestions[ index ] );
			} );
		},

		applySuggestionToWizard: function( suggestion ) {
			// Apply to Basic step fields only for Phase 1 (name + description).
			var fieldDefs = window.WSSCD && window.WSSCD.FieldDefinitions && WSSCD.FieldDefinitions.basic ? WSSCD.FieldDefinitions.basic : {};

			var nameSelector = fieldDefs.name && fieldDefs.name.selector ? fieldDefs.name.selector : 'input[name="name"]';
			var descSelector = fieldDefs.description && fieldDefs.description.selector ? fieldDefs.description.selector : 'textarea[name="description"]';

			var $nameField = $( nameSelector );
			var $descField = $( descSelector );

			if ( $nameField.length && suggestion.name ) {
				$nameField.val( suggestion.name );
				$nameField.trigger( 'input' );
			}

			if ( $descField.length && suggestion.description ) {
				$descField.val( suggestion.description );
				$descField.trigger( 'input' );
			}

			this.showInfo( 'Cycle AI applied the campaign name and description. Review and adjust other settings as needed.' );
		},

		showError: function( message ) {
			if ( window.WSSCD && WSSCD.Shared && WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.error( message );
				return;
			}

			window.alert( message );
		},

		showInfo: function( message ) {
			if ( window.WSSCD && WSSCD.Shared && WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.info( message );
				return;
			}
		},

		escHtml: function( text ) {
			if ( 'string' !== typeof text ) {
				return '';
			}

			return text
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		}
	};

	$( document ).ready( function() {
		CycleAI.init();
	} );

} )( jQuery );

