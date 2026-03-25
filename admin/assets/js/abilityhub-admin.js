/**
 * AbilityHub Admin JavaScript
 */
/* global AbilityHub, jQuery */

( function ( $, config ) {
    'use strict';

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function formatJson( obj ) {
        try {
            return JSON.stringify( obj, null, 2 );
        } catch ( e ) {
            return String( obj );
        }
    }

    function showOutput( $pre, $header, data, isError, durationText ) {
        $pre.text( isError ? data : formatJson( data ) );
        $pre.toggleClass( 'is-success', ! isError );
        $pre.toggleClass( 'is-error', isError );

        const $label = $header.find( '.abilityhub-output-label' );
        $label.text( isError ? config.i18n.error : config.i18n.success );

        const $meta = $header.find( '.abilityhub-output-meta' );
        if ( $meta.length && durationText ) {
            $meta.text( durationText );
        }
    }

    function copyToClipboard( text ) {
        if ( navigator.clipboard && window.isSecureContext ) {
            return navigator.clipboard.writeText( text );
        }
        // Fallback
        const el = document.createElement( 'textarea' );
        el.value = text;
        el.style.position = 'fixed';
        el.style.opacity  = '0';
        document.body.appendChild( el );
        el.select();
        document.execCommand( 'copy' );
        document.body.removeChild( el );
        return Promise.resolve();
    }

    function setButtonLoading( $btn, loading ) {
        $btn.prop( 'disabled', loading );
        $btn.toggleClass( 'is-loading', loading );
        if ( loading ) {
            $btn.data( 'original-text', $btn.text() );
            $btn.text( config.i18n.executing );
        } else {
            $btn.text( $btn.data( 'original-text' ) || $btn.text() );
        }
    }

    // -------------------------------------------------------------------------
    // Copy buttons
    // -------------------------------------------------------------------------

    $( document ).on( 'click', '.abilityhub-copy-btn', function () {
        const $btn       = $( this );
        const targetId   = $btn.data( 'target' );
        const $target    = $( '#' + targetId );
        const textToCopy = $target.text();

        copyToClipboard( textToCopy ).then( function () {
            const original = $btn.text();
            $btn.text( config.i18n.copied );
            setTimeout( function () {
                $btn.text( original );
            }, 1800 );
        } );
    } );

    // -------------------------------------------------------------------------
    // Quick Execute (Dashboard)
    // -------------------------------------------------------------------------

    const $qeAbility  = $( '#qe-ability' );
    const $qeInput    = $( '#qe-input' );
    const $qeRunBtn   = $( '#qe-run' );
    const $qeOutput   = $( '#qe-output' );
    const $qeResult   = $( '#qe-result' );
    const $qeHeader   = $qeOutput.find( '.abilityhub-output-header' );

    // Populate example input when ability is selected
    $qeAbility.on( 'change', function () {
        const $selected = $( this ).find( ':selected' );
        const example   = $selected.data( 'example' );
        if ( example ) {
            $qeInput.val( formatJson( example ) );
        } else {
            $qeInput.val( '{}' );
        }
        $qeOutput.hide();
    } );

    $qeRunBtn.on( 'click', function () {
        const ability = $qeAbility.val();
        if ( ! ability ) {
            alert( 'Please select an ability.' );
            return;
        }

        let inputJson;
        try {
            inputJson = JSON.parse( $qeInput.val() || '{}' );
        } catch ( e ) {
            alert( 'Input must be valid JSON.\n\n' + e.message );
            return;
        }

        const start = Date.now();
        setButtonLoading( $( this ), true );
        $qeOutput.hide();

        $.post( config.ajax_url, {
            action:  'abilityhub_execute',
            nonce:   config.nonce,
            ability: ability,
            input:   JSON.stringify( inputJson ),
        } )
        .done( function ( response ) {
            const elapsed = Date.now() - start;
            $qeOutput.show();
            if ( response.success ) {
                showOutput( $qeResult, $qeHeader, response.data.result, false, elapsed + 'ms' );
            } else {
                showOutput( $qeResult, $qeHeader, response.data.message || 'Unknown error', true, elapsed + 'ms' );
            }
        } )
        .fail( function ( xhr ) {
            const elapsed = Date.now() - start;
            $qeOutput.show();
            showOutput( $qeResult, $qeHeader, 'Request failed: ' + xhr.statusText, true, elapsed + 'ms' );
        } )
        .always( function () {
            setButtonLoading( $qeRunBtn, false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Modal (shared between Store and Installed tabs)
    // -------------------------------------------------------------------------

    const $modal        = $( '#abilityhub-modal' );
    const $modalTitle   = $( '#abilityhub-modal-title' );
    const $modalInput   = $( '#modal-input' );
    const $modalExec    = $( '#modal-execute' );
    const $modalOutput  = $( '#modal-output' );
    const $modalResult  = $( '#modal-result' );
    const $modalStatus  = $( '#modal-status' );
    const $modalHeader  = $modalOutput.find( '.abilityhub-output-header' );

    let activeAbility = '';

    function openModal( abilityName, abilityLabel, exampleData ) {
        activeAbility = abilityName;
        $modalTitle.text( abilityLabel || abilityName );

        let example = exampleData;
        if ( typeof example === 'string' ) {
            try {
                example = JSON.parse( example );
            } catch ( e ) {
                example = {};
            }
        }

        $modalInput.val( formatJson( example || {} ) );
        $modalOutput.hide();
        $modal.show();
        $modalInput.trigger( 'focus' );
    }

    function closeModal() {
        $modal.hide();
        activeAbility = '';
    }

    // Open modal on "Try it" button clicks
    $( document ).on( 'click', '.abilityhub-try-btn', function () {
        const $btn  = $( this );
        openModal(
            $btn.data( 'ability' ),
            $btn.data( 'label' ),
            $btn.data( 'example' )
        );
    } );

    // Close modal
    $( document ).on( 'click', '.abilityhub-modal__close, .abilityhub-modal__backdrop', closeModal );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' && $modal.is( ':visible' ) ) {
            closeModal();
        }
    } );

    // Prevent clicks inside modal content from closing
    $modal.on( 'click', '.abilityhub-modal__content', function ( e ) {
        e.stopPropagation();
    } );

    // Execute from modal
    $modalExec.on( 'click', function () {
        if ( ! activeAbility ) {
            return;
        }

        let inputJson;
        try {
            inputJson = JSON.parse( $modalInput.val() || '{}' );
        } catch ( e ) {
            alert( 'Input must be valid JSON.\n\n' + e.message );
            return;
        }

        const start = Date.now();
        setButtonLoading( $( this ), true );
        $modalOutput.hide();

        $.post( config.ajax_url, {
            action:  'abilityhub_execute',
            nonce:   config.nonce,
            ability: activeAbility,
            input:   JSON.stringify( inputJson ),
        } )
        .done( function ( response ) {
            const elapsed = Date.now() - start;
            $modalOutput.show();

            if ( response.success ) {
                $modalStatus.text( config.i18n.success );
                showOutput( $modalResult, $modalHeader, response.data.result, false, elapsed + 'ms' );
            } else {
                $modalStatus.text( config.i18n.error );
                showOutput( $modalResult, $modalHeader, response.data.message || 'Unknown error', true, elapsed + 'ms' );
            }
        } )
        .fail( function ( xhr ) {
            const elapsed = Date.now() - start;
            $modalOutput.show();
            $modalStatus.text( config.i18n.error );
            showOutput( $modalResult, $modalHeader, 'Request failed: ' + xhr.statusText, true, elapsed + 'ms' );
        } )
        .always( function () {
            setButtonLoading( $modalExec, false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Auto-format JSON on textarea blur
    // -------------------------------------------------------------------------

    $( document ).on( 'blur', '.abilityhub-textarea--code', function () {
        const $el = $( this );
        const val = $el.val().trim();
        if ( ! val || val === '{}' ) {
            return;
        }
        try {
            $el.val( formatJson( JSON.parse( val ) ) );
        } catch ( e ) {
            // Not valid JSON yet — leave as-is
        }
    } );

    // -------------------------------------------------------------------------
    // Dismiss admin notices
    // -------------------------------------------------------------------------

    $( document ).on( 'click', '.notice.is-dismissible .notice-dismiss', function () {
        $( this ).closest( '.notice' ).slideUp( 200, function () {
            $( this ).remove();
        } );
    } );

} ( jQuery, window.AbilityHub || {} ) );
