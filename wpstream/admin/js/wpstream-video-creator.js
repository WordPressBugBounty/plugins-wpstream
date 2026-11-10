/* New Video and New Channel forms. The video source itself is the shared component in wpstream-video-source.js. */
document.addEventListener('DOMContentLoaded', () => {
    const draftKey = 'wpstream_video_draft';
    // The published card has no form, so its Copy link button is wired first.
    const copyLink = document.querySelector('[data-copy-link]');
    if (copyLink) {
        // The video now exists, so New video must start empty.
        try { sessionStorage.removeItem(draftKey); } catch (_) { /* Storage can be blocked. */ }
        const status = document.querySelector('[data-copy-status]');
        copyLink.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(copyLink.dataset.copyLink);
                status.textContent = wpstream_library_vars.linkCopied;
            } catch (_) {
                // The clipboard is unavailable on non-HTTPS admin pages: show the link to copy by hand.
                status.textContent = copyLink.dataset.copyLink;
            }
        });
    }
    const form = document.getElementById('library-video-form');
    if (!form) return;
    const source = document.getElementById('video-source');
    const sourceMode = document.getElementById('video-source-mode');
    const external = document.getElementById('video-external-url');
    const workspace = document.getElementById('library-new-video');
    const submit = form.querySelector('[type=submit]');
    const title = document.getElementById('video-title');
    let suggestedTitle = '';
    let videoSource = null;
    /** Keep inactive source fields out of native validation and FormData. */
    function updateSourceFields() {
        source.disabled = sourceMode.value === 'external';
        source.required = !source.disabled;
        external.disabled = sourceMode.value !== 'external';
        external.required = !external.disabled;
        external.setCustomValidity(!external.disabled && external.value.trim() && !videoSource.externalUrl() ? workspace.querySelector('[data-video-source]').dataset.textInvalidUrl : '');
    }
    function updateAccess() {
        const choice = form.querySelector('[name=video_access]:checked');
        const access = choice.value;
        // A choice this site cannot sell yet explains what it needs and cannot be published.
        const unavailable = 'unavailable' in choice.dataset;
        form.querySelectorAll('[data-access-requirement]').forEach(panel => { panel.hidden = !unavailable || panel.dataset.accessRequirement !== access; });
        for (const [selector, visible] of [['[data-video-price]', access === 'paid'], ['[data-video-subscription]', access === 'subscription']]) {
            const section = form.querySelector(selector);
            if (!section) continue;
            section.hidden = !visible;
            section.querySelectorAll('input,select').forEach(input => { input.disabled = !visible; input.required = visible; });
        }
        // Only the selected subscription path participates in validation or posting.
        const mode = form.querySelector('[name=video_subscription_mode]')?.value;
        for (const [selector, selected] of [['[data-video-plan-fields]', mode === 'existing'], ['[data-video-new-plan-fields]', mode === 'create']]) {
            const group = form.querySelector(selector);
            if (!group) continue;
            group.hidden = !selected;
            group.querySelectorAll('input,select').forEach(input => { input.disabled = access !== 'subscription' || !selected; input.required = !input.disabled; });
        }
        // A new video needs a source and a name. Channels have no source and keep the browser's own name check.
        const needsVideo = !!sourceMode && (sourceMode.value === 'external' ? !videoSource.externalUrl() : !source.value);
        const needsName = !!sourceMode && !title.value.trim();
        submit.disabled = unavailable || needsVideo || needsName;
        // Say what is still missing. An unavailable choice already explains itself in its own panel.
        const requirement = form.querySelector('[data-video-requirement]');
        if (requirement) requirement.textContent = needsVideo && needsName ? wpstream_library_vars.requireVideoAndName : (needsVideo ? wpstream_library_vars.requireVideo : (needsName ? wpstream_library_vars.requireName : ''));
    }
    form.querySelectorAll('[name=video_access]').forEach(input => input.addEventListener('change', updateAccess));
    form.querySelector('[name=video_subscription_mode]')?.addEventListener('change', updateAccess);
    if (!sourceMode) {
        updateAccess();
        form.addEventListener('submit', () => { submit.disabled = true; });
        return;
    }
    /** Keep the unfinished video for this browser session, so leaving the page does not lose it. */
    function saveDraft() {
        const values = {};
        for (const field of form.elements) {
            if (!field.name || field.type === 'hidden' || field.type === 'submit') continue;
            if (field.type !== 'radio' || field.checked) values[field.name] = field.value;
        }
        try { sessionStorage.setItem(draftKey, JSON.stringify(values)); } catch (_) { /* Storage can be blocked; the form still works. */ }
    }
    /** Put a kept draft back. A value that no longer exists, such as a deleted recording, is skipped. */
    function restoreDraft() {
        let values = null;
        try { values = JSON.parse(sessionStorage.getItem(draftKey)); } catch (_) { return; }
        if (!values) return;
        for (const field of form.elements) {
            if (!field.name || !(field.name in values)) continue;
            if (field.type === 'radio') field.checked = field.value === values[field.name];
            else if (field.tagName !== 'SELECT' || Array.from(field.options).some(option => option.value === values[field.name])) field.value = values[field.name];
        }
    }
    // A recording chosen from the list starts a new video instead of reopening the old draft.
    if (!new URLSearchParams(location.search).has('new_video_name')) restoreDraft();
    // The source fields sit outside the form element, so their events only reach the workspace.
    workspace.addEventListener('input', saveDraft);
    workspace.addEventListener('change', saveDraft);
    // Suggest filenames only while the user has not chosen their own title.
    function suggestTitle() {
        if (sourceMode.value === 'external' || !source.value) return;
        if (!title.value.trim() || title.value === suggestedTitle) {
            suggestedTitle = source.value.replace(/\.[^.]+$/, '').slice(0, title.maxLength > 0 ? title.maxLength : 120);
            title.value = suggestedTitle;
        }
    }
    videoSource = WpStreamVideoSource.mount(workspace.querySelector('[data-video-source]'), () => { updateSourceFields(); suggestTitle(); updateAccess(); });
    title.addEventListener('input', updateAccess);
    // The component selects a finished upload through script, which fires no input event to save.
    document.addEventListener('wpstream:recording-ready', saveDraft);
    form.addEventListener('submit', () => { submit.disabled = true; });
    videoSource.refresh();
});
