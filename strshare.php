<?php
session_start(); // Start session to handle multiple devices

$headers = array(
    'Icy-MetaData: 1',
    'Accept-Encoding: identity',
    'Host: starshare.org',
    'Connection: Keep-Alive',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36' // Add user agent
);

function extractDomain($url, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status_code == 302 && preg_match('/Location: (.+?)\r?\n/', $response, $matches)) {
        $location_url = trim($matches[1]);
        return $location_url;
    } else {
        error_log("Error: Unable to extract domain. Status code: $status_code");
        return false;
    }
}

// Cache settings
$cache_time = 60; // Cache time in seconds
$cache_dir = __DIR__ . '/cache/';

if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

// Main script
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id)) {
    echo "Error: 'id' parameter is missing in input.";
} else {
    if (!isset($_SESSION['active_devices'])) {
        $_SESSION['active_devices'] = 0;
    }

    if ($_SESSION['active_devices'] >= 20) {
        echo "Error: Maximum number of devices reached.";
        exit;
    }

    $cache_file = $cache_dir . md5($id) . '.cache';
    
    // Check if cache file exists and is still valid
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        // Lock the cache file for reading
        $fp = fopen($cache_file, 'r');
        if (flock($fp, LOCK_SH)) { // shared lock
            $domain = fread($fp, filesize($cache_file));
            flock($fp, LOCK_UN); // release the lock
        }
        fclose($fp);
    } else {
        session_write_close(); // Unlock the session data
        $url_for_domain = "http://starshare.org:80/live/love95/love95/{$id}.ts";
        $domain = extractDomain($url_for_domain, $headers);

        if ($domain) {
            // Lock the cache file for writing
            $fp = fopen($cache_file, 'w');
            if (flock($fp, LOCK_EX)) { // exclusive lock
                fwrite($fp, $domain);
                flock($fp, LOCK_UN); // release the lock
            }
            fclose($fp);
        } else {
            echo "Error: Unable to extract domain.";
            exit;
        }
    }

    if ($domain) {
        $_SESSION['active_devices']++;
        header("Location: $domain");
        exit;
    } else {
        echo "Error: Unable to redirect to the extracted domain.";
    }
}
?>
