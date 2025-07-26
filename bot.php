<?php

require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;

$telegram = new Telegram($config['api_key'], $config['bot_username']);

// Add commands path
$telegram->addCommandsPath(__DIR__ . '/Commands');

try {
    // Set custom uploads path
    $telegram->setDownloadPath(__DIR__ . '/downloads');
    $telegram->setUploadPath(__DIR__ . '/uploads');

    echo "🤖 Bot started!\n"
    . "📱 Bot username: @" . $config['bot_username'] . "\n"
    . "⏹️  Press Ctrl+C to stop.\n\n";

    $offset = 0;

    while (true) {
        try {
            // Get updates from Telegram
            $result = Request::getUpdates([
                'offset' => $offset,
                'limit' => 100,
                'timeout' => 30,
            ]);

            if ($result->isOk()) {
                $updates = $result->getResult();

                if(!empty($updates)) {
                    echo "📨 Received " . count($updates) . " update(s)\n";

                    foreach ($updates as $update) {
                        $update_id = $update->getUpdateId();
                        echo "  Update ID: " . $update_id . "\n";

                        // Check what type of update this is
                        if ($update->getMessage()) {
                            $message = $update->getMessage();
                            $chat_id = $message->getChat()->getId();
                            echo "   📝 Message from chat: " . $chat_id . "\n";

                            if($message->getPhoto()) {
                                $photo = $message->getPhoto();
                                echo "   📸 Photo detected! Processing...\n";
                                // Manually handle the photo
                                handlePhoto($message);
                            } elseif ($message->getDocument()) {
                                $document = $message->getDocument();
                                $mime = $document->getMimeType();
                                $file_name = $document->getFileName();
                                $is_image = false;
                                if ($mime && strpos($mime, '/image') === 0) {
                                    $is_image = true;
                                } elseif ($file_name) {
                                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'tif', 'tiff'])) {
                                        $is_image = true;
                                    }
                                }
                                if ($is_image) {
                                    echo "   📄 Image document detected! Processing...\n";
                                    handleDocument($message);
                                } else {
                                    echo "   📄 Non-image document received, ignored.\n";
                                }
                            } elseif ($message->getText()) {
                                echo "   💬 Text: " . $message->getText() . "\n";
                                // Process the text
                                $telegram->processUpdate($update);
                            }
                        } elseif ($update->getCallbackQuery()) {
                            echo "   🔘 Callback query detected!\n";
                            // Process the Callback
                            $telegram->processUpdate($update);
                        }

                        $offset = $update_id + 1;
                    }
                }
            } else {
                echo "⚠️  Failed to get updates: " . $result->getDescription() . "\n";
            }

            sleep(1);

        } catch (TelegramException $e) {
            echo "⚠️  Telegram error: " . $e->getMessage() . "\n";
            sleep(5);
        } catch (Exception $e) {
            echo "❌ General error: " . $e->getMessage() . "\n";
            sleep(5);
        }
    }

} catch (TelegramException $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
}

function handlePhoto($message)
{
    $chat_id = $message->getChat()->getId();

    // Get the highest quality photo
    $photo_sizes = $message->getPhoto();
    $best_photo = end($photo_sizes);
    $file_id = $best_photo->getFileId();

    // Store file_id for later processing
    storeUserImage($chat_id, $file_id);

    createKeyboard($chat_id);

}

function handleDocument($message)
{
    $chat_id = $message->getChat()->getId();
    $document = $message->getDocument();
    $file_id = $document->getFileId();

    // Store file_id for later processing
    storeUserImage($chat_id, $file_id);

    createKeyboard($chat_id);

}

function storeUserImage($chat_id, $file_id)
{
    // Simple file-based storage for demo
    $storage_dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;
    if (!is_dir($storage_dir)) {
        if (!mkdir($storage_dir, 0777, true) && !is_dir($storage_dir)) {
            echo "   ❌ Failed to create storage directory: $storage_dir\n";
            return;
        }
    }

    $data = [
        'chat_id' => $chat_id,
        'file_id' => $file_id,
        'timestamp' => time(),
    ];

    $file_path = $storage_dir . $chat_id . '.json';
    if (file_put_contents($file_path, json_encode($data)) === false) {
        echo "   ❌ Failed to write image data to $file_path\n";
    } else {
        echo "   💾 Image stored for chat: " . $chat_id . "\n";
    }
}

// Create inline keyboard as a plain array
function createKeyboard($chat_id)
{
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📐 1:1', 'callback_data' => 'crop_1x1'],
                ['text' => '📐 16:9', 'callback_data' => 'crop_16x9'],
            ],
            [
                ['text' => '📐 3:4', 'callback_data' => 'crop_3x4'],
                ['text' => '📐 4:3', 'callback_data' => 'crop_4x3'],
            ],
            [
                ['text' => '📐 Кадрировать 9:16', 'callback_data' => 'crop_9x16'],
            ],
            [
                ['text' => '⚫ Ч/Б', 'callback_data' => 'bw'],
            ],
            [
                ['text' => '🔄 PNG', 'callback_data' => 'format_png'],
                ['text' => '🔄 JPG', 'callback_data' => 'format_jpg'],
                ['text' => '🔄 TIFF', 'callback_data' => 'format_tiff'],
            ],
        ]
    ];
// Send the message with buttons
    $result = Request::sendMessage([
        'chat_id' => $chat_id,
        'text'         => "🖼️ Изображение получено! Выберите действие:",
        'reply_markup' => json_encode($keyboard),
    ]);

    if ($result->isOk()) {
        echo "   ✅ Buttons sent successfully!\n";
    } else {
        echo "   ❌ Failed to send buttons: " . $result->getDescription() . "\n";
    }
}