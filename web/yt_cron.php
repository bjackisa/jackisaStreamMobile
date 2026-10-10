<?php
/**
 * YouTube import cron.
 * Run every six hours, for example: 0 0,6,12,18 * * * php /path/to/yt_cron.php
 *
 * Flow, on every run (single pass, no phases, no waiting):
 *   For each configured channel: validate its mapped account, search YouTube using that
 *   channel's PRESET keyword query, fetch video details, filter, and import matches.
 *   Then send one summary email.
 *
 * This is a deliberately simplified rewrite:
 *   - No Gemini / AI query generation. Each channel always uses its fixed keyword query below.
 *   - No "dead video cleanup" phase, no batching, no progress bars. Those were the parts making
 *     extra outbound API calls and were the likely cause of the run dying silently mid-script
 *     on shared hosting (a stalled/killed cURL call with nothing left to show for it in the log).
 *   - Every candidate video is still checked against T_VIDEOS by YouTube ID before insertion,
 *     so running this twice in a row just skips duplicates — it never double-imports.
 *
 * Concurrency: this script still does NOT use a lock file. Overlapping runs are safe for the
 * same reason as above (duplicate check before insert) and every log line is PID-tagged so
 * interleaved output from simultaneous runs stays readable in a shared cron log file.
 */
require_once __DIR__ . '/assets/init.php';
decryptConfigData();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cron may only be run from the command line.\n");
}

// Force every echo to reach the terminal / cron log immediately instead of waiting in a buffer.
ini_set('output_buffering', 'off');
ini_set('implicit_flush', '1');
if (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

define('DB_RETRY_ATTEMPTS', 2); // Reconnect once when the cron loses MySQL mid-run.

$summary = array('imported' => 0, 'duplicates' => 0, 'shorts_skipped' => 0, 'invalid_skipped' => 0, 'failures' => array(), 'channels' => array());
$disclaimer = "\n\n\n---\n\n**Disclaimer:** Video hosted on YouTube. Clicking plays the media via embedded player or redirects to the original content. Usage is subject to Youtube Terms of Service and [Google Privacy Policy](https://policies.google.com/privacy).";

// Each channel maps to an account (username), a category, and the FIXED search query used
// every run. Edit the 'query' values any time — no AI call is involved anymore.
$channels = array(
    'news' => array('username' => 'news', 'category_id' => 11, 'query' => 'breaking news latest'),
    'sports' => array('username' => 'sport', 'category_id' => 5, 'query' => 'highlights full match'),
    'movies' => array('username' => 'cinema', 'category_id' => 1, 'query' => 'official trailer'),
    'christian based sermons' => array('username' => 'faith', 'category_id' => 1028, 'query' => 'full sermon'),
    'documentaries' => array('username' => 'docu', 'category_id' => 0, 'query' => 'full documentary'),
    'tv drama full episodes' => array('username' => 'drama', 'category_id' => 0, 'query' => 'full episode')
);

// =================================================================================================
// Logging
// =================================================================================================

function yt_cron_log($message) {
    echo '[' . date('Y-m-d H:i:s') . '] [pid ' . getmypid() . '] ' . $message . PHP_EOL;
    if (function_exists('ob_flush') && ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

// =================================================================================================
// Database helpers (kept — this is what makes a long run survive a dropped MySQL connection;
// it never blocks or waits, it just reconnects once and retries)
// =================================================================================================

function yt_cron_close_database_connection() {
    global $mysqli, $sqlConnect, $db;
    if ($mysqli instanceof mysqli) {
        try {
            @$mysqli->close();
        } catch (Throwable $exception) {
            // Already closed — safe to ignore.
        }
    }
    $mysqli = null;
    $sqlConnect = null;
    $db = null;
}

function yt_cron_ensure_database_connection() {
    global $mysqli, $sqlConnect, $db, $sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name;

    $connected = false;
    if ($mysqli instanceof mysqli) {
        try {
            $connected = @$mysqli->ping();
        } catch (Throwable $exception) {
            $connected = false;
        }
    }
    if ($connected) {
        return;
    }

    yt_cron_log('MySQL connection is no longer available; reconnecting...');
    yt_cron_close_database_connection();
    try {
        $connection = @new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);
    } catch (Throwable $exception) {
        throw new RuntimeException('MySQL reconnect failed: ' . $exception->getMessage(), 0, $exception);
    }
    if ($connection->connect_errno) {
        throw new RuntimeException('MySQL reconnect failed: ' . $connection->connect_error);
    }
    if (!$connection->set_charset('utf8mb4')) {
        throw new RuntimeException('MySQL reconnect could not set utf8mb4: ' . $connection->error);
    }
    $mysqli = $connection;
    $sqlConnect = $connection;
    $db = new MysqliDb($connection);
    yt_cron_log('MySQL connection restored.');
}

function yt_cron_database_call($operation, callable $callback) {
    $last_error = null;
    for ($attempt = 1; $attempt <= DB_RETRY_ATTEMPTS; $attempt++) {
        try {
            yt_cron_ensure_database_connection();
            return $callback();
        } catch (Throwable $exception) {
            $last_error = $exception;
            if ($attempt >= DB_RETRY_ATTEMPTS) {
                break;
            }
            yt_cron_log('MySQL operation "' . $operation . '" failed: ' . $exception->getMessage() . '; reconnecting and retrying once...');
            yt_cron_close_database_connection();
        }
    }
    throw new RuntimeException('MySQL operation "' . $operation . '" failed after reconnect: ' . $last_error->getMessage(), 0, $last_error);
}

function yt_cron_database_insert($operation, array $data) {
    global $db;
    for ($attempt = 1; $attempt <= DB_RETRY_ATTEMPTS; $attempt++) {
        yt_cron_ensure_database_connection();
        $inserted = $db->insert(T_VIDEOS, $data);
        if ($inserted) {
            return $inserted;
        }
        $error = method_exists($db, 'getLastError') ? $db->getLastError() : 'unknown database error';
        if ($attempt >= DB_RETRY_ATTEMPTS) {
            yt_cron_log('MySQL insert "' . $operation . '" failed: ' . $error);
            return false;
        }
        yt_cron_log('MySQL insert "' . $operation . '" failed: ' . $error . '; reconnecting and retrying once...');
        yt_cron_close_database_connection();
    }
    return false;
}

// =================================================================================================
// API / data helpers
// =================================================================================================

function yt_cron_request($url, $method = 'GET', $payload = null, $headers = array()) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 45);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    }
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ($error ? ': ' . $error : ''));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The API returned invalid JSON.');
    }
    return $decoded;
}

function yt_cron_duration_seconds($duration) {
    try {
        $interval = new DateInterval($duration);
        return ($interval->y * 31536000) + ($interval->m * 2592000) + ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    } catch (Exception $exception) {
        return 0;
    }
}

function yt_cron_video_key() {
    global $db;
    do {
        $key = PT_GenerateKey(15, 15);
        $exists = yt_cron_database_call('check generated video key', function () use ($key) {
            global $db;
            return $db->where('video_id', $key)->getValue(T_VIDEOS, 'count(*)');
        });
    } while ($exists > 0);
    return $key;
}

function yt_cron_send_summary($summary) {
    global $pt;
    $channels = array();
    foreach ($summary['channels'] as $name => $details) {
        $channels[] = '<li><strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>: query “' . htmlspecialchars($details['query'], ENT_QUOTES, 'UTF-8') . '”; imported ' . (int) $details['imported'] . '.</li>';
    }
    $failures = empty($summary['failures']) ? '<li>None</li>' : '';
    foreach ($summary['failures'] as $failure) {
        $failures .= '<li>' . htmlspecialchars($failure, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $body = '<p>Dear Barack,</p><p>The following is the summary of the cronjob run to import videos to jackisa.com.</p><ul>' . implode('', $channels) . '</ul><p>Totals: imported ' . (int) $summary['imported'] . ', duplicates skipped ' . (int) $summary['duplicates'] . ', shorts skipped ' . (int) $summary['shorts_skipped'] . ', invalid videos skipped ' . (int) $summary['invalid_skipped'] . '.</p><p>Failures:</p><ul>' . $failures . '</ul>';
    $from = !empty($pt->config->smtp_username) ? $pt->config->smtp_username : $pt->config->email;
    return PT_SendMessage(array('from_email' => $from, 'from_name' => $pt->config->name, 'to_email' => 'barackdanieljackisa@gmail.com', 'to_name' => 'Barack', 'subject' => 'Jackisa YouTube import cron summary', 'message_body' => $body, 'charSet' => 'UTF-8', 'return' => true));
}

function yt_cron_manual_youtube_description($description) {
    $link_regex = '/(http\:\/\/|https\:\/\/|www\.)([^\ ]+)/i';
    preg_match_all($link_regex, $description, $matches);
    foreach ($matches[0] as $match) {
        $description = str_replace($match, '[a]' . urlencode(strip_tags($match)) . '[/a]', $description);
    }
    return $description;
}

/**
 * Fully imports one channel: search -> fetch details -> filter -> insert.
 * Uses the channel's preset $query directly — no AI call, no batching.
 */
function yt_cron_import_channel($channel, array $mapping, $query, array &$summary) {
    global $db, $pt, $disclaimer;

    $limit = 50;
    if (!class_exists('Madcoda\\Youtube\\Youtube')) {
        require_once __DIR__ . '/assets/libs/youtube-sdk/vendor/autoload.php';
    }

    try {
        $youtube = new Madcoda\Youtube\Youtube(array('key' => $pt->config->yt_api));
        $search = $youtube->searchAdvanced(array(
            'q' => $query,
            'type' => 'video',
            'part' => 'id',
            'maxResults' => $limit
        ), true);
    } catch (Exception $exception) {
        yt_cron_log("  '{$channel}': YouTube Search API/SDK exception; query=\"{$query}\": " . $exception->getMessage());
        $summary['failures'][] = $channel . ': YouTube Search API/SDK exception for query "' . $query . '": ' . $exception->getMessage();
        return;
    }

    $search_ids = array();
    foreach ((isset($search['results']) && is_array($search['results'])) ? $search['results'] : array() as $result) {
        if (is_object($result) && !empty($result->id->videoId)) {
            $search_ids[] = (string) $result->id->videoId;
        } elseif (is_array($result) && !empty($result['id']['videoId'])) {
            $search_ids[] = (string) $result['id']['videoId'];
        }
    }
    $search_ids = array_values(array_unique($search_ids));
    if (empty($search_ids)) {
        yt_cron_log("  '{$channel}': YouTube search returned no results for query \"{$query}\".");
        return;
    }

    // One query to find which of these are already imported, instead of one query per ID.
    $existing_ids = yt_cron_database_call('check existing YouTube ids', function () use ($search_ids) {
        global $db;
        $rows = $db->where('youtube', $search_ids, 'IN')->get(T_VIDEOS, null, 'youtube');
        return is_array($rows) ? array_column($rows, 'youtube') : array();
    });
    $candidate_ids = array_values(array_diff($search_ids, $existing_ids));
    $summary['duplicates'] += count($existing_ids);
    if (empty($candidate_ids)) {
        yt_cron_log("  '{$channel}': all " . count($search_ids) . ' search result(s) already imported.');
        return;
    }

    try {
        $details = yt_cron_request('https://www.googleapis.com/youtube/v3/videos?part=contentDetails,snippet,status&id=' . rawurlencode(implode(',', $candidate_ids)) . '&key=' . rawurlencode($pt->config->yt_api));
    } catch (Exception $exception) {
        yt_cron_log("  '{$channel}': YouTube details request failed: " . $exception->getMessage());
        $summary['failures'][] = $channel . ': YouTube details request failed for query "' . $query . '": ' . $exception->getMessage();
        return;
    }
    $items = isset($details['items']) && is_array($details['items']) ? $details['items'] : array();
    if (empty($items)) {
        yt_cron_log("  '{$channel}': YouTube details response had no items.");
        return;
    }

    $total = count($items); $shorts = 0; $non_public = 0; $validation = 0;
    foreach ($items as $index => $video) {
        yt_cron_log("  '{$channel}' video " . ($index + 1) . '/' . $total);
        $youtube_id = $video['id'] ?? '';
        $seconds = yt_cron_duration_seconds($video['contentDetails']['duration'] ?? '');
        $title = $video['snippet']['title'] ?? '';
        $video_category = (int) ($video['snippet']['categoryId'] ?? 0);
        if ($seconds <= 180 || stripos($title, '#shorts') !== false) {
            $summary['shorts_skipped']++; $shorts++; continue;
        }
        if (empty($video['status']['embeddable']) || ($video['status']['privacyStatus'] ?? '') !== 'public') {
            $summary['invalid_skipped']++; $non_public++; continue;
        }
        if ($youtube_id === '' || ($mapping['category_id'] && $video_category !== $mapping['category_id'])) {
            $summary['invalid_skipped']++; $validation++; continue;
        }
        $thumbs = $video['snippet']['thumbnails'] ?? array();
        $thumbnail = $thumbs['maxres']['url'] ?? ($thumbs['high']['url'] ?? ($thumbs['medium']['url'] ?? 'upload/photos/thumbnail.jpg'));
        $tags = !empty($video['snippet']['tags']) && is_array($video['snippet']['tags']) ? implode(',', array_slice($video['snippet']['tags'], 0, 11)) : '';
        $description = yt_cron_manual_youtube_description((string) ($video['snippet']['description'] ?? '')) . $disclaimer;
        $video_insert = array('video_id' => yt_cron_video_key(), 'user_id' => $mapping['user_id'], 'title' => PT_Secure($title), 'description' => PT_Secure($description), 'tags' => PT_Secure($tags), 'duration' => covtime($video['contentDetails']['duration']), 'category_id' => $mapping['category_id'], 'youtube' => $youtube_id, 'thumbnail' => $thumbnail, 'time' => time(), 'registered' => date('Y') . '/' . (int) date('m'), 'is_short' => 0);
        if (yt_cron_database_insert('YouTube video ' . $youtube_id, $video_insert)) { $summary['imported']++; $summary['channels'][$channel]['imported']++; }
        else { $summary['failures'][] = $channel . ': database insert failed for YouTube video ' . $youtube_id . '.'; }
    }
    yt_cron_log("  '{$channel}' complete: search_results=" . count($search_ids) . ' already_imported=' . count($existing_ids) . " detail_items={$total} shorts_skipped={$shorts} non_public_or_non_embeddable={$non_public} validation_skipped={$validation} imported=" . $summary['channels'][$channel]['imported']);
}

// =================================================================================================
// Main — single pass, no phases, no waiting
// =================================================================================================

try {
    if (empty($pt->config->yt_api)) {
        throw new RuntimeException('The YouTube Data API key (yt_api) is not configured.');
    }

    yt_cron_log('=== YouTube import cron started ===');
    yt_cron_log('Channels queued: ' . implode(', ', array_keys($channels)));

    foreach ($channels as $channel => $mapping) {
        $user = yt_cron_database_call('validate configured import account', function () use ($mapping) {
            global $db;
            return $db->where('username', $mapping['username'])->getOne(T_USERS, array('id', 'username'));
        });
        if (empty($user->id)) {
            $failure = "CONFIGURATION FAILURE: channel '{$channel}' maps to missing account @{$mapping['username']} in T_USERS; skipping.";
            $summary['failures'][] = $failure;
            yt_cron_log($failure);
            continue;
        }
        $mapping['user_id'] = (int) $user->id;
        $summary['channels'][$channel] = array('query' => $mapping['query'], 'imported' => 0);

        yt_cron_log("--- Importing '{$channel}' (query: \"{$mapping['query']}\") ---");
        try {
            yt_cron_import_channel($channel, $mapping, $mapping['query'], $summary);
        } catch (Exception $exception) {
            $summary['failures'][] = $channel . ': ' . $exception->getMessage();
            yt_cron_log("  '{$channel}' FAILED: " . $exception->getMessage());
        }
    }

    yt_cron_log('--- Import complete ---');
    yt_cron_database_call('update admin details', function () {
        return PT_UpdateAdminDetails();
    });
} catch (Exception $exception) {
    $summary['failures'][] = $exception->getMessage();
    yt_cron_log('FATAL: ' . $exception->getMessage());
}

yt_cron_log('--- Sending summary email ---');
$mail_result = yt_cron_send_summary($summary);
if ($mail_result !== true) {
    $summary['failures'][] = 'Summary email failed: ' . (is_string($mail_result) ? $mail_result : 'SMTP delivery was not accepted.');
}

yt_cron_log('=== YouTube import cron finished: imported ' . $summary['imported'] . ', duplicates ' . $summary['duplicates'] . ', shorts skipped ' . $summary['shorts_skipped'] . ', invalid skipped ' . $summary['invalid_skipped'] . ', failures ' . count($summary['failures']) . ' ===');

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;