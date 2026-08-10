# WpStream - Live Streaming for WordPress

**WpStream** is a WordPress plugin designed for live streaming, video on demand (VOD), and pay-per-view monetization. It seamlessly integrates with **WooCommerce** to help creators, businesses, and influencers monetize video content.

## 🔄 Latest (4.13.2)

- **Security** — Live/broadcast actions now enforce channel ownership; a user can only control channels they own.
- **Security** — Front-end dashboard profile saves are restricted to address fields, and channel-data saves verify ownership by post author (not editable user meta).
- **Security** — The Larix QR code is now generated locally in the browser (bundled library); the RTMP URL and stream key are no longer sent to a third-party QR service.
- **Security** — Live chat client hardened against stored XSS: message, username, and user-list fields from the chat server are rendered as text, never as HTML, and message ids are matched as opaque data (no dynamic selectors), so a crafted id cannot touch unrelated page elements.
- **Fix** — Live chat keep-alive timer is now cleared on disconnect and auto-reconnect reuses the chat URL; a stale/replaced connection can no longer clear the active socket's timer or trigger duplicate reconnects; the "not live" notice no longer throws.
- **Fix** — Fatal error on the WooCommerce email-preview screen and in order emails when an order item's product was missing (e.g. deleted after purchase).
- **Fix** — Fatal error on the order-received page when no order is available.
- **Fix** — Purchase confirmation now correctly detects live stream / VOD purchases regardless of the product-type term name.

## 🚀 Features

- **Live Streaming**: Stream directly from your browser or using RTMP apps like OBS, StreamYard, Zoom, and more.
- **Video on Demand (VOD)**: Record live streams or upload video files for on-demand viewing.
- **Monetization**: Sell live streams or VODs with WooCommerce (pay-per-view, subscriptions).
- **Frontend Streaming**: Allow users to go live on your site (similar to Twitch).
- **Custom Branding**: Add logos, protect streams, and control access.
- **WordPress Page Builder Support**: Works with Elementor, WPBakery, and more.

## 🎥 Getting Started

1. **Install & Activate**: Search for **WpStream** in the WordPress plugin directory.
2. **Create an Account**: Sign up at [WpStream.net](https://wpstream.net/).
3. **Set Up Streaming**:
   - Create a **Live Channel** or **VOD**.
   - Click **"Broadcast to Channel"** and start streaming.
4. **Monetization (Optional)**:
   - Install **WooCommerce** to sell pay-per-view or subscription-based content.

## 📌 Shortcodes & Widgets

- **Embed Live or VOD Players**: `[wpstream_player]`
- **List Live Channels**: `[wpstream_channel_list]`
- **Frontend Streaming (Twitch-style)**: `[wpstream_start_streaming]`

## 🌐 Useful Links

- 📖 **[Documentation](https://docs.wpstream.net/docs-category/getting-started/)**
- 📺 **[YouTube Tutorials](https://www.youtube.com/channel/UCIjItiJc4Z7aJApj3W6ArJA)**
- 💡 **[Live Streaming Guide](https://wpstream.net/live-streaming/)**

### 📢 Follow Us

[YouTube](https://www.youtube.com/channel/UCIjItiJc4Z7aJApj3W6ArJA) |  
[Facebook](http://facebook.com/wpstreamsoftware) |  
[Twitter](http://twitter.com/streaming4wp) |  
[LinkedIn](http://linkedin.com/company/wpstream)

---

🔗 **[WpStream.net](https://wpstream.net/)** – The ultimate live streaming solution for WordPress.
