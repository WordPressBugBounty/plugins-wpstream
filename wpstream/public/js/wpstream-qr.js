/*global qrcode*/
/*
 * wpstream-qr.js
 *
 * Local, in-browser QR-code generation for the Larix "scan to configure" flow.
 *
 * Previously the Larix QR was built by sending the full RTMP URL + secret stream
 * key to a third-party image service (qrcode.tec-it.com) as a query parameter,
 * disclosing the key to that provider and its logs (SEC-05 / TASK-06). This module
 * generates the QR entirely on the client from the bundled `qrcode-generator`
 * library, so no credential ever leaves the page.
 *
 * Single public entry point, used by both start_streaming.js call sites:
 *   wpstreamRenderQr( imgElement, text )
 */

/**
 * Render `text` as a QR code into an <img> element, using a locally bundled
 * generator. Produces a `data:image/gif` URI and assigns it to the image's src;
 * nothing is requested from the network.
 *
 * @param {HTMLImageElement|jQuery} imgElement The target <img> (raw node or jQuery-wrapped).
 * @param {string}                  text       The payload to encode (e.g. the Larix deep-link).
 * @returns {boolean} true if a QR was rendered, false if inputs/library were unusable.
 */
function wpstreamRenderQr( imgElement, text ) {
    // Normalise a jQuery-wrapped element down to the underlying DOM node.
    var img = ( imgElement && imgElement.jquery ) ? imgElement.get( 0 ) : imgElement;

    // Fail closed on missing target, empty payload, or an unloaded library —
    // never fall back to a remote generator.
    if ( ! img || typeof text !== 'string' || text === '' || typeof qrcode !== 'function' ) {
        return false;
    }

    // Build the QR: typeNumber 0 auto-selects the smallest size that fits the data,
    // error-correction level 'M' matches the density the old service produced.
    var qr = qrcode( 0, 'M' );
    qr.addData( text );
    qr.make();

    // cellSize 4px, 8px quiet-zone margin — a compact, scannable image for the modal.
    img.src = qr.createDataURL( 4, 8 );
    return true;
}

// Expose on window so it is reachable from start_streaming.js (no module system here).
window.wpstreamRenderQr = wpstreamRenderQr;
