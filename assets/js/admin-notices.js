/**
 * PTA Volunteer Sign-Up Sheets — Remote Admin Notices
 *
 * Handles the floating button, slide-out panel, card rendering,
 * and per-notice dismissal via AJAX.
 *
 * Depends on: jquery
 * Localized via: ptaSusNotices (wp_localize_script)
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since   6.3.2
 */

/* global ptaSusNotices, jQuery */
( function ( $ ) {
	'use strict';

	var data       = window.ptaSusNotices || {};
	var notices    = data.notices      || [];
	var nonce      = data.nonce        || '';
	var ajaxUrl    = data.ajaxUrl      || '';
	var allRead    = data.allRead      || 'All caught up!';
	var panelTitle = data.panelTitle   || 'Volunteer Sign Up Sheets — News & Updates';
	var unread     = notices.length;

	var $btn   = $( '#pta-sus-notices-btn' );
	var $badge = $btn.find( '.pta-sus-notice-badge' );

	/**
	 * Update the badge count display.
	 */
	function updateBadge() {
		if ( unread > 0 ) {
			$badge.text( unread ).css( 'display', 'inline-flex' );
		} else {
			$badge.hide();
		}
	}

	/**
	 * Escape a plain-text string for safe insertion as HTML.
	 *
	 * @param {string} str Raw string.
	 * @return {string} HTML-escaped string.
	 */
	function esc( str ) {
		return $( '<span>' ).text( String( str || '' ) ).html();
	}

	/**
	 * Build the HTML for a single notice card.
	 *
	 * @param {Object} notice Notice object from ptaSusNotices.notices.
	 * @return {string} HTML string.
	 */
	function buildCard( notice ) {
		var type  = esc( notice.type  || 'info' );
		var id    = esc( notice.id    || '' );
		var title = esc( notice.title || '' );
		var date  = notice.date ? esc( notice.date ) : '';
		// message is already wp_kses'd server-side; inject as HTML
		var msg   = notice.message || '';

		var html  = '<div class="pta-sus-notice-card" data-id="' + id + '">';
		html += '<span class="pta-sus-notice-type pta-sus-notice-type-' + type + '">' + type + '</span>';
		html += '<p class="pta-sus-notice-card-title">' + title + '</p>';
		if ( date ) {
			html += '<p class="pta-sus-notice-card-date">' + date + '</p>';
		}
		html += '<div class="pta-sus-notice-card-message">' + msg + '</div>';
		html += '<button class="pta-sus-notice-dismiss" data-id="' + id + '">Dismiss</button>';
		html += '</div>';
		return html;
	}

	/**
	 * Build the HTML for the empty-state message.
	 *
	 * @return {string} HTML string.
	 */
	function buildEmptyState() {
		return '<div class="pta-sus-notices-empty">' +
			'<span class="dashicons dashicons-yes-alt"></span>' +
			esc( allRead ) +
			'</div>';
	}

	/**
	 * Build and inject the panel's card list into the given body element.
	 *
	 * @param {jQuery} $body Panel body container.
	 */
	function populateBody( $body ) {
		if ( notices.length === 0 ) {
			$body.html( buildEmptyState() );
			return;
		}
		var html = '';
		for ( var i = 0; i < notices.length; i++ ) {
			html += buildCard( notices[ i ] );
		}
		$body.html( html );
	}

	/**
	 * Open the slide-out panel by creating overlay + panel and animating them in.
	 */
	function openPanel() {
		// Build overlay
		var $overlay = $( '<div id="pta-sus-notices-overlay"></div>' );

		// Build panel shell
		var $panel = $( '<div id="pta-sus-notices-panel"></div>' );

		var $header = $(
			'<div class="pta-sus-notices-header">' +
				'<h3>' + esc( panelTitle ) + '</h3>' +
				'<button class="pta-sus-notices-close" aria-label="Close">&times;</button>' +
			'</div>'
		);

		var $body = $( '<div class="pta-sus-notices-body"></div>' );
		populateBody( $body );

		$panel.append( $header ).append( $body );
		$( 'body' ).append( $overlay ).append( $panel );

		// Animate in (next frame so CSS transition fires)
		requestAnimationFrame( function () {
			$overlay.addClass( 'is-visible' );
			$panel.addClass( 'is-open' );
		} );

		// Close on overlay click
		$overlay.on( 'click', closePanel );

		// Close on × button
		$panel.on( 'click', '.pta-sus-notices-close', closePanel );

		// Dismiss a notice card
		$panel.on( 'click', '.pta-sus-notice-dismiss', function () {
			var id    = $( this ).data( 'id' );
			var $card = $( this ).closest( '.pta-sus-notice-card' );
			dismissNotice( id, $card, $body );
		} );
	}

	/**
	 * Close and remove the panel + overlay from the DOM.
	 */
	function closePanel() {
		var $panel   = $( '#pta-sus-notices-panel' );
		var $overlay = $( '#pta-sus-notices-overlay' );

		$panel.removeClass( 'is-open' );
		$overlay.removeClass( 'is-visible' );

		setTimeout( function () {
			$panel.remove();
			$overlay.remove();
		}, 320 );
	}

	/**
	 * Send an AJAX dismiss request for a notice, then update the UI.
	 *
	 * @param {string} id    Notice ID.
	 * @param {jQuery} $card Card element to remove.
	 * @param {jQuery} $body Panel body container (for empty-state injection).
	 */
	function dismissNotice( id, $card, $body ) {
		$.post(
			ajaxUrl,
			{
				action:    'pta_sus_dismiss_notice',
				notice_id: id,
				nonce:     nonce,
			},
			function ( response ) {
				if ( response && response.success ) {
					// Remove the matching entry from the local array
					notices = notices.filter( function ( n ) {
						return n.id !== id;
					} );
					unread = notices.length;

					$card.fadeOut( 300, function () {
						$( this ).remove();

						// Update badge
						updateBadge();

						// Show empty state if no notices remain
						if ( unread === 0 ) {
							$body.html( buildEmptyState() );
						}
					} );
				}
			}
		);
	}

	// -----------------------------------------------------------------------
	// Initialise
	// -----------------------------------------------------------------------

	updateBadge();
	$btn.on( 'click', openPanel );

} )( jQuery );
