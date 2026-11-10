/* Host-owned navigation and explicit backend shutdown; media remains module-owned. */
document.addEventListener('DOMContentLoaded', () => {
 'use strict';
 const cfg = window.wpstreamBroadcastHost, api = window.wpstreamPortal?.broadcaster;
 const button = document.getElementById('wpstream-backend-stop'), message = document.getElementById('wpstream-backend-status');
 if (!cfg || !api || !button) return;
 const stopControls = document.querySelector('[data-backend-stop]');
 const STATISTICS = ['viewers','duration','bitrate','health'];
 let peak = null, unavailableTimer = null;
 // Statistics cells still waiting 30 seconds after the last reset show NA instead of loading forever.
 function waitForStatistics() {
  clearTimeout(unavailableTimer);
  unavailableTimer = setTimeout(() => {
   document.querySelectorAll('[data-host-stat],[data-host-source]').forEach(node => { if (node.textContent === cfg.statsLoading) node.textContent = cfg.statsNa; });
  }, 30000);
 }
 // Write one module reading into its Statistics cells; Peak and Source follow Viewers.
 function showStatistic(name, value) {
  document.querySelectorAll('[data-host-stat="' + name + '"]').forEach(node => { node.textContent = value; });
  if (value === cfg.statsLoading) waitForStatistics();
  if (name !== 'viewers') return;
  const peakCell = document.querySelector('[data-host-stat="peak"]'), sourceCell = document.querySelector('[data-host-source]');
  if (value === cfg.statsNa) {
   peak = null; peakCell.textContent = value; sourceCell.textContent = value;
  } else if (value === cfg.statsLoading) {
   if (peak === null) peakCell.textContent = value;
   if (sourceCell.textContent === cfg.statsNa) sourceCell.textContent = value;
  } else if (Number.isFinite(Number(value))) {
   peak = Math.max(peak || 0, Number(value));
   peakCell.textContent = String(peak);
  }
 }
 document.addEventListener('wpstream-portal-bc:state', event => {
  if(window.parent !== window) window.parent.postMessage({type:'wpstream-broadcaster-lock',locked:['turning-on','live','reconnecting'].includes(event.detail?.state)},window.location.origin);
 });
 document.addEventListener('wpstream-portal-bc:statistics', event => {
  if (STATISTICS.includes(event.detail?.name)) showStatistic(event.detail.name, event.detail.value);
 });
 // The module booted before this listener existed, so adopt what it shows now.
 Object.entries(api.getStatistics()).forEach(([name, value]) => showStatistic(name, value));
 const histories = {viewers:[],bitrate:[]}, ranges = {viewers:300,bitrate:300};
 function drawChart(metric) {
  const svg = document.querySelector('[data-host-chart="'+metric+'"]'); if (!svg) return;
  const all = histories[metric].filter(point => Number.isFinite(Number(point.val)) && Number.isFinite(Number(point.time)));
  const latest = all.length ? Number(all[all.length-1].time) : 0;
  const unit = latest > 1e12 ? 1000 : 1;
  const values = all.filter(point => Number(point.time) >= latest-ranges[metric]*unit);
  svg.replaceChildren(); document.querySelector('[data-chart-empty="'+metric+'"]').hidden = values.length > 0;
  if (!values.length) return;
  const max = Math.max(1,...values.map(point=>Number(point.val)));
  const start = latest-ranges[metric]*unit;
  const line = document.createElementNS('http://www.w3.org/2000/svg','polyline');
  line.setAttribute('points',values.map(point=>[10+580*(Number(point.time)-start)/(ranges[metric]*unit),170-160*Number(point.val)/max].join(',')).join(' '));
  line.setAttribute('fill','none'); line.setAttribute('stroke','currentColor'); line.setAttribute('stroke-width','2'); svg.append(line);
  svg.setAttribute('aria-label',metric+'; '+values.length+' readings; peak '+(metric==='bitrate'?(max/1000).toFixed(0)+' kbps':max));
 }
 document.addEventListener('wpstream-portal-bc:history', event => {
  for (const metric of ['viewers','bitrate']) { histories[metric] = Array.isArray(event.detail?.[metric]) ? event.detail[metric] : []; drawChart(metric); }
 });
 document.addEventListener('wpstream-portal-bc:source', event => { if(event.detail?.kind && document.querySelector('[data-host-stat="viewers"]').textContent !== cfg.statsNa) document.querySelector('[data-host-source]').textContent = event.detail.kind; });
 document.querySelectorAll('[data-chart-range]').forEach(control=>control.addEventListener('click',()=>{
  ranges[control.dataset.chart]=Number(control.dataset.chartRange);
  document.querySelectorAll('[data-chart="'+control.dataset.chart+'"]').forEach(node=>node.setAttribute('aria-pressed',String(node===control)));
  drawChart(control.dataset.chart);
 }));
 document.querySelector('[data-copy-channel]')?.addEventListener('click', async()=>{
  const input = document.querySelector('.wpstream-host-share input');
  try { await navigator.clipboard.writeText(input.value); document.querySelector('[data-copy-status]').textContent=cfg.copied; }
  catch { input.select(); document.querySelector('[data-copy-status]').textContent=cfg.copyManually; }
 });
 const form = document.getElementById('wpstream-broadcast-settings');
 let disposed = false;
 document.querySelectorAll('[data-workspace-tab]').forEach(tab => tab.addEventListener('click', () => {
  document.querySelectorAll('[data-workspace-tab]').forEach(item => item.setAttribute('aria-pressed', String(item === tab)));
  document.querySelectorAll('[data-workspace-panel]').forEach(panel => { panel.hidden = panel.dataset.workspacePanel !== tab.dataset.workspaceTab; });
 }));
 async function refreshSettings() {
  try {
   const status = await api.getChannelStatus(cfg.channelId, cfg.domain);
   // The same read lets the module pick up a meter that appeared after page load.
   if (status.success) api.updateMeter(cfg.channelId, status);
   // There is nothing to stop once the channel has stopped, or when its status cannot be read.
   stopControls.hidden = !status.success || status.status === 'stopped';
   form.querySelector('fieldset').disabled = !status.success || status.status !== 'stopped';
   // A failed read says nothing about whether the channel runs, so only a running channel asks to be stopped.
   form.querySelector('[data-settings-status]').textContent = !status.success ? cfg.statusUnavailable : status.status === 'stopped' ? '' : cfg.settingsLocked;
  } catch (_) { form.querySelector('fieldset').disabled = true; stopControls.hidden = true; form.querySelector('[data-settings-status]').textContent = cfg.statusUnavailable; }
  if (!disposed) setTimeout(refreshSettings, 5000);
 }
 form.addEventListener('submit', async event => {
  event.preventDefault();
  const data = new URLSearchParams({action:'wpstream_broadcaster_settings', nonce:cfg.nonce,channel_id:cfg.channelId,domain:cfg.domain});
  form.querySelectorAll('input[type=checkbox]').forEach(input => data.set('options[' + input.name + ']', input.checked ? '1' : '0'));
  form.querySelector('fieldset').disabled = true;
  try {
   const result = await fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(r => r.json());
   if (!result.success) throw new Error(result.error || cfg.error);
   form.querySelectorAll('input[type=checkbox]').forEach(input => {input.checked = Boolean(Number(result.options[input.name]));});
   form.querySelector('[data-settings-status]').textContent = cfg.saved;
  } catch (error) {form.querySelector('[data-settings-status]').textContent = error.message;}
 });
 window.addEventListener('pagehide', () => {disposed = true;});
 refreshSettings();
 button.addEventListener('click', async () => {
  if (!window.confirm(cfg.confirmStop)) return;
  button.disabled = true; message.textContent = cfg.stopping;
  try {
   await api.stop();
   const result = await fetch(cfg.ajaxUrl, {method:'POST', credentials:'same-origin', body:new URLSearchParams({action:'wpstream_broadcaster_stop',nonce:cfg.nonce,channel_id:cfg.channelId,domain:cfg.domain})}).then(r => r.json());
   if (!result.connected) throw new Error(result.error || cfg.error);
   for (let attempt = 0; attempt < 40; attempt++) {
    const status = await api.getChannelStatus(cfg.channelId, cfg.domain);
    if (status.success && status.status === 'stopped') { api.onChannelChange(cfg.channelId, status); message.textContent = cfg.stopped; return; }
    await new Promise(resolve => setTimeout(resolve, 3000));
   }
   throw new Error(cfg.error);
  } catch (error) { message.textContent = error.message || cfg.error; }
  finally { button.disabled = false; }
 });
 document.addEventListener('wpstream-portal-bc:channel-create', () => { if (cfg.createUrl) window.top.location.href = cfg.createUrl; });
 if (window.parent !== window) {
  const root = document.querySelector('.wpstream-broadcast-workspace');
  new ResizeObserver(() => window.parent.postMessage({type:'wpstream-broadcaster-height',height:Math.ceil(root.getBoundingClientRect().height + 24)}, window.location.origin)).observe(root);
 }
});
