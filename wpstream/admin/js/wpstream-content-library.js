/* Presentation only. Saves post back to WordPress; recording actions reuse admin utilities. */
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.wpstream-library');
    if (!root) return;
    const usageItems = Array.from(root.querySelectorAll('.library-usage-item'));
    usageItems.forEach(item => item.addEventListener('toggle', () => {
        if (item.open) usageItems.forEach(other => { if (other !== item) other.open = false; });
    }));
    document.addEventListener('click', event => {
        usageItems.forEach(item => { if (!item.contains(event.target)) item.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        usageItems.forEach(item => {
            if (item.open) { item.open = false; item.querySelector('summary').focus(); }
        });
    });
    // Preserve native GET submission while applying select changes and committed searches immediately.
    const filters = root.querySelector('.library-filters');
    if (filters) {
        filters.querySelectorAll('select,input[type=search]').forEach(input => {
            input.addEventListener('change', () => filters.requestSubmit());
        });
    }
    // A row menu closes on a click outside it or on any of its items. Rows can be replaced after a save, so menus are looked up each time.
    document.addEventListener('click', event => {
        const inside = event.target.closest('.library-menu');
        root.querySelectorAll('.library-menu[open]').forEach(menu => {
            if (menu !== inside || event.target.closest('.library-menu-list')) menu.open = false;
        });
    });
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-recording-preview]');
        if (!button) return;
        const row = button.closest('.wpstream_video_wrapper');
        // A row can name the cell that has room for the player.
        const host = row.querySelector('[data-preview-host]') || (row.tagName === 'TR' ? row.lastElementChild : row);
        // Only the preview player counts; a thumbnail in the tile is a video element too.
        let player = host.querySelector('video.library-preview');
        // The source card's button names what a click will do; other preview buttons keep one label.
        const showState = open => {
            button.setAttribute('aria-expanded', String(open));
            if (button.dataset.labelShow) button.textContent = open ? button.dataset.labelHide : button.dataset.labelShow;
        };
        if (player) { player.pause(); player.remove(); showState(false); return; }
        const notice = row.querySelector('.wpstream_video_notice');
        button.disabled = true;
        notice.textContent = wpstream_library_vars.loading;
        try {
            const data = await jQuery.ajax({ url: ajaxurl, type: 'POST', dataType: 'json', data: {
                action: 'wpstream_get_download_link', video_name: button.dataset.filename,
                security: wpstream_admin_control_vars.recordings_nonce
            } });
            if (!data.success || !data.url || !/^https?:\/\//i.test(data.url)) throw new Error(data.error || wpstream_library_vars.previewError);
            player = document.createElement('video');
            player.controls = true; player.playsInline = true; player.preload = 'metadata'; player.className = 'library-preview';
            player.setAttribute('aria-label', button.dataset.filename);
            player.addEventListener('error', () => { notice.textContent = wpstream_library_vars.previewError; });
            player.src = data.url; host.append(player);
            showState(true); notice.textContent = '';
        } catch (error) { notice.textContent = error.message || wpstream_library_vars.previewError; }
        finally { button.disabled = false; }
    });
    // A stored file shows its own frame. The cloud keeps no thumbnails, so the tile loads a short, muted
    // preview of the file once its row is on screen, and seeks a second in to get past a black opening.
    // The preview links are asked for one at a time: ten at once would hold every PHP worker and stall the next page.
    const tiles = Array.from(root.querySelectorAll('[data-recording-thumb]'));
    if (tiles.length && 'IntersectionObserver' in window) {
        const waiting = [];
        let asking = false;
        /** Keep the frame on this site, so the file is never read again just to show its picture. */
        const saveFrame = (tile, frame) => {
            if (!frame.videoWidth || !frame.videoHeight) return;
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 160; canvas.height = 90;
                // Fill the tile: the side the picture has too much of is cropped evenly.
                const scale = Math.max(canvas.width / frame.videoWidth, canvas.height / frame.videoHeight);
                const width = frame.videoWidth * scale, height = frame.videoHeight * scale;
                canvas.getContext('2d').drawImage(frame, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
                jQuery.ajax({ url: ajaxurl, type: 'POST', data: {
                    action: 'wpstream_save_media_thumb', video_name: tile.dataset.recordingThumb, image: canvas.toDataURL('image/jpeg', 0.8),
                    security: wpstream_admin_control_vars.recordings_nonce
                } });
            } catch (error) { /* A frame that cannot be read is shown this once and read again next time. */ }
        };
        const loadFrame = async tile => {
            try {
                const data = await jQuery.ajax({ url: ajaxurl, type: 'POST', dataType: 'json', data: {
                    action: 'wpstream_get_download_link', video_name: tile.dataset.recordingThumb,
                    security: wpstream_admin_control_vars.recordings_nonce
                } });
                if (!data.success || !data.url || !/^https?:\/\//i.test(data.url)) return;
                const frame = document.createElement('video');
                frame.muted = true; frame.playsInline = true; frame.preload = 'metadata'; frame.tabIndex = -1;
                // Reading pixels out of the frame needs a cross-origin load, which the storage allows.
                frame.crossOrigin = 'anonymous';
                frame.addEventListener('loadedmetadata', () => { frame.currentTime = Math.min(1, frame.duration / 10) || 0.001; });
                frame.addEventListener('loadeddata', () => tile.classList.add('has-frame'));
                frame.addEventListener('seeked', () => saveFrame(tile, frame), { once: true });
                frame.addEventListener('error', () => frame.remove());
                frame.src = data.url; tile.append(frame);
            } catch (error) { /* The pale icon stays. */ }
        };
        const loadNext = async () => {
            if (asking || !waiting.length) return;
            asking = true;
            await loadFrame(waiting.shift());
            asking = false;
            loadNext();
        };
        const watcher = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            watcher.unobserve(entry.target);
            waiting.push(entry.target);
            loadNext();
        }), { rootMargin: '200px' });
        tiles.forEach(tile => watcher.observe(tile));
    }
    root.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        // Escape closes an open menu first, and only then an open editor.
        const menu = root.querySelector('.library-menu[open]');
        if (menu) { menu.open = false; menu.querySelector('summary').focus(); return; }
        closeEditors();
    });
    function closeEditors() {
        root.querySelectorAll('.library-editor').forEach(row => { row.hidden = true; });
        root.querySelectorAll('.library-row-toggle').forEach(button => button.setAttribute('aria-expanded', 'false'));
        root.querySelectorAll('video').forEach(player => player.pause());
    }
    if (!root.querySelector('[data-library-status]')) return;
    /** Quick edit lives in a closed menu, so focus returns to the menu button that opened it. */
    function focusRowMenu(editorId) {
        root.querySelector('[aria-controls="' + editorId + '"]').closest('.library-menu').querySelector('summary').focus();
    }
    root.addEventListener('click', function (event) {
        const toggle = event.target.closest('.library-row-toggle');
        if (toggle) {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            closeEditors();
            const editor = document.getElementById(toggle.getAttribute('aria-controls'));
            editor.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) {
                editor.querySelector('input[name=library_title]').focus();
                // Opening a video exposes its file preview without another action.
                if (!editor.querySelector('video')) editor.querySelector('[data-recording-preview]:not(:disabled)')?.click();
            }
        }
        if (event.target.closest('[data-library-close]')) {
            const row = event.target.closest('.library-editor');
            closeEditors();
            row.querySelector('form').reset();
            // Reset restores values, but change-driven price and billing fields also need syncing.
            row.querySelector('[name=library_access]').dispatchEvent(new Event('change'));
            focusRowMenu(row.id);
        }
    });
    /** Show only the price and subscription fields of the chosen access. */
    function bindAccess(select) {
        const sync = () => {
            const form = select.closest('form');
            const mode = form.querySelector('[name=library_subscription_mode]')?.value;
            // A choice this site cannot sell yet explains what it needs and cannot be saved.
            const unavailable = 'unavailable' in select.selectedOptions[0].dataset;
            form.querySelectorAll('[data-access-requirement]').forEach(panel => { panel.hidden = !unavailable || panel.dataset.accessRequirement !== select.value; });
            form.querySelector('[type=submit]').disabled = unavailable;
            const subscription = select.value === 'subscription' && !unavailable;
            const priced = (select.value === 'paid' && !unavailable) || (subscription && mode !== 'existing');
            const groups = [['[data-library-price]', priced], ['[data-library-subscription]', subscription], ['[data-library-plan-fields]', subscription && mode === 'existing'], ['[data-library-plan-name]', subscription && mode === 'create'], ['[data-library-billing]', subscription && mode !== 'existing']];
            groups.forEach(([selector, visible]) => {
                const group = form.querySelector(selector);
                if (!group) return;
                group.hidden = !visible;
                group.querySelectorAll('input,select').forEach(input => { input.disabled = !visible; input.required = visible; });
            });
        };
        select.addEventListener('change', sync);
        select.closest('form').querySelector('[name=library_subscription_mode]')?.addEventListener('change', sync);
        sync();
    }
    root.querySelectorAll('[name=library_access]').forEach(bindAccess);
    // Row Save stays on the page and swaps in the row the server sends back.
    root.addEventListener('submit', async event => {
        const form = event.target.closest('.library-edit-form');
        if (!form) return;
        event.preventDefault();
        const editor = form.closest('.library-editor');
        const notice = form.querySelector('[data-library-row-notice]');
        const button = form.querySelector('[type=submit]');
        const body = new FormData(form);
        body.append('action', 'wpstream_library_save');
        const buttonLabel = button.textContent;
        button.disabled = true;
        button.textContent = wpstream_library_vars.saving;
        notice.textContent = '';
        try {
            const response = await fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body });
            const result = await response.json();
            if (!result.success) throw new Error(result.data && result.data.message);
            const fresh = document.createElement('template');
            fresh.innerHTML = result.data.html;
            const editorId = fresh.content.querySelector('.library-editor').id;
            editor.previousElementSibling.remove();
            editor.replaceWith(fresh.content);
            bindAccess(document.getElementById(editorId).querySelector('[name=library_access]'));
            // The new row's file buttons have no click handlers yet. Rebinding drops the old ones first, so none runs twice.
            jQuery('.wpstream_get_download_link, .wpstream_delete_media').off('click');
            WpStreamUtils.generate_download_link();
            WpStreamUtils.generate_delete_link();
            root.querySelector('[data-library-status]').textContent = result.data.message;
            // The status line can be off screen on a long list, so the saved row confirms it too.
            const saved = document.createElement('span');
            saved.className = 'library-row-saved';
            saved.textContent = result.data.message;
            document.getElementById(editorId).previousElementSibling.querySelector('.library-title-line').append(saved);
            setTimeout(() => saved.remove(), 5000);
            focusRowMenu(editorId);
        } catch (error) {
            notice.textContent = error.message || wpstream_library_vars.saveError;
            button.textContent = buttonLabel;
            button.disabled = false;
        }
    });
});
