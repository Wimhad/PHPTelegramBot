<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Exception;

use finfo;

use CURLFile;

class CallbackqueryCommand extends SystemCommand
{
    protected $name = 'callbackquery';
    protected $description = 'Handle callback queries';

    private $convert_api;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        // Load config
        $config = require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php';
        $this->convert_api = $config['convert_api'] ?? '';
    }

    public function execute(): ServerResponse
    {
        $callback = $this->getCallbackQuery();
        $data = $callback->getData();
        $chat_id = $callback->getMessage()->getChat()->getId();
        $message_id = $callback->getMessage()->getMessageId();

        // Get stored image data
        $image_data = $this->getUserImage($chat_id);
        if (!$image_data) {
            return Request::answerCallbackQuery([
                'callback_query_id' => $callback->getId(),
                'text' => '❌ Изображение не найдено. Отправьте новое фото.',
                'show_alert' => true,
            ]);
        }

        try {
            // Process the image based on callback data
            $result = $this->processImage($image_data['file_id'], $data, $chat_id);

            if ($result) {
                // Answer callback query
                Request::answerCallbackQuery([
                    'callback_query_id' => $callback->getId(),
                    'text' => '✅ Обработка завершена!'
                ]);

                // Edit the message to show completion
                Request::editMessageText([
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "✅ Изображение обработано!\n\nДействие: " . $this->getActionName($data),
                ]);
            }
        } catch (Exception $e) {
            Request::answerCallbackQuery([
                'callback_query_id' => $callback->getId(),
                'text' => '❌ Ошибка при обработке изображения: ' . $e->getMessage(),
                'show_alert' => true,
            ]);
        }

        return Request::emptyResponse();
    }

    private function getUserImage($chat_id)
    {
        $storage_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;
        $file_path = $storage_dir . $chat_id . ".json";

        if (file_exists($file_path)) {
            $data = json_decode(file_get_contents($file_path), true);
            // Check if data is not too old
            if (time() - $data['timestamp'] < 3600) {
                return $data;
            }
        }

        return null;
    }

    private function processImage($file_id, $action, $chat_id)
    {
        // Get file info from Telegram
        $file = Request::getFile(['file_id' => $file_id]);
        if (!$file->isOk()) {
            throw new Exception('Не удалось получить файл');
        }

        $file_path = $file->getResult()->getFilePath();
        $api_key = $this->getTelegram()->getApiKey();
        $url = "https://api.telegram.org/file/bot{$api_key}/{$file_path}";

        // Download image
        $image_content = file_get_contents($url);
        if (!$image_content) {
            throw new Exception('Не удалось скачать изображение');
        }

        $manager = new ImageManager(Driver::class);
        $original_format = null;
        $image_data_for_processing = $image_content;
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($image_content);
        $is_tiff = ($mime === 'image/tiff' || $ext === 'tif' || $ext === 'tiff');
        $temp_tiff = null;
        $png_file = null;

        if ($is_tiff) {
            // Convert TIFF to PNG using ConvertAPI
            $temp_tiff = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'input_' . uniqid() . '.tiff';
            file_put_contents($temp_tiff, $image_content);
            $png_file = $this->convertTiffToPng($temp_tiff);
            if (!$png_file) {
                unlink($temp_tiff);
                throw new Exception('Не удалось конвертировать TIFF в PNG');
            }
            $image_data_for_processing = file_get_contents($png_file);
            $original_format = 'tiff';
            unlink($temp_tiff);
            // Don't unlink $png_file yet, we need it for processing
        } else {
            // Try to detect original format from file path
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $original_format = $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        // Now always process as PNG/JPG
        $image = $manager->read($image_data_for_processing);

        // Apply processing based on action
        switch ($action) {
            case 'crop_1x1':
                $image = $image->cover(512, 512);
                $format = $original_format ?? 'png';
                $caption = '📐 Изображение кадрировано в формате 1:1 (512x512)';
                break;
            case 'crop_16x9':
                $image = $image->cover(1024, 576);
                $format = $original_format ?? 'png';
                $caption = '📐 Изображение кадрировано в формате 16:9 (1024x576)';
                break;
            case 'crop_3x4':
                $image = $image->cover(768, 1024);
                $format = $original_format ?? 'png';
                $caption = '📐 Изображение кадрировано в формате 3:4 (768x1024)';
                break;
            case 'crop_4x3':
                $image = $image->cover(1024, 768);
                $format = $original_format ?? 'png';
                $caption = '📐 Изображение кадрировано в формате 4:3 (1024x768)';
                break;
            case 'bw':
                $image = $image->greyscale();
                $format = $original_format ?? 'png';
                $caption = '⚫ Изображение преобразовано в черно-белое';
                break;
            case 'format_png':
                $format = 'png';
                $caption = '🔄 Изображение сохранено в формате PNG';
                break;
            case 'format_jpg':
                $format = 'jpg';
                $caption = '🔄 Изображение сохранено в формате JPG';
                break;
            case 'crop_9x16':
                $image = $image->cover(576, 1024);
                $format = $original_format ?? 'png';
                $caption = '📐 Изображение кадрировано в формате 9:16 (576x1024)';
                break;
            case 'format_tiff':
                $format = 'tiff';
                $caption = '🔄 Изображение сохранено в формате TIFF';
                break;
            default:
                throw new Exception('Неизвестное действие');
        }

        // Save processed image as PNG or JPG (never TIFF directly)
        $temp_dir = sys_get_temp_dir();
        $temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'processed_' . uniqid() . '.' . ($format === 'jpg' ? 'jpg' : 'png');
        $image->save($temp_file, quality: 90, format: ($format === 'jpg' ? 'jpg' : 'png'));

        // If user wants TIFF, convert processed PNG to TIFF and send
        if ($format === 'tiff') {
            $tiff_file = $this->convertToTiff($temp_file);
            if ($tiff_file) {
                $result = Request::sendDocument([
                    'chat_id' => $chat_id,
                    'document' => Request::encodeFile($tiff_file),
                    'caption' => $caption . " (TIFF)"
                ]);
                unlink($tiff_file);
            } else {
                // fallback: send PNG
                $result = Request::sendDocument([
                    'chat_id' => $chat_id,
                    'document' => Request::encodeFile($temp_file),
                    'caption' => $caption . " (PNG)"
                ]);
            }
        } elseif ($format === 'jpg') {
            $result = Request::sendPhoto([
                'chat_id' => $chat_id,
                'photo'   => Request::encodeFile($temp_file),
                'caption' => $caption,
            ]);
        } else {
            // PNG or fallback
            $result = Request::sendDocument([
                'chat_id' => $chat_id,
                'document' => Request::encodeFile($temp_file),
                'caption' => $caption,
            ]);
        }

        // Clean up
        unlink($temp_file);
        if ($png_file && file_exists($png_file)) {
            unlink($png_file);
        }
        return $result->isOk();
    }

    private function getActionName($action)
    {
        $actions = [
            'crop_1x1' => 'Кадрирование 1:1',
            'crop_16x9' => 'Кадрирование 16:9',
            'crop_3x4' => 'Кадрирование 3:4',
            'crop_4x3' => 'Кадрирование 4:3',
            'bw' => 'Черно-белое',
            'format_png' => 'Формат PNG',
            'format_jpg' => 'Формат JPG',
            'crop_9x16' => 'Кадрирование 9:16',
            'format_tiff' => 'Формат TIFF',
        ];
        return $actions[$action] ?? 'Неизвестное действие';
    }

    private function convertToTiff($input_file)
    {
        $api_key = $this->convert_api;
        $url = 'https://v2.convertapi.com/convert/png/to/tiff?Secret=' . urlencode($api_key);
        $cfile = new CURLFile($input_file, 'image/png');
        $post = ['File' => $cfile];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $tiff_content = false;
        if (isset($data['Files'][0]['Url'])) {
            // Old method: downloads from URL (for compatibility)
            $tiff_url = $data['Files'][0]['Url'];
            $tiff_content = file_get_contents($tiff_url);
        } elseif (isset($data['Files'][0]['FileData'])) {
            // New method: decode base64 data
            $tiff_content = base64_decode($data['Files'][0]['FileData']);
        }

        if ($tiff_content) {
            $tiff_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'converted_' . uniqid() . '.tiff';
            file_put_contents($tiff_file, $tiff_content);
            return $tiff_file;
        }
        return false;
    }

    // Add a helper to convert TIFF to PNG using ConvertAPI
    private function convertTiffToPng($input_file)
    {
        $api_key = $this->convert_api;
        $url = 'https://v2.convertapi.com/convert/tiff/to/png?Secret=' . urlencode($api_key);
        $cfile = new CURLFile($input_file, 'image/tiff');
        $post = ['File' => $cfile];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $png_content = false;
        if (isset($data['Files'][0]['Url'])) {
            $png_url = $data['Files'][0]['Url'];
            $png_content = file_get_contents($png_url);
        } elseif (isset($data['Files'][0]['FileData'])) {
            $png_content = base64_decode($data['Files'][0]['FileData']);
        }

        if ($png_content) {
            $png_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'converted_' . uniqid() . '.png';
            file_put_contents($png_file, $png_content);
            return $png_file;
        }
        return false;
    }

}