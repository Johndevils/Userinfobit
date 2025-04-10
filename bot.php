<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$BOT_TOKEN = $_ENV['BOT_TOKEN'];
$LOG_CHANNEL_ID = $_ENV['LOG_CHANNEL_ID'];
$ADMIN_ID = $_ENV['ADMIN_ID'];

$update = json_decode(file_get_contents("php://input"), true);
file_put_contents("error.log", print_r($update, true), FILE_APPEND);

function sendMessage($chat_id, $text, $reply_markup = null) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    file_get_contents($url . "?" . http_build_query($data));
}

function forwardToLogChannel($text) {
    global $LOG_CHANNEL_ID;
    sendMessage($LOG_CHANNEL_ID, $text);
}

if (isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = $msg['text'] ?? '';
    $user = $msg['from'];

    $user_id = $user['id'] ?? '';
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $username = $user['username'] ?? '';
    $bio = $user['bio'] ?? 'N/A';
    $language = $user['language_code'] ?? 'N/A';
    $chat_type = $msg['chat']['type'] ?? 'N/A';

    $info = "<b>User Info:</b>\n";
    $info .= "👤 Name: $first_name $last_name\n";
    $info .= "🔗 Username: @$username\n";
    $info .= "🆔 User ID: $user_id\n";
    $info .= "🗣 Bio: $bio\n";
    $info .= "💬 Language: $language\n";
    $info .= "🏷 Chat Type: $chat_type";

    if ($text == '/start') {
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => 'Get My Info', 'callback_data' => 'get_info']
            ]]
        ];
        sendMessage($chat_id, "Welcome to the User Info Bot!", $keyboard);
    } elseif ($text == '/help') {
        sendMessage($chat_id, "Use the button below or /start to view your user info.");
    } else {
        forwardToLogChannel("New message from @$username ($user_id): $text");
    }
}

if (isset($update['callback_query'])) {
    $query = $update['callback_query'];
    $data = $query['data'];
    $user = $query['from'];
    $chat_id = $query['message']['chat']['id'];

    if ($data == 'get_info') {
        $user_id = $user['id'];
        $first_name = $user['first_name'] ?? '';
        $last_name = $user['last_name'] ?? '';
        $username = $user['username'] ?? '';
        $language = $user['language_code'] ?? 'N/A';

        $info = "<b>User Info:</b>\n";
        $info .= "👤 Name: $first_name $last_name\n";
        $info .= "🔗 Username: @$username\n";
        $info .= "🆔 User ID: $user_id\n";
        $info .= "💬 Language: $language";

        sendMessage($chat_id, $info);
        forwardToLogChannel("User info requested by @$username ($user_id)");
    }
}
