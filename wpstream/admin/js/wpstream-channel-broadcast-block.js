/*
 * Puts the Broadcast tool directly under the title in the block editor.
 * The title lives inside the editor's canvas frame, so the tool has to be a block
 * in that frame. It is locked in first place and saves no markup of its own.
 */
((wp, config) => {
    'use strict';
    const {createElement: h, useEffect, useRef} = wp.element;
    const BLOCK = 'wpstream/channel-broadcast';

    wp.blocks.registerBlockType(BLOCK, {
        apiVersion: 2, title: 'Broadcast', icon: 'video-alt3', category: 'media',
        supports: {html: false, multiple: false, reusable: false, inserter: false},
        edit: function () {
            const ref = useRef();
            // The broadcaster reports its height to the window around it, which here is the canvas frame.
            useEffect(() => {
                const canvas = ref.current.ownerDocument.defaultView;
                const resize = event => {
                    if (event.origin !== canvas.location.origin || event.data?.type !== 'wpstream-broadcaster-height') return;
                    const frame = ref.current.querySelector('.wpstream-broadcast-frame');
                    if (frame && event.source === frame.contentWindow && Number.isFinite(event.data.height)) {
                        frame.style.height = Math.max(350, Math.min(5000, event.data.height)) + 'px';
                    }
                };
                canvas.addEventListener('message', resize);
                return () => canvas.removeEventListener('message', resize);
            }, []);
            return h('div', {...wp.blockEditor.useBlockProps({className: 'wpstream-channel-broadcast-block'}), ref}, h(wp.element.RawHTML, null, config.html));
        },
        save: () => null
    });

    // Add the block once the editor is ready, unless the saved channel already has it.
    wp.plugins.registerPlugin('wpstream-channel-broadcast', {render: function () {
        const ready = wp.data.useSelect(select => select('core/editor').__unstableIsEditorReady(), []);
        const registry = wp.data.useRegistry();
        useEffect(() => {
            if (!ready) return;
            const editor = registry.select('core/block-editor');
            const blocks = editor.getBlocks();
            if (blocks.some(block => block.name === BLOCK)) return;
            const dispatch = registry.dispatch('core/block-editor');
            const inserted = [wp.blocks.createBlock(BLOCK, {lock: {move: true, remove: true}})];
            // An empty channel still needs a paragraph to type into.
            if (!blocks.length) inserted.push(wp.blocks.createBlock('core/paragraph'));
            // Opening a channel is not an edit, so this must not trigger an unsaved changes warning.
            dispatch.__unstableMarkNextChangeAsNotPersistent();
            dispatch.insertBlocks(inserted, 0, undefined, false);
        }, [ready]);
        return null;
    }});
})(window.wp, window.wpstreamChannelBroadcast);
