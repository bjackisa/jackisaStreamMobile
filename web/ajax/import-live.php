<?php
// Import a livestream (YouTube live or a generic remote live source).
// Reached through /aj/import-live?hash=... (type = import-live).

function pt_import_live_error($message) {
    global $data;
    $data = array('status' => 400, 'message' => $message);
}

if (empty($pt->config->import_system) || $pt->config->import_system != 'on' || empty($pt->config->live_video) || $pt->config->live_video != 1) {
    pt_import_live_error($lang->please_check_details);
} else if (IS_LOGGED == false) {
    pt_import_live_error($lang->please_check_details);
} else if ($pt->user->suspend_import) {
    pt_import_live_error($lang->please_check_details);
} else if (empty($_POST['live_url'])) {
    pt_import_live_error($lang->live_import_invalid);
} else {
    $url = trim(stripslashes($_POST['live_url']));
    $parsed = PT_LiveParseSource($url);

    if (empty($parsed)) {
        pt_import_live_error($lang->live_import_invalid);
    } else {
        // Refuse an exact duplicate of a stream that is already live here.
        $duplicate = $db->where('source_ref', PT_Secure($parsed['source_ref']))
                        ->where('source_url', PT_Secure($url))
                        ->where('is_live', 1)
                        ->getValue(T_LIVE_IMPORTS, 'COUNT(*)');
        if ($duplicate > 0) {
            pt_import_live_error($lang->live_import_not_live);
        } else {
            $title       = !empty($_POST['title']) ? $_POST['title'] : '';
            $description = !empty($_POST['description']) ? $_POST['description'] : '';
            $category    = !empty($_POST['category']) ? (int) $_POST['category'] : 0;
            $privacy     = isset($_POST['privacy']) ? (int) $_POST['privacy'] : 0;

            $result = PT_LiveImportSource($pt->user->id, $url, $title, $description, $category, $privacy);

            if (empty($result['status'])) {
                if ($result['message'] == 'not_live') {
                    pt_import_live_error($lang->live_import_not_live);
                } else if ($result['message'] == 'not_found') {
                    pt_import_live_error($lang->live_import_gone);
                } else {
                    pt_import_live_error($lang->live_import_invalid);
                }
            } else {
                $video = $db->where('id', $result['post_id'])->getOne(T_VIDEOS);
                $slug  = !empty($video) ? PT_Slug($video->title, $video->video_id) : $result['video_id'];
                $data  = array(
                    'status'   => 200,
                    'message'  => $lang->live_import_success,
                    'url'      => PT_Link('watch/' . $slug),
                    'video_id' => $result['post_id'],
                );
            }
        }
    }
}
