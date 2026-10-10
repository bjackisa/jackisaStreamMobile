<?php
// +------------------------------------------------------------------------+
// | Jackisa Stream - imported livestream sources.                            |
// |                                                                          |
// | A YouTube live or a generic remote live URL is turned into a normal      |
// | `videos` row (type = 'live') so it renders in the feed exactly like an   |
// | Agora stream. The source-specific bookkeeping lives in `live_imports`.   |
// |                                                                          |
// | Lifecycle:                                                               |
// |   youtube -> when the stream ends it becomes a regular video (recording) |
// |   remote  -> when the stream ends the placeholder is removed entirely    |
// +------------------------------------------------------------------------+

/** Parse and classify a pasted live URL. Returns null when it is unusable. */
function PT_LiveParseSource($url) {
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, array('http', 'https'))) {
        return null;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = (string) parse_url($url, PHP_URL_PATH);

    // YouTube live / embed / watch / youtu.be
    $yt_id = null;
    if (preg_match('/(?:youtube\.com|youtu\.be|youtube-nocookie\.com)/i', $host)) {
        if (preg_match('#/(?:embed|live|shorts|v)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
            $yt_id = $m[1];
        } elseif (preg_match('/(?:youtu\.be)\/([A-Za-z0-9_-]{6,})/i', $url, $m)) {
            $yt_id = $m[1];
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (!empty($q['v'])) {
            $yt_id = preg_replace('/[^A-Za-z0-9_-]/', '', $q['v']);
        }
    }
    if (!empty($yt_id)) {
        return array(
            'source_type'  => 'youtube',
            'provider'     => 'youtube',
            'source_ref'   => $yt_id,
            'source_url'   => $url,
            'playback_url' => 'https://www.youtube.com/embed/' . $yt_id . '?autoplay=1',
            'keep_after_end' => 1,
        );
    }

    // Twitch live channel
    if (preg_match('/(?:twitch\.tv)/i', $host) && preg_match('#^/([A-Za-z0-9_]{3,})#', $path, $m)) {
        return array(
            'source_type'  => 'remote',
            'provider'     => 'twitch',
            'source_ref'   => $m[1],
            'source_url'   => $url,
            'playback_url' => $url,
            'keep_after_end' => 0,
        );
    }

    // Anything else that looks like a live stream (HLS, mp4, embed).
    $lower = strtolower($url);
    $is_live_like = (strpos($lower, '.m3u8') !== false
        || strpos($lower, '.mp4') !== false
        || strpos($lower, '/hls/') !== false
        || strpos($lower, '/live/') !== false
        || stripos($lower, 'embed') !== false
        || stripos($lower, 'stream') !== false);

    if ($is_live_like) {
        return array(
            'source_type'  => 'remote',
            'provider'     => 'generic',
            'source_ref'   => '',
            'source_url'   => $url,
            'playback_url' => $url,
            'keep_after_end' => 0,
        );
    }

    return null;
}

/**
 * True when a remote URL is a directly playable media file (HLS / mp4 / webm).
 * Such URLs can be probed and handed straight to the native player; anything
 * else (YouTube/Twitch pages, embeds) is treated as an embeddable page.
 */
function PT_LiveIsDirectSource($url) {
    $lower = strtolower(html_entity_decode(trim((string) $url), ENT_QUOTES));
    if ($lower === '') {
        return false;
    }
    if (stripos($lower, 'youtube.com') !== false || stripos($lower, 'youtu.be') !== false || stripos($lower, 'twitch.tv') !== false) {
        return false;
    }
    return (strpos($lower, '.m3u8') !== false
        || strpos($lower, '/hls/') !== false
        || strpos($lower, '.mp4') !== false
        || strpos($lower, '.webm') !== false
        || strpos($lower, '.ogv') !== false
        || strpos($lower, '.ogg') !== false);
}

/**
 * Best-effort liveness probe for a remote (non-YouTube) source. Returns
 * true when the origin answers with a live signal, false when it clearly does
 * not, and null when we cannot tell (then the heartbeat is the only signal).
 */
function PT_LiveProbeRemote($url) {
    $url = html_entity_decode(trim((string) $url), ENT_QUOTES);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, array('http', 'https'))) {
        return null;
    }

    $is_hls = (strpos(strtolower($url), '.m3u8') !== false
        || strpos(strtolower($url), '/hls/') !== false);

    if (!function_exists('curl_init')) {
        return PT_LiveProbeRemoteStream($url, $is_hls);
    }

    $ch = @curl_init($url);
    if (!$ch) {
        return null;
    }
    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    @curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    @curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    @curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; JackisaStream/1.0)');
    if ($is_hls) {
        // A playlist is tiny; fetch it so we can look for live markers.
        @curl_setopt($ch, CURLOPT_RANGE, '0-65535');
    } else {
        // Body isn't needed for a plain media file, just the status line.
        @curl_setopt($ch, CURLOPT_NOBODY, true);
    }
    $body = @curl_exec($ch);
    $code = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    if ($code >= 400) {
        return false;
    }
    if ($code === 200) {
        if ($is_hls) {
            // A stale/dead playlist carries #EXT-X-ENDLIST and no live target.
            if (is_string($body) && stripos($body, '#EXTM3U') !== false) {
                if (stripos($body, '#EXT-X-ENDLIST') !== false) {
                    return false;
                }
                return true;
            }
            return null;
        }
        return true;
    }
    return null;
}

/**
 * Fallback probe used when the curl extension is unavailable. Relies on
 * allow_url_fopen. Returns the same tri-state as PT_LiveProbeRemote.
 */
function PT_LiveProbeRemoteStream($url, $is_hls) {
    if (!ini_get('allow_url_fopen')) {
        return null;
    }
    $ctx = @stream_context_create(array('http' => array(
        'method'        => 'GET',
        'timeout'       => 10,
        'ignore_errors' => true,
        'follow_location' => 1,
        'max_redirects' => 3,
        'header'        => "User-Agent: Mozilla/5.0 (compatible; JackisaStream/1.0)\r\n",
    )));
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $code = (int) $m[1];
            }
        }
    }
    if ($code >= 400) {
        return false;
    }
    if ($code === 200) {
        if ($is_hls) {
            if (is_string($body) && stripos($body, '#EXTM3U') !== false) {
                if (stripos($body, '#EXT-X-ENDLIST') !== false) {
                    return false;
                }
                return true;
            }
            return null;
        }
        return true;
    }
    return null;
}

/** Ask the YouTube Data API whether a video is live, upcoming or ended. */
function PT_LiveYoutubeStatus($video_id) {
    global $pt;
    if (empty($video_id) || empty($pt->config->yt_api)) {
        return null;
    }
    $api = 'https://www.googleapis.com/youtube/v3/videos?part=snippet,liveStreamingDetails,status'
        . '&id=' . rawurlencode($video_id) . '&key=' . rawurlencode($pt->config->yt_api);

    $response = null;
    if (function_exists('connect_to_url')) {
        $response = @connect_to_url($api);
    }
    if (empty($response)) {
        $ch = @curl_init($api);
        if ($ch) {
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = @curl_exec($ch);
            @curl_close($ch);
        }
    }
    if (empty($response)) {
        return null;
    }
    $data = json_decode($response, true);
    if (empty($data['items'][0])) {
        return array('exists' => false, 'live' => false, 'ended' => true, 'title' => '', 'thumbnail' => '', 'description' => '');
    }
    $item     = $data['items'][0];
    $snippet  = isset($item['snippet']) ? $item['snippet'] : array();
    $details  = isset($item['liveStreamingDetails']) ? $item['liveStreamingDetails'] : array();
    $broadcast = isset($snippet['liveBroadcastContent']) ? $snippet['liveBroadcastContent'] : 'none';

    $thumbs    = isset($snippet['thumbnails']) ? $snippet['thumbnails'] : array();
    $thumbnail = !empty($thumbs['maxres']['url']) ? $thumbs['maxres']['url']
        : (!empty($thumbs['high']['url']) ? $thumbs['high']['url']
        : (!empty($thumbs['medium']['url']) ? $thumbs['medium']['url'] : ''));

    return array(
        'exists'       => true,
        'live'         => ($broadcast === 'live' || !empty($details['actualStartTime']) && empty($details['actualEndTime'])),
        'ended'        => ($broadcast === 'none' && !empty($details['actualEndTime'])),
        'upcoming'     => ($broadcast === 'upcoming'),
        'title'        => isset($snippet['title']) ? $snippet['title'] : '',
        'description'  => isset($snippet['description']) ? $snippet['description'] : '',
        'thumbnail'    => $thumbnail,
    );
}

/**
 * Import a live source for a user. Creates the livestream row and the import
 * bookkeeping. Returns a status array.
 */
function PT_LiveImportSource($user_id, $url, $title, $description, $category_id = 0, $privacy = 0) {
    global $db, $pt;

    $source = PT_LiveParseSource($url);
    if (empty($source)) {
        return array('status' => 0, 'message' => 'invalid');
    }

    // YouTube lives must actually be live right now.
    if ($source['source_type'] === 'youtube') {
        $status = PT_LiveYoutubeStatus($source['source_ref']);
        if (empty($status) || empty($status['exists'])) {
            return array('status' => 0, 'message' => 'not_found');
        }
        if (empty($status['live'])) {
            return array('status' => 0, 'message' => 'not_live');
        }
        if (empty($title) && !empty($status['title'])) {
            $title = $status['title'];
        }
        if (empty($description) && !empty($status['description'])) {
            $description = $status['description'];
        }
        if (!empty($status['thumbnail'])) {
            $source['thumbnail'] = $status['thumbnail'];
        }
    }

    $title = trim((string) $title);
    if ($title === '') {
        $title = 'Live stream';
    }
    $description = trim((string) $description);

    $video_id = PT_GenerateKey(15, 15);
    if ($db->where('video_id', $video_id)->getValue(T_VIDEOS, 'COUNT(*)') > 0) {
        $video_id = PT_GenerateKey(15, 15);
    }

    $thumbnail = !empty($source['thumbnail']) ? PT_Secure($source['thumbnail']) : 'upload/photos/thumbnail.jpg';
    $now       = time();

    $insert = array(
        'video_id'       => $video_id,
        'user_id'        => (int) $user_id,
        'type'           => 'live',
        'title'          => PT_Secure($title),
        'description'    => PT_Secure($description),
        'category_id'    => (int) $category_id,
        'privacy'        => (int) $privacy,
        'thumbnail'      => $thumbnail,
        'video_location' => PT_Secure($source['playback_url']),
        'stream_name'    => 'import_' . $video_id,
        'live_time'      => $now,
        'live_ended'     => 0,
        'live_source'    => $source['source_type'],
        'live_source_ref' => PT_Secure($source['source_ref']),
        'registered'     => gmdate('Y') . '/' . intval(gmdate('m')),
        'time'           => $now,
        'approved'       => 1,
    );

    $post_id = $db->insert(T_VIDEOS, $insert);
    if (empty($post_id)) {
        return array('status' => 0, 'message' => 'insert_failed');
    }

    $db->insert(T_LIVE_IMPORTS, array(
        'video_id'        => $post_id,
        'owner_id'        => (int) $user_id,
        'source_type'     => $source['source_type'],
        'provider'        => $source['provider'],
        'source_url'      => PT_Secure($source['source_url']),
        'source_ref'      => PT_Secure($source['source_ref']),
        'playback_url'    => PT_Secure($source['playback_url']),
        'is_live'         => 1,
        'keep_after_end'  => (int) $source['keep_after_end'],
        'last_checked'    => $now,
        'live_started_at' => $now,
        'created_at'      => $now,
        'updated_at'      => $now,
    ));

    if (function_exists('pt_push_channel_notifiations')) {
        @pt_push_channel_notifiations($post_id, 'started_live_video');
    }

    return array('status' => 1, 'post_id' => $post_id, 'video_id' => $video_id, 'source' => $source['source_type']);
}

/**
 * Refresh every imported live stream.
 *
 * $network = true  -> also probes the provider (YouTube API / remote origin).
 *                     Use this from cron, where latency is acceptable.
 * $network = false -> only local heartbeat bookkeeping, so a page render never
 *                     blocks on a third-party request. YouTube entries whose
 *                     status cannot be determined are left untouched.
 */
function PT_LiveRefreshImports($limit = 50, $network = true) {
    global $db, $pt;

    $rows = array();
    try {
        $rows = $db->where('is_live', 1)->orderBy('last_checked', 'ASC')->get(T_LIVE_IMPORTS, $limit);
    } catch (Exception $e) {
        return array('checked' => 0, 'ended' => 0, 'went_live' => 0);
    }
    if (empty($rows)) {
        return array('checked' => 0, 'ended' => 0, 'went_live' => 0);
    }

    $now = time();
    $ended = 0;
    $went_live = 0;

    foreach ($rows as $row) {
        if ($network) {
            $db->where('id', $row->id)->update(T_LIVE_IMPORTS, array('last_checked' => $now, 'updated_at' => $now));
        }

        if ($row->source_type === 'youtube') {
            if (!$network) {
                // A page render must not call the provider. Keep the heartbeat
                // warm; cron performs the authoritative status check.
                $db->where('id', $row->video_id)->update(T_VIDEOS, array('live_time' => $now));
                continue;
            }
            $status = PT_LiveYoutubeStatus($row->source_ref);
            if (empty($status)) {
                // Provider status unknown (no API key, timeout, transient error):
                // treat the import as still live and keep the heartbeat warm so a
                // 24/7 stream never goes stale in the feed.
                $db->where('id', $row->video_id)->update(T_VIDEOS, array('live_time' => $now));
                continue;
            }
            if (empty($status['live'])) {
                // Stream ended: keep the recording as a normal YouTube video.
                $update = array('live_ended' => 1, 'live_time' => 0);
                if (!empty($status['title'])) {
                    $update['title'] = PT_Secure($status['title']);
                }
                if (!empty($status['thumbnail'])) {
                    $update['thumbnail'] = PT_Secure($status['thumbnail']);
                }
                $db->where('id', $row->video_id)->update(T_VIDEOS, $update);
                $db->where('id', $row->id)->update(T_LIVE_IMPORTS, array(
                    'is_live'   => 2,
                    'ended_at'  => $now,
                    'updated_at' => $now,
                ));
                $ended++;
            } else {
                $db->where('id', $row->video_id)->update(T_VIDEOS, array('live_time' => $now));
                $went_live++;
            }
        } else {
            // Remote sources have no provider heartbeat. Deletion only happens
            // on definitive evidence that the origin is dead (a drained HLS
            // playlist, a 4xx/5xx, or a broken media file). A source we cannot
            // probe is treated as still live so a live stream is never removed
            // just because it had no viewers for a few minutes.
            if (!$network) {
                continue;
            }
            $video = $db->where('id', $row->video_id)->getOne(T_VIDEOS);
            $probe = null;
            if (!empty($video) && !empty($row->playback_url) && PT_LiveIsDirectSource($row->playback_url)) {
                $probe = PT_LiveProbeRemote($row->playback_url);
            }
            if ($probe === false) {
                PT_DbGuardedRun(function () use ($db, $row) {
                    $db->where('id', $row->video_id)->delete(T_VIDEOS);
                    return true;
                });
                $db->where('id', $row->id)->update(T_LIVE_IMPORTS, array(
                    'is_live'    => 2,
                    'ended_at'   => $now,
                    'updated_at' => $now,
                ));
                $ended++;
            } else {
                $db->where('id', $row->video_id)->update(T_VIDEOS, array('live_time' => $now));
                $went_live++;
            }
        }
    }

    return array('checked' => count($rows), 'ended' => $ended, 'went_live' => $went_live);
}

/** Mark an imported stream as ended (owner clicked stop). */
function PT_LiveEndImport($video_id) {
    global $db;
    $row = $db->where('video_id', (int) $video_id)->getOne(T_LIVE_IMPORTS);
    if (empty($row)) {
        return false;
    }
    $now = time();
    if ((int) $row->keep_after_end === 1) {
        $db->where('id', $video_id)->update(T_VIDEOS, array('live_ended' => 1, 'live_time' => 0));
        $db->where('id', $row->id)->update(T_LIVE_IMPORTS, array('is_live' => 2, 'ended_at' => $now, 'updated_at' => $now));
    } else {
        PT_DbGuardedRun(function () use ($db, $video_id) {
            $db->where('id', $video_id)->delete(T_VIDEOS);
            return true;
        });
        $db->where('id', $row->id)->update(T_LIVE_IMPORTS, array('is_live' => 2, 'ended_at' => $now, 'updated_at' => $now));
    }
    return true;
}

/** Decorate a video object with live-source metadata for the watch page. */
function PT_LiveDecorateVideo(&$video) {
    global $db;
    if (empty($video) || empty($video->live_source)) {
        return;
    }
    $video->is_imported_live = true;
    $video->live_import_source = $video->live_source;
    $video->live_import_keep = ($video->live_source === 'youtube') ? 1 : 0;

    $is_live = (!empty($video->live_time) && $video->live_time >= (time() - 180) && empty($video->live_ended));
    $video->is_imported_live_active = $is_live;

    // Refresh the heartbeat so the stream stays live between cron passes,
    // exactly like an Agora stream keeps its live_time warm.
    if ($is_live) {
        try {
            $db->where('id', $video->id)->update(T_VIDEOS, array('live_time' => time()));
            $video->live_time = time();
        } catch (Exception $e) {
        }
    }

    if (!empty($video->live_source_ref) && $video->live_source === 'youtube' && empty($video->youtube)) {
        $video->live_source_thumb = $video->thumbnail;
    }
}
