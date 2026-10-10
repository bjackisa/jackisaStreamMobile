<?php
/**
 * Autocontent videos cron.
 *
 * Run from the command line, for example every six hours:
 *   php /path/to/autocontent_videos_cron.php
 *   php /path/to/autocontent_videos_cron.php --user=17   (single account, for testing)
 *
 * On every run, for each configured account it searches YouTube with that account's
 * keyword set using the same YouTube Data API key (yt_api) as the existing import feature,
 * then imports between 5 and 9 fresh, public, embeddable videos per account. Candidate
 * videos are checked against T_VIDEOS by YouTube ID before insertion, so re-running only
 * skips duplicates and never double-imports. No lock file is used, matching yt_cron.php.
 */

require_once __DIR__ . '/assets/init.php';
decryptConfigData();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cron may only be run from the command line.\n");
}

ini_set('output_buffering', 'off');
ini_set('implicit_flush', '1');
if (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

define('AUTOCONTENT_VIDEOS_DB_RETRY_ATTEMPTS', 2);
define('AUTOCONTENT_VIDEOS_MIN', 5);
define('AUTOCONTENT_VIDEOS_MAX', 9);
define('AUTOCONTENT_VIDEOS_CANDIDATE_CAP', 50);
define('AUTOCONTENT_VIDEOS_SEARCH_RESULTS', 15);
define('AUTOCONTENT_VIDEOS_USER_AGENT', 'Mozilla/5.0 (compatible; JackisaBot/1.0; +https://jackisa.com)');

/**
 * Account map: user_id => array(username, keywords). The username is validated against
 * T_USERS so a mismatch is reported instead of posting to the wrong channel.
 */
$autocontent_videos_accounts = array(
    24 => array('username' => 'reality', 'keywords' => array('reality tv', 'reality show', 'reality tv episodes', 'reality show highlights', 'reality tv drama', 'reality entertainment', 'celebrity reality show')),
    23 => array('username' => 'fides', 'keywords' => array('Fides', 'Fides faith', 'Christian faith', 'faith in God', 'Christian teaching', 'Bible faith', 'faith testimony')),
    22 => array('username' => 'sadha', 'keywords' => array('Sadha', 'spirituality', 'spiritual wisdom', 'spiritual teachings', 'meditation', 'mindfulness', 'inner peace')),
    21 => array('username' => 'sikhi', 'keywords' => array('Sikhism', 'Sikh', 'Sikh religion', 'Sikh teachings', 'Gurbani', 'Sikh history', 'Golden Temple')),
    20 => array('username' => 'shraddah', 'keywords' => array('Shraddha', 'Hindu spirituality', 'Hindu devotion', 'Hindu bhakti', 'Hindu teachings', 'Hindu devotional', 'Hindu culture')),
    19 => array('username' => 'emunah', 'keywords' => array('Emunah', 'Jewish faith', 'Judaism', 'Torah teachings', 'Jewish spirituality', 'Jewish prayer', 'Jewish culture')),
    18 => array('username' => 'iman', 'keywords' => array('Iman', 'Islam', 'Islamic teachings', 'Quran', 'Muslim spirituality', 'Islamic lectures', 'Islamic reminders')),
    17 => array('username' => 'tech', 'keywords' => array('technology news', 'latest technology', 'tech news', 'AI technology', 'artificial intelligence', 'gadgets', 'technology reviews', 'software development')),
    16 => array('username' => 'business', 'keywords' => array('business news', 'business', 'entrepreneurship', 'startup news', 'business ideas', 'finance and business', 'entrepreneurship Africa', 'Uganda business')),
    15 => array('username' => 'sportplus', 'keywords' => array('sports news', 'football highlights', 'football news', 'basketball highlights', 'sports highlights', 'live sports', 'sports analysis', 'African football')),
    14 => array('username' => 'music', 'keywords' => array('new music', 'music videos', 'latest songs', 'African music', 'Ugandan music', 'music performances', 'new music releases', 'music charts')),
    13 => array('username' => 'drama', 'keywords' => array('African drama', 'African drama series', 'Ugandan drama', 'TV drama series', 'drama episodes', 'African movies', 'drama series')),
    12 => array('username' => 'docu', 'keywords' => array('documentary', 'documentaries', 'African documentary', 'history documentary', 'true story documentary', 'nature documentary', 'science documentary', 'investigative documentary')),
    11 => array('username' => 'cinema', 'keywords' => array('movies', 'new movies', 'movie trailers', 'film', 'cinema', 'African cinema', 'Hollywood movies', 'movie reviews')),
    8  => array('username' => 'roxy', 'keywords' => array('Roxy', 'Roxy Uganda', 'Roxy music', 'Roxy entertainment', 'Roxy performance', 'Roxy interview', 'Roxy live')),
    7  => array('username' => 'JolisAcademy', 'keywords' => array('Jolis Academy', 'Jolis ICT Academy', 'ICT training Uganda', 'computer training Uganda', 'ICT courses Uganda', 'IT training Kampala', 'computer courses Kampala', 'software training Uganda')),
    6  => array('username' => 'cinemaplus', 'keywords' => array('new movies', 'movie trailers', 'latest films', 'African movies', 'Hollywood movies', 'Netflix movies', 'movie reviews', 'full movies')),
    5  => array('username' => 'retty', 'keywords' => array('Retty', 'Retty Uganda', 'Retty music', 'Retty entertainment', 'Retty performance', 'Retty interview', 'Retty live')),
    4  => array('username' => 'news', 'keywords' => array('latest news', 'breaking news', 'Uganda news', 'Africa news', 'world news', 'current affairs', 'Kampala news', 'international news')),
    3  => array('username' => 'faith', 'keywords' => array('Christian faith', 'Christian sermons', 'Bible teaching', 'Christian worship', 'Gospel music', 'Christian prayer', 'Jesus Christ', 'church service')),
    2  => array('username' => 'sport', 'keywords' => array('football', 'football news', 'football highlights', 'Premier League', 'UEFA Champions League', 'sports news', 'African football', 'Uganda football')),
);

$autocontent_videos_disclaimer = "\n\n\n---\n\n**Disclaimer:** Video hosted on YouTube. Clicking plays the media via embedded player or redirects to the original content. Usage is subject to Youtube Terms of Service and [Google Privacy Policy](https://policies.google.com/privacy).";

// =================================================================================================
// Logging
// =================================================================================================

function autocontent_videos_log($message) {
    echo '[' . date('Y-m-d H:i:s') . '] [pid ' . getmypid() . '] ' . $message . PHP_EOL;
    if (function_exists('ob_flush') && ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

// =================================================================================================
// Database helpers (reconnect once and retry, mirroring yt_cron.php)
// =================================================================================================

function autocontent_videos_close_db() {
    global $mysqli, $sqlConnect, $db;
    if ($mysqli instanceof mysqli) {
        try { @$mysqli->close(); } catch (Throwable $exception) {}
    }
    $mysqli = null;
    $sqlConnect = null;
    $db = null;
}

function autocontent_videos_ensure_db() {
    global $mysqli, $sqlConnect, $db, $sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name;

    $connected = false;
    if ($mysqli instanceof mysqli) {
        try { $connected = @$mysqli->ping(); } catch (Throwable $exception) { $connected = false; }
    }
    if ($connected) {
        return;
    }

    autocontent_videos_log('MySQL connection is no longer available; reconnecting...');
    autocontent_videos_close_db();
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
    autocontent_videos_log('MySQL connection restored.');
}

function autocontent_videos_db_call($operation, callable $callback) {
    $last_error = null;
    for ($attempt = 1; $attempt <= AUTOCONTENT_VIDEOS_DB_RETRY_ATTEMPTS; $attempt++) {
        try {
            autocontent_videos_ensure_db();
            return $callback();
        } catch (Throwable $exception) {
            $last_error = $exception;
            if ($attempt >= AUTOCONTENT_VIDEOS_DB_RETRY_ATTEMPTS) {
                break;
            }
            autocontent_videos_log('MySQL operation "' . $operation . '" failed: ' . $exception->getMessage() . '; reconnecting and retrying once...');
            autocontent_videos_close_db();
        }
    }
    throw new RuntimeException('MySQL operation "' . $operation . '" failed after reconnect: ' . $last_error->getMessage(), 0, $last_error);
}

function autocontent_videos_db_insert($operation, array $data) {
    global $db;
    for ($attempt = 1; $attempt <= AUTOCONTENT_VIDEOS_DB_RETRY_ATTEMPTS; $attempt++) {
        autocontent_videos_ensure_db();
        if ($db->insert(T_VIDEOS, $data)) {
            return true;
        }
        $error = method_exists($db, 'getLastError') ? $db->getLastError() : 'unknown database error';
        if ($attempt >= AUTOCONTENT_VIDEOS_DB_RETRY_ATTEMPTS) {
            autocontent_videos_log('MySQL insert "' . $operation . '" failed: ' . $error);
            return false;
        }
        autocontent_videos_log('MySQL insert "' . $operation . '" failed: ' . $error . '; reconnecting and retrying once...');
        autocontent_videos_close_db();
    }
    return false;
}

// =================================================================================================
// YouTube API helpers
// =================================================================================================

function autocontent_videos_api_get($url) {
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => AUTOCONTENT_VIDEOS_USER_AGENT,
    ));
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ($error ? ': ' . $error : ''));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The YouTube API returned invalid JSON.');
    }
    return $decoded;
}

function autocontent_videos_search($api_key, $query) {
    $url = 'https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=' . AUTOCONTENT_VIDEOS_SEARCH_RESULTS . '&q=' . rawurlencode($query) . '&key=' . rawurlencode($api_key);
    $response = autocontent_videos_api_get($url);
    $ids = array();
    foreach ((isset($response['items']) && is_array($response['items'])) ? $response['items'] : array() as $item) {
        if (!empty($item['id']['videoId'])) {
            $ids[] = (string) $item['id']['videoId'];
        }
    }
    return $ids;
}

function autocontent_videos_duration_seconds($duration) {
    try {
        $interval = new DateInterval($duration);
        return ($interval->y * 31536000) + ($interval->m * 2592000) + ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    } catch (Exception $exception) {
        return 0;
    }
}

function autocontent_videos_video_key() {
    do {
        $key = PT_GenerateKey(15, 15);
        $exists = autocontent_videos_db_call('check generated video key', function () use ($key) {
            global $db;
            return $db->where('video_id', $key)->getValue(T_VIDEOS, 'count(*)');
        });
    } while ($exists > 0);
    return $key;
}

function autocontent_videos_description($description) {
    $link_regex = '/(http\:\/\/|https\:\/\/|www\.)([^\ ]+)/i';
    preg_match_all($link_regex, $description, $matches);
    foreach ($matches[0] as $match) {
        $description = str_replace($match, '[a]' . urlencode(strip_tags($match)) . '[/a]', $description);
    }
    return $description;
}

/**
 * Imports up to $target fresh videos for one account. Returns the number imported.
 */
function autocontent_videos_import_account($account, $user_id, $api_key, &$summary) {
    global $db, $autocontent_videos_disclaimer;

    $target = random_int(AUTOCONTENT_VIDEOS_MIN, AUTOCONTENT_VIDEOS_MAX);
    $keywords = $account['keywords'];
    shuffle($keywords);

    $candidate_ids = array();
    foreach ($keywords as $keyword) {
        if (count($candidate_ids) >= AUTOCONTENT_VIDEOS_CANDIDATE_CAP) {
            break;
        }
        try {
            $found = autocontent_videos_search($api_key, $keyword);
        } catch (Exception $exception) {
            $summary['failures'][] = $account['username'] . ': search failed for "' . $keyword . '": ' . $exception->getMessage();
            autocontent_videos_log("  @{$account['username']} search \"{$keyword}\" failed: " . $exception->getMessage());
            continue;
        }
        foreach ($found as $video_id) {
            if (!in_array($video_id, $candidate_ids, true)) {
                $candidate_ids[] = $video_id;
            }
        }
    }

    if (empty($candidate_ids)) {
        autocontent_videos_log("  @{$account['username']}: no search results for any keyword.");
        return 0;
    }

    $existing_ids = autocontent_videos_db_call('check existing YouTube ids', function () use ($candidate_ids) {
        global $db;
        $rows = $db->where('youtube', $candidate_ids, 'IN')->get(T_VIDEOS, null, 'youtube');
        return is_array($rows) ? array_column($rows, 'youtube') : array();
    });
    $candidate_ids = array_values(array_diff($candidate_ids, $existing_ids));
    $summary['duplicates'] += count($existing_ids);
    if (empty($candidate_ids)) {
        autocontent_videos_log("  @{$account['username']}: all search results already imported.");
        return 0;
    }

    $imported = 0;
    foreach (array_chunk($candidate_ids, 50) as $chunk) {
        if ($imported >= $target) {
            break;
        }
        try {
            $details = autocontent_videos_api_get('https://www.googleapis.com/youtube/v3/videos?part=contentDetails,snippet,status&id=' . rawurlencode(implode(',', $chunk)) . '&key=' . rawurlencode($api_key));
        } catch (Exception $exception) {
            $summary['failures'][] = $account['username'] . ': details request failed: ' . $exception->getMessage();
            autocontent_videos_log("  @{$account['username']} details request failed: " . $exception->getMessage());
            continue;
        }
        $items = (isset($details['items']) && is_array($details['items'])) ? $details['items'] : array();
        foreach ($items as $video) {
            if ($imported >= $target) {
                break;
            }
            $youtube_id = isset($video['id']) ? (string) $video['id'] : '';
            $seconds = autocontent_videos_duration_seconds($video['contentDetails']['duration'] ?? '');
            $title = $video['snippet']['title'] ?? '';
            if ($youtube_id === '' || $seconds <= 180 || stripos($title, '#shorts') !== false) {
                $summary['skipped']++;
                continue;
            }
            if (empty($video['status']['embeddable']) || ($video['status']['privacyStatus'] ?? '') !== 'public') {
                $summary['skipped']++;
                continue;
            }
            $thumbs = $video['snippet']['thumbnails'] ?? array();
            $thumbnail = $thumbs['maxres']['url'] ?? ($thumbs['high']['url'] ?? ($thumbs['medium']['url'] ?? 'upload/photos/thumbnail.jpg'));
            $tags = !empty($video['snippet']['tags']) && is_array($video['snippet']['tags']) ? implode(',', array_slice($video['snippet']['tags'], 0, 11)) : '';
            $description = autocontent_videos_description((string) ($video['snippet']['description'] ?? '')) . $autocontent_videos_disclaimer;

            $insert = array(
                'video_id' => autocontent_videos_video_key(),
                'user_id' => $user_id,
                'title' => PT_Secure($title),
                'description' => PT_Secure($description),
                'tags' => PT_Secure($tags),
                'duration' => covtime($video['contentDetails']['duration']),
                'category_id' => 0,
                'youtube' => $youtube_id,
                'thumbnail' => $thumbnail,
                'time' => time(),
                'registered' => date('Y') . '/' . (int) date('m'),
                'is_short' => 0,
            );
            if (autocontent_videos_db_insert('YouTube video ' . $youtube_id, $insert)) {
                $imported++;
                $summary['imported']++;
            } else {
                $summary['failures'][] = $account['username'] . ': database insert failed for YouTube video ' . $youtube_id . '.';
            }
        }
    }

    autocontent_videos_log("  @{$account['username']} imported {$imported} (target {$target})");
    return $imported;
}

// =================================================================================================
// Main
// =================================================================================================

$summary = array('accounts' => array(), 'imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'failures' => array());

try {
    if (empty($pt->config->yt_api)) {
        throw new RuntimeException('The YouTube Data API key (yt_api) is not configured.');
    }

    $only_user = 0;
    foreach ((array) $argv as $argument) {
        if (preg_match('/^--user=(\d+)$/', (string) $argument, $match)) {
            $only_user = (int) $match[1];
        }
    }

    autocontent_videos_log('=== Autocontent videos cron started ===');
    autocontent_videos_log('Accounts queued: ' . count($autocontent_videos_accounts));

    foreach ($autocontent_videos_accounts as $user_id => $account) {
        $user_id = (int) $user_id;
        if ($only_user && $only_user !== $user_id) {
            continue;
        }
        $user = autocontent_videos_db_call('validate configured account', function () use ($user_id) {
            global $db;
            return $db->where('id', $user_id)->getOne(T_USERS, array('id', 'username'));
        });
        if (empty($user->id)) {
            $failure = 'CONFIGURATION FAILURE: user id ' . $user_id . ' does not exist; skipping.';
            $summary['failures'][] = $failure;
            autocontent_videos_log($failure);
            continue;
        }
        if (strcasecmp((string) $user->username, (string) $account['username']) !== 0) {
            $failure = 'CONFIGURATION FAILURE: user id ' . $user_id . ' is @' . $user->username . ' but config expects @' . $account['username'] . '; skipping.';
            $summary['failures'][] = $failure;
            autocontent_videos_log($failure);
            continue;
        }

        autocontent_videos_log("--- Importing for @{$account['username']} (user {$user_id}) ---");
        try {
            $imported = autocontent_videos_import_account($account, $user_id, $pt->config->yt_api, $summary);
            $summary['accounts'][$account['username']] = $imported;
        } catch (Exception $exception) {
            $summary['failures'][] = $account['username'] . ': ' . $exception->getMessage();
            autocontent_videos_log("  @{$account['username']} FAILED: " . $exception->getMessage());
        }
    }

    autocontent_videos_log('--- Import complete ---');
} catch (Exception $exception) {
    $summary['failures'][] = $exception->getMessage();
    autocontent_videos_log('FATAL: ' . $exception->getMessage());
}

autocontent_videos_log('=== Autocontent videos cron finished: imported ' . $summary['imported'] . ', duplicates ' . $summary['duplicates'] . ', skipped ' . $summary['skipped'] . ', failures ' . count($summary['failures']) . ' ===');

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
