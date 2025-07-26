<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{
    protected $name = 'start';
    protected $description = 'Start command';
    protected $usage = '/start';

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();

        $text = "🎨 Добро пожаловать в Image Editor Bot!\n\n"
        . "Отправьте мне изображение, и я помогу вам:\n"
        . "• Автоматически кадрировать изображение\n"
        . "• Преобразовать в черно-белое\n"
        . "• Изменить формат файла (PNG, JPG, TIFF)\n\n"
        . "Просто отправьте фото и выберите нужное действие!";
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
        ]);
    }
}