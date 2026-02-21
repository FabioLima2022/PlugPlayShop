<?php
require_once __DIR__ . '/config.php';

function upload_videos($files) {
    $uploaded = [];
    $uploadDir = __DIR__ . '/uploads/videos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp = $files['tmp_name'][$i];
            $name = $files['name'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = mime_content_type($tmp);
            
            // Validação básica
            if (in_array($ext, ['mp4', 'webm', 'mov']) && strpos($mime, 'video/') === 0) {
                $newName = uniqid('vid_') . '.' . $ext;
                if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                    $uploaded[] = '/uploads/videos/' . $newName;
                }
            }
        }
    }
    return $uploaded;
}

function process_video_input($files, $raw_urls) {
    $uploaded = upload_videos($files);
    $urls = [];
    if (!empty($raw_urls)) {
        $raw_urls = str_replace([';', "\n", "\r"], [',', ',', ''], $raw_urls);
        $urls = array_filter(array_map('trim', explode(',', $raw_urls)));
    }
    return implode(',', array_merge($uploaded, $urls));
}
