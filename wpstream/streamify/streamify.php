<?php
/**
 * WpStreamIfy — a lightweight HLS reverse-proxy and disk cache.
 *
 * Requests to `/wpstreamify/<path>` are rewritten to a query var, fetched from
 * the WpStream origin, cached under wp-content/uploads/wpstreamify_cache/, and
 * served back with CORS + cache headers. Playlists (.m3u8) are short-lived and
 * revalidated frequently; segments (.ts) are cached longer. A cron job and the
 * activation/deactivation hooks manage the cache directory and stale files.
 *
 * @package    Wpstream
 * @subpackage Wpstream/streamify
 */

// Absolute path to the on-disk cache directory for proxied HLS files.
define('WPSTREAMIFY_CACHE_DIR', WP_CONTENT_DIR . '/uploads/wpstreamify_cache/');

/**
 * Register the `wpstreamify_path` public query var used by the rewrite rule.
 *
 * @param array $vars Existing recognized query vars.
 * @return array Query vars with our proxy path appended.
 */
function wpstreamify_register_query_vars($vars) {
    // Whitelist our custom query var so WordPress will populate it.
    $vars[] = 'wpstreamify_path';
    return $vars;
}
add_filter('query_vars', 'wpstreamify_register_query_vars');

/**
 * Front controller for proxy requests, hooked on `template_redirect`.
 *
 * Resolves the requested path, maps it to the origin URL and cache file, and
 * dispatches HLS extensions to the cache handler (404 for anything else).
 */
function wpstreamify_proxy_handler() {
    // Only act when the rewrite populated our query var.
    $path = get_query_var('wpstreamify_path');
    if (!$path) {
        return;
    }

    // Origin server the HLS assets are proxied from.
    $originHost = 'https://basicstreaming.wpstream.live/';
    // Full upstream URL to fetch for this request.
    $remoteUrl = $originHost . $path;
    // Local cache path; basename() keeps only the filename (path-traversal guard).
    $cacheFile = WPSTREAMIFY_CACHE_DIR . basename($path);
    // Extension decides how the request is handled and cached.
    $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

    // Serve only HLS playlists/segments; reject everything else.
    if ($fileExtension === 'ts' || $fileExtension === 'm3u8') {
        wpstreamify_process_hls_request($fileExtension, $cacheFile, $remoteUrl);

    } else {
        wpstreamify_serve_404();
    }
}

/**
 * Serve an HLS asset from cache, or fetch-and-cache it on a miss.
 *
 * Implements a simple lock-file scheme so that concurrent requests for the same
 * uncached file wait for one fetch rather than stampeding the origin.
 *
 * @param string $fileExtension 'ts' or 'm3u8'.
 * @param string $cacheFile     Absolute path to the cached copy.
 * @param string $remoteUrl     Origin URL to fetch on a cache miss.
 */
function wpstreamify_process_hls_request($fileExtension, $cacheFile, $remoteUrl) {
    // A cached copy already exists on disk.
    if (file_exists($cacheFile)) {
        // Playlists expire quickly (2s); drop a stale one so it gets refetched.
        if ($fileExtension === 'm3u8' && wpstreamify_file_is_expired($cacheFile, 2)) {
            if (!@unlink($cacheFile)) {
                error_log(sprintf(
                    'wpstreamify_process_hls_request: Failed to delete expired cache file: %s',
                    $cacheFile
                ));
            }
        } else {
            // Fresh (or a segment): serve straight from cache as a HIT.
            wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'HIT');
            exit;
        }
    }

    // Per-file lock marks that another request is already fetching this asset.
    $lockFile = $cacheFile . '.lock';
    if (file_exists($lockFile)){
        // Another worker is fetching: wait for it, then serve the result (WAIT).
        wpstreamify_wait_for_lock($lockFile, $cacheFile, $fileExtension);
        wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'WAIT');
        exit;
    }
    // No lock: we own the fetch. On success serve the freshly cached file (MISS).
    else if (wpstreamify_fetch_and_cache_file($lockFile, $cacheFile, $remoteUrl)) {
        wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'MISS');
    } else {
        // Fetch failed: report a server error.
        wpstreamify_serve_500();
    }
    exit;
}

/**
 * Determine whether a cached file is older than a freshness window.
 *
 * @param string $filePath      Absolute path to the file to test.
 * @param int    $expirySeconds Maximum allowed age in seconds.
 * @return bool True if expired (or age is unknowable), false if still fresh.
 */
function wpstreamify_file_is_expired($filePath, $expirySeconds) {
    // Last-modified time drives the age calculation.
    $lastModified = filemtime($filePath);
    if ($lastModified === false) {
        // Cannot read mtime: fail safe by treating the file as stale.
        error_log(sprintf(
            'wpstreamify_file_is_expired: Failed to retrieve last modified time for file: %s',
            $filePath
        ));
        return true; // Consider files with unknown modification time as expired
    }
    // Expired when the elapsed age exceeds the allowed window.
    $currentTime = time();
    return ($currentTime - $lastModified) > $expirySeconds;
}

/**
 * Block until a competing fetch releases its lock (or a timeout elapses).
 *
 * @param string $lockFile      Lock file to poll for.
 * @param string $cacheFile     Cache file the other worker should produce.
 * @param string $fileExtension Asset type (unused beyond diagnostics).
 * @return bool True if the cache file exists after waiting, false otherwise.
 */
function wpstreamify_wait_for_lock($lockFile, $cacheFile, $fileExtension) {
    // Poll the lock at 100ms intervals, capped at 200 cycles (~20 seconds).
    $cycles = 0;
    while (file_exists($lockFile) && $cycles++ <= 200) {
        usleep(100 * 1000);
    }
    // The other worker finished and wrote the cache file: success.
    if (file_exists($cacheFile)) {
        return true;
    }
    else {
        // Lock cleared but no cache file appeared: the fetch must have failed.
        error_log(sprintf(
            'wpstreamify_wait_for_lock: Cached file not found after waiting for lock: %s, Lock file: %s',
            $cacheFile,
            $lockFile
        ));
        return false;
    }
}

/**
 * Stream a cached HLS file to the client with the correct CORS/cache headers.
 *
 * @param string $fileExtension 'ts' or 'm3u8' (selects content type + caching).
 * @param string $cacheFile     Absolute path to the file to output.
 * @param string $cacheStatus   Diagnostic status echoed in the WPS-Cache-Status header.
 */
function wpstreamify_serve_cached_file($fileExtension, $cacheFile, $cacheStatus) {
    // Permit cross-origin playback from any embedding site.
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Guard: the file may have been evicted between the caller's check and now.
    if (!file_exists($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Cache file does not exist: %s',
            $cacheFile
        ));
        wpstreamify_serve_404();
        exit;
    }

    // Guard: file exists but permissions prevent reading it.
    if (!is_readable($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Cache file is not readable: %s',
            $cacheFile
        ));
        wpstreamify_serve_500();
        exit;
    }

    // Segments: long-lived, immutable-ish caching (5 minutes).
    if ($fileExtension === 'ts') {
        header('Content-Type: video/mp2t');
        header('Cache-Control: public, max-age=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
    } elseif ($fileExtension === 'm3u8') {
        // Playlists: very short cache with revalidation so the live edge stays current.
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: public, max-age=2, must-revalidate');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2) . ' GMT');
        // Tell page caches (e.g. WP Rocket) not to cache this playlist response.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
    }

    // Expose the cache outcome (HIT/MISS/WAIT) and mark the response OK.
    header("WPS-Cache-Status: $cacheStatus");
    status_header(200);
    // Send a Last-Modified header derived from the cached file's mtime.
    $lastModified = filemtime($cacheFile);
    if ($lastModified === false) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Failed to retrieve last modified time for cache file: %s',
            $cacheFile
        ));
        wpstreamify_serve_500();
        exit;
    }
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

    // Stream the bytes to the client; a false return means the read failed.
    if (!@readfile($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Failed to read and output cache file: %s',
            $cacheFile
        ));
        wpstreamify_serve_500();
        exit;
    }
}


/**
 * Fetch a file from the origin under an exclusive lock and write it to cache.
 *
 * @param string $lockFile  Lock file path guarding this fetch.
 * @param string $cacheFile Destination cache file to write.
 * @param string $remoteUrl Origin URL to download.
 * @return bool True once the (locked) fetch attempt completes, false on lock failure.
 */
function wpstreamify_fetch_and_cache_file($lockFile, $cacheFile, $remoteUrl) {
    // Keep running even if the client disconnects, so the cache still fills.
    ignore_user_abort(true);

    // Create/open the lock file that signals other workers to wait.
    $lockHandle = fopen($lockFile, 'w');
    if (!$lockHandle) {
        error_log(sprintf(
            'wpstreamify_fetch_and_cache_file: Failed to create or open lock file: %s',
            $lockFile
        ));
        return false;
    }

    // Take an exclusive advisory lock so only one worker fetches at a time.
    if (flock($lockHandle, LOCK_EX)) {
        try {
            // Download the asset from the origin.
            $response = wpstreamify_fetch_remote_content($remoteUrl);

            if ($response === false) {
                // Upstream fetch failed; nothing to cache.
                error_log(sprintf(
                    'wpstreamify_fetch_and_cache_file: Failed to fetch content from remote URL: %s',
                    $remoteUrl
                ));
            } else {
                // Persist the fetched bytes to the cache file.
                if (file_put_contents($cacheFile, $response) === false) {
                    error_log(sprintf(
                        'wpstreamify_fetch_and_cache_file: Failed to write content to cache file: %s',
                        $cacheFile
                    ));
                }
            }
        } catch (Exception $e) {
            // Log any unexpected error raised during fetch/write.
            error_log(sprintf(
                'wpstreamify_fetch_and_cache_file: Exception during fetch and cache process. Remote URL: %s, Error: %s',
                $remoteUrl,
                $e->getMessage()
            ));
        } finally {
            // Always release the lock, close the handle, and remove the lock file.
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            if (!@unlink($lockFile)) {
                error_log(sprintf(
                    'wpstreamify_fetch_and_cache_file: Failed to delete lock file: %s',
                    $lockFile
                ));
            }
        }
        return true;
    }
    else {
        // Could not obtain the lock: clean up and report failure.
        error_log(sprintf(
            'wpstreamify_fetch_and_cache_file: Failed to acquire lock on file: %s',
            $lockFile
        ));
        if (!@unlink($lockFile)) {
            error_log(sprintf(
                'wpstreamify_fetch_and_cache_file: Failed to delete lock file after failing to acquire lock: %s',
                $lockFile
            ));
        }
        return false;
    }
}

/**
 * Emit a 404 Not Found response and terminate the request.
 */
function wpstreamify_serve_404() {
    status_header(404);
    header('HTTP/1.1 404 Not Found');
    echo 'Resource not found...';
    exit;
}

/**
 * Emit a 500 Internal Server Error response and terminate the request.
 */
function wpstreamify_serve_500() {
    status_header(500);
    header('HTTP/1.1 500 Internal Server Error');
    echo 'An unexpected error occurred.';
    exit;
}

// Run the proxy front controller once WordPress has resolved the request.
add_action('template_redirect', 'wpstreamify_proxy_handler');

/**
 * Download a URL's contents via cURL, returning the body or false on error.
 *
 * @param string $url Origin URL to fetch.
 * @return string|false Response body, or false on transport/HTTP error.
 */
function wpstreamify_fetch_remote_content($url) {
    // Initialize a cURL handle for this request.
    $ch = curl_init();

    // Bail if the cURL handle could not be created.
    if ($ch === false) {
        error_log(sprintf(
            'wpstreamify_fetch_remote_content: Failed to initialize cURL for URL: %s',
            $url
        ));
        return false;
    }

    // Configure the transfer: return the body, follow redirects, set a UA and timeouts.
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   // return body instead of printing
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);   // follow HTTP redirects
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "WpStreamIfy/1.0");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);      // max 5s to connect
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);            // max 15s total

    // Perform the request.
    $response = curl_exec($ch);

    if ($response === false) {
        // Transport-level failure (DNS, timeout, TLS, etc.).
        error_log(sprintf(
            'wpstreamify_fetch_remote_content: cURL error for URL: %s. Error: %s',
            $url,
            curl_error($ch)
        ));
    } else {
        // Transport succeeded: treat any 4xx/5xx status as a failed fetch.
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            error_log(sprintf(
                'wpstreamify_fetch_remote_content: HTTP error for URL: %s. Status Code: %d',
                $url,
                $httpCode
            ));
            $response = false;
        }
    }

    // Release the handle and hand back the body (or false).
    curl_close($ch);
    return $response;
}


// Age (in seconds) beyond which cached HLS files are pruned by the cron job.
define('WPSTREAMIFY_CACHE_CLEANUP_THRESHOLD', 300);

/**
 * Register a custom 10-minute cron schedule for the cache cleanup job.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Schedules with an 'every_ten_minutes' entry added.
 */
function wpstreamify_custom_cron_intervals($schedules) {
    // Define a 600-second (10 minute) recurrence for the cleanup event.
    $schedules['every_ten_minutes'] = array(
        'interval' => 600,
        'display'  => __('Every 10 Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'wpstreamify_custom_cron_intervals');

/**
 * Cron callback: delete cache files older than the cleanup threshold.
 */
function wpstreamify_delete_old_hls_files() {
    // Nothing to prune if the cache directory is missing.
    if (!defined('WPSTREAMIFY_CACHE_DIR') || !is_dir(WPSTREAMIFY_CACHE_DIR)) {
        error_log('wpstreamify_delete_old_hls_files: Cache directory is not defined or does not exist.');
        return;
    }

    // Reference "now" and the full list of cache entries.
    $current_time = time();
    $files = glob(WPSTREAMIFY_CACHE_DIR . '*');

    // glob() failure: abort the sweep.
    if ($files === false) {
        error_log('wpstreamify_delete_old_hls_files: Failed to read cache directory: ' . WPSTREAMIFY_CACHE_DIR);
        return;
    }

    // Inspect each entry and remove regular files past their expiry.
    foreach ($files as $file) {
        if (is_file($file)) {
            // Age each file by its modification time.
            $file_modification_time = filemtime($file);
            if ($file_modification_time === false) {
                // Skip files whose mtime cannot be read.
                error_log(sprintf(
                    'wpstreamify_delete_old_hls_files: Failed to get modification time for file: %s',
                    $file
                ));
                continue;
            }

            // Delete anything older than the cleanup threshold.
            if ($current_time - $file_modification_time > WPSTREAMIFY_CACHE_CLEANUP_THRESHOLD) {
                if (!unlink($file)) {
                    error_log(sprintf(
                        'wpstreamify_delete_old_hls_files: Failed to delete file: %s',
                        $file
                    ));
                }
            }
        } else {
            // Log unexpected non-file entries (e.g. stray directories).
            error_log(sprintf(
                'wpstreamify_delete_old_hls_files: Skipped non-file entry: %s',
                $file
            ));
        }
    }
}


/**
 * Ensure the recurring cache-cleanup cron event is scheduled.
 */
function wpstreamify_schedule_cleanup_event() {
    // Schedule the event only if it is not already queued.
    if (!wp_next_scheduled('wpstreamify_cleanup_event')) {
        if (!wp_schedule_event(time(), 'every_ten_minutes', 'wpstreamify_cleanup_event')) {
            error_log('wpstreamify_schedule_cleanup_event: Failed to schedule cleanup event.');
        }
    }
}
// Register the cron event on every request (idempotent guard above).
add_action('wp', 'wpstreamify_schedule_cleanup_event');

// Wire the scheduled event to the actual cleanup routine.
add_action('wpstreamify_cleanup_event', 'wpstreamify_delete_old_hls_files');

/**
 * Remove the cache-cleanup cron event (used on plugin deactivation).
 */
function wpstreamify_unschedule_cleanup_event() {
    // Look up the next scheduled run so we can clear it.
    $timestamp = wp_next_scheduled('wpstreamify_cleanup_event');
    if ($timestamp) {
        if (!wp_unschedule_event($timestamp, 'wpstreamify_cleanup_event')) {
            error_log('wpstreamify_unschedule_cleanup_event: Failed to unschedule cleanup event.');
        }
    }
}
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_unschedule_cleanup_event');

/**
 * Register the rewrite tag/rule that maps /wpstreamify/<path> to the query var.
 */
function wpstreamify_add_rewrite_rules() {
	// Declare the capture tag and route the pretty URL into index.php.
	add_rewrite_tag('%wpstreamify_path%', '([^&]+)');
	add_rewrite_rule('^wpstreamify/(.+)$', 'index.php?wpstreamify_path=$matches[1]', 'top');
}
add_action('init', 'wpstreamify_add_rewrite_rules');

/**
 * Flush rewrite rules (run on activation/deactivation so the rule takes effect).
 */
function wpstreamify_flush_rewrite_rules() {
    if (!flush_rewrite_rules()) {
        error_log('wpstreamify_flush_rewrite_rules: Failed to flush rewrite rules.');
    }
}
register_activation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_flush_rewrite_rules');
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_flush_rewrite_rules');

/**
 * Suppress WordPress's canonical redirect for proxied HLS URLs.
 *
 * Without this, WP may 301-redirect the .ts/.m3u8 requests and break playback.
 *
 * @param string $redirect_url  URL WordPress intends to redirect to.
 * @param string $requested_url The originally requested URL.
 * @return string|false Original redirect URL, or false to cancel the redirect.
 */
function wpstreamify_disable_hls_canonical_redirect($redirect_url, $requested_url) {
    // Only interfere with our own proxy requests.
    if (get_query_var('wpstreamify_path')) {
        // Parse the requested URL to inspect its path/extension.
        $parsed_url = parse_url($requested_url);

        if ($parsed_url === false) {
            // Unparseable URL: leave the redirect untouched.
            error_log(sprintf(
                'wpstreamify_disable_hls_canonical_redirect: Failed to parse requested URL: %s',
                $requested_url
            ));
            return $redirect_url;
        }

        // Extract the path and its file extension.
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        if (!$fileExtension) {
            // No extension to classify: keep default behavior.
            error_log(sprintf(
                'wpstreamify_disable_hls_canonical_redirect: Unable to determine file extension for path: %s',
                $path
            ));
            return $redirect_url;
        }

        // For HLS assets, cancel the canonical redirect entirely.
        if (in_array($fileExtension, ['ts', 'm3u8'], true)) {
            return false;
        }
    }
    // Non-proxy or non-HLS request: preserve WordPress's redirect.
    return $redirect_url;
}
add_filter('redirect_canonical', 'wpstreamify_disable_hls_canonical_redirect', 10, 2);


/**
 * Create the cache directory on activation (and log permission problems).
 */
function wpstreamify_create_cache_dir() {
    // Create the directory only if it does not already exist.
    if (!file_exists(WPSTREAMIFY_CACHE_DIR)) {
        if (!mkdir(WPSTREAMIFY_CACHE_DIR, 0755, true)) {
            error_log('wpstreamify_create_cache_dir: Failed to create HLS cache directory: ' . WPSTREAMIFY_CACHE_DIR);
        } elseif (!is_dir(WPSTREAMIFY_CACHE_DIR)) {
            // mkdir reported success but the path is not a directory.
            error_log('wpstreamify_create_cache_dir: Path exists but is not a directory: ' . WPSTREAMIFY_CACHE_DIR);
        }
    } elseif (!is_writable(WPSTREAMIFY_CACHE_DIR)) {
        // Directory exists but we cannot write cache files into it.
        error_log('wpstreamify_create_cache_dir: Cache directory exists but is not writable: ' . WPSTREAMIFY_CACHE_DIR);
    }
}
register_activation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_create_cache_dir');

/**
 * Empty and remove the cache directory on deactivation.
 */
function wpstreamify_remove_cache_dir() {
    // Only proceed if the cache directory actually exists.
    if (file_exists(WPSTREAMIFY_CACHE_DIR) && is_dir(WPSTREAMIFY_CACHE_DIR)) {
        // List directory contents, excluding the . and .. entries.
        $files = array_diff(scandir(WPSTREAMIFY_CACHE_DIR), ['.', '..']);

        if ($files === false) {
            // Could not scan the directory: abort removal.
            error_log('wpstreamify_remove_cache_dir: Failed to scan directory: ' . WPSTREAMIFY_CACHE_DIR);
            return;
        }

        // Delete each cached file before removing the directory itself.
        foreach ($files as $file) {
            $filePath = WPSTREAMIFY_CACHE_DIR . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                if (!unlink($filePath)) {
                    // Log the failure and the underlying error for diagnostics.
                    error_log('wpstreamify_remove_cache_dir: Failed to delete file: ' . $filePath);
                    error_log('Error: ' . json_encode(error_get_last()));
                }
            } elseif (is_dir($filePath)) {
                // We never create subdirectories; flag any that appear.
                error_log('wpstreamify_remove_cache_dir: Unexpected directory found inside cache directory: ' . $filePath);
            } else {
                // Anything that is neither file nor directory is unexpected.
                error_log('wpstreamify_remove_cache_dir: Skipped unknown entry: ' . $filePath);
            }
        }

        // Finally remove the now-empty cache directory.
        if (!rmdir(WPSTREAMIFY_CACHE_DIR)) {
            error_log('wpstreamify_remove_cache_dir: Failed to remove directory: ' . WPSTREAMIFY_CACHE_DIR);
            error_log('Error: ' . json_encode(error_get_last()));
        }
    } else {
        // Nothing to remove.
        error_log('wpstreamify_remove_cache_dir: Cache directory does not exist or is not a directory: ' . WPSTREAMIFY_CACHE_DIR);
    }
}
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_remove_cache_dir');
