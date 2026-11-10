/* Editor video panel: the shared video source component, plus native WordPress saves. */
(function ($, wp) {
    'use strict';
    const config = window.wpstreamNativeVideo;
    if (!config) return;
    function mount(panel, persist) {
        if (!panel || panel.dataset.mounted) return;
        panel.dataset.mounted = 'true';
        const find = selector => panel.querySelector(selector);
        const mode = find('[data-video-mode]'), source = find('[data-video-recording]');
        const external = find('[data-video-external]'), type = find('[name=wpstream_product_type]');
        const captions = find('[data-video-captions]');
        if (config.block) {
            const meta = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
            source.value = config.preset || meta.wpstream_free_video || '';
            external.value = meta.wpstream_free_video_external || '';
            captions.value = meta.wpstream_closed_captions_file || '';
            mode.value = meta.wpstream_product_type === '3' ? 'external' : (source.value ? 'recording' : 'upload');
        }
        function save() {
            type.value = mode.value === 'external' ? '3' : '2';
            if (config.product) {
                const field = document.getElementById('_movie_url');
                const recording = type.value === '3' ? '' : source.value;
                if (field) { if (![...field.options].some(o => o.value === recording)) field.add(new Option(recording, recording)); $(field).val(recording).trigger('change'); }
            } else {
                persist({wpstream_product_type: type.value, wpstream_free_video: type.value === '2' ? source.value : '', wpstream_free_video_external: type.value === '3' ? external.value : '', wpstream_closed_captions_file: captions.value});
            }
        }
        /** Save a change the person made, and keep the toolbar's name in step with the source. */
        const videoSource = WpStreamVideoSource.mount(find('[data-video-source]'), changed => {
            if (changed) save();
            find('[data-video-filename]').textContent = mode.value === 'external' ? 'External video' : source.value || 'Choose a video';
        });
        if (config.product) {
            // WordPress prints the panel above the editor, so on products it is moved directly under Product data.
            // Dragging another box can land between the two, so the position is restored after every drag.
            function placeUnderProductData() {
                const productData = document.getElementById('woocommerce-product-data');
                if (productData && productData.nextElementSibling !== panel) productData.after(panel);
            }
            placeUnderProductData();
            $('.meta-box-sortables').on('sortstop', placeUnderProductData);
            function productVisibility() {
                const productType = $('#product-type').val();
                panel.hidden = !(productType === 'video_on_demand' || (productType === 'subscription' && $('#_subscript_live_event').val() === 'no'));
                panel.querySelectorAll('input,select,button').forEach(input => { input.disabled = panel.hidden; });
                $('._movie_url_field').toggle(panel.hidden);
            }
            if (new URLSearchParams(location.search).get('product_type') === 'video_on_demand' || config.preset) $('#product-type').val('video_on_demand').trigger('change');
            productVisibility();
            if (!panel.hidden) save();
            // Bound after the first reveal above, so only a type the person selects scrolls the page.
            $('#product-type,#_subscript_live_event').on('change', function () {
                const comingBack = panel.hidden;
                productVisibility();
                // The panel sits below Product data, so bring it into view when it appears, clear of the admin bar.
                if (comingBack && !panel.hidden) window.scrollTo({top: panel.getBoundingClientRect().top + window.scrollY - 60, behavior: 'smooth'});
            });
        }
        window.WpStreamUtils?.initializeUpload(panel.ownerDocument);
        if (config.preset && config.block) save();
        videoSource.refresh();
        // Gutenberg may unmount its iframe panel, so it can be mounted again.
        return () => { delete panel.dataset.mounted; };
    }
    if (config.block && !config.product) {
        const {createElement: h, useEffect, useRef} = wp.element;
        wp.blocks.registerBlockType('wpstream/video-source', {
            apiVersion: 2, title: 'Video source and preview', icon: 'video-alt3', category: 'media',
            supports: {html: false, multiple: false, reusable: false},
            edit: function () {
                const ref = useRef();
                useEffect(() => mount(ref.current.querySelector('.editor-video-panel'), meta => wp.data.dispatch('core/editor').editPost({meta})), []);
                return h('div', {...wp.blockEditor.useBlockProps({className: 'wpstream-native-video-block'}), ref}, h(wp.element.RawHTML, null, config.html));
            }, save: () => null
        });
        wp.plugins.registerPlugin('wpstream-native-video-panel', {render: function () {
            const {blocks, ready} = wp.data.useSelect(select => ({blocks: select('core/block-editor').getBlocks(), ready: select('core/editor').__unstableIsEditorReady()}), []);
            const {insertBlocks} = wp.data.useDispatch('core/block-editor');
            const registry = wp.data.useRegistry();
            useEffect(() => { if (ready && !registry.select('core/block-editor').getBlocks().some(b => b.name === 'wpstream/video-source')) insertBlocks([wp.blocks.createBlock('wpstream/video-source', {lock: {move: true, remove: true}}), ...(blocks.length ? [] : [wp.blocks.createBlock('core/paragraph')])], 0); }, [ready, blocks]);
            return null;
        }});
    } else { $(function () { mount(document.querySelector('.editor-video-panel'), () => {}); }); }
})(jQuery, window.wp);
