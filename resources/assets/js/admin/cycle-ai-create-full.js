/**
 * Cycle AI Create Full Campaign (Campaigns list page)
 *
 * Handles "Create with AI" button: shows progress modal with loader and
 * fading conversational phrases, calls AJAX, then redirects to the wizard
 * review step with session prefilled.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	var PHRASE_INTERVAL_MS = 3500;
	var CROSSFADE_DURATION_MS = 520;

	function showModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		$modal.addClass( 'wsscd-modal--visible' ).attr( 'aria-hidden', 'false' );
		$( 'body' ).addClass( 'wsscd-modal-open' );
	}

	function hideModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		$modal.removeClass( 'wsscd-modal--visible' ).attr( 'aria-hidden', 'true' );
		$( 'body' ).removeClass( 'wsscd-modal-open' );
	}

	function startPhraseRotation( $wrap, intervalMs ) {
		var phrases = [];
		try {
			var raw = $wrap.attr( 'data-phrases' );
			if ( raw ) {
				phrases = JSON.parse( raw );
			}
		} catch ( err ) {
			phrases = [];
		}
		if ( phrases.length === 0 ) {
			return null;
		}
		var $slotA = $wrap.find( '.wsscd-cycle-ai-create-modal__phrase--a' );
		var $slotB = $wrap.find( '.wsscd-cycle-ai-create-modal__phrase--b' );
		var index = 0;
		var useA = true;

		function normalizePhrase( str ) {
			if ( typeof str !== 'string' ) {
				return '';
			}
			return str.replace( /\s*[\r\n]+\s*/g, ' ' ).replace( /\s{2,}/g, ' ' ).trim();
		}

		function next() {
			index = ( index + 1 ) % phrases.length;
			var $leaving = useA ? $slotA : $slotB;
			var $entering = useA ? $slotB : $slotA;

			/* Ensure entering slot is in hidden state (no leftover classes). */
			$entering.removeClass( 'is-visible is-leaving' ).text( normalizePhrase( phrases[index] ) );
			$leaving.addClass( 'is-leaving' );

			/* Let the browser paint the new text at opacity 0, then transition to visible. */
			requestAnimationFrame( function() {
				requestAnimationFrame( function() {
					$entering.addClass( 'is-visible' );
				} );
			} );

			setTimeout( function() {
				$leaving.removeClass( 'is-visible is-leaving' );
				useA = ! useA;
			}, CROSSFADE_DURATION_MS );
		}

		$slotA.text( normalizePhrase( phrases[0] ) ).addClass( 'is-visible' );
		return setInterval( next, intervalMs );
	}

	$( function() {
		var $modal = $( '#wsscd-cycle-ai-create-modal' );
		var phraseTimer = null;
		var cancelled = false;
		var currentBtn = null;
		var currentOriginalText = '';

		function resetState() {
			if ( phraseTimer ) {
				clearInterval( phraseTimer );
				phraseTimer = null;
			}
			cancelled = false;
			if ( currentBtn && currentBtn.length ) {
				currentBtn.prop( 'disabled', false ).text( currentOriginalText );
				currentBtn = null;
			}
		}

		$( document ).on( 'click', '#wsscd-cycle-ai-create-cancel', function() {
			cancelled = true;
			if ( phraseTimer ) {
				clearInterval( phraseTimer );
				phraseTimer = null;
			}
			hideModal( $modal );
			if ( currentBtn && currentBtn.length ) {
				currentBtn.prop( 'disabled', false ).text( currentOriginalText );
				currentBtn = null;
			}
		} );

		$( document ).on( 'click', '.wsscd-create-with-ai-btn', function( e ) {
			e.preventDefault();

			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}

			if ( ! window.WSSCD || ! window.WSSCD.Ajax || ! WSSCD.Ajax.post ) {
				if ( window.WSSCD && WSSCD.Shared && WSSCD.Shared.NotificationService ) {
					WSSCD.Shared.NotificationService.error( 'Cycle AI is not available. Please refresh the page.' );
				} else {
					window.alert( 'Cycle AI is not available. Please refresh the page.' );
				}
				return;
			}

			var $phraseWrap = $modal.find( '.wsscd-cycle-ai-create-modal__phrase-wrap' );
			cancelled = false;
			currentBtn = $btn;
			currentOriginalText = $btn.text();

			$btn.prop( 'disabled', true ).text( $btn.data( 'loading-text' ) || 'Creating campaign…' );

			showModal( $modal );
			phraseTimer = startPhraseRotation( $phraseWrap, PHRASE_INTERVAL_MS );

			WSSCD.Ajax.post( 'wsscd_cycle_ai_create_full_campaign', {}, { timeout: 90000 } )
				.then( function( response ) {
					if ( cancelled ) {
						return;
					}
					if ( phraseTimer ) {
						clearInterval( phraseTimer );
						phraseTimer = null;
					}
					var url = response && response.redirectUrl ? response.redirectUrl : '';
					if ( url ) {
						window.location.href = url;
					} else {
						hideModal( $modal );
						resetState();
						if ( window.WSSCD && WSSCD.Shared && WSSCD.Shared.NotificationService ) {
							WSSCD.Shared.NotificationService.error( 'No redirect URL received.' );
						}
					}
				} )
				.catch( function( error ) {
					if ( cancelled ) {
						return;
					}
					if ( phraseTimer ) {
						clearInterval( phraseTimer );
						phraseTimer = null;
					}
					hideModal( $modal );
					resetState();
					var msg = error && error.message ? error.message : 'Cycle AI could not create the campaign. Please try again.';
					if ( window.WSSCD && WSSCD.Shared && WSSCD.Shared.NotificationService ) {
						WSSCD.Shared.NotificationService.error( msg );
					} else {
						window.alert( msg );
					}
				} );
		} );
	} );
} )( jQuery );
