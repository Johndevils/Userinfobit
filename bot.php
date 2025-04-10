<?php
require_once __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();

$botToken = $_ENV['BOT_TOKEN'];
$mongoURI = $_ENV['MONGODB_URI'];
$apiURL = "https://api.telegram.org/bot$botToken";

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/error.log");

try {
    $mongoClient = new MongoDB\Client($mongoURI);
    $db = $mongoClient->selectDatabase('telegramBot');
    $usersCollection = $db->selectCollection('users');
} catch (Exception $e) {
    error_log("MongoDB connection error: " . $e->getMessage());
    exit;
}

$update = json_decode(file_get_contents("php://input"), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $user = $message['from'];
    $user_id = $user['id'];

    if ($text === '/start') {
        $startText = "Welcome to the *User Info Bot*!\n\nUse /help to see available commands.";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Help', 'callback_data' => 'help']]
            ]
        ];
        sendMessage($chat_id, $startText, 'Markdown', $keyboard);
    }

    elseif ($text === '/help') {
        $helpText = "*Available Commands:*\n\n";
        $helpText .= "/start - Start the bot\n";
        $helpText .= "/help - Show help info\n";
        $helpText .= "/userinfo - Show your Telegram info\n";
        $helpText .= "/export - Export all users (admin only)";
        sendMessage($chat_id, $helpText, 'Markdown');
    }

    elseif ($text === '/userinfo') {
        $first_name = $user['first_name'] ?? '';
        $last_name = $user['last_name'] ?? '';
        $username = $user['username'] ?? 'N/A';
        $language = $user['language_code'] ?? 'N/A';
        $chat_type = $message['chat']['type'];

        $chatInfo = json_decode(file_get_contents("$apiURL/getChat?chat_id=$user_id"), true);
        $bio = $chatInfo['result']['bio'] ?? 'No bio';

        $photo_url = "No profile picture";
        $photos = json_decode(file_get_contents("$apiURL/getUserProfilePhotos?user_id=$user_id&limit=1"), true);
        if (!empty($photos['result']['total_count'])) {
            $file_id = $photos['result']['photos'][0][0]['file_id'];
            $file = json_decode(file_get_contents("$apiURL/getFile?file_id=$file_id"), true);
            if (isset($file['result']['file_path'])) {
                $photo_url = "https://api.telegram.org/file/bot$botToken/" . $file['result']['file_path'];
            }
        }

        try {
            $usersCollection->updateOne(
                ['user_id' => $user_id],
                [
                    '$set' => [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'username' => $username,
                        'language' => $language,
                        'bio' => $bio,
                        'photo_url' => $photo_url,
                        'chat_type' => $chat_type,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ],
                ['upsert' => true]
            );
        } catch (Exception $e) {
            error_log("MongoDB insert error: " . $e->getMessage());
        }

        $info = "👤 <b>User Info</b>\n\n";
        $info .= "<b>Name:</b> $first_name $last_name\n";
        $info .= "<b>Username:</b> @$username\n";
        $info .= "<b>User ID:</b> <code>$user_id</code>\n";
        $info .= "<b>Chat Type:</b> $chat_type\n";
        $info .= "<b>Language:</b> $language\n";
        $info .= "<b>Bio:</b> $bio\n";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'View Profile Picture', 'url' => $photo_url]]
            ]
        ];

        sendMessage($chat_id, $info, 'HTML', $keyboard);
    }

    elseif ($text === '/export') {
        $admin_ids = [123456789]; // Replace with your Telegram user ID(s)
        if (in_array($user_id, $admin_ids)) {
            $data = $usersCollection->find()->toArray();
            $json = json_encode($data, JSON_PRETTY_PRINT);
            $file = 'export.json';
            file_put_contents($file, $json);

            sendDocument($chat_id, $file, "User data exported.");
        } else {
            sendMessage($chat_id, "❌ You are not authorized to use this command.");
        }
    }
}

function sendMessage($chat_id, $text, $parse = null, $keyboard = null) {
    global $apiURL;
    $params = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    if ($parse) $params['parse_mode'] = $parse;
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);

    file_get_contents("$apiURL/sendMessage?" . http_build_query($params));
}

function sendDocument($chat_id, $file, $caption = '') {
    global $apiURL;
    $cFile = curl_file_create($file);
    $data = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'document' => $cFile
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiURL/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
