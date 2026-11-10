# WpStream plugin blueprint v2: review feedback

Feedback on the v2 clickable prototype in `work-on-blueprints/work-on-blueprints-2`, compared with v1 and with the implementation spec in `BAKER-AND-LIVE-DATA-SPEC.md`.

**How to read this.** Most of the feedback is subjective and is offered as opinion to weigh. Where a point is a firm recommendation rather than a preference, it is marked **Recommendation**. Everything else is open to the UX implementor's judgement.

**Overall.** This is really good work, and v2 is a significant step up from v1. Subjectively, it is also much cleaner than the parallel broadcaster work in the hello-wpstream dashboard theme that the v2 mapping page uses as its reference. The notes below are about refining it, not reworking it.

---

## 1. The broadcaster runs inside the main admin window

While a browser broadcast is running, any click that navigates the page ends the transmission. Keeping the broadcaster inline is a valid UX decision, but creators must be aware of the consequence.

What v2 already does: it blocks leaving while live or connecting through the browser's own leave-page prompt, disables Back, the channel picker and the source cards, and shows "Keep this page open while broadcasting". The WordPress sidebar links stay active, and the browser's prompt text cannot be customised, so the creator sees a generic "Leave site?" dialog with no mention of the broadcast.

Suggested, pick one or both:

1. Intercept navigation in the page itself, so any link click while live shows a WpStream-worded warning that leaving will interrupt the transmission.
2. If the design allows it, offer a way to open a skinny, minimal broadcaster in a new window or tab, so navigation in the main window is safe.

**Recommendation.** The same problem is sharper in the editor embedding. The broadcaster is loaded as an iframe inside the channel editor and inside the WooCommerce product editor. In the real classic product editor, Update is a full page post, so saving the product while live ends the broadcast. Either keep the embedded view read-only with a link to the broadcast page, or design the save path so it never reloads while a broadcast runs.

## 2. Explicit camera and microphone enable

Endorsed. The previous approach forced the browser permission dialog on page entry. v2 shows an in-interface button first, and only that click triggers the browser dialog. It costs an extra click but feels safer and less aggressive.

The v2 docs already state that production must request permission only from the explicit activation action, never on page entry or encoder selection. Please make sure that constraint survives into the production implementation, since the legacy broadcaster code does request access on load.

## 3. Browser versus streaming app

Praise. In v1 the switch between broadcasting from the browser and from an external app was not obvious. The two large cards at the top of the v2 workspace, "From this browser" marked as recommended and "From a streaming app" listing OBS, vMix and Larix, make the choice immediate. This is a strong improvement.

## 4. Changing channel settings while live

**Recommendation.** Four channel settings change the stream itself: record, low latency, adaptive bitrate and encrypt. Baker applies them only when an event starts, so a change made while the channel is live does not touch the running broadcast. To apply it, the plugin saves the settings and then stops the channel. The channel restarts by itself, with the same stream key, and the new setting applies to the new event. This was verified on the live platform on 2026-10-21: the channel was back on air about two and a half minutes after the stop, with the encoder left connected and nothing changed on it.

What this means for the interface:

- When one of those four settings is changed on a channel that is live, the interface must say, before the save, that the channel will be reset and that viewers may not see the feed for a while.
- Nothing about the stream key changes. Encoders keep working. Do not mention a new key.
- When the channel is not live, no warning is needed. The change simply applies to the next broadcast.
- Other settings, such as domain lock, viewer count, autoplay, mute, title and poster, never interrupt anything.

**Exact API calls on a settings change.** This is different from every previous approach, so it must be implemented precisely. The old flow only allowed these changes while the channel was off, and an earlier draft of the spec called a channel reset, which rotates the stream key. Neither applies any more. The plugin makes these Baker calls, and nothing else:

| Situation | Calls, in order |
|---|---|
| Any setting changed, channel not live | `channel/update` only |
| Only non-stream settings changed (domain lock, viewer count, autoplay, mute, title, description, poster, logo), live or not | `channel/update` only |
| Record, low latency, adaptive bitrate or encrypt changed value, channel live | `channel/update`, then, after it succeeded, `channel/stop` |

No `channel/reset`, no `channel/start`, no explicit turn-on. After the stop the channel restarts by itself with the stored settings, roughly two to three minutes later, and the interface only has to show the states as they pass through stopped, starting and on air. The decision for the second call must compare old and new values: re-saving an unchanged record setting on a live channel must not stop it. The full rule is in section 4.3 of `BAKER-AND-LIVE-DATA-SPEC.md`.

How v2 stands: the Channel settings tab and the Recording switch beside Start Broadcast save each toggle instantly with "Saved.", and both are locked while live. Locking while live is an acceptable choice and avoids the warning entirely. If the UX implementor decides to allow those four changes while live, which production supports, the warning above is required and the instant-save pattern needs a confirm step for them.

A deliberate "reset stream key" button is a separate thing, for a leaked key. Whether and where to offer it is the UX implementor's call. It can be absent now and added later if requested.

## 5. The broadcaster state machine must survive the move

**Recommendation.** The standalone browser broadcaster, running in its own window, has a complex state machine that was tuned to near perfect over many iterations. It handles WHIP disconnections and reconnection, camera and microphone permission, switching cameras, and changing resolutions. v2 embeds that broadcaster inside a richer workspace with more surrounding state: the channel picker, the recording toggle, the statistics tab, the source cards, and two editor embeddings over postMessage. When the two are merged, take extra care that the proven state machine is not broken by the new context. The transitions to design and test explicitly are switching from browser broadcast to an external encoder and back, while capture or a WHIP session may be active.

Specific points where v2 and the real broadcaster differ today:

- **Reconnect is manual in v2.** After a drop or an offline event the prototype offers only a Reconnect button. The real broadcaster reconnects automatically after an unexpected drop. Decide which, and show it.
- **Device changes are locked while live in v2.** The real broadcaster recreates capture on any camera, microphone or resolution change, including mid-broadcast. That is a capability choice worth making explicitly rather than inheriting.
- **Screen share is absent.** The real broadcaster supports display capture, and the competitor page notes that every rival offers it, but whether to include it is the UX implementor's decision. Leaving it out to avoid bloat is fine, and it can be added later if requested.
- **The encoder on-air state is never demonstrated.** Nothing in the prototype sets an encoder channel to receiving video, so the monitor for a live encoder channel has not been reviewed.

## 6. Remember the last broadcast method

Make the browser-versus-streaming-app choice sticky. Store the last method in local browser storage and restore it on refresh or return. If the last method was the streaming app, the page must not attempt any camera access. This was attempted in the dashboard theme and did not work reliably.

How v2 stands: the method is already stored per channel, so the encoder choice survives a refresh within the same tab, and the prototype only requests the camera from the explicit button, which already covers the second half. Two things to change for production: use local storage rather than session storage so the choice survives closing the browser, and keep the per-channel scope, since a creator may run one channel from OBS and another from the browser. Note that the prototype resets to the login screen on every reload, which gets in the way of reviewing stickiness.

## 7. Status vocabulary

**Recommendation.** The primordial channel states are the four the digest feed sends: `onair`, `starting`, `paused` and `stopped`, plus an unknown state before the first message arrives. The interface should be built on those four.

v2 uses Off air, Connecting, Live and Disconnected, and for encoders Waiting, Receiving video and Encoder disconnected. Two problems follow:

- `paused` is a normal state, meaning the channel is open but no video is arriving right now. It is not an error. The v2 wording would render it as "Signal lost" or "Encoder disconnected".
- All Channels shows a status for every row from the start. Until the first digest message arrives a channel's state is unknown, which is not the same as off air.

If the UX implementor decides to rename the states for clarity, that is fine, but the renaming must be consistent throughout: the broadcaster monitor, the statistics tab, the encoder panel and the All Channels list must all use the same word for the same state. Keep "Disconnected" only for the browser publisher's own connection, which is a local condition and not a channel state.

## 8. Thumbnails

The digest feed that delivers the channel states also delivers a live thumbnail for each channel, as a JPEG frame, over the same WebSocket. Grabbing it is easy: it arrives with the state and needs no extra request. It is only meaningful for `onair` and `starting`; for `paused` and `stopped` any held frame is stale and a placeholder belongs there.

v2 has no thumbnails anywhere. The channel player preview stage is a placeholder with a play icon, and the All Channels rows have no image. A thumbnail would be a nice addition in both places, and it is the natural content for the encoder-mode monitor, where the owner otherwise has nothing to look at. Thumbnails should be requested only for channels currently on screen, so a long list does not pull frames for rows nobody sees.

## 9. Error states are not designed

**Recommendation.** The only errors in v2 are form validation and empty lists. The spec asks for Baker's actual reason on failures, such as channel limit reached or not enough traffic, and for a visible "couldn't load" per section rather than an empty list. States that need a design:

- Channel creation rejected by Baker, with its message.
- A list section that failed to load, distinguishable from a list with nothing in it.
- Broadcast details unavailable for a channel.
- A settings save rejected by Baker.
- The status feed reconnecting, so the owner knows the shown state may be stale.

## 10. Smaller observations, all subjective

- **The Statistics tab has no data source yet.** It shows bitrate, resolution, codec and health. The digest feed supplies only state, viewer count and thumbnail, and the spec has no workstream for the rest. Either define a source for these or trim the tab to what the feed provides. See also section 11 for the parts of that page that must not be copied at all.
- **Multi-owner sites.** All Channels shows status for every channel. When non-admin broadcaster roles are enabled, the feed and therefore the list must be scoped to the channels the current user may manage.
- **The Larix view shows the combined RTMP address with the key unmasked**, while the standard view masks it.
- **Recording toggle placement.** Sitting beside Start Broadcast, it invites a flip right before going live. With the warning from section 4 in place that is fine; without it, the Channel settings tab may be the safer home.

## 11. Remove Max viewers and every superadmin-only detail from the Statistics tab

**Important.** The statistics data that flows through the stream carries more than a channel owner should see. Part of it is meant for WpStream superadmins only. The v2 tab copied some of that part. Those parts must go.

**Max viewers.** The "MAX VIEWERS … Change" control in the Audience card must be removed from the interface. It is not a user-adjustable setting. It is meant for WpStream superadmins only, and if a creator tried to change it, it would not work.

**Other superadmin-only data in the stream.** None of the following may appear in the plugin interface, even though the stream delivers them. They are not security critical, but a creator who is not streaming-savvy would see them and raise unnecessary questions or criticism:

- Any data-consumed figure, of any kind: the "Data Consumed" and "Data Consumed · avg" series on the Audience chart with their megabytes axis, and the Data Consumed card under Technical details. Usage is no longer measured in data. It is measured in broadcast hours and viewer hours, and showing the hours used is fine. One caution: the hours that arrive through the socket are per event, meaning one broadcast session, not per channel. A creator does not know the difference between an event and a channel, so a per-event figure next to a channel name would confuse. Either leave the hours to the account usage area at the top of the page, which shows account totals, or label the Statistics tab figure unmistakably as this broadcast only.
- The extra event fields: stream name, stream key, HLS encryption flag, plugin version, available data, size and permalink. The Event card may keep the owner-facing fields: website, locked to, user id, low latency, adaptive bitrate and record. WP IP is irrelevant to the owner and should be dropped too.
- The video "profile" and "settings" rows in the Video card.
- Viewer session details and the hostname of the server behind the stream source.

Of these, v2 currently shows the Max viewers control, the two Data Consumed series on the Audience chart and the Data Consumed card. Remove all three. The rest are listed so they are not added later by mistake when the tab is wired to real data.

## What matches the spec well

For the record, these were checked and need no change: broadcast details appear on channel select without any Start step; there is no activation or autostart step; encoder mode has no manual start or stop, which is already the spec's end state; the encrypt versus low latency conflict rule is implemented; the viewer page carries no stream key; and camera access is explicit.
