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

$nextVideoText = "📺 Следующее Видео";
$videos_dir = __DIR__ . '/videos'; // fallback folder if VIDEO_SOURCES is not set

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$text = $message['text'] ?? '';

// “Next video” reply-keyboard button
if ($chat_id && $text === $nextVideoText) {
    $replyKeyboard = [
        'keyboard' => [
            [
                ['text' => $nextVideoText]
            ]
        ],
        'resize_keyboard' => true
    ];

    sendRandomVideo($chat_id, $videos_dir, $video_sources, '', $replyKeyboard);
    exit;
}

if (strpos($text, '/start') === 0) {
    $parts = explode(' ', trim($text));
    $payload = $parts[1] ?? 'default';

    // If /start was used with a ref payload (/start XXXXX) — send random video + show reply keyboard
    $has_ref = isset($parts[1]) && $parts[1] !== '';

    if ($has_ref) {
        $replyKeyboard = [
            'keyboard' => [
                [
                    ['text' => $nextVideoText]
                ]
            ],
            'resize_keyboard' => true
        ];

        sendRandomVideo($chat_id, $videos_dir, $video_sources, '', $replyKeyboard);

        // Keep your postback on ref-start
        file_get_contents("http://142.93.227.96/2a7ba26/postback?subid=$payload&status=lead");
        exit;
    }

    // Normal /start (no payload) — keep your old behavior (photo + inline button)
    $default_url = "https://gorcnakanhandipum.com/";

    $caption = '';

    $keyboardButtons = [];

    $keyboardButtons[] = [
        [
            'text' => "gorcnakanhandipum.com",
            'url'  => $default_url
        ]
    ];

    $keyboard = [
        'inline_keyboard' => $keyboardButtons
    ];

    sendPhoto($chat_id, $photo_source, $caption, $keyboard);
    exit;
}

function sendPhoto($chat_id, $photoSource, $caption = '', $keyboard = null) {
    global $api;

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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api . '/sendPhoto');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function sendRandomVideo($chat_id, $videosDir, $videoSources = [], $caption = '', $keyboard = null) {
    $videoSource = pickRandomVideo($videosDir, $videoSources);
    if (!$videoSource) {
        // No videos found; silently do nothing
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
    global $api;

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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api . '/sendVideo');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
