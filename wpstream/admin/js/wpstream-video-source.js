/*
 * Behaviour of the one video source component: which controls show for the chosen source,
 * the preview, captions, and picking up a finished upload. New Video and both editors mount it and
 * react to its changes for what only they do, such as saving or a suggested title.
 */
window.WpStreamVideoSource = {
    /**
     * Wire one component.
     *
     * @param {HTMLElement} root [data-video-source] element.
     * @param {function(boolean)} onChange Runs after every update; true when the person changed the source.
     * @return {{refresh: function(boolean=), externalUrl: function(): ?URL}} Refresh runs the first update once the screen has set its values.
     */
    mount(root, onChange) {
        const find = selector => root.querySelector(selector);
        const mode = find('[data-video-mode]'), source = find('[data-video-recording]'), external = find('[data-video-external]');
        const stage = find('[data-video-stage]'), preview = find('video'), placeholder = find('[data-video-placeholder]'), notice = find('[data-video-source-notice]'), loading = find('[data-video-loading]');
        const captions = find('[data-video-captions]'), removeCaptions = find('[data-caption-remove]');
        const text = root.dataset;
        let request = 0;

        /** Accept only absolute web URLs; native URL inputs also accept unsafe schemes. */
        function externalUrl() {
            try {
                const url = new URL(external.value.trim());
                return ['http:', 'https:'].includes(url.protocol) ? url : null;
            } catch (_) { return null; }
        }

        /** While a recording loads, its message takes the place of the placeholder in the preview box. */
        function showLoading(active) {
            loading.hidden = !active;
            placeholder.hidden = active;
        }

        /** Put the captions file on the preview, and offer Remove only when there is one. */
        function renderCaptions() {
            preview.querySelector('track')?.remove();
            removeCaptions.hidden = !captions.value;
            if (!captions.value) return;
            const track = root.ownerDocument.createElement('track');
            track.kind = 'captions'; track.label = 'Captions'; track.src = captions.value; track.default = true;
            preview.append(track);
        }

        /** Change the captions the way typing does, so the preview, saves and drafts all follow. */
        function setCaptions(url) {
            captions.value = url;
            captions.dispatchEvent(new Event('change', {bubbles: true}));
        }

        /** Show the controls for the chosen source, then load its preview. */
        async function refresh(changed = false) {
            const version = ++request;
            preview.pause(); preview.removeAttribute('src'); preview.load(); preview.hidden = true;
            showLoading(false); notice.textContent = '';
            find('[data-video-upload-fields]').hidden = mode.value !== 'upload';
            find('[data-video-recording-fields]').hidden = mode.value !== 'recording';
            find('[data-video-external-fields]').hidden = mode.value !== 'external';
            // An upload has nothing to preview until its file is ready.
            stage.hidden = mode.value === 'upload' && !source.value;
            renderCaptions();
            onChange(changed);
            if (mode.value === 'external') {
                const url = externalUrl();
                if (url) { preview.src = url.href; preview.hidden = false; placeholder.hidden = true; }
                else if (external.value.trim()) { notice.textContent = text.textInvalidUrl; }
                return;
            }
            if (!source.value) return;
            showLoading(true);
            try {
                const data = await jQuery.ajax({url: ajaxurl, type: 'POST', dataType: 'json', data: {
                    action: 'wpstream_get_download_link', video_name: source.value,
                    security: wpstream_admin_control_vars.recordings_nonce
                }});
                if (version !== request) return;
                if (!data.success || !data.url || !/^https?:\/\//i.test(data.url)) throw new Error();
                loading.hidden = true; preview.src = data.url; preview.hidden = false;
            } catch (_) { if (version === request) { showLoading(false); notice.textContent = text.textPreviewError; } }
        }

        mode.addEventListener('change', () => refresh(true));
        source.addEventListener('change', () => refresh(true));
        external.addEventListener('input', () => refresh(true));
        preview.addEventListener('error', () => { notice.textContent = mode.value === 'external' ? text.textExternalError : text.textPreviewError; });
        captions.addEventListener('change', () => { renderCaptions(); onChange(true); });
        find('[data-caption-picker]').addEventListener('click', () => {
            const frame = wp.media({title: text.textCaptionsTitle, multiple: false, library: {type: 'text/vtt'}});
            frame.on('select', () => {
                const file = frame.state().get('selection').first().toJSON();
                if (/\.vtt(?:[?#]|$)/i.test(file.url)) { notice.textContent = ''; setCaptions(file.url); }
                else { notice.textContent = text.textCaptionsType; }
            });
            frame.open();
        });
        removeCaptions.addEventListener('click', () => setCaptions(''));
        // A finished upload becomes the selected video.
        document.addEventListener('wpstream:recording-ready', event => {
            const name = event.detail && event.detail.name;
            if (typeof name !== 'string' || !name || !root.isConnected) return;
            if (!Array.from(source.options).some(option => option.value === name)) source.add(new Option(name, name));
            source.value = name;
            mode.value = 'upload';
            refresh(true);
        });
        return {refresh, externalUrl};
    }
};
