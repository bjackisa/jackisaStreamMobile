<?php
require_once __DIR__ . '/../yt_cron_search_classification.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$empty = yt_cron_classify_search_results(array('info' => array('totalResults' => 0), 'results' => array()), array());
check($empty['outcome'] === 'zero_api_results', 'zero API results');
$duplicates = yt_cron_classify_search_results(array('info' => array('totalResults' => 2), 'results' => array((object) array('id' => (object) array('videoId' => 'a')), (object) array('id' => (object) array('videoId' => 'b')))), array('a', 'b'));
check($duplicates['outcome'] === 'all_duplicates' && $duplicates['duplicate_count'] === 2, 'all duplicates');
$details_failure = yt_cron_classify_detail_response(array('error' => array('message' => 'quota exceeded')));
check($details_failure['outcome'] === 'missing_or_invalid_items', 'detail lookup failure');
$success = yt_cron_classify_search_results(array('info' => array('totalResults' => 1), 'results' => array((object) array('id' => (object) array('videoId' => 'manual-equivalent')))), array());
check($success['outcome'] === 'candidates' && $success['search_result_count'] === 1, 'manual-equivalent response');
echo "yt_cron search classification checks passed\n";
