<?php

define('WPSTREAMIFY_CACHE_DIR', WP_CONTENT_DIR . '/uploads/wpstreamify_cache/');

function wpstreamify_register_query_vars($vars) {
    $vars[] = 'wpstreamify_path';
    return $vars;
}
add_filter('query_vars', 'wpstreamify_register_query_vars');

function wpstreamify_proxy_handler() {
    $path = get_query_var('wpstreamify_path');
    if (!$path) {
        return;
    }

    $originHost = 'https://basicstreaming.wpstream.live/';
    $remoteUrl = $originHost . $path;
    $cacheFile = WPSTREAMIFY_CACHE_DIR . basename($path);
    $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

    if ($fileExtension === 'ts' || $fileExtension === 'm3u8') {
        wpstreamify_process_hls_request($fileExtension, $cacheFile, $remoteUrl);
    } else {
        wpstreamify_serve_404();
    }
}

function wpstreamify_process_hls_request($fileExtension, $cacheFile, $remoteUrl) {
    if (file_exists($cacheFile)) {
        if ($fileExtension === 'm3u8' && wpstreamify_file_is_expired($cacheFile, 2)) {
            if (!@unlink($cacheFile)) {
                error_log(sprintf(
                    'wpstreamify_process_hls_request: Failed to delete expired cache file: %s',
                    $cacheFile
                ));
            }
        } else {
            wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'HIT');
            exit;
        }
    }

    $lockFile = $cacheFile . '.lock';
    if (file_exists($lockFile)){
        wpstreamify_wait_for_lock($lockFile, $cacheFile, $fileExtension);
        wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'WAIT');
        exit;
    }
    else if (wpstreamify_fetch_and_cache_file($lockFile, $cacheFile, $remoteUrl)) {
        wpstreamify_serve_cached_file($fileExtension, $cacheFile, 'MISS');
    } else {
        wpstreamify_serve_500();
    }
    exit;
}

function wpstreamify_file_is_expired($filePath, $expirySeconds) {
    $lastModified = filemtime($filePath);
    if ($lastModified === false) {
        error_log(sprintf(
            'wpstreamify_file_is_expired: Failed to retrieve last modified time for file: %s',
            $filePath
        ));
        return true; // Consider files with unknown modification time as expired
    }
    $currentTime = time();
    return ($currentTime - $lastModified) > $expirySeconds;
}

function wpstreamify_wait_for_lock($lockFile, $cacheFile, $fileExtension) {
    $cycles = 0;
    while (file_exists($lockFile) && $cycles++ <= 200) {
        usleep(100 * 1000);
    }
    if (file_exists($cacheFile)) {
        return true; 
    }
    else {
        error_log(sprintf(
            'wpstreamify_wait_for_lock: Cached file not found after waiting for lock: %s, Lock file: %s',
            $cacheFile,
            $lockFile
        ));
        return false; 
    }
}

function wpstreamify_serve_cached_file($fileExtension, $cacheFile, $cacheStatus) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if (!file_exists($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Cache file does not exist: %s',
            $cacheFile
        ));
        wpstreamify_serve_404();
        exit;
    }

    if (!is_readable($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Cache file is not readable: %s',
            $cacheFile
        ));
        wpstreamify_serve_500();
        exit;
    }

    if ($fileExtension === 'ts') {
        header('Content-Type: video/mp2t');
        header('Cache-Control: public, max-age=300'); 
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
    } elseif ($fileExtension === 'm3u8') {
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: public, max-age=2, must-revalidate'); 
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2) . ' GMT');
    }

    header("WPS-Cache-Status: $cacheStatus");
    status_header(200);
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

    if (!@readfile($cacheFile)) {
        error_log(sprintf(
            'wpstreamify_serve_cached_file: Failed to read and output cache file: %s',
            $cacheFile
        ));
        wpstreamify_serve_500();
        exit;
    }
}


function wpstreamify_fetch_and_cache_file($lockFile, $cacheFile, $remoteUrl) {
    ignore_user_abort(true);

    $lockHandle = fopen($lockFile, 'w');
    if (!$lockHandle) {
        error_log(sprintf(
            'wpstreamify_fetch_and_cache_file: Failed to create or open lock file: %s',
            $lockFile
        ));
        return false;
    }

    if (flock($lockHandle, LOCK_EX)) {
        try {
            $response = wpstreamify_fetch_remote_content($remoteUrl);

            if ($response === false) {
                error_log(sprintf(
                    'wpstreamify_fetch_and_cache_file: Failed to fetch content from remote URL: %s',
                    $remoteUrl
                ));
            } else {
                if (file_put_contents($cacheFile, $response) === false) {
                    error_log(sprintf(
                        'wpstreamify_fetch_and_cache_file: Failed to write content to cache file: %s',
                        $cacheFile
                    ));
                }
            }
        } catch (Exception $e) {
            error_log(sprintf(
                'wpstreamify_fetch_and_cache_file: Exception during fetch and cache process. Remote URL: %s, Error: %s',
                $remoteUrl,
                $e->getMessage()
            ));
        } finally {
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

function wpstreamify_serve_404() {
    status_header(404);
    header('HTTP/1.1 404 Not Found');
    echo 'Resource not found...';
    exit;
}

function wpstreamify_serve_500() {
    status_header(500);
    header('HTTP/1.1 500 Internal Server Error');
    echo 'An unexpected error occurred.';
    exit;
}

add_action('template_redirect', 'wpstreamify_proxy_handler');

function wpstreamify_fetch_remote_content($url) {
    $ch = curl_init();

    if ($ch === false) {
        error_log(sprintf(
            'wpstreamify_fetch_remote_content: Failed to initialize cURL for URL: %s',
            $url
        ));
        return false;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); 
    curl_setopt($ch, CURLOPT_USERAGENT, "WpStreamIfy/1.0");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log(sprintf(
            'wpstreamify_fetch_remote_content: cURL error for URL: %s. Error: %s',
            $url,
            curl_error($ch)
        ));
    } else {
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

    curl_close($ch);
    return $response;
}


define('WPSTREAMIFY_CACHE_CLEANUP_THRESHOLD', 300); 

function wpstreamify_custom_cron_intervals($schedules) {
    $schedules['every_ten_minutes'] = array(
        'interval' => 600,
        'display'  => __('Every 10 Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'wpstreamify_custom_cron_intervals');

function wpstreamify_delete_old_hls_files() {
    if (!defined('WPSTREAMIFY_CACHE_DIR') || !is_dir(WPSTREAMIFY_CACHE_DIR)) {
        error_log('wpstreamify_delete_old_hls_files: Cache directory is not defined or does not exist.');
        return;
    }

    $current_time = time();
    $files = glob(WPSTREAMIFY_CACHE_DIR . '*');

    if ($files === false) {
        error_log('wpstreamify_delete_old_hls_files: Failed to read cache directory: ' . WPSTREAMIFY_CACHE_DIR);
        return;
    }

    foreach ($files as $file) {
        if (is_file($file)) {
            $file_modification_time = filemtime($file);
            if ($file_modification_time === false) {
                error_log(sprintf(
                    'wpstreamify_delete_old_hls_files: Failed to get modification time for file: %s',
                    $file
                ));
                continue;
            }

            if ($current_time - $file_modification_time > WPSTREAMIFY_CACHE_CLEANUP_THRESHOLD) {
                if (!unlink($file)) {
                    error_log(sprintf(
                        'wpstreamify_delete_old_hls_files: Failed to delete file: %s',
                        $file
                    ));
                }
            }
        } else {
            error_log(sprintf(
                'wpstreamify_delete_old_hls_files: Skipped non-file entry: %s',
                $file
            ));
        }
    }
}


function wpstreamify_schedule_cleanup_event() {
    if (!wp_next_scheduled('wpstreamify_cleanup_event')) {
        if (!wp_schedule_event(time(), 'every_ten_minutes', 'wpstreamify_cleanup_event')) {
            error_log('wpstreamify_schedule_cleanup_event: Failed to schedule cleanup event.');
        }
    }
}
add_action('wp', 'wpstreamify_schedule_cleanup_event');

add_action('wpstreamify_cleanup_event', 'wpstreamify_delete_old_hls_files');

function wpstreamify_unschedule_cleanup_event() {
    $timestamp = wp_next_scheduled('wpstreamify_cleanup_event');
    if ($timestamp) {
        if (!wp_unschedule_event($timestamp, 'wpstreamify_cleanup_event')) {
            error_log('wpstreamify_unschedule_cleanup_event: Failed to unschedule cleanup event.');
        }
    }
}
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_unschedule_cleanup_event');

function wpstreamify_add_rewrite_rules() {
    add_rewrite_rule('^wpstreamify/(.+)$', 'index.php?wpstreamify_path=$matches[1]', 'top');
}
add_action('init', 'wpstreamify_add_rewrite_rules');

function wpstreamify_flush_rewrite_rules() {
    if (!flush_rewrite_rules()) {
        error_log('wpstreamify_flush_rewrite_rules: Failed to flush rewrite rules.');
    }
}
register_activation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_flush_rewrite_rules');
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_flush_rewrite_rules');

function wpstreamify_disable_hls_canonical_redirect($redirect_url, $requested_url) {
    if (get_query_var('wpstreamify_path')) {
        $parsed_url = parse_url($requested_url);

        if ($parsed_url === false) {
            error_log(sprintf(
                'wpstreamify_disable_hls_canonical_redirect: Failed to parse requested URL: %s',
                $requested_url
            ));
            return $redirect_url;
        }

        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        if (!$fileExtension) {
            error_log(sprintf(
                'wpstreamify_disable_hls_canonical_redirect: Unable to determine file extension for path: %s',
                $path
            ));
            return $redirect_url;
        }

        if (in_array($fileExtension, ['ts', 'm3u8'], true)) {
            return false;
        }
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'wpstreamify_disable_hls_canonical_redirect', 10, 2);


function wpstreamify_create_cache_dir() {
    if (!file_exists(WPSTREAMIFY_CACHE_DIR)) {
        if (!mkdir(WPSTREAMIFY_CACHE_DIR, 0755, true)) {
            error_log('wpstreamify_create_cache_dir: Failed to create HLS cache directory: ' . WPSTREAMIFY_CACHE_DIR);
        } elseif (!is_dir(WPSTREAMIFY_CACHE_DIR)) {
            error_log('wpstreamify_create_cache_dir: Path exists but is not a directory: ' . WPSTREAMIFY_CACHE_DIR);
        }
    } elseif (!is_writable(WPSTREAMIFY_CACHE_DIR)) {
        error_log('wpstreamify_create_cache_dir: Cache directory exists but is not writable: ' . WPSTREAMIFY_CACHE_DIR);
    }
}
register_activation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_create_cache_dir');

function wpstreamify_remove_cache_dir() {
    if (file_exists(WPSTREAMIFY_CACHE_DIR) && is_dir(WPSTREAMIFY_CACHE_DIR)) {
        $files = array_diff(scandir(WPSTREAMIFY_CACHE_DIR), ['.', '..']);

        if ($files === false) {
            error_log('wpstreamify_remove_cache_dir: Failed to scan directory: ' . WPSTREAMIFY_CACHE_DIR);
            return;
        }

        foreach ($files as $file) {
            $filePath = WPSTREAMIFY_CACHE_DIR . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                if (!unlink($filePath)) {
                    error_log('wpstreamify_remove_cache_dir: Failed to delete file: ' . $filePath);
                    error_log('Error: ' . json_encode(error_get_last()));
                }
            } elseif (is_dir($filePath)) {
                error_log('wpstreamify_remove_cache_dir: Unexpected directory found inside cache directory: ' . $filePath);
            } else {
                error_log('wpstreamify_remove_cache_dir: Skipped unknown entry: ' . $filePath);
            }
        }

        if (!rmdir(WPSTREAMIFY_CACHE_DIR)) {
            error_log('wpstreamify_remove_cache_dir: Failed to remove directory: ' . WPSTREAMIFY_CACHE_DIR);
            error_log('Error: ' . json_encode(error_get_last()));
        }
    } else {
        error_log('wpstreamify_remove_cache_dir: Cache directory does not exist or is not a directory: ' . WPSTREAMIFY_CACHE_DIR);
    }
}
register_deactivation_hook(WP_PLUGIN_DIR . '/' . WPSTREAM_PLUGIN_BASE, 'wpstreamify_remove_cache_dir');
