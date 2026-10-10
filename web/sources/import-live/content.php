<?php
if (IS_LOGGED == false || empty($pt->config->import_system) || $pt->config->import_system != 'on') {
    header("Location: " . PT_Link('login'));
    exit();
}
if ($pt->user->suspend_import) {
    header("Location: " . PT_Link(''));
    exit();
}
if (empty($pt->config->live_video) || $pt->config->live_video != 1) {
    header("Location: " . PT_Link('404'));
    exit();
}

$pt->page_url_   = $pt->config->site_url . '/import-live';
$pt->page        = 'import-live';
$pt->title       = $lang->import_live_stream . ' | ' . $pt->config->title;
$pt->description = $pt->config->description;
$pt->keyword     = @$pt->config->keyword;
$pt->content     = PT_LoadPage('import-live/content');
