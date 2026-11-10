# WPStream plugin: Baker API handling and live data pipeline

Implementation spec. Describes what to change in the WPStream WordPress plugin and why.
It does not prescribe UI design. Where a UX decision is needed, this document says so.

**Code base:** file and line references are to branch `wpstream2026` (release 4.14.2), which is ahead of `master`. They are approximate. See section 7 about branches and releases.

---

## 1. Remote services involved

| Service | What it is | Who talks to it |
|---|---|---|
| **Baker** (`https://baker.wpstream.net`, `WPSTREAM_API`) | The WPStream backend API: channels, videos, quota, tokens. | The client's WordPress site (PHP), never the browser directly. |
| **Digest** (`wss://chandler.wpstream.live/digest`) | Real-time WebSocket feed of channel status, viewer count and a thumbnail. | **The channel owner's browser only.** See the warning in 4.1. |
| **Hosted player** (`https://player.wpstream.net`) | The iframe player viewers watch in. Handles its own live/offline state. | The viewer's browser. |

"Digest" is the name of the service. The host it runs on serves other services too, so refer to it as the digest feed, not by the host name.

---

## 2. Goals

1. **Move owners' channel status onto the digest feed** instead of polling Baker. Baker polling stays only where digest cannot replace it yet (see 4.1).
2. **Channels are always ready to broadcast.** A channel turns on automatically when video arrives at its stream key and turns off after inactivity. There is no manual Start/Stop in the end state.
3. **Make Baker calls robust.** Correct token handling, correct error detection, no duplicate calls, no silent failures.

---

## 3. Target state at a glance

- **Owner dashboard / broadcaster page:** one digest WebSocket shows each channel's state (`onair`, `starting`, `paused`, `stopped`), viewer count and thumbnail.
- **Broadcast details** (RTMP server, stream key, WHIP URL): read from Baker when the owner opens the view that shows them. Never require Start to obtain them.
- **Channel creation:** registers the channel in Baker immediately, with all its settings.
- **Settings that change the stream** (record, low latency, adaptive bitrate, encryption) are applied by updating the channel and, if a broadcast is running, stopping it. The channel restarts on its own with the new settings and the same stream key. Viewers lose the feed for a few minutes.
- **Viewers:** hosted iframe player only. No viewer-side polling of WordPress or Baker.

---

## 4. Workstreams

### 4.1 Owner live status via the digest feed

> **⚠ The digest feed is for channel owners only, never for viewers.**
> - Every subscription must carry the channel's **stream key**, which only the owner may know. Putting it on a viewer-facing page hands out the credential to broadcast on that channel.
> - The feed is sized for owners' dashboards. One connection per viewer would overwhelm it.
>
> Open the socket only on pages restricted to the channel's owner (the existing `wpstream_can_manage_channel` check), and only for that owner's channels. Viewer pages must never load the feed client or receive stream keys.

#### What digest replaces, and what stays on Baker

Today the owner's browser polls WordPress admin-ajax `wpstream_check_event_status`, which calls Baker `channel/info` uncached:
- `public/js/start_streaming.js`: 10s polls after Start/Stop (~L363, ~L464), 60s poll while active (~L608), 60s poll on page load for pending rows (~L1528). The AJAX wrapper is `wpstream_check_event_status_in_js` (~L658).
- `public/js/broadcaster.js`: `checkChannelStatus` (~L1113), before publishing (~L715) and during WebRTC reconnect (~L825).
- Server handler: `includes/class-wpstream-live-api-connection.php` `wpstream_check_event_status` (~L445).

**To keep the transition smooth, keep Baker polling wherever its result is used for something digest cannot provide.** In particular, the server-side handler's side effects must keep working:
- It fires `wpstream_channel_became_active` and `wpstream_channel_became_inactive` (~L477-514). The BuddyBoss integration uses them to post and close "live now" activity items (`integrations/buddyboss/buddyboss.php` ~L429, ~L503).
- It writes channel post meta through `Wpstream_Channel_State::store`.

The digest feed runs in the browser, so it cannot trigger server-side code. Concretely:
- **Use digest** for everything the owner *sees*: the status shown, viewer count, thumbnail, and browser-side decisions such as the broadcaster page's reconnect logic.
- **Keep the existing Baker status polls** as long as they are the only trigger for the lifecycle hooks and meta updates above. Don't add new ones. Where a poll exists only to refresh what the owner sees, remove it.
- **Loosen the polls that remain to at least 1 minute.** Since they no longer drive what the owner sees, they don't need to be fast. In particular, the 10s polls after Start/Stop (`start_streaming.js` ~L363, ~L464) go to 60s or more. The existing 60s polls stay at 60s or more.
- The render-time status checks through `Wpstream_Channel_State::for_channel()` (45s cache, used by viewer pages, BuddyBoss timeline and theme sections) stay for now. See 4.4 about whether viewer pages still need them.

> **Note: a server-side trigger may be needed.** The lifecycle hooks only fire while some owner page is open and polling. The polls are started by pressing Start, or by "pending" rows on page load. Once Start/Stop is removed (rollout step 6), an owner broadcasting from an external encoder with no dashboard open may trigger no poll at all, so BuddyBoss "live now" items would never be posted or closed. Before removing Start/Stop, check this, and if needed add a server-side trigger, for example a WP-Cron job (1 minute or more) that checks the status of the site's channels and fires the hooks.

**Possible later step (not part of this work):** digest may gain an HTTP interface, so that PHP can query it instead of Baker. At that point the remaining server-side Baker status polls can move to digest.

#### Protocol (the parts an implementer needs)

**Endpoint:** `wss://chandler.wpstream.live/digest`. TLS on 443. No credential on the handshake.

**Subscribe:** immediately after the socket opens, send one JSON frame listing every channel to watch:
```json
{ "subscribe": [ { "channel": "<global_id>", "streamKey": "<streamKey>", "options": { "snapshots": true } } ] }
```
- `channel` is the channel's Baker `global_id`. All server messages are keyed by it.
- `streamKey` is that channel's ingest key. It authorises the subscription for that channel.
- To change the watched set, send a new `subscribe` with the full new list. The server keeps no state across connections. After a reconnect, resubscribe the full list.

**Server messages:** JSON frames containing either or both of these:
```json
{ "channels": { "<global_id>": { "state": "onair", "viewers": 12, "snapshot": "<base64 jpeg>" } } }
{ "delta": [ { "channel": "<global_id>", "state": "paused" } ] }
```
- Records are **partial**. Merge field by field into what you hold for that channel; never replace the whole record. A state-only update must not wipe the viewer count or the thumbnail.
- Don't depend on message order. Ignore frames that fail to parse, and ignore unknown fields.
- A channel with no record yet means **unknown**, which is not the same as off air. Render nothing, or "unknown", until the first `state` arrives.

**States:**

| State | Meaning |
|---|---|
| `onair` | Broadcasting normally. |
| `starting` | Stream is coming up. |
| `paused` | Channel is still open but no video is arriving, because the broadcast just finished or dropped. This is a normal state, not an error. The channel returns to `onair` if video resumes, or goes to `stopped` after the inactivity timeout. |
| `stopped` | Off air, at rest. |

> **Baker statuses and digest states are different things. Don't map one onto the other.**
> - Baker's channel `status` (from `channel/list` / `channel/info`): `starting`, `active`, `stopped`, `stopping`, `error`, `ended`. The server-side lifecycle hooks are based on Baker's `active`.
> - Digest's `state`: `onair`, `starting`, `paused`, `stopped`. This is what the owner sees.
>
> Even where names match (`starting`, `stopped`), they come from different systems and may not agree at a given moment. Use digest states for display, and Baker statuses only for the server-side logic that already depends on them.

**Thumbnail:** `snapshot` is base64 JPEG with no `data:` prefix (display it as `data:image/jpeg;base64,<value>`). `null` means no frame. Only display it for `onair` and `starting`. For `paused` and `stopped`, show a placeholder, because any frame you hold is stale.

**Reconnection:**
- On error, close the socket and let the close handler drive reconnection. Don't run two recovery paths.
- Use capped exponential backoff: first retry after 1s, doubling each attempt, capped at 15s.
- Reset the backoff to 1s on every successful open.
- On deliberate teardown, detach the close handler before closing, so your own shutdown doesn't schedule a reconnect.
- Keep the records you already have across a reconnect.

**Client rules:**
- Send a new `subscribe` only when the list actually changes (channels, keys or thumbnail flags), and send it on the open socket rather than reconnecting. Compare a stable signature, such as sorted `channel:streamKey:snapshots` entries joined, so re-renders don't re-send identical lists.
- If there are no channels to watch, don't open a socket.
- **Request thumbnails only for channels currently on screen.** Thumbnails are base64 JPEGs, so for an owner with many channels, asking for all of them wastes bandwidth. Set `options.snapshots: true` only for visible channels (e.g. tracked with `IntersectionObserver`) and `false` for the rest. When visibility changes, send a new `subscribe` with the full list **on the open socket**; don't reconnect. Debounce these updates (e.g. 500ms) so scrolling doesn't flood the server. Status and viewer count still arrive for every subscribed channel.

**Reference client (framework-free):**
`items` is `[{ channel, streamKey, snapshots }]`. Call `setItems()` to change which channels get thumbnails without reconnecting.
```js
function subscribeFeed(items, onRecords) {
  let records = {}, closed = false, backoff = 1000, ws, retry;
  const send = () => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ subscribe: items.map(({ channel, streamKey, snapshots }) =>
        ({ channel, streamKey, options: { snapshots: !!snapshots } })) }));
    }
  };
  const open = () => {
    ws = new WebSocket('wss://chandler.wpstream.live/digest');
    ws.onopen = () => { backoff = 1000; send(); };
    ws.onmessage = (e) => {
      let msg; try { msg = JSON.parse(e.data); } catch { return; }
      const merge = (id, rec) => { records[id] = { ...(records[id] || {}), ...rec }; };
      if (msg.channels) for (const [id, rec] of Object.entries(msg.channels)) merge(id, rec);
      if (Array.isArray(msg.delta)) for (const d of msg.delta) if (d && d.channel) merge(d.channel, d);
      onRecords({ ...records });
    };
    ws.onerror = () => ws.close();
    ws.onclose = () => {
      if (closed) return;
      retry = setTimeout(open, backoff);
      backoff = Math.min(backoff * 2, 15000);
    };
  };
  open();
  return {
    setItems(next) { items = next; send(); },   // same socket, full list
    close() { closed = true; clearTimeout(retry); if (ws) { ws.onclose = null; ws.close(); } },
  };
}
```

#### Where the subscribe list comes from

- Baker `channel/list` returns `global_id`, `streamKey`, `broadcastUrl` and `whipUrl` for each channel. Build the subscribe list from it, resolved server-side.
- **Filter the list to the current user's channels before anything reaches the browser.** A WordPress site can have several channel owners, but the whole site uses one Baker account, so `channel/list` returns every channel on the site, each with its stream key. Each entry's `channel_id` is the channel's WordPress post ID: keep only entries whose post the current user may manage (`wpstream_can_manage_channel`; admins may see all). Otherwise one owner's browser would receive another owner's stream key and could broadcast on their channel.
- **The plugin currently calls `channel/list` with `status=active`** (`class-wpstream-live-api-connection.php` ~L1817), so it only gets channels that are already live. The subscribe list must cover **all** of the owner's channels. Without `status`, Baker returns every channel of any status; keep sending `domain` so the list is limited to this site. Use a separate call, or drop the filter where nothing else depends on it.
- Some older channels may be missing the key fields. For those, call `channel/info` (which returns them whenever they're set). If a key still can't be found, leave that channel out. Never subscribe without a key.

### 4.2 Channel lifecycle and broadcast details

#### Always-on channels

Every channel turns on when video arrives at its stream key and turns off after inactivity. There is no opt-in.

- **Do not send `autostart`** to Baker. Baker still accepts the field, but it is obsolete.
- Remove the `'autostart' => 'true'` the plugin sends today:
  - on `channel/update`, built in `Wpstream_Channel_Settings::prepare_baker` (`includes/class-wpstream-channel-settings.php` ~L365);
  - on `channel/start` (`class-wpstream-live-api-connection.php` ~L1554).

#### Create

Today, publishing a channel calls `Wpstream_Streaming_Content_Creation::ensure_provisioned` (`includes/class-wpstream-streaming-content-creation.php` ~L474), which calls `Wpstream_Channel_Service::create_channel` (`includes/api/class-wpstream-channel-service.php` ~L68). It sends only `channel_id` and `domain`. It stores the channel id and `embedUrl`, and not the broadcast details Baker returns.

Change to:
- Send the channel's settings in the same call. `channel/create` accepts the same fields as `channel/update`: `channel_title`, `description`, `poster`, `logo`, `record`, `low_latency`, `abr`, `encrypt` (plus its key fields), `allow_access_from` and `metadata` (player options).
- Keep Baker's error code. Today `create_channel` maps any failure to `false` (~L85), so the reason is lost.
- Treat `CHANNEL_EXISTS` as success. Today it marks provisioning as failed.

#### Channels with no Baker record

Older channels may exist only in WordPress. `ensure_provisioned` runs on publish and before `channel/start` (`class-wpstream-live-api-connection.php` ~L1473).

- Also run it when the owner opens such a channel, so its broadcast details can be shown without Start. Treat `CHANNEL_EXISTS` as success.
- Then read its broadcast details through `channel/info`.

#### Broadcast details

Today the broadcast modal reads post meta `obs_uri` / `obs_stream` (`includes/class-wpstream-live-channel-presentation.php` ~L534-536). Those are written only by the status poll once the channel is active. `wpstream_check_whipurl` reads only the `whipUrl` meta.

Change to:
- **Don't use WordPress meta as the source of truth.** Fetch the details from Baker when the owner opens the view that shows them: from `channel/list` if the page already has it, otherwise from one `channel/info` call. Never poll for them.
- Doing it this way means a stream key reset (4.3) is picked up automatically.
- Use Baker's `whipUrl` as-is. Don't build the WHIP URL locally from the stream key.
- Never call Start just to obtain credentials.

### 4.3 Settings changes while a broadcast is running

Stream settings are applied by Baker only when an event starts: `channel/update` stores them, and the auto-start path reads them when the next event comes up. A change made while the channel is live therefore does not touch the running event. To apply it, the running event has to end; the channel then restarts by itself with the stored settings and the **same stream key**. Verified on the live platform on 2026-10-21: with an encoder left connected, `channel/update` followed by `channel/stop` brought the channel back to active about two and a half minutes later, on a new event, with the new setting applied and the key unchanged. No `channel/reset` and no explicit turn-on call were needed.

In the old flow, stream settings could only be changed while the channel was off. In the new flow:

- **These settings need the running event ended when changed:** `record`, `low_latency`, `adaptive_bitrate` (`abr`), `encrypt`.
- **These do not:** `domain_lock`, `view_count`, `autoplay`, `mute`, title, description, poster, logo. (`ses_encrypt` is site-wide and isn't sent to Baker.)

The setting keys live in `Wpstream_Channel_Settings::CHANNEL_KEYS` (`class-wpstream-channel-settings.php` ~L94-101).

**The plugin must do this when saving:**
1. Call `channel/update` with the new settings. Always.
2. If, and only if, one of the four settings above actually changed value **and the channel is currently live**, call `channel/stop`. Do not call `channel/reset`; the key must stay the same so encoders keep working.
3. Nothing else. The channel comes back on its own while the encoder stays connected: the browser broadcaster reconnects, an external encoder such as OBS is re-attached by the ingest. The digest feed subscription stays valid because the key did not change.

> **Exact Baker calls on a settings change. Read this twice.** This is different from every previous approach: the old flow only allowed these changes while the channel was off, and an earlier draft of this document called `channel/reset`. Neither applies any more. Implement exactly this:
>
> | Situation | Calls, in order |
> |---|---|
> | Any setting changed, channel **not live** | `channel/update` only |
> | Only non-stream settings changed (`domain_lock`, `view_count`, `autoplay`, `mute`, title, description, poster, logo), channel live or not | `channel/update` only |
> | `record`, `low_latency`, `abr` or `encrypt` changed value, channel **live** | `channel/update`, then, after it succeeded, `channel/stop` |
>
> Both calls take `channel_id` and `domain`; `channel/update` also carries the changed settings. There is **no third call**: not `channel/reset` (it rotates the key and breaks every configured encoder), not `channel/start`, no explicit turn-on. After `channel/stop` the channel restarts by itself with the stored settings, roughly two to three minutes later, and the plugin only has to render the digest states as they pass through `stopped`, `starting` and `onair`. Compare old and new values before deciding on step two: re-saving an unchanged record setting on a live channel must not stop it.

**The interface must make the consequence clear before the save:** when one of the four settings is changed on a channel that is live, tell the owner that the channel will be reset and that viewers may not see the feed for a few minutes. If the channel is not live, no warning is needed; the change simply applies to the next broadcast.

Keep the existing conflict rules between settings (`class-wpstream-channel-settings.php` ~L263-277): low latency or adaptive bitrate turns encryption off, and encryption turns low latency off.

When Baker rejects a settings save, `synchronize_after_save` already returns `baker_rejected` (~L456-469). Show that to the owner; today a message is only shown when the site isn't connected.

A standalone "reset stream key" action, for example after a leaked key, is optional. Whether and where to offer it is a UX decision.

### 4.4 Viewers: hosted iframe player only

The legacy player is no longer used, but it is still loaded and still selected in some cases. Remove it and everything only it needs:
- `public/js/wpstream-player.js` and `public/js/wpstream-player-bootstrap.js`: registered in `public/class-wpstream-public.php` (~L179-195), and `wpstream-player` is enqueued on every player page (`wpstream.php` ~L355-359), even for the iframe player.
- The logged-out admin-ajax handler `wpstream_player_check_status` (`includes/class-wpstream-player.php` ~L54-56), which only the legacy player polls (default 30s).
- **The fallback that still selects the legacy player** when a channel has no `embedUrl` meta (`includes/class-wpstream-playback-presentation.php` ~L539-544; VOD equivalent ~L1291, ~L1324). Every channel must render the hosted player. Channels missing `embedUrl` need it populated (see 4.2, "Channels with no Baker record").
- Markup and config used only by the legacy player (`data-me`, `data-stats-uri`, `data-chat-url`, `data-nonce`, ~L544).

After this, viewers make no repeated calls to WordPress or Baker. Viewer pages still make cached, one-off Baker calls while rendering:
- the hosted player's embed key from `channel/info` / `video/info` (12h cache). This stays.
- channel status via `Wpstream_Channel_State` (45s cache). **This may be dead weight.** The hosted player handles live/offline state itself, so the check may now only feed markup the player could provide (for example a "not live" message or poster). Analyse what still depends on it on viewer pages, and remove it from the viewer path if nothing essential does. It is still needed by the BuddyBoss timeline and theme sections unless those are changed too.

### 4.5 Baker client robustness

All Baker calls go through `includes/class-wpstream-live-api-connection.php`: `request()` (~L193), `get()` (~L234), `handle_response()` (~L250), `authorized_request()` (~L362), `wpstream_get_token()` (~L1590).

**Already done:** on HTTP 401, `authorized_request()` discards the token, logs in again and retries once. Every authenticated call uses it. Keep this.

#### Token handling

| Today | Required |
|---|---|
| Token cached for a fixed 3500s (~L1608). | Cache for Baker's `expires_in` (from the `access_token` response) minus a safety margin (e.g. 60s). |
| Simultaneous requests can each log in. | Only one login at a time per site (e.g. a short-lived lock via `add_option`/transient). Other requests wait briefly for it or use the new token. |
| A failed login is cached for 1s by default (~L1621, filterable up to 300s). With a wrong password or a Baker outage, logins are retried on almost every request, including front-end page views. | Back off after a failed login (e.g. start at 1 minute, grow to a cap such as 10 minutes). Reset on success, and immediately when the admin saves new credentials. |

#### Response handling

Baker reports business errors with **HTTP 200** and a JSON body like `{ "success": false, "error": "CHANNEL_EXISTS", "message": "..." }`. Examples: `INVALID_PARAMETERS`, `CHANNEL_EXISTS`, `MAX_ALLOWED_CHANNELS`, `NOT_ENOUGH_TRAFFIC`. Expired or invalid tokens return a real 401.

Today `handle_response()` checks only the HTTP status and JSON validity. Each caller checks `success` itself, and many then reduce the result to `false` or `[]`, losing Baker's reason.

- Treat `success: false` in a 200 response as a failure in the client itself. Return it as a `WP_Error` carrying Baker's `error` code and `message`. This keeps the client's existing `array|WP_Error` contract (ADR 0003).
- Callers should pass Baker's reason on: log it, and show a meaningful message to the user where relevant, instead of a generic error.
- Never write tokens or credentials into logs (`record_failure` already logs only message and endpoint; keep it that way).

### 4.6 Owner dashboard data loading

**Keep the existing refresh intervals,** with one exception. The processing-videos poll (`admin/js/recordings_videos_list.js` ~L63) calls `video/list` uncached for the whole account every 10s while any video is pending. Change it to **30s**, since processing takes minutes.

Fix these behaviours:

- **Duplicate fetches on one screen.** `channel/list` (via `wpstream_get_live_event_for_user()`) is called from several places (`class-wpstream-live-channel-presentation.php` ~L239, ~L507, ~L1148). Some of these are deduplicated only by a global variable. Fetch each Baker resource at most once per request (request-scoped memo) and pass it down.
- **Silent empty sections.** When a Baker call fails, sections render as if there were no data. For example, `wpstream_get_videos_from_api()` returns `[]` on an API failure (`class-wpstream-live-api-connection.php` ~L1985-2002), and the Recordings screen only shows an error when the site isn't connected (`admin/class-wpstream-admin.php` ~L1871). Show a visible "couldn't load" error for that section instead, so a failure is distinguishable from an empty list. Each section should fail independently.

---

## 5. Rollout

The end state can be reached gradually. Timing of the UX-visible steps is a product decision.

1. **Baker client robustness** (4.5) and **dashboard fixes** (4.6). No visible behaviour change beyond better errors.
2. **Viewer cleanup** (4.4). Make sure every channel has `embedUrl` before removing the legacy fallback.
3. **Channel lifecycle** (4.2): full create, backfill for channels without a Baker record, broadcast details from Baker, no `autostart`.
4. **Digest status for owners** (4.1). Owner-facing status comes from digest; Baker polls stay only where they drive server-side behaviour. The existing UI can be kept at first, mapping the four states onto it.
5. **New status UI:** the four states and thumbnails.
6. **Settings changes while live** (4.3), and **removal of manual Start/Stop.**

---

## 6. Out of scope

- HLS encryption key delivery.
- Embed key generation (already moved to Baker in 4.14.2).
- The onboarding captcha endpoint.
- A support diagnostics panel.
- Changes to how the plugin authenticates to Baker (account credentials stay as they are).
- An HTTP interface for digest (a possible later step, see 4.1).

---

## 7. Branches and releases

Plugin releases should be made from the main branch (`master`). If work is done on a secondary branch, merge it into `master` **before** releasing.

At the time of writing this is not the case. Releases 4.14.0 to 4.14.2 were tagged on `wpstream2026`, which is 40 commits ahead of `master`, while `master` (the GitHub default branch) has not moved since 2026-08-13. Merge `wpstream2026` into `master`, and base this work on the merged `master`.

---

## 8. Acceptance checks

- The digest feed is opened only on owner-restricted pages. No viewer-facing page loads the feed client or contains a stream key.
- On a site with two channel owners, each owner's page receives only their own channels' stream keys.
- With an owner's dashboard open, status changes appear within seconds, from digest. No Baker status polls exist beyond those that drive server-side behaviour (lifecycle hooks, channel meta).
- BuddyBoss "live now" activity is still posted and closed as before (within about a minute of the change).
- No remaining owner-side Baker status poll runs more often than every 60s. The processing-videos poll runs every 30s.
- A viewer watching a live channel causes no repeated requests to the client site and no repeated Baker calls.
- Starting to broadcast to a channel's stream key shows `starting` then `onair` on the owner's page without pressing anything. Ending the broadcast shows `paused`, then `stopped` after the inactivity timeout.
- Dropping the network and restoring it reconnects the feed, retrying at 1s, 2s, 4s… up to 15s, and status resumes.
- Wrong Baker credentials: login attempts back off and are not made on every page view.
- A Baker `success: false` response (e.g. creating a channel over the limit) shows Baker's reason to the user.
- A newly created channel shows its RTMP server, stream key and WHIP URL immediately, without Start.
- Changing only the title or a player option never interrupts a broadcast and never changes the stream key. Changing record, low latency, adaptive bitrate or encryption on a live channel warns the owner first, then calls update and stop; the channel comes back on its own with the new setting, the stream key is unchanged, and a connected encoder needs no change.
- Each admin screen makes each Baker call at most once. A failed Baker call shows an error in its section, not an empty list.
