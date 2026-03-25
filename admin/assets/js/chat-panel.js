/* global AbilityHubChat */
( function () {
	'use strict';

	const cfg       = window.AbilityHubChat || {};
	const restBase  = cfg.rest_url  || '';
	const nonce     = cfg.nonce     || '';
	const historyUrl = cfg.history_url || '';

	const messagesEl = document.getElementById( 'abilityhub-chat-messages' );
	const typingEl   = document.getElementById( 'abilityhub-chat-typing' );
	const formEl     = document.getElementById( 'abilityhub-chat-form' );
	const inputEl    = document.getElementById( 'abilityhub-chat-input' );
	const sendBtn    = document.getElementById( 'abilityhub-chat-send' );
	const clearBtn   = document.getElementById( 'abilityhub-chat-clear' );

	if ( ! messagesEl || ! formEl ) {
		return; // Not on the chat page.
	}

	// ---- Load history on page load ----------------------------------------

	if ( historyUrl ) {
		fetch( historyUrl, { headers: { 'X-WP-Nonce': nonce } } )
			.then( r => r.json() )
			.then( data => {
				if ( ! data.history || ! data.history.length ) return;
				// Replace the default greeting with stored history.
				messagesEl.innerHTML = '';
				data.history.forEach( msg => {
					appendMessage( msg.role, msg.content );
				} );
				scrollToBottom();
			} )
			.catch( () => {} ); // Silently ignore — default greeting stays.
	}

	// ---- Form submit -------------------------------------------------------

	formEl.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		const text = inputEl.value.trim();
		if ( ! text ) return;
		sendMessage( text );
	} );

	// Auto-grow textarea.
	inputEl.addEventListener( 'input', function () {
		this.style.height = 'auto';
		this.style.height = Math.min( this.scrollHeight, 120 ) + 'px';
	} );

	// Send on Enter (Shift+Enter = newline).
	inputEl.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			formEl.dispatchEvent( new Event( 'submit' ) );
		}
	} );

	// ---- Example prompts ---------------------------------------------------

	document.querySelectorAll( '.abilityhub-chat-example' ).forEach( link => {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			const prompt = this.dataset.prompt || '';
			if ( prompt ) {
				inputEl.value = prompt;
				inputEl.dispatchEvent( new Event( 'input' ) );
				inputEl.focus();
			}
		} );
	} );

	// ---- Clear conversation ------------------------------------------------

	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( cfg.i18n.confirm_clear || 'Clear the conversation?' ) ) return;

			fetch( historyUrl, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': nonce },
			} )
				.then( () => {
					messagesEl.innerHTML = '';
					appendMessage( 'assistant', cfg.i18n.cleared || 'Conversation cleared. How can I help you?' );
				} )
				.catch( () => {} );
		} );
	}

	// ---- Core send ---------------------------------------------------------

	function sendMessage( text ) {
		appendMessage( 'user', escapeHtml( text ) );
		inputEl.value       = '';
		inputEl.style.height = '';
		setLoading( true );

		fetch( restBase, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   nonce,
			},
			body: JSON.stringify( { message: text } ),
		} )
			.then( r => r.json() )
			.then( data => {
				setLoading( false );

				if ( data.code ) {
					// WP_Error format.
					appendMessage( 'assistant', '<em class="abilityhub-chat-error">' + escapeHtml( data.message || cfg.i18n.error ) + '</em>' );
					return;
				}

				const reply = data.reply || '';
				appendMessage( 'assistant', markdownToHtml( reply ), data.intent_result );
			} )
			.catch( err => {
				setLoading( false );
				appendMessage( 'assistant', '<em class="abilityhub-chat-error">' + escapeHtml( cfg.i18n.error || 'An error occurred.' ) + '</em>' );
				console.error( '[AbilityHub Chat]', err );
			} );
	}

	// ---- UI helpers --------------------------------------------------------

	function appendMessage( role, html, intentResult ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'abilityhub-chat-message abilityhub-chat-message--' + role;

		const avatar = document.createElement( 'div' );
		avatar.className = 'abilityhub-chat-message__avatar';
		avatar.textContent = role === 'user' ? ( cfg.i18n.you || 'You' ).slice( 0, 2 ).toUpperCase() : 'AI';

		const body = document.createElement( 'div' );
		body.className = 'abilityhub-chat-message__body';
		body.innerHTML  = html;

		if ( intentResult ) {
			body.appendChild( buildIntentCard( intentResult ) );
		}

		wrap.appendChild( avatar );
		wrap.appendChild( body );
		messagesEl.appendChild( wrap );
		scrollToBottom();
	}

	function buildIntentCard( result ) {
		const card = document.createElement( 'div' );
		card.className = result.success
			? 'abilityhub-intent-result abilityhub-intent-result--success'
			: 'abilityhub-intent-result abilityhub-intent-result--error';

		const icon = document.createElement( 'span' );
		icon.className   = 'abilityhub-intent-result__icon';
		icon.textContent = result.success ? '✓' : '✕';
		icon.setAttribute( 'aria-hidden', 'true' );

		const msg = document.createElement( 'span' );
		msg.textContent = result.message || '';

		card.appendChild( icon );
		card.appendChild( msg );

		// If there's batch data, add a progress link.
		if ( result.success && result.data && result.data.batch_id ) {
			const link = document.createElement( 'a' );
			link.href        = cfg.batch_status_url
				? cfg.batch_status_url.replace( '{id}', result.data.batch_id )
				: '#';
			link.textContent = ' ' + ( cfg.i18n.view_batch || 'View batch' );
			link.style.marginLeft = '.4em';
			card.appendChild( link );
			pollBatchProgress( result.data.batch_id, msg );
		}

		return card;
	}

	function pollBatchProgress( batchId, statusEl ) {
		if ( ! cfg.batch_status_url ) return;

		const url       = cfg.batch_status_url.replace( '{id}', batchId );
		let   attempts  = 0;
		const maxPolls  = 60; // 5 minutes at 5s intervals

		const interval = setInterval( () => {
			if ( ++attempts > maxPolls ) {
				clearInterval( interval );
				return;
			}

			fetch( url, { headers: { 'X-WP-Nonce': nonce } } )
				.then( r => r.json() )
				.then( data => {
					if ( data.total ) {
						statusEl.textContent = ( cfg.i18n.batch_progress || 'Processing: {p}/{t}' )
							.replace( '{p}', data.progress )
							.replace( '{t}', data.total );
					}
					if ( data.status === 'publish' ) {
						clearInterval( interval );
						statusEl.textContent = ( cfg.i18n.batch_complete || 'Batch complete ({t} posts processed)' )
							.replace( '{t}', data.total );
					}
				} )
				.catch( () => clearInterval( interval ) );
		}, 5000 );
	}

	function setLoading( loading ) {
		sendBtn.disabled   = loading;
		inputEl.disabled   = loading;
		typingEl.hidden    = ! loading;
		if ( loading ) scrollToBottom();
	}

	function scrollToBottom() {
		requestAnimationFrame( () => {
			messagesEl.scrollTop = messagesEl.scrollHeight;
		} );
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * Minimal Markdown → HTML converter for chat replies.
	 * Handles: **bold**, *italic*, `code`, ```fenced```, and line breaks.
	 */
	function markdownToHtml( text ) {
		// Fenced code blocks.
		text = text.replace( /```[\s\S]*?```/g, '' ); // strip remaining code fences
		// Bold.
		text = text.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
		// Italic.
		text = text.replace( /\*(.+?)\*/g, '<em>$1</em>' );
		// Inline code.
		text = text.replace( /`([^`]+)`/g, '<code>$1</code>' );
		// Paragraphs from double newlines.
		text = text.split( /\n{2,}/ ).map( p => '<p>' + p.replace( /\n/g, '<br>' ) + '</p>' ).join( '' );
		return text;
	}

}() );
