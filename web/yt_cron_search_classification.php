<?php
/**
 * Classifies a manual-importer-shaped YouTube search response before detail lookup.
 * This is intentionally dependency-free so its edge cases can be checked without booting the app.
 */
function yt_cron_classify_search_results($search, array $existing_ids) {
    $total_results = isset($search['info']['totalResults']) ? (int) $search['info']['totalResults'] : null;
    $results = isset($search['results']) && is_array($search['results']) ? $search['results'] : array();
    $ids = array();
    foreach ($results as $result) {
        $video_id = '';
        if (is_object($result) && !empty($result->id->videoId)) {
            $video_id = (string) $result->id->videoId;
        } elseif (is_array($result) && !empty($result['id']['videoId'])) {
            $video_id = (string) $result['id']['videoId'];
        }
        if ($video_id !== '' && !in_array($video_id, $ids, true)) {
            $ids[] = $video_id;
        }
    }

    $candidate_ids = array_values(array_diff($ids, $existing_ids));
    if ($total_results === 0) {
        $outcome = 'zero_api_results';
    } elseif (empty($ids)) {
        $outcome = 'missing_search_items';
    } elseif (empty($candidate_ids)) {
        $outcome = 'all_duplicates';
    } else {
        $outcome = 'candidates';
    }

    return array(
        'outcome' => $outcome,
        'api_total' => $total_results,
        'search_result_count' => count($ids),
        'duplicate_count' => count($ids) - count($candidate_ids),
        'candidate_ids' => $candidate_ids
    );
}

/** Classifies the response from the videos-detail lookup used after candidate discovery. */
function yt_cron_classify_detail_response($details) {
    if (!is_array($details) || !isset($details['items']) || !is_array($details['items'])) {
        return array('outcome' => 'missing_or_invalid_items', 'items' => array());
    }
    if (empty($details['items'])) {
        return array('outcome' => 'zero_items', 'items' => array());
    }
    return array('outcome' => 'items', 'items' => $details['items']);
}
