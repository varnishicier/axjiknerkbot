<?php
$token = '8328975300:AAGHGckuqnI1MgxgtLXG8WUnOq5TE48YB_k'; // токен бота
$pic_filename = 'pic.jpg'; // название картинки в той же папке что и скрипт
$api = "https://api.telegram.org/bot$token";

$nextVideoText = "📺 Следующее Видео";
$videos_dir = __DIR__ . '/videos';

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

    sendRandomVideo($chat_id, $videos_dir, '', $replyKeyboard);
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

        sendRandomVideo($chat_id, $videos_dir, '', $replyKeyboard);

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

    sendPhoto($chat_id, $pic_filename, $caption, $keyboard);
    exit;
}

function sendPhoto($chat_id, $photoPath, $caption = '', $keyboard = null) {
    global $api;

    if (!file_exists($photoPath)) {
        return;
    }

    $data = [
        'chat_id' => $chat_id,
        'photo'   => new CURLFile(realpath($photoPath)),
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

function sendRandomVideo($chat_id, $videosDir, $caption = '', $keyboard = null) {
    $videoPath = pickRandomVideo($videosDir);
    if (!$videoPath) {
        // No videos found; silently do nothing
        return;
    }

    sendVideo($chat_id, $videoPath, $caption, $keyboard);
}

function pickRandomVideo($videosDir) {
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

function sendVideo($chat_id, $videoPath, $caption = '', $keyboard = null) {
    global $api;

    if (!file_exists($videoPath)) {
        return;
    }

    $data = [
        'chat_id' => $chat_id,
        'video'   => new CURLFile(realpath($videoPath)),
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
