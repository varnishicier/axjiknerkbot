<?php
// Read secrets/settings from environment (Heroku Config Vars)
$token = getenv('BOT_TOKEN');
if (!$token) {
    // BOT_TOKEN is required
    http_response_code(500);
    exit;
}

// Media sources can be:
// - Local file path (e.g. pic.jpg or ./videos/*.mp4)
// - Public HTTPS URL (recommended for Heroku)
// - Telegram file_id
$photo_source = getenv('PHOTO_SOURCE');
if (!$photo_source) {
    // fallback: local file next to the script (more reliable than relative CWD on Heroku/Apache)
    $photo_source = __DIR__ . '/pic.jpg';
}

// Optional: provide videos as a JSON array or a comma-separated list of HTTPS URLs / Telegram file_ids
// Examples:
//   VIDEO_SOURCES='["https://cdn.example.com/v1.mp4","https://cdn.example.com/v2.mp4"]'
//   VIDEO_SOURCES='https://cdn.example.com/v1.mp4,https://cdn.example.com/v2.mp4'
$video_sources_env = getenv('VIDEO_SOURCES');
$video_sources = [];
if ($video_sources_env) {
    $decoded = json_decode($video_sources_env, true);
    if (is_array($decoded)) {
        $video_sources = $decoded;
    } else {
        $video_sources = array_values(array_filter(array_map('trim', explode(',', $video_sources_env))));
    }
}

$api = "https://api.telegram.org/bot$token";

// Centralized request helper (also logs Telegram API errors into Heroku logs)
function tgRequest($method, $data) {
    global $api;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api . '/' . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        error_log("TG {$method} curl_error={$err}");
        return null;
    }

    $decoded = json_decode($resp, true);
    if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
        error_log("TG {$method} http_code={$code} resp=" . $resp);
    }

    return $decoded;
}

$nextVideoText = "📺 Следующее Видео";
$videos_dir = __DIR__ . '/videos'; // fallback folder if VIDEO_SOURCES is not set

$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$from_id = $message['from']['id'] ?? null;
$text = $message['text'] ?? '';

// Admin-only helper: reply with Telegram file_id for received media
// Only works for user_id 6309428191
if ($chat_id && $message && (string)$from_id === '6309428191') {
    // Photo (array of sizes)
    if (isset($message['photo']) && is_array($message['photo']) && count($message['photo']) > 0) {
        $largest = $message['photo'][count($message['photo']) - 1];
        $file_id = $largest['file_id'] ?? '';
        $file_unique_id = $largest['file_unique_id'] ?? '';

        $reply = "PHOTO\nfile_id: {$file_id}\nfile_unique_id: {$file_unique_id}";
        sendMessage($chat_id, $reply);
        exit;
    }

    // Video
    if (isset($message['video']) && is_array($message['video'])) {
        $file_id = $message['video']['file_id'] ?? '';
        $file_unique_id = $message['video']['file_unique_id'] ?? '';

        $reply = "VIDEO\nfile_id: {$file_id}\nfile_unique_id: {$file_unique_id}";
        sendMessage($chat_id, $reply);
        exit;
    }

    // Document (in case user sends media as a file)
    if (isset($message['document']) && is_array($message['document'])) {
        $file_id = $message['document']['file_id'] ?? '';
        $file_unique_id = $message['document']['file_unique_id'] ?? '';
        $file_name = $message['document']['file_name'] ?? '';

        $reply = "DOCUMENT\nfile_name: {$file_name}\nfile_id: {$file_id}\nfile_unique_id: {$file_unique_id}";
        sendMessage($chat_id, $reply);
        exit;
    }

    // Animation (GIF)
    if (isset($message['animation']) && is_array($message['animation'])) {
        $file_id = $message['animation']['file_id'] ?? '';
        $file_unique_id = $message['animation']['file_unique_id'] ?? '';

        $reply = "ANIMATION\nfile_id: {$file_id}\nfile_unique_id: {$file_unique_id}";
        sendMessage($chat_id, $reply);
        exit;
    }
}

// “Next video” reply-keyboard button
if ($chat_id && $text === $nextVideoText) {
    $replyKeyboard = [
        'keyboard' => [
            [
                ['text' => $nextVideoText]
            ]
        ],
        'resize_keyboard' => true,
        'is_persistent' => true,
        'one_time_keyboard' => false
    ];

    sendRandomVideo($chat_id, $videos_dir, $video_sources, '', $replyKeyboard);
    exit;
}

// /start handler
if ($chat_id && strpos($text, '/start') === 0) {
    $parts = explode(' ', trim($text));

    $payload_raw = $parts[1] ?? '';
    $payload = $payload_raw !== '' ? $payload_raw : 'default';

    // Campaign suffixes
    $suffix_video = '_camp5RpZFn4FoayRc9q';
    $suffix_link  = '_campvB9Lz2GSof8qYSq';

    $is_video_campaign = false;
    $is_link_campaign  = false;

    // Detect & strip suffixes (keep "real payload" in $payload)
    if ($payload_raw !== '' && (function_exists('str_ends_with') ? str_ends_with($payload_raw, $suffix_video) : substr($payload_raw, -strlen($suffix_video)) === $suffix_video)) {
        $is_video_campaign = true;
        $stripped = substr($payload_raw, 0, -strlen($suffix_video));
        $payload = $stripped !== '' ? $stripped : 'default';
    } elseif ($payload_raw !== '' && (function_exists('str_ends_with') ? str_ends_with($payload_raw, $suffix_link) : substr($payload_raw, -strlen($suffix_link)) === $suffix_link)) {
        $is_link_campaign = true;
        $stripped = substr($payload_raw, 0, -strlen($suffix_link));
        $payload = $stripped !== '' ? $stripped : 'default';
    }
    error_log("POSTBACK payload_raw={$payload_raw} stripped_payload={$payload} is_link_campaign=" . ($is_link_campaign?'1':'0') . " is_video_campaign=" . ($is_video_campaign?'1':'0'));

    // 1) Random video flow ONLY for the video-campaign suffix
    if ($is_video_campaign) {
        $replyKeyboard = [
            'keyboard' => [
                [
                    ['text' => $nextVideoText]
                ]
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
            'one_time_keyboard' => false
        ];

        sendRandomVideo($chat_id, $videos_dir, $video_sources, '', $replyKeyboard);

        // Postback only for this campaign (payload suffix stripped)
        @file_get_contents("http://142.93.227.96/2a7ba26/postback?subid=" . urlencode($payload) . "&status=lead");
        exit;
    }

    // 2) Otherwise show photo + inline button
    // Use /l ONLY for the link-campaign suffix
    $default_url = $is_link_campaign ? "https://gorcnakanhandipum.com/l" : "https://gorcnakanhandipum.com/";

    $caption = '';

    $keyboardButtons = [
        [
            [
                'text' => "Перейти в Канал",
                'url'  => $default_url
            ]
        ]
    ];

    $keyboard = [
        'inline_keyboard' => $keyboardButtons
    ];

    sendPhoto($chat_id, $photo_source, $caption, $keyboard);

    // Postback for ALL ref-starts (any /start with a payload)
    // Uses stripped payload (real subid) so campaign suffixes don't leak into subid
    if ($payload_raw !== '') {
        @file_get_contents("http://142.93.227.96/2a7ba26/postback?subid=" . urlencode($payload) . "&status=lead");
    }

    exit;
}

function sendPhoto($chat_id, $photoSource, $caption = '', $keyboard = null) {
    $photoField = $photoSource;

    // If it's a local file, resolve relative paths from the script directory
    if (is_string($photoSource)) {
        $localPath = $photoSource;
        if (!file_exists($localPath) && file_exists(__DIR__ . '/' . ltrim($photoSource, '/'))) {
            $localPath = __DIR__ . '/' . ltrim($photoSource, '/');
        }

        if (file_exists($localPath)) {
            $photoField = new CURLFile(realpath($localPath));
        }
    }

    $data = [
        'chat_id' => $chat_id,
        'photo'   => $photoField,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    tgRequest('sendPhoto', $data);
}

function sendRandomVideo($chat_id, $videosDir, $videoSources = [], $caption = '', $keyboard = null) {
    $videoSource = pickRandomVideo($videosDir, $videoSources);
    if (!$videoSource) {
        // No videos configured; fall back to a message so the user isn't left with nothing
        sendMessage($chat_id, "⚠️ Видео не настроено. Установи VIDEO_SOURCES (URL или file_id) в Heroku Config Vars.");
        return;
    }

    sendVideo($chat_id, $videoSource, $caption, $keyboard);
}

function pickRandomVideo($videosDir, $videoSources = []) {
    // 1) Prefer VIDEO_SOURCES env (URLs or Telegram file_ids)
    if (is_array($videoSources) && !empty($videoSources)) {
        $idx = array_rand($videoSources);
        return $videoSources[$idx];
    }

    // 2) Fallback to local ./videos directory
    if (!is_dir($videosDir)) return null;

    $patterns = ['*.mp4', '*.mov', '*.m4v', '*.webm', '*.mkv'];
    $files = [];

    foreach ($patterns as $p) {
        $matches = glob(rtrim($videosDir, '/').'/'.$p);
        if (!empty($matches)) {
            $files = array_merge($files, $matches);
        }
    }

    if (empty($files)) return null;

    $idx = array_rand($files);
    return $files[$idx];
}

function sendVideo($chat_id, $videoSource, $caption = '', $keyboard = null) {
    $videoField = $videoSource;

    // If it's a local file, resolve relative paths from the script directory
    if (is_string($videoSource)) {
        $localPath = $videoSource;
        if (!file_exists($localPath) && file_exists(__DIR__ . '/' . ltrim($videoSource, '/'))) {
            $localPath = __DIR__ . '/' . ltrim($videoSource, '/');
        }

        if (file_exists($localPath)) {
            $videoField = new CURLFile(realpath($localPath));
        }
    }

    $data = [
        'chat_id' => $chat_id,
        'video'   => $videoField,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    tgRequest('sendVideo', $data);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    tgRequest('sendMessage', $data);
}