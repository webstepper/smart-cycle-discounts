/**
 * Cycle AI Create Full Campaign (Campaigns list page)
 *
 * Handles "Create with AI" button: opens modal with campaign type cards
 * (Recommended + Other), then shows progress (loader + phrases), calls AJAX
 * with userBrief from selected card, and redirects to the wizard review step.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	var PHRASE_INTERVAL_MS = 3200;
	var CROSSFADE_DURATION_MS = 600;

	function showModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		$modal.removeClass( 'wsscd-cycle-ai-create-modal--progress' ).attr( 'aria-hidden', 'false' );
		$modal.addClass( 'wsscd-modal--visible' );
		$( 'body' ).addClass( 'wsscd-modal-open' );
	}

	function hideModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		$modal.removeClass( 'wsscd-modal--visible' ).attr( 'aria-hidden', 'true' );
		$( 'body' ).removeClass( 'wsscd-modal-open' );
	}

	function showBriefStep( $modal ) {
		$modal.removeClass( 'wsscd-cycle-ai-create-modal--progress' );
		$modal.find( '#wsscd-cycle-ai-create-step-progress' ).attr( 'aria-hidden', 'true' );
		$modal.find( '#wsscd-cycle-ai-create-step-brief' ).attr( 'aria-hidden', 'false' );
	}

	function showProgressStep( $modal ) {
		$modal.addClass( 'wsscd-cycle-ai-create-modal--progress' );
		$modal.find( '#wsscd-cycle-ai-create-step-brief' ).attr( 'aria-hidden', 'true' );
		$modal.find( '#wsscd-cycle-ai-create-step-progress' ).attr( 'aria-hidden', 'false' );
	}

	function startPhraseRotation( $wrap, intervalMs ) {
		var $phrase = $wrap.find( '.wsscd-cycle-ai-create-modal__phrase' );
		if ( ! $phrase.length ) {
			return null;
		}

		var phrases = [];
		try {
			var raw = $wrap.attr( 'data-phrases' );
			if ( raw ) {
				phrases = JSON.parse( raw );
			}
		} catch ( err ) {
			phrases = [];
		}
		if ( ! Array.isArray( phrases ) ) {
			phrases = [];
		}
		if ( phrases.length === 0 ) {
			var firstText = $phrase.text();
			if ( firstText && firstText.trim() ) {
				phrases = [ firstText.trim() ];
			}
		}
		if ( phrases.length === 0 ) {
			return null;
		}

		function normalizePhrase( str ) {
			if ( typeof str !== 'string' ) {
				return '';
			}
			return str.replace( /\s*[\r\n]+\s*/g, ' ' ).replace( /\s{2,}/g, ' ' ).trim();
		}

		$phrase.text( normalizePhrase( phrases[0] ) ).removeClass( 'is-leaving' );

		if ( phrases.length === 1 ) {
			return null;
		}

		var index = 0;

		function next() {
			index = ( index + 1 ) % phrases.length;
			var nextText = normalizePhrase( phrases[index] );
			$phrase.addClass( 'is-leaving' );
			setTimeout( function() {
				$phrase.text( nextText ).removeClass( 'is-leaving' );
			}, CROSSFADE_DURATION_MS );
		}

		return setInterval( next, intervalMs );
	}

	$( function() {
		var $modal = $( '#wsscd-cycle-ai-create-modal' );
		var $createBtn = $modal.find( '#wsscd-cycle-ai-create-start' );
		var phraseTimer = null;
		var cancelled = false;
		var listBtn = null;
		var listBtnOriginalText = '';

		function getSelectedBrief() {
			var $card = $modal.find( '.wsscd-cycle-ai-create-modal__card.is-selected' );
			if ( ! $card.length ) {
				return '';
			}
			var raw = $card.data( 'campaignBrief' );
			return ( typeof raw === 'string' && raw ) ? raw.trim() : '';
		}

		function setSelectedCard( $card ) {
			$modal.find( '.wsscd-cycle-ai-create-modal__card' ).removeClass( 'is-selected' ).attr( 'aria-pressed', 'false' );
			if ( $card.length ) {
				$card.addClass( 'is-selected' ).attr( 'aria-pressed', 'true' );
			}
		}

		function resetState() {
			if ( phraseTimer ) {
				clearInterval( phraseTimer );
				phraseTimer = null;
			}
			cancelled = false;
			showBriefStep( $modal );
			setSelectedCard( $modal.find( '.wsscd-cycle-ai-create-modal__card--no-preference' ) );
			$createBtn.prop( 'disabled', false );
			if ( listBtn && listBtn.length ) {
				listBtn.prop( 'disabled', false ).text( listBtnOriginalText );
				listBtn = null;
			}
		}

		function closeModal() {
			cancelled = true;
			if ( phraseTimer ) {
				clearInterval( phraseTimer );
				phraseTimer = null;
			}
			hideModal( $modal );
			resetState();
		}

		$( document ).on( 'click', '#wsscd-cycle-ai-create-cancel, #wsscd-cycle-ai-create-cancel-brief', function() {
			closeModal();
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

			listBtn = $btn;
			listBtnOriginalText = $btn.text();
			$btn.prop( 'disabled', true );
			showModal( $modal );
			setSelectedCard( $modal.find( '.wsscd-cycle-ai-create-modal__card--no-preference' ) );
		} );

		$( document ).on( 'click', '.wsscd-cycle-ai-create-modal__card', function( e ) {
			e.preventDefault();
			setSelectedCard( $( this ) );
		} );

		$( document ).on( 'click', '#wsscd-cycle-ai-create-start', function( e ) {
			e.preventDefault();
			if ( $createBtn.prop( 'disabled' ) ) {
				return;
			}

			var userBrief = getSelectedBrief();
			var payload = {};
			if ( userBrief ) {
				payload.userBrief = userBrief;
			}

			var $phraseWrap = $modal.find( '.wsscd-cycle-ai-create-modal__phrase-wrap' );
			cancelled = false;
			$createBtn.prop( 'disabled', true );
			showProgressStep( $modal );
			// Defer phrase rotation until after the progress step is painted so the phrase is visible.
			requestAnimationFrame( function() {
				requestAnimationFrame( function() {
					if ( ! cancelled && $phraseWrap.length ) {
						phraseTimer = startPhraseRotation( $phraseWrap, PHRASE_INTERVAL_MS );
					}
				} );
			} );

			WSSCD.Ajax.post( 'wsscd_cycle_ai_create_full_campaign', payload, { timeout: 90000 } )
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
