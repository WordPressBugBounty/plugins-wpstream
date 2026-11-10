/* Isolated iframe mounts keep media controls out of editor forms and legacy runtimes. */
(() => {
 'use strict';
 document.getElementById('wpstream-workspace-channel')?.addEventListener('change', event => { window.location.assign(event.target.value); });
 document.querySelector('.wpstream-channel-picker .button')?.addEventListener('click', event => { if (event.currentTarget.getAttribute('aria-disabled') === 'true') event.preventDefault(); });
 window.addEventListener('message', event => {
  if (event.origin !== window.location.origin) return;
  if (event.data?.type === 'wpstream-broadcaster-lock' && Array.from(document.querySelectorAll('.wpstream-broadcast-frame')).some(frame => frame.contentWindow === event.source)) {
   const picker = document.getElementById('wpstream-workspace-channel'); if(picker) picker.disabled = Boolean(event.data.locked);
   const add = document.querySelector('.wpstream-channel-picker .button'); if(add) {add.setAttribute('aria-disabled',String(Boolean(event.data.locked)));add.style.pointerEvents=event.data.locked?'none':'';}
  }
  if (event.data?.type !== 'wpstream-broadcaster-height') return;
  document.querySelectorAll('.wpstream-broadcast-frame').forEach(frame => {
   if (event.source === frame.contentWindow && Number.isFinite(event.data.height)) frame.style.height = Math.max(350, Math.min(5000, event.data.height)) + 'px';
  });
 });
})();
